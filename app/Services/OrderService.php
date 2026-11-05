<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Logger;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Plan;

/**
 * Writes an order from the cart inside one transaction.
 *
 * Two rules matter here: prices always come from the database (never from the
 * request), and payment_status starts as "unpaid". Nothing this class does can
 * mark an order paid - only the admin verification step can.
 */
final class OrderService
{
    public const STATUSES = ['pending', 'processing', 'completed', 'cancelled', 'failed'];

    public const PAYMENT_STATUSES = ['unpaid', 'pending_verification', 'paid', 'refunded'];

    /**
     * @param array<string, mixed> $customer name, email, phone, note
     * @param array{ip: string, user_id: int|null} $context
     * @return array{ok: bool, order_no: string, message: string, order_id: int}
     */
    public static function fromCart(array $customer, array $context): array
    {
        $lines = CartService::lines();

        if ($lines === []) {
            return ['ok' => false, 'order_no' => '', 'message' => 'Your cart is empty.', 'order_id' => 0];
        }

        $totals = CartService::totals();
        $unitCount = array_sum(array_column($lines, 'quantity'));

        if ($unitCount > (int) Config::get('app.max_items_per_order', 10)) {
            return [
                'ok' => false,
                'order_no' => '',
                'message' => 'An order can hold up to ' . (int) Config::get('app.max_items_per_order', 10) . ' plans.',
                'order_id' => 0,
            ];
        }

        $orderModel = new Order();
        $planModel = new Plan();

        try {
            $orderId = Database::transaction(function () use ($lines, $totals, $customer, $context, $orderModel, $planModel): int {
                $orderNo = $orderModel->nextOrderNumber();

                $id = $orderModel->create([
                    'order_no' => $orderNo,
                    'user_id' => $context['user_id'],
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'fulfillment_status' => 'unassigned',
                    'subtotal' => $totals['subtotal'],
                    'discount' => $totals['discount'],
                    'total' => $totals['total'],
                    'coupon_code' => $totals['coupon'] === '' ? null : $totals['coupon'],
                    'customer_name' => (string) $customer['name'],
                    'email' => mb_strtolower((string) $customer['email']),
                    'phone' => (string) $customer['phone'],
                    'note' => mb_substr((string) ($customer['note'] ?? ''), 0, 500),
                    'ip_address' => mb_substr($context['ip'], 0, 45),
                ]);

                $itemModel = new OrderItem();

                foreach ($lines as $line) {
                    // Re-read the live price at write time: the cart was priced a
                    // request ago and an admin may have changed it since.
                    $fresh = $planModel->withService((int) $line['plan_id']);

                    if ($fresh === null) {
                        throw new \App\Exceptions\HttpException(422, 'One of the plans in your cart is no longer available.');
                    }

                    $unitPrice = (float) $fresh['price'];
                    $quantity = (int) $line['quantity'];

                    if ((int) ($fresh['stock'] ?? -1) >= 0 && !$planModel->takeStock((int) $fresh['id'], $quantity)) {
                        throw new \App\Exceptions\HttpException(422, $fresh['service_name'] . ' just sold out. Remove it and try again.');
                    }

                    $itemModel->create([
                        'order_id' => $id,
                        'plan_id' => (int) $fresh['id'],
                        'service_name' => (string) $fresh['service_name'],
                        'plan_title' => (string) $fresh['title'],
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'line_total' => round($unitPrice * $quantity, 2),
                        'status' => 'pending',
                    ]);
                }

                // Anything the customer typed is encrypted at rest; a leaked
                // database dump should not read like a support inbox.
                $requirement = trim((string) ($customer['requirement'] ?? ''));

                if ($requirement !== '') {
                    Database::execute(
                        'update orders set delivery_request = :note where id = :id',
                        [':note' => Crypto::encrypt($requirement), ':id' => $id]
                    );
                }

                if ($totals['coupon'] !== '') {
                    (new Coupon())->recordUsage((string) $totals['coupon']);
                }

                self::history($id, 'pending', 'Order created from the cart.');

                return $id;
            });
        } catch (\Throwable $exception) {
            Logger::error('order_create_failed', [
                'reason' => $exception->getMessage(),
                'ip' => $context['ip'],
            ]);

            $message = $exception instanceof \App\Exceptions\HttpException
                ? $exception->getMessage()
                : 'We could not place the order. Please try again or message us on WhatsApp.';

            return ['ok' => false, 'order_no' => '', 'message' => $message, 'order_id' => 0];
        }

        $orderNo = (string) Database::scalar('select order_no from orders where id = :id', [':id' => $orderId]);

        CartService::clear();

        Logger::info('order_created', ['order_no' => $orderNo, 'total' => $totals['total'], 'ip' => $context['ip']]);

        return [
            'ok' => true,
            'order_no' => $orderNo,
            'message' => 'Order ' . $orderNo . ' is reserved.',
            'order_id' => $orderId,
        ];
    }

