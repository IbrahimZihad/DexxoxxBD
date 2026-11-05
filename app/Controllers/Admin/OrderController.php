<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;

/**
 * The staff workbench for orders: filter the list, open a job, verify the money
 * a human has just checked against the wallet statement, hand the account over.
 *
 * Three rules hold this controller together:
 *  - every write goes through OrderService (or the model method that exists for
 *    it); no order row is written from here with hand-built SQL;
 *  - every state change is a POST behind csrf + staff middleware, lands in the
 *    audit trail, and answers with a redirect - never with the note it was given;
 *  - a payment only becomes "paid" while a pending payment row exists, which is
 *    exactly what OrderService::verifyPayment() refuses without.
 */
final class OrderController extends Controller
{
    private const PER_PAGE = 25;

    /** The fulfillment column of the orders table, in the order a job moves through it. */
    private const FULFILLMENT_STATES = ['unassigned', 'assigned', 'delivered'];

    public function index(Request $request): Response
    {
        $filters = [
            'status' => $this->whitelisted((string) $request->queryValue('status', ''), OrderService::STATUSES),
            'payment_status' => $this->whitelisted((string) $request->queryValue('payment_status', ''), OrderService::PAYMENT_STATUSES),
            'fulfillment' => $this->whitelisted((string) $request->queryValue('fulfillment', ''), self::FULFILLMENT_STATES),
            'search' => mb_substr(trim((string) $request->queryValue('search', '')), 0, 80),
        ];

        $page = max(1, (int) $request->queryValue('page', 1));

        return $this->view('admin.orders', [
            'paging' => $this->paginateOrders($filters, $page),
            'filters' => $filters,
            'statuses' => OrderService::STATUSES,
            'paymentStatuses' => OrderService::PAYMENT_STATUSES,
            'fulfillmentStates' => self::FULFILLMENT_STATES,
        ]);
    }

    /**
     * Everything about one job on one screen: the three statuses, what the customer
     * asked to be delivered to, the lines with their encrypted notes, the payment
     * rows waiting for a human, and the history.
     */
    public function show(Request $request, string $id): Response
    {
        $orderId = (int) $id;
        $order = $this->findOrder($orderId);
        $items = new OrderItem();

        // Order::withItems() is the model method that returns an order with its
        // lines, payments and history in one go; it is keyed by the order number.
        $full = (new Order())->withItems((string) $order['order_no']);
        $lines = [];

        foreach ((array) ($full['items'] ?? []) as $line) {
            $lines[] = $items->withDecryptedNote((array) $line);
        }

        $payments = [];

        foreach ((array) ($full['payments'] ?? []) as $payment) {
            $payment = (array) $payment;
            $payment['method_label'] = PaymentService::label((string) ($payment['method'] ?? ''));
            $payments[] = $payment;
        }

        return $this->view('admin.order', [
            'order' => $order,
            'items' => $lines,
            'payments' => $payments,
            'history' => (array) ($full['history'] ?? []),
            'statuses' => OrderService::STATUSES,
            'fulfillmentStates' => self::FULFILLMENT_STATES,
            'keyPresent' => Crypto::keyPresent(),
            'verifiedByName' => $this->staffName($order['verified_by'] ?? null),
        ]);
    }

    public function setStatus(Request $request, string $id): Response
    {
        $orderId = (int) $id;
        $order = $this->findOrder($orderId);

        $data = $this->validate($request, [
            'status' => 'required|in:' . implode(',', OrderService::STATUSES),
            'note' => 'max:300',
        ], ['status' => 'Status', 'note' => 'Note']);

        $status = (string) $data['status'];
        $note = trim((string) ($data['note'] ?? ''));
        $before = (string) $order['status'];

        if ($status === $before && $note === '') {
            return $this->redirect(
                $this->orderUrl($orderId),
                'info',
                'That is already the status of this order, so nothing was changed.'
            );
        }

        (new Order())->changeStatus($orderId, $status);
        OrderService::history(
            $orderId,
            $status,
            $note === '' ? sprintf('Status moved from %s to %s by staff.', $before, $status) : $note
        );

        AuditLog::record('order.status', 'order', $orderId, sprintf(
            '%s: status %s -> %s%s.',
            (string) $order['order_no'],
            $before,
            $status,
            $note === '' ? '' : ' (' . mb_substr($note, 0, 120) . ')'
        ));

        return $this->redirect(
            $this->orderUrl($orderId),
            'success',
            sprintf('Order %s is now marked "%s".', (string) $order['order_no'], status_label($status))
        );
    }

