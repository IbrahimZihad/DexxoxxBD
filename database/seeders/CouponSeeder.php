<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;

/**
 * Discount codes with their product and category restrictions. The validity
 * window moves with each run, so a freshly seeded demo always has live coupons
 * to try at checkout.
 */
final class CouponSeeder extends AbstractSeed
{
    public function run(): void
    {
        Seed::ensure($this, ProductSeeder::class);

        foreach (Seed::list('content', 'coupons') as $coupon) {
            $couponId = Seed::upsert($this, 'coupons', ['code' => $coupon['code']], [
                'name' => $coupon['name'],
                'description' => $coupon['description'],
                'type' => $coupon['type'],
                'value' => (float) $coupon['value'],
                'min_order_amount' => (float) $coupon['min_order_amount'],
                'max_discount' => isset($coupon['max_discount']) ? (float) $coupon['max_discount'] : null,
                'restriction_mode' => $coupon['restriction_mode'],
                'usage_limit' => $coupon['usage_limit'] ?? null,
                'usage_limit_per_user' => $coupon['usage_limit_per_user'] ?? null,
                'first_order_only' => (bool) ($coupon['first_order_only'] ?? false),
                'auto_apply' => (bool) ($coupon['auto_apply'] ?? false),
                'stackable' => false,
                'starts_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+' . (int) $coupon['days_valid'] . ' days')),
                'status' => 1,
            ]);

            $this->linkSlugs('coupon_categories', 'category_id', 'categories', $couponId, $coupon);
            $this->linkSlugs('coupon_products', 'product_id', 'products', $couponId, $coupon);
        }
    }

    /**
     * @param array<string, mixed> $coupon
     */
    private function linkSlugs(string $table, string $column, string $lookup, int $couponId, array $coupon): void
    {
        $rows = [];

        foreach ((array) ($coupon[$lookup] ?? []) as $slug) {
            $id = Seed::id($this, $lookup, ['slug' => $slug]);
            if ($id === null) {
                continue;
            }

            $rows[] = ['coupon_id' => $couponId, $column => $id];
        }

        Seed::sync($this, $table, 'coupon_id', $couponId, $rows);
    }
}
