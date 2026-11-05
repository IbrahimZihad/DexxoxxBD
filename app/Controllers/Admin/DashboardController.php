<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\PaymentService;

/**
 * The two read-only pages a staff member lives in: the overview and the delivery
 * queue. The only write that happens here is the housekeeping one - stale unpaid
 * orders are released while the dashboard is being built, never from a link.
 */
final class DashboardController extends Controller
{
    /** How many rows each side card shows before the staff member clicks through. */
    private const CARD_ROWS = 6;

    private const QUEUE_ROWS = 100;

    public function index(Request $request): Response
    {
        $orders = new Order();

        $expired = $this->releaseStalePending($orders);
        $pending = (new Payment())->pendingVerification(self::CARD_ROWS);

        // The rail key is stored raw; the readable name comes from config/payments.php
        // so the card never has to know where a label lives.
        foreach ($pending as $index => $payment) {
            $pending[$index]['method_label'] = PaymentService::label((string) ($payment['method'] ?? ''));
        }

        return $this->view('admin.dashboard', [
            'stats' => $orders->dashboard(),
            'series' => $orders->lastDaysSeries(14),
            'bestSellers' => $orders->bestSellers(5),
            'recentOrders' => $orders->recent(8),
            'pendingPayments' => $pending,
            'lowStock' => (new Plan())->lowStock(3),
            'unreadMessages' => (new ContactMessage())->unreadCount(),
            'userCounts' => (new User())->counts(),
            'expiredCount' => $expired,
            'holdMinutes' => max(5, Settings::int('order.pending_hold_minutes', 90)),
        ]);
    }

    /**
     * Every line of a paid order that has not been handed over yet. The queue is
     * the work list: one row per plan, with the order context next to it so nobody
     * has to open eight tabs to deliver an order.
     */
    public function deliveryQueue(Request $request): Response
    {
        $items = new OrderItem();

        $awaiting = $items->pendingDeliveryCount();

        return $this->view('admin.delivery', [
            'queue' => $items->pendingDelivery(self::QUEUE_ROWS),
            'awaiting' => $awaiting,
            'truncated' => $awaiting > self::QUEUE_ROWS,
        ]);
    }

    /**
     * Cancel unpaid orders whose hold has run out, while the dashboard renders.
     *
     * A count runs first so an ordinary dashboard load stays read-only: the UPDATE
     * only happens when something has actually gone stale. The hold length is the
     * shop's setting, and the release is written to the audit trail because it is
     * a state change a customer will notice.
     */
    private function releaseStalePending(Order $orders): int
    {
        $minutes = max(5, Settings::int('order.pending_hold_minutes', 90));
        $deadline = date('Y-m-d H:i:s', time() - $minutes * 60);

        $stale = $orders->query()
            ->where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->where('created_at', '<', $deadline)
            ->count();

        if ($stale === 0) {
            return 0;
        }

        $expired = $orders->expirePending($minutes);

        if ($expired > 0) {
            AuditLog::record(
                'order.expired',
                'order',
                0,
                sprintf(
                    '%d order(s) released automatically: still unpaid after the %d minute hold.',
                    $expired,
                    $minutes
                )
            );
        }

        return $expired;
    }
}
