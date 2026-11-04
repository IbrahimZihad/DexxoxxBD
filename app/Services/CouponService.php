<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;

/**
 * Coupon resolution and discount maths.
 *
 * Every rule is evaluated here, on the server, from live product prices. A
 * coupon code typed in the browser is only a lookup key - the amount it saves
 * is computed from the cart lines MySQL holds, never from anything the client
 * sends. Checkout re-runs apply() before an order is written, so a stale cart
 * cannot smuggle a discount through.
 */
final class CouponService
{
    /**
     * Active coupon row for a code, or null when the code is unknown, disabled
     * or soft-deleted.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        $coupon = db()->selectOne(
            'SELECT * FROM coupons
             WHERE code = :code AND status = 1 AND deleted_at IS NULL
             LIMIT 1',
            ['code' => $code]
        );

        return is_array($coupon) ? $coupon : null;
    }

    /**
     * Window check used by both apply() and any caller that already holds a row
     * (CartService re-validates a stored code on every page view).
     *
     * @param array<string, mixed> $coupon
     */
    public static function stillValid(array $coupon): bool
    {
        if ((int) ($coupon['status'] ?? 0) !== 1 || ($coupon['deleted_at'] ?? null) !== null) {
            return false;
        }

        $now = now();
        $starts = $coupon['starts_at'] ?? null;
        $expires = $coupon['expires_at'] ?? null;

        if ($starts !== null && (string) $starts > $now) {
            return false;
        }

        return !($expires !== null && (string) $expires < $now);
    }

    /**
     * @param array<string, mixed> $cart the carts row (id, user_id, coupon_code)
     * @return array{ok: bool, message: string, discount?: float}
     */
    public function apply(string $code, array $cart): array
    {
        $coupon = $this->find($code);
        if ($coupon === null) {
            return ['ok' => false, 'message' => 'We could not find an active coupon with that code.'];
        }

        if (!self::stillValid($coupon)) {
            return ['ok' => false, 'message' => 'That coupon is not running right now.'];
        }

        $lines = $this->cartLines((int) $cart['id']);
        if ($lines === []) {
            return ['ok' => false, 'message' => 'Add a plan to your cart before applying a code.'];
        }

        $subtotal = 0.0;
        foreach ($lines as $line) {
            $subtotal += (float) $line['line_total'];
        }

        $minimum = (float) ($coupon['min_order_amount'] ?? 0);
        if ($minimum > 0 && $subtotal < $minimum) {
            return [
                'ok' => false,
                'message' => 'Spend at least ' . money($minimum) . ' to use that coupon.',
            ];
        }

        $blocked = $this->rejectByUsage($coupon, $cart);
        if ($blocked !== null) {
            return ['ok' => false, 'message' => $blocked];
        }

        $discount = $this->discountFor($coupon, $lines);
        if ($discount <= 0.0) {
            return ['ok' => false, 'message' => 'That coupon does not cover any plan in your cart.'];
        }

        return [
            'ok' => true,
            'message' => 'Coupon ' . strtoupper(trim($code)) . ' applied - you save ' . money($discount) . '.',
            'discount' => $discount,
        ];
    }

    /**
     * Discount produced by a coupon for a set of priced cart lines.
     *
     * Restricted coupons only count the lines they cover, so a "30% off one
     * brand" code can never be applied to the whole basket.
     *
     * @param array<string, mixed>    $coupon
     * @param list<array<string, mixed>> $lines rows with product_id and line_total
     */
    public function discountFor(array $coupon, array $lines): float
    {
        $eligible = $this->eligibleTotal($coupon, $lines);
        if ($eligible <= 0.0) {
            return 0.0;
        }

        $value = (float) ($coupon['value'] ?? 0);
        $discount = ($coupon['type'] ?? 'percent') === 'fixed'
            ? min($value, $eligible)
            : round($eligible * $value / 100, 2);

        $cap = (float) ($coupon['max_discount'] ?? 0);
        if ($cap > 0 && $discount > $cap) {
            $discount = $cap;
        }

        return round(max(0.0, min($discount, $eligible)), 2);
    }

    /**
     * @param array<string, mixed>       $coupon
     * @param list<array<string, mixed>> $lines
     */
    private function eligibleTotal(array $coupon, array $lines): float
    {
        $mode = (string) ($coupon['restriction_mode'] ?? 'all');
        $total = 0.0;

        if ($mode === 'all') {
            foreach ($lines as $line) {
                $total += (float) $line['line_total'];
            }
            return round($total, 2);
        }

        $productIds = array_values(array_unique(array_map(
            static fn (array $line): int => (int) $line['product_id'],
            $lines
        )));
        if ($productIds === []) {
            return 0.0;
        }

        $couponId = (int) ($coupon['id'] ?? 0);

        $allowed = $mode === 'products'
            ? $this->linkedIds('coupon_products', 'product_id', 'product', $couponId, $productIds)
            : $this->categoryIdsFor($productIds);

        if ($allowed === []) {
            return 0.0;
        }

        if ($mode === 'products') {
            foreach ($lines as $line) {
                if (in_array((int) $line['product_id'], $allowed, true)) {
                    $total += (float) $line['line_total'];
                }
            }
            return round($total, 2);
        }

        // Category scope: a line counts when its product lives in a linked tree.
        $categoryIds = $this->withDescendants($allowed);
        foreach ($lines as $line) {
            $categoryId = $this->categoryOf((int) $line['product_id']);
            if ($categoryId !== null && in_array($categoryId, $categoryIds, true)) {
                $total += (float) $line['line_total'];
            }
        }

        return round($total, 2);
    }

