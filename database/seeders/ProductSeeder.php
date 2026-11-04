<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;

/**
 * Thirty subscriptions with their copy, feature bullets, FAQs, artwork reference
 * and same-shelf recommendations. Prices live on the plans, which
 * ProductVariationSeeder writes.
 */
final class ProductSeeder extends AbstractSeed
{
    /** Keyword to Bootstrap-Icon map for the feature bullets. */
    private const FEATURE_ICONS = [
        'bi-people' => ['seat', 'team', 'device', 'user'],
        'bi-stars' => ['credit', 'generation', 'render', 'bonus'],
        'bi-cloud-download' => ['offline', 'download', 'backup', 'storage'],
        'bi-shield-check' => ['warranty', 'support', 'protection', 'no-logs', 'privacy'],
        'bi-clock-history' => ['history', 'retention', 'renewal', 'expiry'],
    ];

    public function run(): void
    {
        Seed::ensure($this, CategorySeeder::class);
        Seed::ensure($this, BrandSeeder::class);

        $products = Seed::list('catalog', 'products');
        $idsBySlug = [];
        $categoryOf = [];

        foreach ($products as $index => $product) {
            $categoryId = $this->required('categories', 'slug', $product['category'], 'category');
            $brandId = $this->required('brands', 'slug', $product['brand'], 'brand');
            $badge = (string) ($product['badge'] ?? '');

            $id = Seed::upsert($this, 'products', ['slug' => $product['slug']], [
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'name' => $product['name'],
                'short_description' => $product['short'],
                'description' => $product['description'],
                'image' => 'products/' . $product['slug'] . '.svg',
                'badge' => $badge,
                'account_type' => $product['account_type'],
                'delivery_method' => $product['delivery_method'],
                'warranty_info' => $product['warranty'],
                'support_info' => $product['support'],
                'activation_instructions' => $product['activation'],
                'is_featured' => $badge !== '',
                'is_bestseller' => stripos($badge, 'best') !== false,
                'is_new_arrival' => stripos($badge, 'new') !== false,
                // products.status is an enum; writing 1 stores 'draft' and hides the
                // whole catalogue from the storefront.
                'status' => 'published',
                'sort_order' => ($index + 1) * 10,
                'meta_title' => $product['name'] . ' plans and prices - DexxoxxBD',
                'meta_description' => mb_substr((string) $product['short'], 0, 155),
            ]);

            $idsBySlug[$product['slug']] = $id;
            $categoryOf[$product['slug']] = (string) $product['category'];

            $this->fill(
                $id,
                (string) $product['slug'],
                (string) $product['name'],
                (array) $product['features'],
                (array) $product['faqs']
            );
        }

        $this->relate($idsBySlug, $categoryOf, $products);
    }

    /**
     * @param list<string>           $features
     * @param list<array<string, mixed>> $faqs
     */
    private function fill(int $productId, string $slug, string $name, array $features, array $faqs): void
    {
        $rows = [];
        foreach ($features as $offset => $label) {
            $rows[] = [
                'product_id' => $productId,
                'label' => $label,
                'icon' => $this->iconFor($label),
                'sort_order' => ($offset + 1) * 10,
            ];
        }
        Seed::sync($this, 'product_features', 'product_id', $productId, $rows);

        $rows = [];
        foreach ($faqs as $offset => $faq) {
            $rows[] = [
                'product_id' => $productId,
                'question' => $faq['q'],
                'answer' => $faq['a'],
                'sort_order' => ($offset + 1) * 10,
                'status' => 1,
            ];
        }
        Seed::sync($this, 'product_faqs', 'product_id', $productId, $rows);

        Seed::sync($this, 'product_images', 'product_id', $productId, [[
            'product_id' => $productId,
            'path' => 'products/' . $slug . '.svg',
            'alt' => $name . ' plan artwork',
            'is_primary' => true,
            'sort_order' => 10,
        ]]);
    }

    /**
     * Four neighbours from the same shelf - same sub-category first, then the rest
     * of the parent shelf - which is what the product page and the
     * "you may also like" rails need.
     *
     * @param array<string, int>         $idsBySlug
     * @param array<string, string>      $categoryOf
     * @param list<array<string, mixed>> $products
     */
    private function relate(array $idsBySlug, array $categoryOf, array $products): void
    {
        $rootOf = $this->rootShelves($categoryOf);

        foreach ($products as $product) {
            $ownSlug = (string) $product['slug'];
            $ownShelf = $rootOf[$categoryOf[$ownSlug]];

            $candidates = [];
            foreach ($products as $other) {
                if ($other['slug'] === $ownSlug || $rootOf[$categoryOf[$other['slug']]] !== $ownShelf) {
                    continue;
                }

                // Closer relatives first, then keep the factory order.
                $candidates[] = [
                    'distance' => $categoryOf[$other['slug']] === $categoryOf[$ownSlug] ? 0 : 1,
                    'slug' => (string) $other['slug'],
                ];
            }

            usort($candidates, static fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);

            $rows = [];
            foreach (array_slice($candidates, 0, 4) as $offset => $candidate) {
                $rows[] = [
                    'product_id' => $idsBySlug[$ownSlug],
                    'related_product_id' => $idsBySlug[$candidate['slug']],
                    'sort_order' => ($offset + 1) * 10,
                ];
            }

            Seed::sync($this, 'product_related_items', 'product_id', $idsBySlug[$ownSlug], $rows);
        }
    }

    /**
     * leaf slug => the slug of the shelf it hangs under.
     *
     * @param array<string, string> $categoryOf
     * @return array<string, string>
     */
    private function rootShelves(array $categoryOf): array
    {
        $roots = [];

        foreach (array_unique(array_values($categoryOf)) as $leaf) {
            $row = Seed::first(
                $this,
                'SELECT parent.slug AS root
                 FROM `categories` AS child
                 LEFT JOIN `categories` AS parent ON parent.id = child.parent_id
                 WHERE child.slug = ? LIMIT 1',
                [$leaf]
            );

            $roots[$leaf] = $row === null || $row['root'] === null ? $leaf : (string) $row['root'];
        }

        return $roots;
    }

    private function iconFor(string $label): string
    {
        $needle = mb_strtolower($label);
        foreach (self::FEATURE_ICONS as $icon => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($needle, $keyword)) {
                    return $icon;
                }
            }
        }

        return 'bi-check2';
    }

    /**
     * @param mixed $value
     */
    private function required(string $table, string $column, mixed $value, string $what): int
    {
        $id = Seed::id($this, $table, [$column => $value]);
        if ($id === null) {
            throw new RuntimeException(
                'Catalog factory references an unknown ' . $what . ' [' . (string) $value . '].'
            );
        }

        return $id;
    }
}
