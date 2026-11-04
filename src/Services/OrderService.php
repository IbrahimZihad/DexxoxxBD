<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Exceptions\HttpException;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SettingRepository;
use App\Support\OrderNumber;

final class OrderService
{
    /**
     * Turns the saved cart into a pending order.
     *
     * Stock is reserved here (not at payment time) with row locks, so two buyers
     * cannot reserve the same last unit. Prices are re-read from the database
     * inside the same transaction, which closes the "client edited the price"
     * and "admin changed the price after the cart was built" gaps.
     *
     * @return array<string,mixed>
     */
    public function createFromCart(int $userId, array $contact, string $ipAddress): array
    {
        $items = CartRepository::items($userId);

        if ($items === []) {
            throw new HttpException(422, 'Your cart is empty.');
        }

        $unavailable = array_filter($items, static fn ($item) => !$item['available']);

        if ($unavailable !== []) {
            throw new HttpException(422, 'Some cart items are no longer available. Remove them and try again.', [
                'items' => implode(', ', array_column(array_values($unavailable), 'name')),
            ]);
        }

        $maxPerItem = (int) Config::get('orders.max_quantity_per_item', 20);

        foreach ($items as $item) {
            if ($item['quantity'] > $maxPerItem) {
                throw new HttpException(422, sprintf('You can order at most %s units of %s.', $maxPerItem, $item['name']));
            }
        }

        $created = Database::transaction(function ($pdo) use ($items, $userId, $contact, $ipAddress) {
            $productIds = array_column(array_filter($items, static fn ($i) => $i['type'] === 'product'), 'id');
            $planIds = array_column(array_filter($items, static fn ($i) => $i['type'] === 'plan'), 'id');

            $lockedProducts = ProductRepository::lockForCheckout($productIds);
            $lockedPlans = \App\Repositories\PlanRepository::lockForCheckout($planIds);

            // Prices are taken from the locked rows, never from the cart snapshot
            // or the client, so an admin price edit mid-checkout cannot be gamed.
            $lines = [];
            $subtotal = 0.0;

            foreach ($items as $item) {
                if ($item['type'] === 'product') {
                    $row = $lockedProducts[$item['id']] ?? null;

                    if ($row === null || $row['status'] !== 'active') {
                        throw new HttpException(422, sprintf('%s is no longer on sale.', $item['name']));
                    }

                    if ((int) $row['stock'] < (int) $item['quantity']) {
                        throw new HttpException(422, sprintf('Only %s left of %s.', $row['stock'], $item['name']));
                    }

                    $unit = round((float) $row['price'], 2);
                    $duration = (int) $row['duration_days'];
                } else {
                    $row = $lockedPlans[$item['id']] ?? null;

                    if ($row === null || $row['status'] !== 'active') {
                        throw new HttpException(422, sprintf('%s is no longer on sale.', $item['name']));
                    }

                    $unit = round((float) $row['price'], 2);
                    $duration = (int) $row['duration_days'];
                }

                $lineTotal = round($unit * (int) $item['quantity'], 2);
                $subtotal += $lineTotal;

                $lines[] = [
                    'item_type' => $item['type'],
                    'product_id' => $item['type'] === 'product' ? (int) $item['id'] : null,
                    'plan_id' => $item['type'] === 'plan' ? (int) $item['id'] : null,
                    'item_name' => (string) $item['name'],
                    'unit_price' => $unit,
                    'quantity' => (int) $item['quantity'],
                    'duration_days' => $duration,
                    'line_total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $discount = 0.0;
            $total = round(max(0, $subtotal - $discount), 2);

            $minimum = (float) SettingRepository::get('min_order_amount', 0);

            if ($minimum > 0 && $total < $minimum) {
                throw new HttpException(422, sprintf('The smallest order we can take is %s. Yours totals %s.', number_format($minimum, 2), number_format($total, 2)));
            }

            $orderId = OrderRepository::create([
                'order_number' => OrderNumber::generate(),
                'user_id' => $userId,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => $total,
                'status' => 'pending',
                'payment_method' => 'pending',
                'customer_name' => $contact['customer_name'],
                'customer_email' => $contact['customer_email'],
                'customer_phone' => $contact['customer_phone'],
                'ip_address' => $ipAddress,
                'pending_ttl_minutes' => (int) Config::get('orders.pending_ttl_minutes', 360),
            ]);

            foreach ($lines as $line) {
                OrderRepository::addItem($pdo, $orderId, $line);

                if ($line['product_id'] !== null && !ProductRepository::reserveStock((int) $line['product_id'], (int) $line['quantity'])) {
                    throw new HttpException(422, sprintf('%s sold out while you were checking out.', $line['item_name']));
                }
            }

            CartRepository::clear($userId);

            $order = OrderRepository::findForUser($orderId, $userId) ?? [];

            return [
                'order_id' => $orderId,
                'order_number' => $order['order_number'] ?? null,
                'total_amount' => (float) ($order['total_amount'] ?? $total),
                'status' => $order['status'] ?? 'pending',
                'expires_at' => $order['expires_at'] ?? null,
            ];
        });

        Logger::info('Order created', [
            'order_id' => $created['order_id'],
            'user_id' => $userId,
            'total' => $created['total_amount'],
            'ip' => $ipAddress,
        ]);

        return $created;
    }

    /**
     * Gives reserved stock back to the shop when an order can never be paid.
     * Returns false when there was nothing to release, so callers can report
     * honestly instead of claiming a change that did not happen.
     */
    public function release(int $orderId, string $finalStatus): bool
    {
        return (bool) Database::transaction(function ($pdo) use ($orderId, $finalStatus) {
            $order = OrderRepository::lock($pdo, $orderId);

            if ($order === null || in_array($order['status'], ['paid', 'cancelled'], true)) {
                return false;
            }

            $items = OrderRepository::items($orderId);

            foreach ($items as $item) {
                if ($item['item_type'] === 'product' && $item['product_id'] !== null && (int) $item['stock_released'] === 0) {
                    ProductRepository::releaseStock($pdo, (int) $item['product_id'], (int) $item['quantity']);
                    $pdo->prepare('UPDATE order_items SET stock_released = 1 WHERE id = ?')->execute([(int) $item['id']]);
                }
            }

            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$finalStatus, $orderId]);

            return true;
        });
    }
}
