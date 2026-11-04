<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;

/**
 * The permission catalogue and the role -> permission grants.
 *
 * Grants are re-synced on every run, so narrowing a role in
 * database/factories/rbac.php is reflected by a plain `seed:run` instead of
 * leaving stale abilities behind.
 */
final class PermissionSeeder extends AbstractSeed
{
    public function run(): void
    {
        Seed::ensure($this, RoleSeeder::class);

        $permissions = Seed::list('rbac', 'permissions');
        $ids = [];

        foreach ($permissions as $permission) {
            $ids[$permission['slug']] = Seed::upsert($this, 'permissions', ['slug' => $permission['slug']], [
                'name' => $permission['name'],
                'module' => $permission['module'],
                'description' => $permission['description'],
            ]);
        }

        foreach (Seed::list('rbac', 'roles') as $role) {
            $roleId = Seed::id($this, 'roles', ['slug' => $role['slug']]);
            if ($roleId === null) {
                throw new RuntimeException('Role [' . $role['slug'] . '] was not seeded.');
            }

            $granted = $role['permissions'] === '*'
                ? array_keys($ids)
                : (array) $role['permissions'];

            $rows = [];
            foreach ($granted as $slug) {
                if (!isset($ids[$slug])) {
                    throw new RuntimeException(
                        'Role [' . $role['slug'] . '] grants an unknown permission [' . $slug . '].'
                    );
                }
                $rows[] = ['role_id' => $roleId, 'permission_id' => $ids[$slug]];
            }

            Seed::sync($this, 'role_permissions', 'role_id', $roleId, $rows);
        }
    }
}
