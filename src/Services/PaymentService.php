<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Payment\SslCommerz;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;

/**
 * Turns gateway chatter into settled orders. Every public callback endpoint
 * (success/fail/cancel/ipn) funnels through here so the checks happen once.
 */
final class PaymentService
{
    public function __construct(
        private readonly SslCommerz $gateway,
        private readonly PassService $passes
    ) {
    }

    /**
     * @return array{ok:bool, reason:string, order_id:int|null}
     */
    public function settle(string $tranId, string $valId, string $via = 'gateway'): array
    {
        if (!$this->gateway->isConfigured()) {
            Logger::warning('Settlement callback with no gateway credentials configured');

            return ['ok' => false, 'reason' => 'gateway_not_configured', 'order_id' => null];
        }

        $payment = PaymentRepository::findByTranId($tranId);

        if ($payment === null) {
            Logger::warning('Callback for unknown transaction', ['tran_id' => $tranId, 'via' => $via]);

            return ['ok' => false, 'reason' => 'unknown_transaction', 'order_id' => null];
        }

        if ($payment['status'] === 'valid') {
            return ['ok' => true, 'reason' => 'already_settled', 'order_id' => (int) $payment['order_id']];
        }

        if ($payment['status'] === 'cancelled') {
            return ['ok' => false, 'reason' => 'already_cancelled', 'order_id' => (int) $payment['order_id']];
        }

        $response = $this->gateway->validate($valId);

        if ($response === null) {
            Logger::error('Gateway validation unavailable', ['tran_id' => $tranId, 'via' => $via]);

            return ['ok' => false, 'reason' => 'validation_unreachable', 'order_id' => (int) $payment['order_id']];
        }

        $verdict = $this->gateway->verify($response, $tranId, round((float) $payment['amount'], 2));

        if (!$verdict['ok']) {
            PaymentRepository::markFailed((int) $payment['id'], $verdict['reason'], $response);
            Logger::warning('Rejected settlement attempt', ['tran_id' => $tranId, 'reason' => $verdict['reason'], 'via' => $via]);

            return ['ok' => false, 'reason' => $verdict['reason'], 'order_id' => (int) $payment['order_id']];
        }

        PaymentRepository::markValid((int) $payment['id'], $response);
        $granted = $this->passes->grantForOrder((int) $payment['order_id'], $via);

        return [
            'ok' => true,
            'reason' => $granted ? 'passes_issued' : 'already_settled',
            'order_id' => (int) $payment['order_id'],
        ];
    }

    public function markFailed(string $tranId, string $reason): void
    {
        $payment = PaymentRepository::findByTranId($tranId);

        if ($payment === null || $payment['status'] === 'valid') {
            return;
        }

        PaymentRepository::markFailed((int) $payment['id'], $reason);
        (new OrderService())->release((int) $payment['order_id'], 'failed');
    }

    public function markCancelled(string $tranId): void
    {
        $payment = PaymentRepository::findByTranId($tranId);

        if ($payment === null || $payment['status'] === 'valid') {
            return;
        }

        PaymentRepository::markCancelled((int) $payment['id']);
        (new OrderService())->release((int) $payment['order_id'], 'cancelled');
    }

    public function orderForUser(int $orderId, int $userId): ?array
    {
        return OrderRepository::findForUser($orderId, $userId);
    }
}
