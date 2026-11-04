<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    protected string $table = 'users';

    protected bool $softDeletes = false;

    // Users are never hard-deleted while orders reference them: the table keeps
    // a `deleted_at` column that raw queries in this model filter on explicitly.
    public const STATUS_SUSPENDED = 0;

    public const STATUS_ACTIVE = 1;

    public const STATUS_PENDING_VERIFICATION = 2;

    /** @return array<string, mixed>|null */
    public function findActiveById(int $id): ?array
    {
        return $this->db()->selectOne(
            'SELECT id, name, username, email, phone, status, email_verified_at, locale, avatar,
                    last_login_at, created_at, updated_at
             FROM users WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** @return array<string, mixed>|null includes the password hash */
    public function findByIdentifier(string $identifier): ?array
    {
        $identifier = strtolower(trim($identifier));

        return $this->db()->selectOne(
            'SELECT * FROM users
             WHERE deleted_at IS NULL
               AND (LOWER(email) = :email OR LOWER(username) = :username)
             LIMIT 1',
            ['email' => $identifier, 'username' => $identifier]
        );
    }

    public function emailExists(string $email): bool
    {
        return (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM users WHERE LOWER(email) = :email AND deleted_at IS NULL',
            ['email' => strtolower(trim($email))]
        ) > 0;
    }

    public function usernameExists(string $username): bool
    {
        return (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM users WHERE LOWER(username) = :username AND deleted_at IS NULL',
            ['username' => strtolower(trim($username))]
        ) > 0;
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->db()->selectOne(
            'SELECT * FROM users WHERE LOWER(email) = :email AND deleted_at IS NULL LIMIT 1',
            ['email' => strtolower(trim($email))]
        );
    }

    public function replacePassword(int $userId, string $plainPassword): void
    {
        $hash = \App\Core\Auth::instance()->hashPassword($plainPassword);
        $this->db()->statement(
            'UPDATE users SET password = :hash, updated_at = NOW(), must_change_password = 0 WHERE id = :id',
            ['hash' => $hash, 'id' => $userId]
        );
    }

    public function updateLocale(int $userId, string $locale): void
    {
        $this->db()->statement(
            'UPDATE users SET locale = :locale, updated_at = NOW() WHERE id = :id',
            ['locale' => $locale, 'id' => $userId]
        );
    }

    public function markEmailVerified(int $userId): void
    {
        $this->db()->statement(
            'UPDATE users SET email_verified_at = NOW(), status = 1, updated_at = NOW() WHERE id = :id',
            ['id' => $userId]
        );
    }

    public function setStatus(int $userId, int $status): void
    {
        $this->db()->statement(
            'UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id',
            ['status' => $status, 'id' => $userId]
        );
    }

    /** @param array<string, mixed> $filters */
    public function paginateCustomers(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['u.deleted_at IS NULL'];
        $bindings = [];

        if (!empty($filters['search'])) {
            // Three bindings, not one: an emulated-free prepare cannot reuse a
            // named placeholder, so the same term is passed once per column.
            $like = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(u.name LIKE :search_name OR u.email LIKE :search_email OR u.phone LIKE :search_phone)';
            $bindings['search_name'] = $like;
            $bindings['search_email'] = $like;
            $bindings['search_phone'] = $like;
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = 'u.status = :status';
            $bindings['status'] = (int) $filters['status'];
        }
        if (!empty($filters['has_orders'])) {
            $where[] = 'EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id)';
        }

        $clause = implode(' AND ', $where);
        $sortMap = [
            'newest' => 'u.created_at DESC',
            'oldest' => 'u.created_at ASC',
            'name' => 'u.name ASC',
            'spend' => 'total_spent DESC',
        ];
        $order = $sortMap[(string) ($filters['sort'] ?? 'newest')] ?? $sortMap['newest'];

        $total = (int) $this->db()->scalar('SELECT COUNT(*) FROM users u WHERE ' . $clause, $bindings);
        $offset = max(0, ($page - 1) * $perPage);

        $rows = $this->db()->select(
            'SELECT u.id, u.name, u.email, u.phone, u.status, u.email_verified_at, u.created_at, u.last_login_at,
                    COALESCE((SELECT SUM(o.grand_total) FROM orders o
                              WHERE o.user_id = u.id AND o.status IN (\'completed\',\'delivered\')), 0) AS total_spent,
                    (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count
             FROM users u WHERE ' . $clause . ' ORDER BY ' . $order . ' LIMIT ' . (int) $perPage . ' OFFSET ' . $offset,
            $bindings
        );

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil(max(1, $total) / max(1, $perPage)),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function adminAccounts(): array
    {
        return $this->db()->select(
            'SELECT u.id, u.name, u.email, u.status, u.last_login_at,
                    GROUP_CONCAT(DISTINCT r.slug ORDER BY r.level) AS roles
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.deleted_at IS NULL
             GROUP BY u.id
             ORDER BY u.name ASC'
        );
    }

    public function isStaff(int $userId): bool
    {
        return (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid AND r.staff = 1',
            ['uid' => $userId]
        ) > 0;
    }

    public function customersCount(): int
    {
        return (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM users u
             WHERE u.deleted_at IS NULL
               AND NOT EXISTS (SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id
                               WHERE ur.user_id = u.id AND r.staff = 1)'
        );
    }
}
