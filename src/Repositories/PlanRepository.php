<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class PlanRepository
{
    public static function paginate(array $filters, bool $includeInactive): array
    {
        $where = $includeInactive ? '1=1' : "status = 'active'";
        $bindings = [];

        if (!empty($filters['q'])) {
            $like = '%' . addcslashes((string) $filters['q'], '%_\\') . '%';
            $where .= ' AND (name LIKE ? OR description LIKE ?)';
            $bindings[] = $like;
            $bindings[] = $like;
        }

        $perPage = max(1, min(60, (int) ($filters['per_page'] ?? 20)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $total = (int) (Database::selectOne("SELECT COUNT(*) AS c FROM plans WHERE $where", $bindings)['c'] ?? 0);
        $rows = Database::select(
            "SELECT id, name, slug, description, price, duration_days, features, badge, status, sort_order, created_at, updated_at
             FROM plans WHERE $where ORDER BY sort_order ASC, price ASC LIMIT $perPage OFFSET $offset",
            $bindings
        );

        foreach ($rows as &$row) {
            $row['features'] = self::decodeFeatures($row['features'] ?? null);
        }

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function find(int $id, bool $activeOnly = false): ?array
    {
        $plan = Database::selectOne(
            'SELECT id, name, slug, description, price, duration_days, features, badge, status, sort_order
             FROM plans WHERE id = ?' . ($activeOnly ? " AND status = 'active'" : '') . ' LIMIT 1',
            [$id]
        );

        return self::hydrate($plan);
    }

    public static function findBySlug(string $slug, bool $activeOnly = false): ?array
    {
        $plan = Database::selectOne(
            'SELECT id, name, slug, description, price, duration_days, features, badge, status, sort_order
             FROM plans WHERE slug = ?' . ($activeOnly ? " AND status = 'active'" : '') . ' LIMIT 1',
            [$slug]
        );

        return self::hydrate($plan);
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO plans (name, slug, description, price, duration_days, features, badge, status, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'],
                $data['slug'],
                $data['description'] ?? '',
                $data['price'],
                $data['duration_days'],
                json_encode($data['features'] ?? [], JSON_UNESCAPED_UNICODE),
                $data['badge'] ?? null,
                $data['status'] ?? 'active',
                $data['sort_order'] ?? 0,
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        if (array_key_exists('features', $data)) {
            $data['features'] = json_encode((array) $data['features'], JSON_UNESCAPED_UNICODE);
        }

        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $sets[] = "`$column` = ?";
            $bindings[] = $value;
        }

        if ($sets === []) {
            return;
        }

        $bindings[] = $id;
        Database::statement('UPDATE plans SET ' . implode(', ', $sets) . ' WHERE id = ?', $bindings);
    }

    public static function delete(int $id): void
    {
        Database::statement('DELETE FROM plans WHERE id = ?', [$id]);
    }

    /** Row locks for checkout, keyed by plan id (see OrderService::createFromCart). */
    public static function lockForCheckout(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = Database::pdo()->prepare(
            "SELECT id, name, price, duration_days, status FROM plans WHERE id IN ($placeholders) FOR UPDATE"
        );
        $statement->execute($ids);

        $locked = [];

        foreach ($statement->fetchAll() as $row) {
            $locked[(int) $row['id']] = $row;
        }

        return $locked;
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $clean = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($base)) ?? '', '-') ?: 'plan';
        $candidate = $clean;
        $suffix = 1;

        while (true) {
            $sql = 'SELECT 1 AS x FROM plans WHERE slug = ?';
            $bindings = [$candidate];

            if ($ignoreId !== null) {
                $sql .= ' AND id <> ?';
                $bindings[] = $ignoreId;
            }

            if (Database::selectOne($sql . ' LIMIT 1', $bindings) === null) {
                return $candidate;
            }

            $candidate = $clean . '-' . (++$suffix);
        }
    }

    private static function hydrate(?array $plan): ?array
    {
        if ($plan === null) {
            return null;
        }

        $plan['features'] = self::decodeFeatures($plan['features'] ?? null);

        return $plan;
    }

    private static function decodeFeatures(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
