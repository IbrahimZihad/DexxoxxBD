<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * CMS content: banners, testimonials, FAQs, static pages and menus.
 *
 * Rows are always filtered by status and time window here, and locale falls back
 * to English, so a view can never print a draft or an expired campaign.
 */
final class ContentRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * Banners for one placement, live right now, in this locale or English.
     *
     * @return list<array<string, mixed>>
     */
    public function banners(string $placement, string $locale = 'en', int $limit = 4): array
    {
        return $this->db->select(
            'SELECT b.* FROM banners b
             WHERE b.placement = :placement AND b.status = 1
               AND (b.locale = :locale OR b.locale = :fallback)
               AND (b.starts_at IS NULL OR b.starts_at <= NOW())
               AND (b.ends_at IS NULL OR b.ends_at >= NOW())
             ORDER BY b.sort_order ASC, b.id ASC
             LIMIT ' . max(1, $limit),
            ['placement' => $placement, 'locale' => $locale, 'fallback' => 'en']
        );
    }

    /** @return list<array<string, mixed>> */
    public function testimonials(string $locale = 'en', int $limit = 6): array
    {
        return $this->db->select(
            'SELECT t.customer_name, t.customer_title, t.customer_location, t.quote, t.rating,
                    t.avatar, t.product_id, p.name AS product_name, p.slug AS product_slug
             FROM testimonials t
             LEFT JOIN products p ON p.id = t.product_id AND p.deleted_at IS NULL
             WHERE t.status = 1 AND (t.locale = :locale OR t.locale = :fallback)
             ORDER BY t.sort_order ASC, t.id ASC
             LIMIT ' . max(1, $limit),
            ['locale' => $locale, 'fallback' => 'en']
        );
    }

    /** @return list<array<string, mixed>> */
    public function faqs(string $locale = 'en', int $limit = 10, bool $homeOnly = false): array
    {
        $scope = $homeOnly ? 'AND f.show_on_home = 1' : '';

        return $this->db->select(
            'SELECT f.question, f.answer, f.category FROM faqs f
             WHERE f.status = 1 ' . $scope . '
               AND (f.locale = :locale OR f.locale = :fallback)
             ORDER BY f.sort_order ASC, f.id ASC
             LIMIT ' . max(1, $limit),
            ['locale' => $locale, 'fallback' => 'en']
        );
    }

    /** @return array<string, mixed>|null */
    public function page(string $slug, string $locale = 'en'): ?array
    {
        return $this->db->selectOne(
            'SELECT p.* FROM pages p
             WHERE p.slug = :slug AND p.status = \'published\' AND p.deleted_at IS NULL
               AND (p.locale = :locale OR p.locale = :fallback)',
            ['slug' => $slug, 'locale' => $locale, 'fallback' => 'en']
        );
    }

    /** @return list<array<string, mixed>> */
    public function footerPages(int $limit = 12): array
    {
        return $this->db->select(
            'SELECT p.title, p.slug FROM pages p
             WHERE p.status = \'published\' AND p.is_footer = 1 AND p.deleted_at IS NULL
             ORDER BY p.sort_order ASC, p.title ASC
             LIMIT ' . max(1, $limit)
        );
    }

    /**
     * Menu items for a location, nested under their parent, each with a resolved URL.
     *
     * @return list<array<string, mixed>>
     */
    public function menu(string $location): array
    {
        $rows = $this->db->select(
            'SELECT m.id, m.parent_id, m.label, m.target_type, m.target_id, m.url, m.icon, m.open_in_new_tab,
                    p.slug AS page_slug, c.slug AS category_slug, pr.slug AS product_slug
             FROM menus m
             LEFT JOIN pages p ON m.target_type = \'page\' AND p.id = m.target_id
                  AND p.status = \'published\' AND p.deleted_at IS NULL
             LEFT JOIN categories c ON m.target_type = \'category\' AND c.id = m.target_id
                  AND c.status = 1 AND c.deleted_at IS NULL
             LEFT JOIN products pr ON m.target_type = \'product\' AND pr.id = m.target_id
                  AND pr.status = \'published\' AND pr.deleted_at IS NULL
             WHERE m.location = :location AND m.status = 1
             ORDER BY m.sort_order ASC, m.id ASC',
            ['location' => $location]
        );

        $known = [];
        foreach ($rows as $row) {
            $known[(int) $row['id']] = true;
        }

        $roots = [];
        $childrenByParent = [];
        foreach ($rows as $row) {
            $parentId = $row['parent_id'] === null ? null : (int) $row['parent_id'];
            if ($parentId === null || !isset($known[$parentId])) {
                $roots[] = $this->withHref($row);
                continue;
            }
            $childrenByParent[$parentId][] = $this->withHref($row);
        }

        $attach = static function (array $node) use (&$attach, $childrenByParent): array {
            $node['children'] = $childrenByParent[(int) $node['id']] ?? [];
            foreach ($node['children'] as $index => $child) {
                $node['children'][$index] = $attach($child);
            }
            return $node;
        };

        return array_map($attach, $roots);
    }

    /**
     * Link target resolved against live records: a menu entry pointing at a draft
     * page or a removed product renders as nothing rather than a broken link.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function withHref(array $item): array
    {
        $item['href'] = match ((string) $item['target_type']) {
            'page' => is_string($item['page_slug']) && $item['page_slug'] !== ''
                ? url('page/' . $item['page_slug']) : '',
            'category' => is_string($item['category_slug']) && $item['category_slug'] !== ''
                ? url('category/' . $item['category_slug']) : '',
            'product' => is_string($item['product_slug']) && $item['product_slug'] !== ''
                ? url('product/' . $item['product_slug']) : '',
            default => $this->safeExternalUrl($item['url']),
        };

        return $item;
    }

    /**
     * Stored URLs come from the CMS, so only http(s) targets are allowed through
     * and everything else (javascript:, data:) is dropped.
     */
    private function safeExternalUrl(mixed $url): string
    {
        $candidate = trim((string) $url);
        if ($candidate === '') {
            return '';
        }

        $scheme = strtolower((string) (parse_url($candidate, PHP_URL_SCHEME) ?? ''));
        if ($scheme === '') {
            return url(ltrim($candidate, '/'));
        }

        return in_array($scheme, ['http', 'https'], true) ? $candidate : '';
    }

    /** @return list<string> */
    public function pageSlugsForSitemap(): array
    {
        $slugs = [];
        $rows = $this->db->select(
            "SELECT slug FROM pages WHERE status = 'published' AND deleted_at IS NULL"
        );

        foreach ($rows as $row) {
            $slugs[] = (string) $row['slug'];
        }

        return $slugs;
    }
}
