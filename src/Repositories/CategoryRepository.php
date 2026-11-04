<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class CategoryRepository
{
    public static function all(): array
    {
        return Database::select(
            'SELECT c.id, c.name, c.slug, c.icon, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.status = \'active\'
             GROUP BY c.id, c.name, c.slug, c.icon
             ORDER BY c.sort_order ASC, c.name ASC'
        );
    }

    public static function idForSlug(string $slug): ?int
    {
        $row = Database::selectOne('SELECT id FROM categories WHERE slug = ?', [$slug]);

        return $row === null ? null : (int) $row['id'];
    }

    public static function exists(int $id): bool
    {
        return Database::selectOne('SELECT 1 AS x FROM categories WHERE id = ? LIMIT 1', [$id]) !== null;
    }
}
