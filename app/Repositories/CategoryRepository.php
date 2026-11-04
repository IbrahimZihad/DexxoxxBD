<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Read-only access to the category tree.
 *
 * The storefront only ever shows active, non-deleted categories, so every query
 * carries those predicates instead of trusting callers to remember them.
 */
final class CategoryRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * Every active category with its live product count, ordered for display.
     *
     * @return list<array<string, mixed>>
     */
    public function allWithCounts(): array
    {
        return $this->db->select(
            'SELECT c.id, c.parent_id, c.name, c.slug, c.description, c.image, c.icon, c.color,
                    c.is_featured, c.sort_order,
                    (SELECT COUNT(*) FROM products p
                      WHERE p.category_id = c.id AND p.status = \'published\' AND p.deleted_at IS NULL) AS product_count
             FROM categories c
             WHERE c.status = 1 AND c.deleted_at IS NULL
             ORDER BY c.sort_order ASC, c.name ASC'
        );
    }

    /**
     * Top-level categories, each carrying its children.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(): array
    {
        $rows = $this->allWithCounts();

        $known = [];
        foreach ($rows as $row) {
            $known[(int) $row['id']] = true;
        }

        $roots = [];
        $childrenByParent = [];
        foreach ($rows as $row) {
            $parentId = $row['parent_id'] === null ? null : (int) $row['parent_id'];
            if ($parentId === null || !isset($known[$parentId])) {
                $roots[] = $row;
                continue;
            }
            $childrenByParent[$parentId][] = $row;
        }

        $attach = static function (array $node) use (&$attach, $childrenByParent): array {
            $children = [];
            foreach ($childrenByParent[(int) $node['id']] ?? [] as $child) {
                $children[] = $attach($child);
            }
            $node['children'] = $children;
            return $node;
        };

        return array_map($attach, $roots);
    }

    /** @return list<array<string, mixed>> */
    public function topLevel(int $limit = 12): array
    {
        return $this->db->select(
            'SELECT c.id, c.name, c.slug, c.description, c.image, c.icon, c.color, c.sort_order,
                    (SELECT COUNT(*) FROM products p
                      WHERE (p.category_id = c.id OR p.category_id IN
                            (SELECT k.id FROM categories k WHERE k.parent_id = c.id))
                        AND p.status = \'published\' AND p.deleted_at IS NULL) AS product_count
             FROM categories c
             WHERE c.parent_id IS NULL AND c.status = 1 AND c.deleted_at IS NULL
             ORDER BY c.sort_order ASC, c.name ASC
             LIMIT ' . max(1, $limit)
        );
    }

    /** @return list<array<string, mixed>> */
    public function featured(int $limit = 8): array
    {
        return $this->db->select(
            'SELECT c.id, c.name, c.slug, c.image, c.icon, c.color, c.sort_order,
                    (SELECT COUNT(*) FROM products p
                      WHERE p.category_id = c.id AND p.status = \'published\' AND p.deleted_at IS NULL) AS product_count
             FROM categories c
             WHERE c.parent_id IS NULL AND c.is_featured = 1 AND c.status = 1 AND c.deleted_at IS NULL
             ORDER BY c.sort_order ASC, c.name ASC
             LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<string, mixed>|null */
    public function bySlug(string $slug): ?array
    {
        $category = $this->db->selectOne(
            'SELECT c.*, (SELECT COUNT(*) FROM products p
                          WHERE p.category_id = c.id AND p.status = \'published\' AND p.deleted_at IS NULL) AS product_count
             FROM categories c
             WHERE c.slug = :slug AND c.status = 1 AND c.deleted_at IS NULL',
            ['slug' => $slug]
        );

        if (!is_array($category)) {
            return null;
        }

        $id = (int) $category['id'];
        $category['children'] = $this->children($id);
        $category['parent'] = $category['parent_id'] === null
            ? null
            : $this->db->selectOne(
                'SELECT id, name, slug FROM categories WHERE id = :id AND status = 1 AND deleted_at IS NULL',
                ['id' => (int) $category['parent_id']]
            );

        return $category;
    }

    /** @return list<array<string, mixed>> */
    public function children(int $parentId): array
    {
        return $this->db->select(
            'SELECT c.id, c.name, c.slug, c.image, c.icon, c.sort_order,
                    (SELECT COUNT(*) FROM products p
                      WHERE p.category_id = c.id AND p.status = \'published\' AND p.deleted_at IS NULL) AS product_count
             FROM categories c
             WHERE c.parent_id = :parent AND c.status = 1 AND c.deleted_at IS NULL
             ORDER BY c.sort_order ASC, c.name ASC',
            ['parent' => $parentId]
        );
    }

    /**
     * Sibling categories under the same parent, used for cross-navigation.
     *
     * @return list<array<string, mixed>>
     */
    public function siblings(?int $parentId, int $excludeId, int $limit = 8): array
    {
        // Built in PHP because a NULL parent cannot be compared with a placeholder.
        $scope = $parentId === null ? 'c.parent_id IS NULL' : 'c.parent_id = :parent';
        $bindings = ['exclude' => $excludeId];
        if ($parentId !== null) {
            $bindings['parent'] = $parentId;
        }

        return $this->db->select(
            'SELECT c.id, c.name, c.slug, c.icon, c.image,
                    (SELECT COUNT(*) FROM products p
                      WHERE p.category_id = c.id AND p.status = \'published\' AND p.deleted_at IS NULL) AS product_count
             FROM categories c
             WHERE c.status = 1 AND c.deleted_at IS NULL AND c.id <> :exclude
               AND ' . $scope . '
             ORDER BY c.sort_order ASC, c.name ASC
             LIMIT ' . max(1, $limit),
            $bindings
        );
    }

    /** @return list<string> */
    public function slugsForSitemap(): array
    {
        $slugs = [];
        foreach ($this->db->select('SELECT slug FROM categories WHERE status = 1 AND deleted_at IS NULL') as $row) {
            $slugs[] = (string) $row['slug'];
        }

        return $slugs;
    }
}