    public function verifyPayment(Request $request, string $id): Response
    {
        $orderId = (int) $id;
        $order = $this->findOrder($orderId);

        $data = $this->validate($request, [
            'note' => 'max:300',
        ], ['note' => 'Verification note']);

        $note = trim((string) ($data['note'] ?? ''));
        $result = OrderService::verifyPayment($orderId, (int) Auth::id(), $note);

        AuditLog::record(
            $result['ok'] ? 'order.payment_verified' : 'order.payment_verify_refused',
            'order',
            $orderId,
            sprintf(
                '%s: %s%s',
                (string) $order['order_no'],
                $result['message'],
                $note === '' ? '' : ' Note: ' . mb_substr($note, 0, 160)
            )
        );

        return $this->redirect(
            $this->orderUrl($orderId),
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function rejectPayment(Request $request, string $id): Response
    {
        $orderId = (int) $id;
        $order = $this->findOrder($orderId);

        // Rejecting sends the customer back to the payment page, so the reason is
        // required: it is what staff will read out to them on WhatsApp.
        $data = $this->validate($request, [
            'note' => 'required|min:4|max:300',
        ], ['note' => 'Reason']);

        $note = (string) $data['note'];
        $result = OrderService::rejectPayment($orderId, (int) Auth::id(), $note);

        AuditLog::record(
            $result['ok'] ? 'order.payment_rejected' : 'order.payment_reject_refused',
            'order',
            $orderId,
            sprintf(
                '%s: %s Reason: %s',
                (string) $order['order_no'],
                $result['message'],
                mb_substr($note, 0, 200)
            )
        );

        return $this->redirect(
            $this->orderUrl($orderId),
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function cancel(Request $request, string $id): Response
    {
        $orderId = (int) $id;
        $order = $this->findOrder($orderId);

        $data = $this->validate($request, [
            'reason' => 'max:200',
        ], ['reason' => 'Reason']);

        $reason = trim((string) ($data['reason'] ?? ''));
        $result = OrderService::cancel($orderId, $reason);

        AuditLog::record(
            $result['ok'] ? 'order.cancelled' : 'order.cancel_refused',
            'order',
            $orderId,
            sprintf(
                '%s: %s%s',
                (string) $order['order_no'],
                $result['message'],
                $reason === '' ? '' : ' Reason: ' . mb_substr($reason, 0, 160)
            )
        );

        return $this->redirect(
            $this->orderUrl($orderId),
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Hand one line over. The note typed here is the account itself: it goes to
     * OrderService::deliverItem(), which seals it with Crypto before it touches the
     * database, and this action answers with a redirect that carries no trace of it.
     */
    public function deliverItem(Request $request, string $id): Response
    {
        $itemId = (int) $id;
        $itemModel = new OrderItem();
        $item = $itemModel->find($itemId);

        if ($item === null) {
            throw new HttpException(404, 'That order line does not exist.');
        }

        $data = $this->validate($request, [
            'note' => 'required|min:4|max:2000',
            'back' => 'in:order,queue',
        ], ['note' => 'Delivery details', 'back' => 'Return page']);

        $orderId = (int) $item['order_id'];
        $order = $this->findOrder($orderId);
        $result = OrderService::deliverItem($itemId, (string) $data['note'], (int) Auth::id());

        // The audit line names the order, the plan and where it went - never the
        // credentials themselves.
        AuditLog::record('order.item_delivered', 'order', $orderId, sprintf(
            '%s: line %d (%s - %s) delivered to %s (%s).',
            (string) $order['order_no'],
            $itemId,
            (string) $item['service_name'],
            (string) $item['plan_title'],
            (string) $order['phone'],
            (string) $order['email']
        ));

        $message = $result['message'];

        if ($result['ok'] && (string) $order['payment_status'] !== 'paid') {
            $message .= ' Heads up: this order was not marked paid when you delivered it.';
        }

        $fromQueue = (string) ($data['back'] ?? 'order') === 'queue';

        // A queue row is one job among many, so stay in the queue; otherwise go
        // back to the order the line belongs to. Either way the response is a
        // redirect that carries no part of the note that was just stored.
        return $this->redirect(
            $fromQueue ? route('admin.delivery') : $this->orderUrl($orderId),
            $result['ok'] ? 'success' : 'error',
            $message
        );
    }

    /**
     * Put a delivered line back into the queue. OrderService declares no transition
     * for this, so it uses the model methods that exist, writes the same history
     * row the rest of the panel reads, and clears the stored note.
     */
    public function undoDelivery(Request $request, string $id): Response
    {
        $itemId = (int) $id;
        $itemModel = new OrderItem();
        $item = $itemModel->find($itemId);

        if ($item === null) {
            throw new HttpException(404, 'That order line does not exist.');
        }

        $orderId = (int) $item['order_id'];
        $orders = new Order();
        $order = $orders->find($orderId);

        if ($order === null) {
            throw new HttpException(404, 'That order does not exist.');
        }

        if ((string) $item['status'] !== 'delivered') {
            return $this->redirect(
                $this->orderUrl($orderId),
                'info',
                sprintf('Line %d has not been delivered, so there is nothing to undo.', $itemId)
            );
        }

        $itemModel->update($itemId, ['status' => 'pending', 'delivery_note' => null, 'delivered_at' => null]);

        $state = $itemModel->fulfillmentState($orderId);
        $orders->update($orderId, ['fulfillment_status' => $state]);

        // An order that auto-completed when the last line went out is reopened.
        if ($state !== 'delivered' && (string) $order['status'] === 'completed') {
            $orders->changeStatus($orderId, 'processing');
        }

        OrderService::history($orderId, 'delivery_undone', sprintf(
            'Delivery of line %d (%s - %s) undone by staff; the stored note was cleared.',
            $itemId,
            (string) $item['service_name'],
            (string) $item['plan_title']
        ));

        AuditLog::record('order.item_undone', 'order', $orderId, sprintf(
            '%s: line %d (%s - %s) put back in the delivery queue.',
            (string) $order['order_no'],
            $itemId,
            (string) $item['service_name'],
            (string) $item['plan_title']
        ));

        return $this->redirect(
            $this->orderUrl($orderId),
            'success',
            'Line ' . $itemId . ' is back in the queue and its delivery note is gone.'
        );
    }

    /**
     * @param array<string, string> $filters
     * @return array{items: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
     */
    private function paginateOrders(array $filters, int $page): array
    {
        $orders = new Order();

        if ($filters['fulfillment'] === '') {
            return $orders->paginateAdmin($filters, $page, self::PER_PAGE);
        }

        // Order::paginateAdmin() reads status, payment_status and search but has no
        // branch for fulfillment_status. Rather than quietly ignoring the choice the
        // staff member made, the same list is narrowed through the model's builder.
        $query = $orders->query()->orderBy(['id' => 'desc']);

        foreach (['status', 'payment_status', 'fulfillment'] as $key) {
            if ($filters[$key] !== '') {
                $query->where($key === 'fulfillment' ? 'fulfillment_status' : $key, $filters[$key]);
            }
        }

        if ($filters['search'] !== '') {
            $query->search(['order_no', 'customer_name', 'email', 'phone'], $filters['search']);
        }

        return $query->paginate($page, self::PER_PAGE);
    }

    /**
     * A filter from the query string is only honoured when it is one of the values
     * the column can actually hold; anything else means "no filter".
     *
     * @param list<string> $allowed
     */
    private function whitelisted(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : '';
    }

    /** @return array<string, mixed> */
    private function findOrder(int $orderId): array
    {
        $order = (new Order())->find($orderId);

        if ($order === null) {
            throw new HttpException(404, 'That order does not exist.');
        }

        return $order;
    }

    private function orderUrl(int $orderId): string
    {
        return route('admin.order.show', ['id' => $orderId]);
    }

    /** The staff account that marked a payment verified, when there is one. */
    private function staffName(mixed $userId): string
    {
        $id = (int) $userId;

        if ($id <= 0) {
            return '';
        }

        $user = (new User())->find($id);

        return $user === null ? '' : (string) ($user['name'] ?? '');
    }
}
