<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Session;
use App\Models\Plan;

/**
 * Session cart. Only the plan ids are kept; price, title and service are
 * re-read from MySQL on every render so a stale cart can never be used to buy a
 * ৳499 plan for ৳49.
 *
 * This shop sells subscriptions, not stock: each plan is a single subscription
 * and cannot be bought in multiples. A customer can still put several different
 * plans in one order.
 */
final class CartService
{
    private const KEY = 'cart';

    private const COUPON_KEY = 'cart_coupon';

    private const UNLIMITED = 9999;

    /** @return array<int, array{plan_id: int, quantity: int}> */
    public static function raw(): array
    {
        $stored = Session::get(self::KEY, []);

        if (!is_array($stored)) {
            return [];
        }

        $lines = [];

        foreach ($stored as $planId => $line) {
            if (!is_array($line) || !is_numeric($planId)) {
                continue;
            }

            $id = (int) $planId;

            if ($id > 0) {
                $lines[$id] = ['plan_id' => $id, 'quantity' => 1];
            }
        }

        return $lines;
    }

    /**
     * Live cart lines, priced from the database. Lines whose plan disappeared or
     * was deactivated are dropped silently so a broken cart cannot block checkout.
     *
     * @return list<array<string, mixed>>
     */
    public static function lines(): array
    {
        $lines = self::raw();

        if ($lines === []) {
            return [];
        }

        $planModel = new Plan();
        $cart = [];

        foreach ($lines as $line) {
            $plan = $planModel->withService($line['plan_id']);

            if ($plan === null) {
                self::forget($line['plan_id']);

                continue;
            }

            $cart[] = [
                'plan_id' => (int) $plan['id'],
                'service_name' => (string) $plan['service_name'],
                'service_slug' => (string) $plan['service_slug'],
                'brand_color' => (string) ($plan['brand_color'] ?? '#6366f1'),
                'logo_mark' => (string) ($plan['logo_mark'] ?? 'Z'),
                'title' => (string) $plan['title'],
                'duration_months' => (int) $plan['duration_months'],
                'seats' => (int) ($plan['seats'] ?? 1),
                'quality' => (string) ($plan['quality'] ?? ''),
                'unit_price' => (float) $plan['price'],
                'compare_at_price' => (float) ($plan['compare_at_price'] ?? 0),
                'quantity' => 1,
                'line_total' => round((float) $plan['price'], 2),
                'stock' => (int) ($plan['stock'] ?? -1),
                'service_id' => (int) $plan['service_id'],
            ];
        }

        return $cart;
    }

    /** @return array{subtotal: float, discount: float, total: float, coupon: string, coupon_message: string} */
    public static function totals(): array
    {
        $subtotal = 0.0;

        foreach (self::lines() as $line) {
            $subtotal += (float) $line['line_total'];
        }

        $subtotal = round($subtotal, 2);
        $couponCode = self::coupon();

        if ($couponCode === '') {
            return ['subtotal' => $subtotal, 'discount' => 0.0, 'total' => $subtotal, 'coupon' => '', 'coupon_message' => ''];
        }

        $result = CouponService::evaluate($couponCode, $subtotal, self::customerEmail());

        if (!$result['ok']) {
            // An expired or fully-used coupon must not survive into checkout.
            self::forgetCoupon();

            return ['subtotal' => $subtotal, 'discount' => 0.0, 'total' => $subtotal, 'coupon' => '', 'coupon_message' => ''];
        }

        $discount = min($subtotal, (float) $result['discount']);

        return [
            'subtotal' => $subtotal,
            'discount' => round($discount, 2),
            'total' => round($subtotal - $discount, 2),
            'coupon' => $result['code'],
            'coupon_message' => (string) $result['message'],
        ];
    }

    /** One subscription per plan, so the badge counts distinct plans. */
    public static function count(): int
    {
        return count(self::raw());
    }

    public static function isEmpty(): bool
    {
        return self::lines() === [];
    }

    /**
     * Add a subscription. A plan is always one subscription, so adding the same
     * plan twice is a no-op rather than a quantity bump.
     *
     * @return array{ok: bool, message: string, count: int}
     */
    public static function add(int $planId): array
    {
        $plan = (new Plan())->withService($planId);

        if ($plan === null) {
            return ['ok' => false, 'message' => 'That plan is no longer on sale.', 'count' => self::count()];
        }

        if (self::availableStock($plan) <= 0) {
            return ['ok' => false, 'message' => 'Sold out for now - ping us on WhatsApp and we will restock it.', 'count' => self::count()];
        }

        $lines = self::raw();

        if (isset($lines[$planId])) {
            return ['ok' => true, 'message' => 'That subscription is already selected.', 'count' => self::count()];
        }

        if (count($lines) >= self::maxPlansPerOrder()) {
            return [
                'ok' => false,
                'message' => 'An order can hold up to ' . self::maxPlansPerOrder() . ' subscription plans.',
                'count' => self::count(),
            ];
        }

        $lines[$planId] = ['plan_id' => $planId, 'quantity' => 1];
        Session::set(self::KEY, $lines);

        return [
            'ok' => true,
            'message' => $plan['service_name'] . ' - ' . $plan['title'] . ' added to your subscriptions.',
            'count' => self::count(),
        ];
    }

    /**
     * Kept for the old update endpoint. Subscription quantities are fixed at
     * one; a zero quantity still removes the line.
     *
     * @return array{ok: bool, message: string, count: int}
     */
    public static function update(int $planId, int $quantity): array
    {
        $lines = self::raw();

        if (!isset($lines[$planId])) {
            return ['ok' => false, 'message' => 'That subscription is not selected.', 'count' => self::count()];
        }

        if ($quantity <= 0) {
            self::remove($planId);

            return ['ok' => true, 'message' => 'Subscription removed.', 'count' => self::count()];
        }

        $lines[$planId]['quantity'] = 1;
        Session::set(self::KEY, $lines);

        return ['ok' => true, 'message' => 'Each plan is a single subscription.', 'count' => self::count()];
    }

    public static function remove(int $planId): void
    {
        $lines = self::raw();
        unset($lines[$planId]);
        Session::set(self::KEY, $lines);
    }

    public static function forget(int $planId): void
    {
        self::remove($planId);
    }

    public static function clear(): void
    {
        Session::forget(self::KEY);
        self::forgetCoupon();
    }

    public static function coupon(): string
    {
        return strtoupper(trim((string) Session::get(self::COUPON_KEY, '')));
    }

    public static function applyCoupon(string $code, string $email = ''): array
    {
        $subtotal = 0.0;

        foreach (self::lines() as $line) {
            $subtotal += (float) $line['line_total'];
        }

        $result = CouponService::evaluate($code, round($subtotal, 2), $email === '' ? null : $email);

        if ($result['ok']) {
            Session::set(self::COUPON_KEY, $result['code']);
        }

        return $result;
    }

    public static function forgetCoupon(): void
    {
        Session::forget(self::COUPON_KEY);
    }

    public static function customerEmail(): ?string
    {
        $user = Auth::user();

        return $user === null ? null : (string) $user['email'];
    }

    /** The order-wide cap on distinct subscription plans. */
    private static function maxPlansPerOrder(): int
    {
        return max(1, (int) Config::get('app.max_items_per_order', 10));
    }

    /**
     * Stock is the merchant's own counter; a negative value means "not limited"
     * (that is how the seed data sells most plans), so it must not block a sale.
     */
    private static function availableStock(array $plan): int
    {
        $stock = (int) ($plan['stock'] ?? -1);

        return $stock < 0 ? self::UNLIMITED : max(0, $stock);
    }
}
