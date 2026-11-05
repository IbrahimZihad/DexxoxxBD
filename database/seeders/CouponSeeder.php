<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Coupons covering every branch CouponService::evaluate() has to reason about:
 * percent vs fixed, a minimum basket, a per-customer cap, a global use cap, an
 * expiry in the future, and one deliberately dead code so the "invalid coupon"
 * path is visible in the admin list too.
 */
final class CouponSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return [];
    }

    public function run(): void
    {
        $now = time();
        $stamp = static fn (int $offset): string => date('Y-m-d H:i:s', $now + $offset);

        $rows = [
            [
                'code' => 'ZAP10',
                'type' => 'percent',
                'value' => 10.00,
                'min_order' => 0.00,
                'max_uses' => 0,
                'used_count' => 0,
                'per_customer_limit' => 0,
                'expires_at' => null,
                'is_active' => 1,
                'created_at' => $stamp(-86400 * 30),
                'updated_at' => $stamp(-86400 * 30),
            ],
            [
                'code' => 'STUDENT15',
                'type' => 'percent',
                'value' => 15.00,
                'min_order' => 500.00,
                'max_uses' => 500,
                'used_count' => 61,
                'per_customer_limit' => 1,
                'expires_at' => $stamp(86400 * 90),
                'is_active' => 1,
                'created_at' => $stamp(-86400 * 12),
                'updated_at' => $stamp(-3600 * 6),
            ],
            [
                'code' => 'ANNUAL100',
                'type' => 'fixed',
                'value' => 100.00,
                'min_order' => 2000.00,
                'max_uses' => 200,
                'used_count' => 18,
                'per_customer_limit' => 1,
                'expires_at' => $stamp(86400 * 45),
                'is_active' => 1,
                'created_at' => $stamp(-86400 * 20),
                'updated_at' => $stamp(-86400),
            ],
            [
                'code' => 'FIRST50',
                'type' => 'fixed',
                'value' => 50.00,
                'min_order' => 300.00,
                'max_uses' => 0,
                'used_count' => 214,
                // Once per customer, enforced against the order history rather
                // than a flag on the user row.
                'per_customer_limit' => 1,
                'expires_at' => null,
                'is_active' => 1,
                'created_at' => $stamp(-86400 * 60),
                'updated_at' => $stamp(-1800),
            ],
            [
                'code' => 'WEEKEND',
                'type' => 'percent',
                'value' => 7.50,
                'min_order' => 0.00,
                'max_uses' => 40,
                'used_count' => 40,
                'per_customer_limit' => 1,
                'expires_at' => $stamp(86400 * 7),
                'is_active' => 1,
                // Cap already reached: shows the "used up" message in checkout.
                'created_at' => $stamp(-86400 * 4),
                'updated_at' => $stamp(-7200),
            ],
            [
                'code' => 'EID2024',
                'type' => 'percent',
                'value' => 20.00,
                'min_order' => 0.00,
                'max_uses' => 0,
                'used_count' => 312,
                'per_customer_limit' => 1,
                'expires_at' => $stamp(-86400 * 40),
                'is_active' => 1,
                'created_at' => $stamp(-86400 * 200),
                'updated_at' => $stamp(-86400 * 40),
            ],
            [
                'code' => 'PARTNER30',
                'type' => 'percent',
                'value' => 30.00,
                'min_order' => 1000.00,
                'max_uses' => 0,
                'used_count' => 3,
                'per_customer_limit' => 1,
                'expires_at' => $stamp(86400 * 20),
                'is_active' => 0,
                'created_at' => $stamp(-86400 * 9),
                'updated_at' => $stamp(-86400 * 2),
            ],
        ];

        $table = $this->table('coupons');
        $table->truncate();
        $table->insert($rows)->saveData();
    }
}
