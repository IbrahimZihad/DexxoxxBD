<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Supplier brands: the logos, names and catalogue sizes the storefront shows.
 */
final class BrandRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * Active brands with their published product counts.
     *
     * @return list<array<string, mixed>>
     */
    public function allWithCounts(int $limit = 60): array
    {
        return $this->db->select(
            'SELECT b.id, b.name, b.slug, b.logo, b.description, b.country, b.sort_order,
                    (SELECT COUNT(*) FROM products p
                      WHERE p.brand_id = b.id AND p.status = \'published\' AND p.deleted_at IS NULL) AS product_count
             FROM brands b
             WHERE b.status = 1 AND b.deleted_at IS NULL
             ORDER BY b.sort_order ASC, b.name ASC
             LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<string, mixed>|null */
    public function bySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM products p
                      WHERE p.brand_id = b.id AND p.status = \'published\' AND p.deleted_at IS NULL) AS product_count
             FROM brands b
             WHERE b.slug = :slug AND b.status = 1 AND b.deleted_at IS NULL',
            ['slug' => $slug]
        );
    }

    /** @return list<array<string, mixed>> */
    public function popular(int $limit = 10): array
    {
        return $this->db->select(
            'SELECT b.id, b.name, b.slug, b.logo, b.country, COUNT(p.id) AS product_count,
                    COALESCE(MAX(p.rating_avg), 0) AS top_rating
             FROM brands b
             INNER JOIN products p ON p.brand_id = b.id
                  AND p.status = \'published\' AND p.deleted_at IS NULL
             WHERE b.status = 1 AND b.deleted_at IS NULL
             GROUP BY b.id, b.name, b.slug, b.logo, b.country
             ORDER BY product_count DESC, COALESCE(SUM(p.sales_count), 0) DESC, b.name ASC
             LIMIT ' . max(1, $limit)
        );
    }

    /** @return list<string> */
    public function slugsForSitemap(): array
    {
        $slugs = [];
        foreach ($this->db->select('SELECT slug FROM brands WHERE status = 1 AND deleted_at IS NULL') as $row) {
            $slugs[] = (string) $row['slug'];
        }

        return $slugs;
    }
}
