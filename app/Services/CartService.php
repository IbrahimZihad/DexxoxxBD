<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Session;
use App\Models\Plan;

/**
 * Session cart. Only the plan id and a quantity are kept; price, title and
 * service are re-read from MySQL on every render so a stale cart can never be
 * used to buy a ৳499 plan for ৳49.
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
            $quantity = max(1, (int) ($line['quantity'] ?? 1));

            if ($id > 0) {
                $lines[$id] = ['plan_id' => $id, 'quantity' => $quantity];
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

            $quantity = min($line['quantity'], self::capFor($plan));

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
                'quantity' => $quantity,
                'line_total' => round((float) $plan['price'] * $quantity, 2),
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

    public static function count(): int
    {
        $total = 0;

        foreach (self::raw() as $line) {
            $total += (int) $line['quantity'];
        }

        return $total;
    }

    public static function isEmpty(): bool
    {
        return self::lines() === [];
    }

    /** @return array{ok: bool, message: string, count: int} */
    public static function add(int $planId, int $quantity = 1): array
    {
        $plan = (new Plan())->withService($planId);

        if ($plan === null) {
            return ['ok' => false, 'message' => 'That plan is no longer on sale.', 'count' => self::count()];
        }

        $lines = self::raw();
        $existing = (int) ($lines[$planId]['quantity'] ?? 0);
        $wanted = $existing + max(1, $quantity);

        $cap = self::capFor($plan);
        $available = self::availableStock($plan);

        if ($available <= 0) {
            return ['ok' => false, 'message' => 'Sold out for now - ping us on WhatsApp and we will restock it.', 'count' => self::count()];
        }

        if ($wanted > self::maxPerLine()) {
            $wanted = self::maxPerLine();
        }

        if ($wanted > $cap) {
            $wanted = $cap;
        }

        if ($wanted > $available) {
            $wanted = $available;
        }

        if ($wanted <= 0) {
            return ['ok' => false, 'message' => 'That plan is no longer on sale.', 'count' => self::count()];
        }

        $lines[$planId] = ['plan_id' => $planId, 'quantity' => $wanted];
        Session::set(self::KEY, $lines);

        $message = $wanted === $existing
            ? 'You already have the maximum quantity of this plan.'
            : $plan['service_name'] . ' - ' . $plan['title'] . ' added to your cart.';

        return ['ok' => true, 'message' => $message, 'count' => self::count()];
    }

    /** @return array{ok: bool, message: string, count: int} */
    public static function update(int $planId, int $quantity): array
    {
        $lines = self::raw();

        if (!isset($lines[$planId])) {
            return ['ok' => false, 'message' => 'That item is not in your cart.', 'count' => self::count()];
        }

        if ($quantity <= 0) {
            self::remove($planId);

            return ['ok' => true, 'message' => 'Item removed.', 'count' => self::count()];
        }

        $plan = (new Plan())->withService($planId);

        if ($plan === null) {
            self::remove($planId);

            return ['ok' => false, 'message' => 'That plan is no longer on sale.', 'count' => self::count()];
        }

        $quantity = min($quantity, self::maxPerLine(), self::capFor($plan), max(0, self::availableStock($plan)));

        if ($quantity <= 0) {
            self::remove($planId);

            return ['ok' => false, 'message' => 'Sold out for now.', 'count' => self::count()];
        }

        $lines[$planId]['quantity'] = $quantity;
        Session::set(self::KEY, $lines);

        return ['ok' => true, 'message' => 'Cart updated.', 'count' => self::count()];
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

    private static function maxPerLine(): int
    {
        return max(1, (int) Config::get('app.max_quantity_per_line', 5));
    }

    /** A plan can never be ordered more than the order-wide cap allows. */
    private static function capFor(array $plan): int
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
