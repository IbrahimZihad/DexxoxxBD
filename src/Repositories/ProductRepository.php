<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ProductRepository
{
    private const SELECT = 'SELECT p.id, p.category_id, p.name, p.slug, p.description, p.image_url,
               p.price, p.compare_at_price, p.duration_days, p.stock, p.status, p.is_featured,
               p.sold_count, p.created_at, p.updated_at, c.name AS category_name, c.slug AS category_slug
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id';

    /**
     * @param array<string,mixed> $filters category|q|featured|status|sort|page|per_page
     * @return array{data: list<array<string,mixed>>, total: int, page: int, per_page: int}
     */
    public static function paginate(array $filters, bool $includeInactive): array
    {
        $where = [];
        $bindings = [];

        if (!$includeInactive) {
            $where[] = "p.status = 'active'";
        }

        if (!empty($filters['category'])) {
            $where[] = 'c.slug = ?';
            $bindings[] = (string) $filters['category'];
        }

        if (!empty($filters['featured'])) {
            $where[] = 'p.is_featured = 1';
        }

        if (!empty($filters['q'])) {
            // Wildcards in user input are escaped so "sale" cannot become "%sale%" tricks.
            $like = '%' . addcslashes((string) $filters['q'], '%_\\') . '%';
            $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
            $bindings[] = $like;
            $bindings[] = $like;
        }

        if (!empty($filters['on_sale'])) {
            $where[] = 'p.compare_at_price IS NOT NULL AND p.compare_at_price > p.price';
        }

        if (!empty($filters['in_stock'])) {
            $where[] = 'p.stock > 0';
        }

        $clause = $where === [] ? '1=1' : implode(' AND ', $where);
        $order = self::orderBy((string) ($filters['sort'] ?? 'newest'));
        $perPage = max(1, min(60, (int) ($filters['per_page'] ?? 12)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $total = (int) (Database::selectOne(
            "SELECT COUNT(*) AS c FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $clause",
            $bindings
        )['c'] ?? 0);

        $rows = Database::select(self::SELECT . " WHERE $clause ORDER BY $order LIMIT $perPage OFFSET $offset", $bindings);

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    private static function orderBy(string $sort): string
    {
        return match ($sort) {
            'price_asc' => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'name' => 'p.name ASC',
            'popular' => 'p.sold_count DESC, p.id DESC',
            'featured' => 'p.is_featured DESC, p.created_at DESC',
            default => 'p.created_at DESC, p.id DESC',
        };
    }

    public static function find(int $id, bool $activeOnly = false): ?array
    {
        $sql = self::SELECT . ' WHERE p.id = ?' . ($activeOnly ? " AND p.status = 'active'" : '') . ' LIMIT 1';

        return Database::selectOne($sql, [$id]);
    }

    public static function findBySlug(string $slug, bool $activeOnly = false): ?array
    {
        $sql = self::SELECT . ' WHERE p.slug = ?' . ($activeOnly ? " AND p.status = 'active'" : '') . ' LIMIT 1';

        return Database::selectOne($sql, [$slug]);
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO products (category_id, name, slug, description, image_url, price, compare_at_price,
                    duration_days, stock, status, is_featured)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['category_id'] ?? null,
                $data['name'],
                $data['slug'],
                $data['description'] ?? '',
                $data['image_url'] ?? null,
                $data['price'],
                $data['compare_at_price'] ?? null,
                $data['duration_days'] ?? 30,
                $data['stock'] ?? 0,
                $data['status'] ?? 'active',
                !empty($data['is_featured']) ? 1 : 0,
            ]
        );
    }

    /** @param array<string,mixed> $data already whitelisted column => value */
    public static function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }

        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $sets[] = "`$column` = ?";
            $bindings[] = $value;
        }

        $bindings[] = $id;
        Database::statement('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?', $bindings);
    }

    public static function delete(int $id): void
    {
        Database::statement('DELETE FROM products WHERE id = ?', [$id]);
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $clean = preg_replace('/[^a-z0-9]+/', '-', strtolower($base)) ?? '';
        $clean = trim($clean, '-') ?: 'product';
        $candidate = $clean;
        $suffix = 1;

        while (true) {
            $sql = 'SELECT 1 AS x FROM products WHERE slug = ? LIMIT 1';
            $bindings = [$candidate];

            if ($ignoreId !== null) {
                $sql = 'SELECT 1 AS x FROM products WHERE slug = ? AND id <> ? LIMIT 1';
                $bindings[] = $ignoreId;
            }

            if (Database::selectOne($sql, $bindings) === null) {
                return $candidate;
            }

            $candidate = $clean . '-' . (++$suffix);
        }
    }

    /**
     * Locks the rows a checkout needs and returns them keyed by id.
     * Must run inside a transaction; this is what stops two buyers draining the
     * same stock unit at the same instant.
     */
    public static function lockForCheckout(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = Database::pdo()->prepare(
            "SELECT id, name, price, duration_days, stock, status FROM products
             WHERE id IN ($placeholders) FOR UPDATE"
        );
        $statement->execute($ids);

        $locked = [];

        foreach ($statement->fetchAll() as $row) {
            $locked[(int) $row['id']] = $row;
        }

        return $locked;
    }

    /** Atomically reserves stock; returns false when the row no longer has enough. */
    public static function reserveStock(int $productId, int $quantity): bool
    {
        $affected = Database::statement(
            'UPDATE products SET stock = stock - ?, sold_count = sold_count + ? WHERE id = ? AND stock >= ?',
            [$quantity, $quantity, $productId, $quantity]
        );

        return $affected === 1;
    }

    /** Returns reserved stock when an order dies without payment. */
    public static function releaseStock(PDO $pdo, int $productId, int $quantity): void
    {
        $statement = $pdo->prepare('UPDATE products SET stock = stock + ?, sold_count = GREATEST(0, sold_count - ?) WHERE id = ?');
        $statement->execute([$quantity, $quantity, $productId]);
    }
}
