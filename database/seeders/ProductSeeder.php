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
                'status' => 1,
                'sort_order' => ($index + 1) * 10,
                'meta_title' => $product['name'] . ' plans and prices - DexxoxxBD',
                'meta_description' => mb_substr((string) $product['short'], 0, 155),
            ]);

            $idsBySlug[$product['slug']] = $id;
            $categoryOf[$product['slug']] = (string) $product['category'];

            $this->fill($id, (array) $product['features'], (array) $product['faqs'], (string) $product['name']);
        }

        $this->relate($idsBySlug, $categoryOf, $products);
    }

    /**
     * @param list<string>           $features
     * @param list<array<string, mixed>> $faqs
     */
    private function fill(int $productId, array $features, array $faqs, string $name): void
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
            'path' => 'products/' . slugify($name) . '.svg',
            'alt' => $name . ' plan artwork',
            'is_primary' => true,
            'sort_order' => 10,
        ]]);
    }

    /**
     * Four neighbours from the same shelf, which is what the product page and the
     * "you may also like" rails need.
     *
     * @param array<string, int>         $idsBySlug
     * @param array<string, string>      $categoryOf
     * @param list<array<string, mixed>> $products
     */
    private function relate(array $idsBySlug, array $categoryOf, array $products): void
    {
        foreach ($products as $product) {
            $ownId = $idsBySlug[$product['slug']];
            $rows = [];

            foreach ($products as $other) {
                if ($other['slug'] === $product['slug'] || $categoryOf[$other['slug']] !== $categoryOf[$product['slug']]) {
                    continue;
                }

                $rows[] = [
                    'product_id' => $ownId,
                    'related_product_id' => $idsBySlug[$other['slug']],
                    'sort_order' => (count($rows) + 1) * 10,
                ];

                if (count($rows) === 4) {
                    break;
                }
            }

            Seed::sync($this, 'product_related_items', 'product_id', $ownId, $rows);
        }
    }

    private function iconFor(string $label): string
    {
        $needle = mb_strtolower($label);
        foreach ([
            'bi-people' => ['seat', 'team', 'device', 'user'],
            'bi-stars' => ['credit', 'generation', 'render', 'bonus'],
            'bi-cloud-download' => ['offline', 'download', 'backup', 'storage'],
            'bi-shield-check' => ['warranty', 'support', 'protection', 'no-logs', 'privacy'],
            'bi-clock-history' => ['history', 'retention', 'renewal', 'expiry'],
        ] as $icon => $keywords) {
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
