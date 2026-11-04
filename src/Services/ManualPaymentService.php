<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Exceptions\HttpException;
use App\Repositories\ManualPaymentRepository;
use App\Repositories\OrderRepository;

final class ManualPaymentService
{
    public function __construct(private readonly PassService $passes)
    {
    }

    /** Customer files bKash/Nagad/Rocket/bank proof against their own pending order. */
    public function submit(int $userId, array $data): array
    {
        $order = OrderRepository::findForUser((int) $data['order_id'], $userId);

        if ($order === null) {
            throw new HttpException(404, 'Order not found.');
        }

        if ($order['status'] !== 'pending') {
            throw new HttpException(422, 'This order is already ' . $order['status'] . '.');
        }

        if (ManualPaymentRepository::pendingForOrder((int) $order['id']) !== null) {
            throw new HttpException(409, 'A payment for this order is already waiting for review.');
        }

        // One transaction id may only ever back one order.
        if (ManualPaymentRepository::transactionIdTaken((string) $data['transaction_id'], (string) $data['payment_method'])) {
            throw new HttpException(409, 'That transaction id has already been submitted. Contact support if this is your payment.');
        }

        $id = ManualPaymentRepository::submit([
            'order_id' => (int) $order['id'],
            'payment_method' => $data['payment_method'],
            'sender_number' => $data['sender_number'] ?? null,
            'transaction_id' => $data['transaction_id'],
            // Amount is the order's own total, never whatever the client typed.
            'amount' => round((float) $order['total_amount'], 2),
            'note' => $data['note'] ?? null,
        ]);

        OrderRepository::setMethod((int) $order['id'], (string) $data['payment_method']);
        Logger::info('Manual payment submitted', ['order_id' => (int) $order['id'], 'method' => $data['payment_method']]);

        return ['id' => $id, 'order_number' => $order['order_number']];
    }

    /** @return array{status:string, order_id:int, order_was_settled:bool} */
    public function review(int $paymentId, string $action, ?string $adminNote, int $adminId): array
    {
        return Database::transaction(function ($pdo) use ($paymentId, $action, $adminNote, $adminId) {
            $statement = $pdo->prepare('SELECT mp.*, o.status AS order_status FROM manual_payments mp JOIN orders o ON o.id = mp.order_id WHERE mp.id = ? FOR UPDATE');
            $statement->execute([$paymentId]);
            $payment = $statement->fetch();

            if ($payment === false) {
                throw new HttpException(404, 'Payment record not found.');
            }

            if ($payment['status'] !== 'pending') {
                throw new HttpException(409, 'This payment was already reviewed.');
            }

            // Approving would double-settle an order the gateway (or an admin) already
            // closed as paid. Rejecting is still allowed — it is the only way to take the
            // claim off the review queue, and release() no-ops on a settled order.
            if ($payment['order_status'] === 'paid' && $action === 'approve') {
                throw new HttpException(409, 'The order is already paid through another method.');
            }

            $approved = $action === 'approve';

            if (ManualPaymentRepository::markReviewed($paymentId, $approved ? 'approved' : 'rejected', $adminNote, $adminId) !== 1) {
                throw new HttpException(409, 'This payment was already reviewed.');
            }

            if ($approved) {
                $this->passes->grantForOrder((int) $payment['order_id'], 'manual');
            } else {
                (new OrderService())->release((int) $payment['order_id'], 'failed');
            }

            Logger::info('Manual payment reviewed', [
                'payment_id' => $paymentId,
                'action' => $action,
                'admin_id' => $adminId,
                'order_id' => (int) $payment['order_id'],
            ]);

            return [
                'status' => $approved ? 'approved' : 'rejected',
                'order_id' => (int) $payment['order_id'],
                'order_was_settled' => in_array($payment['order_status'], ['paid', 'cancelled'], true),
            ];
        });
    }
}
