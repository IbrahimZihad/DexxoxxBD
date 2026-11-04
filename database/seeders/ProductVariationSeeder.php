<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;

/**
 * Plan (variation) rows for every product: own SKU, own price, own duration and
 * own delivery method, which is what the product page tier selector reads.
 *
 * 30/90/180/365-day tiers are priced 499 / 1,299 / 2,399 / 4,299 for the lead
 * product and follow the same short-term-cheap, long-term-value shape for the
 * rest. Manual and account-transfer plans are "not stock limited" (-1); key and
 * code plans carry a stock counter that DigitalInventorySeeder tops up with real
 * encrypted credentials in the fulfilment phase.
 */
final class ProductVariationSeeder extends AbstractSeed
{
    public function run(): void
    {
        Seed::ensure($this, ProductSeeder::class);

        foreach (Seed::list('catalog', 'products') as $productNumber => $product) {
            $productId = $this->productId((string) $product['slug']);
            $prefix = $this->brandPrefix((string) $product['brand']);
            $sales = 0;

            foreach ($product['plans'] as $planIndex => $plan) {
                $days = (int) $plan['days'];
                $sku = $this->uniqueSku(sprintf('%s-P%02d-%dD', $prefix, $productNumber + 1, $days), $productId);
                $sold = $this->demoSales($productNumber, $planIndex, $days);
                $sales += $sold;

                Seed::upsert($this, 'product_variations', ['sku' => $sku], [
                    'product_id' => $productId,
                    'label' => $plan['label'],
                    'price' => (float) $plan['price'],
                    'compare_price' => (float) $plan['compare'],
                    'duration_days' => $days,
                    'duration_label' => $this->durationLabel($days),
                    'device_limit' => $this->deviceLimit((string) $product['account_type']),
                    'account_type' => $product['account_type'],
                    'delivery_method' => $product['delivery_method'],
                    'stock' => $this->stock($productNumber, $planIndex, (string) $product['delivery_method']),
                    'min_quantity' => 1,
                    'max_quantity' => 5,
                    'warranty_days' => 7,
                    'notes' => $this->note((string) $product['delivery_method']),
                    'sort_order' => ($planIndex + 1) * 10,
                    'sales_count' => $sold,
                    'status' => 1,
                ]);
            }

            // The catalog sorts and badges by the product aggregate, so keep it
            // equal to the sum of its plans.
            Seed::statement(
                $this,
                'UPDATE `products` SET `sales_count` = ? WHERE `id` = ?',
                [$sales, $productId]
            );
        }
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
     * Three letters of the supplier, so a SKU reads like something a warehouse
     * person would actually write down.
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
     * SKUs are unique in the table, so the second plan with the same length gets
     * a suffix instead of a duplicate key error.
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

        if (stripos($accountType, 'duo') !== false) {
            return 2;
        }

        return 1;
    }

    private function durationLabel(int $days): string
    {
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
     * Key and code plans are limited by the credential vault, so they get a small
     * counter; staff-delivered plans are not stock limited at all.
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
            'account_transfer' => 'Our staff completes the handover with you after payment verification.',
            default => 'Our staff activates this plan with you after payment verification.',
        };
    }
}
