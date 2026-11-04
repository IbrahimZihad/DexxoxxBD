<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;

/**
 * Plan (variation) rows for every product: own SKU, own price, own duration, own
 * stock and own delivery method - exactly what the product page tier selector
 * reads, and what an order line is priced from at checkout.
 *
 * Manual and account-transfer plans are "not stock limited" (stock = -1). Key
 * and code plans carry a counter that DigitalInventorySeeder tops up with real
 * encrypted credentials later, because the cart will only sell what the vault
 * can actually deliver.
 */
final class ProductVariationSeeder extends AbstractSeed
{
    public function run(): void
    {
        Seed::ensure($this, ProductSeeder::class);

        foreach (Seed::list('catalog', 'products') as $productNumber => $product) {
            $productId = $this->productId((string) $product['slug']);
            $prefix = $this->brandPrefix((string) $product['brand']);
            $sold = 0;

            foreach ($this->plans((array) $product['plans']) as $planIndex => $plan) {
                $sku = $this->uniqueSku(
                    sprintf('%s-P%02d-%dD', $prefix, $productNumber + 1, $plan['days']),
                    $productId
                );
                $quantity = $this->demoSales($productNumber, $planIndex, $plan['days']);
                $sold += $quantity;

                Seed::upsert($this, 'product_variations', ['sku' => $sku], [
                    'product_id' => $productId,
                    'label' => $plan['label'],
                    'price' => $plan['price'],
                    'compare_price' => $plan['compare'],
                    'duration_days' => $plan['days'],
                    'duration_label' => $this->durationLabel($plan['days']),
                    'device_limit' => $this->deviceLimit((string) $product['account_type']),
                    'account_type' => $product['account_type'],
                    'delivery_method' => $product['delivery_method'],
                    'stock' => $this->stock($productNumber, $planIndex, (string) $product['delivery_method']),
                    'min_quantity' => 1,
                    'max_quantity' => 5,
                    'warranty_days' => 7,
                    'notes' => $this->note((string) $product['delivery_method']),
                    'sort_order' => ($planIndex + 1) * 10,
                    'sales_count' => $quantity,
                    'status' => 1,
                ]);
            }

            // Catalog sorting and the "sold" badge read the product aggregate, so
            // keep it equal to the sum of its plans.
            Seed::statement($this, 'UPDATE `products` SET `sales_count` = ? WHERE `id` = ?', [$sold, $productId]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $plans
     * @return list<array{label: string, days: int, price: float, compare: float|null}>
     */
    private function plans(array $plans): array
    {
        $tiers = [];

        foreach ($plans as $plan) {
            $price = (float) $plan['price'];
            $compare = isset($plan['compare']) ? (float) $plan['compare'] : null;

            $tiers[] = [
                'label' => (string) $plan['label'],
                'days' => max(1, (int) $plan['days']),
                'price' => $price,
                // A compare price only earns a place when it is above the price,
                // otherwise the storefront would show a negative discount.
                'compare' => $compare !== null && $compare > $price ? $compare : null,
            ];
        }

        return $tiers;
    }

    private function productId(string $slug): int
    {
        $id = Seed::id($this, 'products', ['slug' => $slug]);
        if ($id === null) {
            throw new RuntimeException('Product [' . $slug . '] was not seeded before its plans.');
        }

        return $id;
    }

    /**
     * Three letters of the supplier, so a SKU reads like something a person would
     * actually write on a packing note.
     */
    private function brandPrefix(string $brandSlug): string
    {
        $letters = preg_replace('/[^a-z]/', '', $brandSlug) ?? '';
        if (strlen($letters) < 3) {
            throw new RuntimeException('Brand slug [' . $brandSlug . '] is too short for a SKU prefix.');
        }

        return strtoupper(substr($letters, 0, 3));
    }

    /**
     * SKUs are unique in the table, so a second plan of the same length on the
     * same product gets a suffix instead of a duplicate-key error.
     */
    private function uniqueSku(string $sku, int $productId): string
    {
        $candidate = $sku;
        $suffix = 2;

        while (true) {
            $owner = Seed::id($this, 'product_variations', ['sku' => $candidate]);
            if ($owner === null) {
                return $candidate;
            }

            $heldBy = (int) Seed::scalar(
                $this,
                'SELECT product_id FROM `product_variations` WHERE `sku` = ? LIMIT 1',
                [$candidate]
            );
            if ($heldBy === $productId) {
                return $candidate;
            }

            $candidate = $sku . '-' . $suffix++;
        }
    }

    private function deviceLimit(string $accountType): ?int
    {
        if (stripos($accountType, 'unlimited') !== false) {
            return null;
        }

        if (preg_match('/(\d+)/', $accountType, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return str_contains(mb_strtolower($accountType), 'duo') ? 2 : 1;
    }

    private function durationLabel(int $days): string
    {
        if ($days >= 3650) {
            return 'Lifetime';
        }

        if ($days % 365 === 0) {
            $years = intdiv($days, 365);

            return $years === 1 ? '1 year' : $years . ' years';
        }

        if ($days % 30 === 0) {
            $months = intdiv($days, 30);

            return $months === 1 ? '1 month' : $months . ' months';
        }

        return $days . ' days';
    }

    /**
     * Staff-delivered plans are not stock limited; vault plans start with a small
     * counter until the credential import fills the vault.
     */
    private function stock(int $productNumber, int $planIndex, string $deliveryMethod): int
    {
        if ($deliveryMethod !== 'auto_key' && $deliveryMethod !== 'auto_code') {
            return -1;
        }

        return 15 + (($productNumber * 7 + $planIndex * 13) % 46);
    }

    /**
     * A plausible sales history: monthly tiers move faster than multi-year ones.
     */
    private function demoSales(int $productNumber, int $planIndex, int $days): int
    {
        $base = max(6, (int) round(90 / max(1, $days / 30)));
        $wobble = ($productNumber * 11 + $planIndex * 5) % 17;

        return $base + $wobble;
    }

    private function note(string $deliveryMethod): string
    {
        return match ($deliveryMethod) {
            'auto_key' => 'Licence key is released automatically once the payment is verified.',
            'auto_code' => 'Activation code is released automatically once the payment is verified.',
            'account_transfer' => 'Our staff completes the account handover with you after payment verification.',
            default => 'Our staff activates this plan with you after payment verification.',
        };
    }
}
