<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class CartRepository
{
    /**
     * Prices and durations always come from the database — the client never
     * sends money, it only sends "which item, how many".
     *
     * @return list<array<string,mixed>>
     */
    public static function items(int $userId): array
    {
        $rows = Database::select(
            "SELECT ci.id AS cart_item_id, ci.item_type, ci.quantity,
                    p.id AS product_id, p.name AS product_name, p.price AS product_price,
                    p.image_url AS product_image, p.duration_days AS product_duration, p.stock AS product_stock,
                    p.status AS product_status, p.slug AS product_slug,
                    pl.id AS plan_id, pl.name AS plan_name, pl.price AS plan_price,
                    pl.duration_days AS plan_duration, pl.status AS plan_status
             FROM cart_items ci
             LEFT JOIN products p ON p.id = ci.product_id
             LEFT JOIN plans pl ON pl.id = ci.plan_id
             WHERE ci.user_id = ?
             ORDER BY ci.created_at DESC, ci.id DESC",
            [$userId]
        );

        $items = [];

        foreach ($rows as $row) {
            $isProduct = $row['item_type'] === 'product';
            $reference = $isProduct ? $row['product_id'] : $row['plan_id'];

            if ($reference === null) {
                continue; // orphaned row (item deleted) — never price it
            }

            $status = $isProduct ? $row['product_status'] : $row['plan_status'];
            $stock = $isProduct ? (int) $row['product_stock'] : PHP_INT_MAX;
            $unitPrice = round((float) ($isProduct ? $row['product_price'] : $row['plan_price']), 2);
            $quantity = (int) $row['quantity'];

            $items[] = [
                'cart_item_id' => (int) $row['cart_item_id'],
                'type' => $row['item_type'],
                'id' => (int) $reference,
                'name' => (string) ($isProduct ? $row['product_name'] : $row['plan_name']),
                'slug' => $isProduct ? $row['product_slug'] : null,
                'image' => $isProduct ? $row['product_image'] : null,
                'unit_price' => $unitPrice,
                'duration_days' => (int) ($isProduct ? $row['product_duration'] : $row['plan_duration']),
                'quantity' => $quantity,
                'line_total' => round($unitPrice * $quantity, 2),
                'available' => $status === 'active' && $quantity <= $stock,
            ];
        }

        return $items;
    }

    public static function totals(int $userId): array
    {
        $items = self::items($userId);
        $subtotal = 0.0;
        $count = 0;

        foreach ($items as $item) {
            if ($item['available']) {
                $subtotal += (float) $item['line_total'];
            }
            $count += (int) $item['quantity'];
        }

        return ['items' => $items, 'subtotal' => round($subtotal, 2), 'count' => $count];
    }

    public static function find(int $userId, int $cartItemId): ?array
    {
        return Database::selectOne('SELECT * FROM cart_items WHERE id = ? AND user_id = ? LIMIT 1', [$cartItemId, $userId]);
    }

    public static function existing(int $userId, string $type, int $referenceId): ?array
    {
        $column = $type === 'product' ? 'product_id' : 'plan_id';

        return Database::selectOne(
            "SELECT id, quantity FROM cart_items WHERE user_id = ? AND item_type = ? AND $column = ? LIMIT 1",
            [$userId, $type, $referenceId]
        );
    }

    public static function add(int $userId, string $type, int $referenceId, int $quantity): int
    {
        $column = $type === 'product' ? 'product_id' : 'plan_id';

        return Database::insert(
            "INSERT INTO cart_items (user_id, item_type, $column, quantity) VALUES (?, ?, ?, ?)",
            [$userId, $type, $referenceId, $quantity]
        );
    }

    public static function bump(int $cartItemId, int $quantity): void
    {
        Database::statement('UPDATE cart_items SET quantity = ? WHERE id = ?', [$quantity, $cartItemId]);
    }

    public static function remove(int $userId, int $cartItemId): int
    {
        return Database::statement('DELETE FROM cart_items WHERE id = ? AND user_id = ?', [$cartItemId, $userId]);
    }

    public static function clear(int $userId): int
    {
        return Database::statement('DELETE FROM cart_items WHERE user_id = ?', [$userId]);
    }

    public static function countItems(int $userId): int
    {
        $row = Database::selectOne('SELECT COALESCE(SUM(quantity), 0) AS c FROM cart_items WHERE user_id = ?', [$userId]);

        return (int) ($row['c'] ?? 0);
    }
}
