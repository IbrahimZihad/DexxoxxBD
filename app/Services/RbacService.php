<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Role/permission lookups. Results are memoised per request because every
 * middleware and every admin menu check hits the same rows.
 */
final class RbacService
{
    private Database $db;

    /** @var array<int, list<string>> */
    private array $permissionCache = [];

    /** @var array<int, list<string>> */
    private array $roleCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /** @return list<string> */
    public function permissionsFor(int $userId): array
    {
        if (isset($this->permissionCache[$userId])) {
            return $this->permissionCache[$userId];
        }

        $rows = $this->db->select(
            'SELECT DISTINCT p.slug
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             INNER JOIN roles r ON r.id = ur.role_id AND r.status = 1
             WHERE ur.user_id = :uid',
            ['uid' => $userId]
        );

        return $this->permissionCache[$userId] = array_map(
            static fn (array $r): string => (string) $r['slug'],
            $rows
        );
    }

    /** @return list<string> */
    public function rolesFor(int $userId): array
    {
        if (isset($this->roleCache[$userId])) {
            return $this->roleCache[$userId];
        }

        $rows = $this->db->select(
            'SELECT r.slug FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :uid AND r.status = 1
             ORDER BY r.level ASC',
            ['uid' => $userId]
        );

        return $this->roleCache[$userId] = array_map(
            static fn (array $r): string => (string) $r['slug'],
            $rows
        );
    }

    public function userCan(int $userId, string $permission): bool
    {
        if ($permission === '') {
            return false;
        }

        // Super admin implicitly holds everything.
        if (in_array('super-admin', $this->rolesFor($userId), true)) {
            return true;
        }

        return in_array($permission, $this->permissionsFor($userId), true);
    }

    public function userHasRole(int $userId, string $roleSlug): bool
    {
        return in_array($roleSlug, $this->rolesFor($userId), true);
    }

    public function hasAnyRole(int $userId, array $roleSlugs): bool
    {
        return array_intersect($this->rolesFor($userId), $roleSlugs) !== [];
    }

    /** @return list<string> */
    public function permissionSlugs(): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['slug'],
            $this->db->select('SELECT slug FROM permissions ORDER BY module, slug')
        );
    }

    /** @return list<array<string, mixed>> */
    public function roles(): array
    {
        return $this->db->select('SELECT * FROM roles WHERE status = 1 ORDER BY level ASC');
    }

    /** @return list<array<string, mixed>> */
    public function permissionsByModule(): array
    {
        return $this->db->select('SELECT * FROM permissions ORDER BY module ASC, name ASC');
    }

    public function syncRoles(int $userId, array $roleIds, ?int $assignedBy = null): void
    {
        $this->db->transaction(function (Database $db) use ($userId, $roleIds, $assignedBy): void {
            $db->statement('DELETE FROM user_roles WHERE user_id = :uid', ['uid' => $userId]);

            foreach (array_unique(array_map('intval', $roleIds)) as $roleId) {
                if ($roleId <= 0) {
                    continue;
                }
                $db->insert('user_roles', [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'assigned_by' => $assignedBy,
                    'created_at' => now(),
                ]);
            }
        });

        unset($this->permissionCache[$userId], $this->roleCache[$userId]);
    }

    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->transaction(function (Database $db) use ($roleId, $permissionIds): void {
            $db->statement('DELETE FROM role_permissions WHERE role_id = :rid', ['rid' => $roleId]);

            foreach (array_unique(array_map('intval', $permissionIds)) as $permissionId) {
                if ($permissionId <= 0) {
                    continue;
                }
                $db->insert('role_permissions', [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        });

        $this->permissionCache = [];
    }

    public function forget(int $userId): void
    {
        unset($this->permissionCache[$userId], $this->roleCache[$userId]);
    }
}