    /**
     * Attach the payment the customer claimed to make. The row starts pending and
     * the order moves to pending_verification - still not paid.
     *
     * @return array{ok: bool, message: string}
     */
    public static function recordPayment(int $orderId, string $method, string $txnId, float $amount, string $sender = ''): array
    {
        $order = Database::selectOne('select * from orders where id = :id', [':id' => $orderId]);

        if ($order === null) {
            return ['ok' => false, 'message' => 'Order not found.'];
        }

        if ((string) $order['payment_status'] === 'paid') {
            return ['ok' => false, 'message' => 'This order is already paid.'];
        }

        $paymentModel = new Payment();

        if ($txnId !== '' && $paymentModel->txnIdTaken($txnId, $method)) {
            Logger::security('duplicate_txn_id', [
                'order_id' => $orderId,
                'method' => $method,
                'txn_hash' => substr(hash('sha256', strtoupper($txnId)), 0, 12),
            ]);

            return ['ok' => false, 'message' => 'That transaction ID is already attached to another order.'];
        }

        $mismatch = abs($amount - (float) $order['total']) > 0.5;

        $paymentModel->create([
            'order_id' => $orderId,
            'method' => $method,
            'txn_id' => $txnId === '' ? null : mb_strtoupper(mb_substr($txnId, 0, 60)),
            'amount' => $amount,
            'status' => 'pending',
            'sender_number' => $sender === '' ? null : mb_substr($sender, 0, 20),
        ]);

        Database::execute(
            "update orders set payment_status = :pending, updated_at = now() where id = :id and payment_status <> :paid",
            [':pending' => 'pending_verification', ':id' => $orderId, ':paid' => 'paid']
        );

        self::history(
            $orderId,
            'pending_verification',
            $method . ' payment declared' . ($mismatch ? ' with an amount mismatch' : '') . '.'
        );

        if ($mismatch) {
            Logger::warning('payment_amount_mismatch', ['order_id' => $orderId, 'declared' => $amount, 'due' => $order['total']]);
        }

        return ['ok' => true, 'message' => 'Payment recorded. We verify it manually and deliver within minutes.'];
    }

    public static function history(int $orderId, string $status, string $note = ''): void
    {
        Database::execute(
            'insert into order_status_history (order_id, status, note, created_at) values (:id, :status, :note, :created)',
            [':id' => $orderId, ':status' => $status, ':note' => mb_substr($note, 0, 300), ':created' => date('Y-m-d H:i:s')]
        );
    }

    /**
     * Admin-only transition. This is the single place an order becomes paid, and
     * it refuses unless the matching payment row has been verified by a person.
     */
    public static function verifyPayment(int $orderId, int $staffId, string $note = ''): array
    {
        $payment = Database::selectOne(
            'select * from payments where order_id = :id and status = :pending order by id desc limit 1',
            [':id' => $orderId, ':pending' => 'pending']
        );

        if ($payment === null) {
            return ['ok' => false, 'message' => 'There is no declared payment to verify for this order.'];
        }

        $order = Database::selectOne('select * from orders where id = :id', [':id' => $orderId]);

        if ($order === null) {
            return ['ok' => false, 'message' => 'Order not found.'];
        }

        Database::transaction(static function () use ($payment, $orderId, $staffId, $note): void {
            (new Payment())->verify((int) $payment['id'], $staffId, $note);
            (new Order())->markPaymentVerified($orderId, $staffId);
            Database::execute(
                "update orders set status = :processing where id = :id and status = :pending",
                [':processing' => 'processing', ':id' => $orderId, ':pending' => 'pending']
            );
            self::history($orderId, 'paid', 'Payment verified by staff.' . ($note === '' ? '' : ' ' . $note));
        });

        Logger::info('payment_verified', ['order_id' => $orderId, 'staff' => $staffId]);

        return ['ok' => true, 'message' => 'Marked as paid. The order is now in the delivery queue.'];
    }

