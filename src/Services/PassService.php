<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\OrderRepository;
use App\Repositories\PassRepository;

final class PassService
{
    /**
     * Marks an order paid and issues one pass per purchased unit.
     *
     * Idempotent by design: the order row is locked and the paid check happens
     * inside the transaction, so a buyer refreshing success_url and a duplicate
     * IPN callback can never mint the same pass twice.
     *
     * @param string $via what confirmed the money (gateway|ipn|admin|manual)
     */
    public function grantForOrder(int $orderId, string $via): bool
    {
        return Database::transaction(function ($pdo) use ($orderId, $via) {
            $order = OrderRepository::lock($pdo, $orderId);

            if ($order === null) {
                Logger::warning('Pass grant for unknown order', ['order_id' => $orderId, 'via' => $via]);
                return false;
            }

            if ($order['status'] === 'paid') {
                return false; // already granted
            }

            // 'failed'/'cancelled' are deliberate outcomes; a late settlement on one
            // of those goes to the logs for a human instead of silently minting passes.
            if (in_array($order['status'], ['failed', 'cancelled'], true)) {
                Logger::warning('Settlement arrived for a closed order', [
                    'order_id' => $orderId,
                    'status' => $order['status'],
                    'via' => $via,
                ]);

                return false;
            }

            $pdo->prepare("UPDATE orders SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$orderId]);

            $items = OrderRepository::items($orderId);
            $granted = 0;

            foreach ($items as $item) {
                $duration = max(1, (int) $item['duration_days']);

                for ($i = 0; $i < (int) $item['quantity']; $i++) {
                    $code = PassRepository::insert($pdo, [
                        'user_id' => (int) $order['user_id'],
                        'order_item_id' => (int) $item['id'],
                        'item_type' => $item['item_type'],
                        'item_name' => (string) $item['item_name'],
                        'starts_at' => date('Y-m-d'),
                        'ends_at' => date('Y-m-d', strtotime("+{$duration} days")),
                    ]);

                    if ($code !== null) {
                        $granted++;
                    }
                }
            }

            Logger::info('Order settled', ['order_id' => $orderId, 'via' => $via, 'passes' => $granted]);

            return true;
        });
    }
}
