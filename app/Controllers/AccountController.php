<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

/**
 * The customer's own corner of the shop: their order history, their totals.
 *
 * Everything is keyed off the signed-in user id, and each row is narrowed to the
 * columns a customer is allowed to see before it reaches a template - no staff
 * ids, no stored IPs, no other account's data.
 */
final class AccountController extends Controller
{
    /** Order cards on the page. Older ones still count towards the totals. */
    private const HISTORY_LIMIT = 10;

    /** How many delivery states the "latest activity" panel lists. */
    private const DELIVERY_PREVIEW_LIMIT = 6;

    public function index(Request $request): Response
    {
        $user = auth_user();

        if ($user === null) {
            // The auth middleware normally gets here first; a session that went
            // stale mid-request must not render an account page with no owner.
            throw new HttpException(401, 'Sign in to see your account.');
        }

        $userId = (int) $user['id'];
        $orderRows = (new Order())->forUser($userId, self::HISTORY_LIMIT);
        $items = new OrderItem();

        $lines = [];
        $deliveries = [];

        foreach ($orderRows as $row) {
            $row = (array) $row;
            $orderId = (int) ($row['id'] ?? 0);
            $orderNo = strtoupper(trim((string) ($row['order_no'] ?? '')));

            if ($orderId <= 0 || $orderNo === '') {
                continue;
            }

            $orderLines = [];

            foreach ($items->forOrder($orderId) as $item) {
                $item = (array) $item;
                $state = (string) ($item['status'] ?? 'pending');
                $line = [
                    'service_name' => (string) ($item['service_name'] ?? ''),
                    'plan_title' => (string) ($item['plan_title'] ?? ''),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'line_total' => (float) ($item['line_total'] ?? 0),
                    'status' => $state,
                    'delivered_at' => (string) ($item['delivered_at'] ?? ''),
                ];

                $orderLines[] = $line;
                $deliveries[] = $line + ['order_no' => $orderNo];
            }

            $lines[$orderNo] = $orderLines;
        }

        return $this->view('account.index', [
            'user' => [
                'name' => trim((string) ($user['name'] ?? '')),
                'email' => (string) ($user['email'] ?? ''),
                'phone' => (string) ($user['phone'] ?? ''),
                'joined_at' => (string) ($user['created_at'] ?? ''),
            ],
            'orders' => array_map(
                static fn (array $row): array => [
                    'order_no' => strtoupper(trim((string) ($row['order_no'] ?? ''))),
                    'status' => (string) ($row['status'] ?? 'pending'),
                    'payment_status' => (string) ($row['payment_status'] ?? 'unpaid'),
                    'fulfillment_status' => (string) ($row['fulfillment_status'] ?? 'unassigned'),
                    'total' => (float) ($row['total'] ?? 0),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'paid_at' => (string) ($row['paid_at'] ?? ''),
                ],
                array_map(static fn (array $row): array => (array) $row, $orderRows)
            ),
            'lines' => $lines,
            // forUser() already returns newest first, so the first rows here are
            // the freshest delivery states.
            'deliveries' => array_slice($deliveries, 0, self::DELIVERY_PREVIEW_LIMIT),
            'summary' => (new User())->orderSummary($userId),
            'counters' => $this->counters(array_map(static fn (array $row): array => (array) $row, $orderRows)),
            'whatsappUrl' => wa_link('Hi ' . setting('brand.name', 'DexxozzBD') . ', I need help with my account.'),
        ]);
    }

    /**
     * Little stats card figures, counted from the orders on the page so they
     * match what the customer can actually see.
     *
     * @param list<array<string, mixed>> $orders
     * @return array{awaiting_payment: int, in_delivery: int, delivered: int, unpaid_total: float}
     */
    private function counters(array $orders): array
    {
        $awaiting = 0;
        $inDelivery = 0;
        $delivered = 0;
        $unpaidTotal = 0.0;

        foreach ($orders as $order) {
            $payment = (string) ($order['payment_status'] ?? '');
            $fulfillment = (string) ($order['fulfillment_status'] ?? '');
            $status = (string) ($order['status'] ?? '');

            if ($payment !== 'paid' && $status !== 'cancelled') {
                $awaiting++;
                $unpaidTotal += (float) ($order['total'] ?? 0);
            }

            if ($payment === 'paid' && $fulfillment !== 'delivered') {
                $inDelivery++;
            }

            if ($fulfillment === 'delivered') {
                $delivered++;
            }
        }

        return [
            'awaiting_payment' => $awaiting,
            'in_delivery' => $inDelivery,
            'delivered' => $delivered,
            'unpaid_total' => $unpaidTotal,
        ];
    }
}
