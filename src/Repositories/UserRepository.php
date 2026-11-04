<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class UserRepository
{
    private const PUBLIC_COLUMNS = 'id, name, email, phone, role, status, created_at, last_login_at';

    public static function find(int $id): ?array
    {
        return Database::selectOne(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM users WHERE id = ?',
            [$id]
        );
    }

    /** Login lookup — the only place the hash leaves the table. */
    public static function findByEmailWithHash(string $email): ?array
    {
        return Database::selectOne('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE email = ?';
        $bindings = [$email];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $bindings[] = $exceptId;
        }

        return Database::selectOne($sql . ' LIMIT 1', $bindings) !== null;
    }

    public static function create(string $name, string $email, string $passwordHash, ?string $phone, string $role = 'customer'): int
    {
        return Database::insert(
            'INSERT INTO users (name, email, password_hash, phone, role, status) VALUES (?, ?, ?, ?, ?, \'active\')',
            [$name, $email, $passwordHash, $phone, $role]
        );
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        Database::statement('UPDATE users SET password_hash = ? WHERE id = ?', [$passwordHash, $id]);
    }

    public static function touchLogin(int $id): void
    {
        Database::statement('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public static function updateRoleOrStatus(int $id, ?string $role, ?string $status): void
    {
        $sets = [];
        $bindings = [];

        if ($role !== null) {
            $sets[] = 'role = ?';
            $bindings[] = $role;
        }
        if ($status !== null) {
            $sets[] = 'status = ?';
            $bindings[] = $status;
        }

        if ($sets === []) {
            return;
        }

        $bindings[] = $id;
        Database::statement('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $bindings);
    }

    /** @return array{data: list<array<string,mixed>>, total: int, page: int, per_page: int} */
    public static function paginate(int $page, int $perPage, string $search = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $where = '1=1';
        $bindings = [];

        if ($search !== '') {
            $where .= ' AND (name LIKE ? OR email LIKE ?)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $bindings[] = $like;
            $bindings[] = $like;
        }

        $total = (int) (Database::selectOne("SELECT COUNT(*) AS c FROM users WHERE $where", $bindings)['c'] ?? 0);
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            'SELECT ' . self::PUBLIC_COLUMNS . " FROM users WHERE $where ORDER BY created_at DESC, id DESC LIMIT $perPage OFFSET $offset",
            $bindings
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function counts(): array
    {
        $row = Database::selectOne(
            "SELECT COUNT(*) AS total, SUM(role = 'admin') AS admins, SUM(status = 'suspended') AS suspended FROM users"
        ) ?? [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'admins' => (int) ($row['admins'] ?? 0),
            'suspended' => (int) ($row['suspended'] ?? 0),
        ];
    }
}