    /**
     * @param array<string, mixed>       $coupon
     * @param array<string, mixed>       $cart
     * @return string|null a customer-facing refusal, or null when usage is allowed
     */
    private function rejectByUsage(array $coupon, array $cart): ?string
    {
        $limit = (int) ($coupon['usage_limit'] ?? 0);
        if ($limit > 0 && (int) ($coupon['usage_count'] ?? 0) >= $limit) {
            return 'That coupon has already been claimed the maximum number of times.';
        }

        $userId = $cart['user_id'] ?? null;
        $userId = $userId === null ? null : (int) $userId;

        $perUser = (int) ($coupon['usage_limit_per_user'] ?? 0);
        if ($perUser > 0 && $userId !== null) {
            $used = (int) db()->scalar(
                'SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = :coupon AND user_id = :user',
                ['coupon' => (int) $coupon['id'], 'user' => $userId]
            );
            if ($used >= $perUser) {
                return 'You have already used that coupon on your account.';
            }
        }

        if ((bool) ($coupon['first_order_only'] ?? false) && $this->hasPaidOrder($userId)) {
            return 'That coupon is only valid on a first order.';
        }

        return null;
    }

    private function hasPaidOrder(?int $userId): bool
    {
        $userId ??= Auth::instance()->id();
        if ($userId === null) {
            // An unidentified guest has no order history to hold against them;
            // the global usage limit still applies.
            return false;
        }

        return (int) db()->scalar(
            "SELECT COUNT(*) FROM orders
             WHERE user_id = :user AND deleted_at IS NULL
               AND status IN ('paid', 'delivered', 'completed')",
            ['user' => $userId]
        ) > 0;
    }

    /**
     * @return list<array<string, mixed>> priced lines straight from MySQL
     */
    private function cartLines(int $cartId): array
    {
        return array_map(
            static fn (array $row): array => [
                'product_id' => (int) $row['product_id'],
                'line_total' => round((float) $row['unit_price'] * (int) $row['quantity'], 2),
            ],
            db()->select(
                "SELECT ci.product_id, ci.quantity, v.price AS unit_price
                 FROM cart_items ci
                 JOIN product_variations v ON v.id = ci.variation_id AND v.status = 1 AND v.deleted_at IS NULL
                 WHERE ci.cart_id = :cart",
                ['cart' => $cartId]
            )
        );
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function linkedIds(string $table, string $column, string $alias, int $couponId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        /** @var list<array<string, mixed>> $rows */
        $rows = db()->select(
            'SELECT ' . $column . ' AS ' . $alias . ' FROM ' . $table
                . ' WHERE coupon_id = ? AND ' . $column . ' IN (' . $placeholders . ')',
            array_merge([$couponId], $ids)
        );

        return array_values(array_map(static fn (array $row): int => (int) $row[$alias], $rows));
    }

    /**
     * @param list<int> $productIds
     * @return list<int>
     */
    private function categoryIdsFor(array $productIds): array
    {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $rows = db()->select(
            'SELECT category_id FROM products WHERE id IN (' . $placeholders . ')',
            $productIds
        );

        return array_values(array_unique(array_filter(array_map(
            static fn (array $row): ?int => $row['category_id'] === null ? null : (int) $row['category_id'],
            $rows
        ))));
    }

    /**
     * A category-scoped coupon also covers its children, so "Streaming - all"
     * keeps working after a new sub-category is added.
     *
     * @param list<int> $categoryIds
     * @return list<int>
     */
    private function withDescendants(array $categoryIds): array
    {
        $resolved = $categoryIds;
        $frontier = $categoryIds;
        $guard = 0;

        while ($frontier !== [] && $guard++ < 10) {
            /** @var list<array{id: int, parent_id: int}> $children */
            $children = db()->select(
                'SELECT id, parent_id FROM categories WHERE parent_id IN ('
                    . implode(',', array_fill(0, count($frontier), '?')) . ')',
                $frontier
            );

            $frontier = [];
            foreach ($children as $child) {
                if (in_array((int) $child['id'], $resolved, true)) {
                    continue;
                }
                $resolved[] = (int) $child['id'];
                $frontier[] = (int) $child['id'];
            }
        }

        return array_values(array_unique($resolved));
    }

    private function categoryOf(int $productId): ?int
    {
        $value = db()->scalar('SELECT category_id FROM products WHERE id = :id', ['id' => $productId]);

        return $value === null ? null : (int) $value;
    }
}