    public static function rejectPayment(int $orderId, int $staffId, string $note = ''): array
    {
        $payment = Database::selectOne(
            'select * from payments where order_id = :id and status = :pending order by id desc limit 1',
            [':id' => $orderId, ':pending' => 'pending']
        );

        if ($payment === null) {
            return ['ok' => false, 'message' => 'No pending payment on this order.'];
        }

        Database::transaction(static function () use ($payment, $orderId, $staffId, $note): void {
            (new Payment())->reject((int) $payment['id'], $staffId, $note);
            Database::execute(
                "update orders set payment_status = :unpaid, updated_at = now() where id = :id",
                [':unpaid' => 'unpaid', ':id' => $orderId]
            );
            self::history($orderId, 'payment_rejected', 'Payment rejected.' . ($note === '' ? '' : ' ' . $note));
        });

        return ['ok' => true, 'message' => 'Payment rejected. The customer can declare it again.'];
    }

    public static function cancel(int $orderId, string $reason = ''): array
    {
        $order = Database::selectOne('select * from orders where id = :id', [':id' => $orderId]);

        if ($order === null) {
            return ['ok' => false, 'message' => 'Order not found.'];
        }

        if (in_array((string) $order['status'], ['completed', 'cancelled'], true)) {
            return ['ok' => false, 'message' => 'This order is already closed.'];
        }

        Database::transaction(static function () use ($order, $orderId, $reason): void {
            Database::execute(
                "update orders set status = :cancelled, updated_at = now() where id = :id",
                [':cancelled' => 'cancelled', ':id' => $orderId]
            );

            // Put stock back for anything that was actually decremented.
            $items = Database::select('select plan_id, quantity from order_items where order_id = :id', [':id' => $orderId]);
            $planModel = new Plan();

            foreach ($items as $item) {
                if ((int) ($item['plan_id'] ?? 0) > 0) {
                    $planModel->releaseStock((int) $item['plan_id'], (int) $item['quantity']);
                }
            }

            $coupon = $order['coupon_code'] ?? null;

            if (is_string($coupon) && $coupon !== '') {
                (new Coupon())->releaseUsage($coupon);
            }

            self::history($orderId, 'cancelled', $reason === '' ? 'Order cancelled.' : mb_substr($reason, 0, 200));
        });

        return ['ok' => true, 'message' => 'Order cancelled and stock returned.'];
    }

    /** Deliver one line: store the credentials encrypted and flip its status. */
    public static function deliverItem(int $itemId, string $note, int $staffId): array
    {
        $item = Database::selectOne('select * from order_items where id = :id', [':id' => $itemId]);

        if ($item === null) {
            return ['ok' => false, 'message' => 'That line does not exist.'];
        }

        if (trim($note) === '') {
            return ['ok' => false, 'message' => 'Write the delivery details before marking it delivered.'];
        }

        $orderId = (int) $item['order_id'];

        (new OrderItem())->markDelivered($itemId, $note);

        $state = (new OrderItem())->fulfillmentState($orderId);

        Database::execute(
            'update orders set fulfillment_status = :state, updated_at = now() where id = :id',
            [':state' => $state, ':id' => $orderId]
        );

        if ($state === 'delivered') {
            Database::execute(
                "update orders set status = :completed where id = :id and payment_status = :paid",
                [':completed' => 'completed', ':id' => $orderId, ':paid' => 'paid']
            );
            self::history($orderId, 'delivered', 'Every line delivered.');
        }

        Logger::info('item_delivered', ['item' => $itemId, 'staff' => $staffId]);

        return ['ok' => true, 'message' => $state === 'delivered' ? 'Order fully delivered.' : 'Line delivered.'];
    }
}
