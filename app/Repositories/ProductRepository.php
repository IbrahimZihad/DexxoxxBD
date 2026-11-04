<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Config;
use App\Core\Database;

/**
 * Catalog reads. Every listing query is built here so filtering, sorting and
 * pagination stay consistent across the storefront, sitemaps and the API.
 */
final class ProductRepository
{
    private Database $db;

    /** @var list<string> */
    private const SORTS = ['newest', 'oldest', 'price_asc', 'price_desc', 'popular', 'rating', 'name', 'discount'];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** @return array<string, mixed>|null */
    public function findPublishable(int $productId): ?array
    {
        return $this->db->selectOne(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name, b.slug AS brand_slug
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE p.id = :id AND p.status = \'published\' AND p.deleted_at IS NULL',
            ['id' => $productId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.parent_id AS category_parent_id,
                    b.name AS brand_name, b.slug AS brand_slug, b.logo AS brand_logo
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE p.slug = :slug AND p.status = \'published\' AND p.deleted_at IS NULL',
            ['slug' => $slug]
        );
    }

    /**
     * Filtered, sorted and paginated catalog page.
     *
     * @param array{
     *   search?: string, category?: string, brand?: string, min_price?: float, max_price?: float,
     *   duration?: string, availability?: string, rating?: float, sort?: string, featured?: bool,
     *   bestseller?: bool, new_arrival?: bool, on_sale?: bool, page?: int, per_page?: int, status?: string
     * } $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? Config::get('app.pagination.per_page', 12));
        $perPage = max(1, min((int) Config::get('app.pagination.max_per_page', 100), $perPage));

        [$where, $bindings] = $this->filterClause($filters);

        $countSql = 'SELECT COUNT(DISTINCT p.id) FROM products p
                     INNER JOIN categories c ON c.id = p.category_id
                     LEFT JOIN brands b ON b.id = p.brand_id
                     INNER JOIN product_variations v ON v.product_id = p.id
                          AND v.status = 1 AND v.deleted_at IS NULL
                     WHERE ' . implode(' AND ', $where);

        $total = (int) $this->db->scalar($countSql, $bindings);

        $order = $this->orderClause((string) ($filters['sort'] ?? 'newest'));
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            'SELECT p.id, p.name, p.slug, p.short_description, p.description, p.image, p.badge,
                    p.is_featured, p.is_bestseller, p.is_new_arrival, p.rating_avg, p.rating_count,
                    p.view_count, p.sales_count, p.warranty_info, p.delivery_method, p.account_type,
                    c.name AS category_name, c.slug AS category_slug,
                    b.name AS brand_name, b.slug AS brand_slug,
                    MIN(v.price) AS from_price,
                    (SELECT v2.compare_price FROM product_variations v2
                      WHERE v2.product_id = p.id AND v2.status = 1 AND v2.deleted_at IS NULL
                      ORDER BY v2.price ASC LIMIT 1) AS from_compare_price,
                    MAX(v.duration_days) AS max_duration_days,
                    SUM(GREATEST(v.stock, 0)) AS total_stock,
                    MAX(v.stock = -1) AS has_unlimited
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             INNER JOIN product_variations v ON v.product_id = p.id
                  AND v.status = 1 AND v.deleted_at IS NULL
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY p.id
             ' . $order . '
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $bindings
        );

        foreach ($rows as &$row) {
            $row['on_sale'] = $this->rowOnSale($row);
            $row['percent_off'] = $this->rowPercentOff($row);
            $row['available'] = $this->rowAvailable($row);
        }
        unset($row);

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil(max(1, $total) / $perPage),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{list<string>, array<string, mixed>}
     */
    private function filterClause(array $filters): array
    {
        $where = ["p.status = 'published'", 'p.deleted_at IS NULL', 'c.status = 1'];
        $bindings = [];

        if (!empty($filters['search'])) {
            $where[] = '(p.name LIKE :search OR p.short_description LIKE :search
                        OR b.name LIKE :search OR c.name LIKE :search)';
            $bindings['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (!empty($filters['category'])) {
            $slug = (string) $filters['category'];
            $where[] = '(c.slug = :category OR c.parent_id = '
                . '(SELECT pc.id FROM categories pc WHERE pc.slug = :category2))';
            $bindings['category'] = $slug;
            $bindings['category2'] = $slug;
        }

        if (!empty($filters['category_id'])) {
            $where[] = '(c.id = :category_id OR c.parent_id = :category_id)';
            $bindings['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['brand'])) {
            $brands = array_values(array_filter(
                (array) $filters['brand'],
                static fn ($v): bool => $v !== '' && $v !== null
            ));
            if ($brands !== []) {
                $placeholders = [];
                foreach ($brands as $index => $brand) {
                    $key = 'brand_' . $index;
                    $placeholders[] = ':' . $key;
                    $bindings[$key] = (string) $brand;
                }
                $where[] = 'b.slug IN (' . implode(',', $placeholders) . ')';
            }
        }

        $minPrice = $filters['min_price'] ?? null;
        $maxPrice = $filters['max_price'] ?? null;
        if ($minPrice !== null && is_numeric($minPrice)) {
            $where[] = 'v.price >= :min_price';
            $bindings['min_price'] = (float) $minPrice;
        }
        if ($maxPrice !== null && is_numeric($maxPrice)) {
            $where[] = 'v.price <= :max_price';
            $bindings['max_price'] = (float) $maxPrice;
        }

        $duration = (string) ($filters['duration'] ?? '');
        if ($duration !== '' && $duration !== 'all') {
            $range = match ($duration) {
                '1m' => [25, 35],
                '3m' => [85, 95],
                '6m' => [175, 190],
                '12m' => [350, 380],
                'lifetime' => [3650, 365000],
                default => null,
            };
            if ($range !== null) {
                $where[] = 'v.duration_days BETWEEN :duration_low AND :duration_high';
                $bindings['duration_low'] = $range[0];
                $bindings['duration_high'] = $range[1];
            }
        }

        $availability = (string) ($filters['availability'] ?? '');
        if ($availability === 'in_stock') {
            $where[] = '(v.stock > 0 OR v.stock = -1)';
        } elseif ($availability === 'out_of_stock') {
            $where[] = 'v.stock = 0';
        }

        $rating = $filters['rating'] ?? null;
        if (is_numeric($rating) && (float) $rating > 0) {
            $where[] = 'p.rating_avg >= :rating';
            $bindings['rating'] = (float) $rating;
        }

        if (filter_var($filters['featured'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $where[] = 'p.is_featured = 1';
        }
        if (filter_var($filters['bestseller'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $where[] = 'p.is_bestseller = 1';
        }
        if (filter_var($filters['new_arrival'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $where[] = 'p.is_new_arrival = 1';
        }
        if (filter_var($filters['on_sale'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $where[] = 'v.compare_price IS NOT NULL AND v.compare_price > v.price';
        }

        if (filter_var($filters['digital_delivery'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $where[] = "v.delivery_method IN ('auto_key','auto_code')";
        }

        return [$where, $bindings];
    }

    private function orderClause(string $sort): string
    {
        if (!in_array($sort, self::SORTS, true)) {
            $sort = 'newest';
        }

        return match ($sort) {
            'oldest' => 'ORDER BY p.created_at ASC, p.id ASC',
            'price_asc' => 'ORDER BY from_price ASC, p.name ASC',
            'price_desc' => 'ORDER BY from_price DESC, p.name ASC',
            'popular' => 'ORDER BY p.sales_count DESC, p.view_count DESC, p.id DESC',
            'rating' => 'ORDER BY p.rating_avg DESC, p.rating_count DESC, p.id DESC',
            'name' => 'ORDER BY p.name ASC',
            'discount' => 'ORDER BY (from_compare_price - from_price) DESC, p.id DESC',
            default => 'ORDER BY p.is_featured DESC, p.created_at DESC, p.id DESC',
        };
    }

    /** @param array<string, mixed> $row */
    private function rowOnSale(array $row): bool
    {
        return $row['from_compare_price'] !== null
            && (float) $row['from_compare_price'] > (float) $row['from_price'];
    }

    /** @param array<string, mixed> $row */
    private function rowPercentOff(array $row): int
    {
        if (!$this->rowOnSale($row)) {
            return 0;
        }
        return percent_off($row['from_compare_price'], $row['from_price']);
    }

    /**
     * @param array<string, mixed> $row
     *
     * -1 is the "not stock limited" sentinel used by manually delivered plans,
     * so a variation carrying it always counts as available.
     */
    private function rowAvailable(array $row): bool
    {
        if ((int) ($row['has_unlimited'] ?? 0) === 1) {
            return true;
        }
        return (int) ($row['total_stock'] ?? 0) > 0;
    }

    /** @return list<array<string, mixed>> */
    public function featured(int $limit = 8): array
    {
        return $this->summaryQuery(
            "p.status = 'published' AND p.deleted_at IS NULL AND p.is_featured = 1",
            'ORDER BY p.sort_order ASC, p.sales_count DESC, p.id DESC',
            $limit
        );
    }

    /** @return list<array<string, mixed>> */
    public function bestSellers(int $limit = 8): array
    {
        return $this->summaryQuery(
            "p.status = 'published' AND p.deleted_at IS NULL AND p.is_bestseller = 1",
            'ORDER BY p.sales_count DESC, p.rating_avg DESC, p.id DESC',
            $limit
        );
    }

    /** @return list<array<string, mixed>> */
    public function newArrivals(int $limit = 8): array
    {
        return $this->summaryQuery(
            "p.status = 'published' AND p.deleted_at IS NULL AND p.is_new_arrival = 1",
            'ORDER BY p.created_at DESC, p.id DESC',
            $limit
        );
    }

    /** @return list<array<string, mixed>> */
    public function onSale(int $limit = 8): array
    {
        return $this->summaryQuery(
            "p.status = 'published' AND p.deleted_at IS NULL
              AND EXISTS (SELECT 1 FROM product_variations v WHERE v.product_id = p.id
                          AND v.status = 1 AND v.deleted_at IS NULL
                          AND v.compare_price IS NOT NULL AND v.compare_price > v.price)",
            'ORDER BY p.sales_count DESC, p.id DESC',
            $limit
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function summaryQuery(string $where, string $order, int $limit): array
    {
        $rows = $this->db->select(
            'SELECT p.id, p.name, p.slug, p.short_description, p.image, p.badge, p.rating_avg, p.rating_count,
                    p.is_featured, p.is_bestseller, p.is_new_arrival, p.delivery_method, p.warranty_info,
                    c.name AS category_name, c.slug AS category_slug, b.name AS brand_name, b.slug AS brand_slug,
                    (SELECT MIN(v.price) FROM product_variations v
                      WHERE v.product_id = p.id AND v.status = 1 AND v.deleted_at IS NULL) AS from_price,
                    (SELECT v2.compare_price FROM product_variations v2
                      WHERE v2.product_id = p.id AND v2.status = 1 AND v2.deleted_at IS NULL
                      ORDER BY v2.price ASC LIMIT 1) AS from_compare_price,
                    (SELECT SUM(GREATEST(v3.stock, 0)) FROM product_variations v3
                      WHERE v3.product_id = p.id AND v3.status = 1 AND v3.deleted_at IS NULL) total_stock,
                    (SELECT MAX(v4.stock = -1) FROM product_variations v4
                      WHERE v4.product_id = p.id AND v4.status = 1 AND v4.deleted_at IS NULL) has_unlimited
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE ' . $where . '
             GROUP BY p.id
             ' . $order . '
             LIMIT ' . max(1, min(60, $limit))
        );

        foreach ($rows as &$row) {
            $row['from_price'] = (float) ($row['from_price'] ?? 0);
            $row['on_sale'] = $this->rowOnSale($row);
            $row['percent_off'] = $this->rowPercentOff($row);
            $row['available'] = $this->rowAvailable($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * Columns every product card needs, so the catalogue, the homepage strips and
     * the related block cannot drift apart.
     */
    private const CARD_COLUMNS = 'p.id, p.name, p.slug, p.image, p.badge, p.rating_avg, p.rating_count,
        p.short_description, p.is_featured, p.is_bestseller, p.is_new_arrival,
        c.name AS category_name, c.slug AS category_slug, b.name AS brand_name, b.slug AS brand_slug,
        (SELECT MIN(v.price) FROM product_variations v
          WHERE v.product_id = p.id AND v.status = 1 AND v.deleted_at IS NULL) AS from_price,
        (SELECT v2.compare_price FROM product_variations v2
          WHERE v2.product_id = p.id AND v2.status = 1 AND v2.deleted_at IS NULL
          ORDER BY v2.price ASC LIMIT 1) AS from_compare_price,
        (SELECT SUM(GREATEST(v3.stock, 0)) FROM product_variations v3
          WHERE v3.product_id = p.id AND v3.status = 1 AND v3.deleted_at IS NULL) AS total_stock,
        (SELECT MAX(v4.stock = -1) FROM product_variations v4
          WHERE v4.product_id = p.id AND v4.status = 1 AND v4.deleted_at IS NULL) AS has_unlimited';

    /**
     * Related products.
     *
     * Staff-curated pairs from product_related_items come first; the remaining
     * slots are filled from the same category, then the same brand.
     *
     * @return list<array<string, mixed>>
     */
    public function related(int $productId, int $categoryId, ?int $brandId, int $limit = 4): array
    {
        $limit = max(1, min(12, $limit));

        $rows = $this->db->select(
            'SELECT ' . self::CARD_COLUMNS . '
             FROM product_related_items ri
             INNER JOIN products p ON p.id = ri.related_product_id
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE ri.product_id = :pid AND p.status = \'published\' AND p.deleted_at IS NULL
               AND c.status = 1 AND c.deleted_at IS NULL
             ORDER BY ri.sort_order ASC, ri.id ASC
             LIMIT ' . $limit,
            ['pid' => $productId]
        );

        $taken = array_map(static fn (array $row): int => (int) $row['id'], $rows);

        if (count($rows) < $limit) {
            $exclude = array_merge([$productId], $taken);
            $placeholders = [];
            foreach ($exclude as $index => $id) {
                $placeholders[] = ':ex' . $index;
            }

            $sql = 'SELECT ' . self::CARD_COLUMNS . '
                 FROM products p
                 INNER JOIN categories c ON c.id = p.category_id
                 LEFT JOIN brands b ON b.id = p.brand_id
                 WHERE p.status = \'published\' AND p.deleted_at IS NULL
                   AND c.status = 1 AND c.deleted_at IS NULL
                   AND p.id NOT IN (' . implode(',', $placeholders) . ')
                   AND (p.category_id = :cid';

            $bindings = ['cid' => $categoryId];
            foreach ($exclude as $index => $id) {
                $bindings['ex' . $index] = $id;
            }

            if ($brandId !== null) {
                $sql .= ' OR p.brand_id = :bid';
                $bindings['bid'] = $brandId;
            }

            $sql .= ') GROUP BY p.id ORDER BY (p.category_id = :cid2) DESC, p.sales_count DESC LIMIT '
                . ($limit - count($rows));
            $bindings['cid2'] = $categoryId;

            foreach ($this->db->select($sql, $bindings) as $row) {
                $rows[] = $row;
            }
        }

        foreach ($rows as &$row) {
            $row['from_price'] = (float) ($row['from_price'] ?? 0);
            $row['on_sale'] = $this->rowOnSale($row);
            $row['percent_off'] = $this->rowPercentOff($row);
            $row['available'] = $this->rowAvailable($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * Type-ahead suggestions for the header search box.
     *
     * @return list<array{label: string, url: string, type: string}>
     */
    public function suggestions(string $term, int $limit = 8): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%' . $term . '%';

        $products = $this->db->select(
            "SELECT p.name, p.slug, p.image FROM products p
             WHERE p.status = 'published' AND p.deleted_at IS NULL AND p.name LIKE :term
             ORDER BY p.sales_count DESC LIMIT " . max(1, min(20, $limit)),
            ['term' => $like]
        );

        $categories = $this->db->select(
            'SELECT c.name, c.slug FROM categories c
             WHERE c.status = 1 AND c.deleted_at IS NULL AND c.name LIKE :term
             ORDER BY c.sort_order LIMIT 4',
            ['term' => $like]
        );

        $out = [];
        foreach ($products as $product) {
            $out[] = [
                'label' => (string) $product['name'],
                'url' => url('products/' . $product['slug']),
                'type' => 'product',
                'image' => (string) ($product['image'] ?? ''),
            ];
        }
        foreach ($categories as $category) {
            $out[] = [
                'label' => (string) $category['name'],
                'url' => url('categories/' . $category['slug']),
                'type' => 'category',
                'image' => '',
            ];
        }

        return array_slice($out, 0, $limit);
    }

    /** @return list<array<string, mixed>> */
    public function lowestPricedVariations(int $limit = 6): array
    {
        return $this->db->select(
            "SELECT p.id AS product_id, p.name, p.slug, p.image, v.id AS variation_id, v.label, v.sku,
                    v.price, v.compare_price, v.duration_days
             FROM products p
             INNER JOIN product_variations v ON v.product_id = p.id AND v.status = 1 AND v.deleted_at IS NULL
             WHERE p.status = 'published' AND p.deleted_at IS NULL
               AND v.compare_price IS NOT NULL AND v.compare_price > v.price
             ORDER BY (v.compare_price - v.price) / v.compare_price DESC
             LIMIT " . max(1, min(24, $limit))
        );
    }

    public function incrementViews(int $productId): void
    {
        $this->db->statement(
            'UPDATE products SET view_count = view_count + 1 WHERE id = :id',
            ['id' => $productId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function forAdmin(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1 = 1'];
        $bindings = [];

        if (!empty($filters['search'])) {
            $where[] = '(p.name LIKE :search OR p.slug LIKE :search OR v.sku LIKE :search)';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'p.status = :status';
            $bindings['status'] = (string) $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = :category_id';
            $bindings['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['brand_id'])) {
            $where[] = 'p.brand_id = :brand_id';
            $bindings['brand_id'] = (int) $filters['brand_id'];
        }
        if (filter_var($filters['low_stock'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $threshold = (int) Config::get('shop.low_stock_threshold', 5);
            $where[] = 'v.stock BETWEEN 0 AND ' . $threshold;
        }

        $clause = implode(' AND ', $where);
        $offset = max(0, ($page - 1) * $perPage);

        $total = (int) $this->db->scalar(
            'SELECT COUNT(DISTINCT p.id) FROM products p
             INNER JOIN product_variations v ON v.product_id = p.id AND v.deleted_at IS NULL
             WHERE ' . $clause,
            $bindings
        );

        $rows = $this->db->select(
            'SELECT p.*, c.name AS category_name, b.name AS brand_name,
                    COUNT(v.id) AS variation_count,
                    MIN(v.price) AS from_price,
                    SUM(GREATEST(v.stock, 0)) AS total_stock,
                    SUM(v.stock) AS raw_stock
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN product_variations v ON v.product_id = p.id AND v.deleted_at IS NULL AND v.status = 1
             WHERE ' . $clause . '
             GROUP BY p.id
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT ' . (int) $perPage . ' OFFSET ' . $offset,
            $bindings
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage,
                'last_page' => (int) ceil(max(1, $total) / max(1, $perPage))];
    }

    public function publishedCount(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM products WHERE status = 'published' AND deleted_at IS NULL"
        );
    }

    /** @return list<array<string, mixed>> */
    public function slugsForSitemap(): array
    {
        return $this->db->select(
            "SELECT slug, updated_at FROM products WHERE status = 'published' AND deleted_at IS NULL
             ORDER BY id ASC LIMIT 5000"
        );
    }

    /** @return list<array<string, mixed>> */
    public function imagesFor(int $productId): array
    {
        return $this->db->select(
            'SELECT path, alt, is_primary FROM product_images
             WHERE product_id = :id ORDER BY is_primary DESC, sort_order ASC, id ASC',
            ['id' => $productId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function featuresFor(int $productId): array
    {
        return $this->db->select(
            'SELECT label, icon FROM product_features
             WHERE product_id = :id ORDER BY sort_order ASC, id ASC',
            ['id' => $productId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function faqsFor(int $productId): array
    {
        return $this->db->select(
            'SELECT question, answer FROM product_faqs
             WHERE product_id = :id AND status = 1 ORDER BY sort_order ASC, id ASC',
            ['id' => $productId]
        );
    }

    /**
     * Approved reviews only; pending and rejected must never reach a buyer.
     *
     * @return list<array<string, mixed>>
     */
    public function reviewsFor(int $productId, int $limit = 6): array
    {
        return $this->db->select(
            "SELECT author_name, rating, title, body, is_verified_purchase, helpful_count, created_at
             FROM reviews
             WHERE product_id = :id AND status = 'approved' AND deleted_at IS NULL
             ORDER BY helpful_count DESC, created_at DESC
             LIMIT " . max(1, $limit),
            ['id' => $productId]
        );
    }

    /**
     * Rating histogram for the product page summary.
     *
     * @return array<int, int> star count => number of reviews
     */
    public function ratingBreakdown(int $productId): array
    {
        $rows = $this->db->select(
            "SELECT rating, COUNT(*) AS total FROM reviews
             WHERE product_id = :id AND status = 'approved' AND deleted_at IS NULL
             GROUP BY rating",
            ['id' => $productId]
        );

        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($rows as $row) {
            $star = (int) $row['rating'];
            if ($star >= 1 && $star <= 5) {
                $breakdown[$star] = (int) $row['total'];
            }
        }

        return $breakdown;
    }

    /**
     * Public trust figures for the homepage.
     *
     * @return array<string, int>
     */
    public function marketplaceStats(): array
    {
        return [
            'products' => $this->publishedCount(),
            'plans' => (int) $this->db->scalar(
                'SELECT COUNT(*) FROM product_variations v
                 INNER JOIN products p ON p.id = v.product_id
                 WHERE v.status = 1 AND v.deleted_at IS NULL
                   AND p.status = \'published\' AND p.deleted_at IS NULL'
            ),
            'brands' => (int) $this->db->scalar('SELECT COUNT(*) FROM brands WHERE status = 1 AND deleted_at IS NULL'),
            'categories' => (int) $this->db->scalar(
                'SELECT COUNT(*) FROM categories WHERE status = 1 AND deleted_at IS NULL'
            ),
            'delivered' => (int) $this->db->scalar(
                "SELECT COUNT(*) FROM orders WHERE status IN ('completed','delivered')"
            ),
            'reviews' => (int) $this->db->scalar("SELECT COUNT(*) FROM reviews WHERE status = 'approved'"),
        ];
    }
}
