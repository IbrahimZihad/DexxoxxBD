<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Core\Config;
use App\Exceptions\HttpException;
use App\Http\Controller;
use App\Http\Response;
use App\Payment\SslCommerz;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\SettingRepository;
use App\Security\Sanitizer;
use App\Services\ManualPaymentService;
use App\Services\PassService;
use App\Support\OrderNumber;

final class PaymentController extends Controller
{
    /** Starts a card/Wallet session on SSLCommerz for one of the buyer's own orders. */
    public function init(): Response
    {
        $user = Auth::requireUser();
        $data = $this->validate(['order_id' => 'required|integer|min:1']);
        $orderId = (int) $data['order_id'];

        $order = OrderRepository::findForUser($orderId, (int) $user['id']);

        if ($order === null) {
            throw new HttpException(404, 'Order not found.');
        }

        if ($order['status'] !== 'pending') {
            throw new HttpException(409, 'This order is already ' . $order['status'] . '.');
        }

        if ((float) $order['total_amount'] <= 0) {
            throw new HttpException(422, 'This order has nothing to pay for.');
        }

        $gateway = SslCommerz::make();

        if (!$gateway->isConfigured()) {
            throw new HttpException(503, 'Card payments are not available right now. Please use bKash, Nagad, Rocket or bank transfer.');
        }

        // Reuse a live attempt instead of stacking parallel transactions for the
        // same order — that is how a buyer ends up charged twice.
        $payment = $this->liveAttempt($orderId);

        if ($payment === null) {
            $tranId = OrderNumber::transactionId();
            $amount = round((float) $order['total_amount'], 2);
            PaymentRepository::initiate($orderId, $tranId, $amount, $gateway->currency());

            $session = $gateway->initiate($order, $tranId);
        } else {
            $tranId = (string) $payment['tran_id'];
            $session = $gateway->initiate($order, $tranId);
        }

        if (!$session['ok']) {
            if (isset($payment)) {
                PaymentRepository::markFailed((int) $payment['id'], 'session_refused');
            }

            throw new HttpException(502, (string) ($session['error'] ?? 'The payment gateway refused this transaction.'));
        }

        return $this->json([
            'gateway_url' => $session['gateway_url'],
            'tran_id' => $tranId,
            'order_number' => $order['order_number'],
            'amount' => round((float) $order['total_amount'], 2),
            'currency' => (string) Config::get('payment.sslcommerz.currency', 'BDT'),
        ]);
    }

    /** Customer files manual payment proof for an order they own. */
    public function manual(): Response
    {
        $user = Auth::requireUser();

        if (!Config::get('payment.manual_enabled', true)) {
            throw new HttpException(503, 'Manual payment submissions are disabled right now.');
        }

        $data = $this->validate([
            'order_id' => 'required|integer|min:1',
            'payment_method' => 'required|in:bkash,nagad,rocket,bank',
            'transaction_id' => 'required|string|min:4|max:60',
            'sender_number' => 'nullable|string|max:20',
            'note' => 'nullable|string|max:500',
        ], [
            'transaction_id' => 'transaction ID',
            'payment_method' => 'payment channel',
            'sender_number' => 'sender number',
        ]);

        $reference = Sanitizer::digits($data['transaction_id'], 60);

        if (strlen($reference) < 4) {
            throw new HttpException(422, 'Please check the highlighted fields.', [
                'transaction_id' => 'Transaction ID must use letters and numbers only.',
            ]);
        }

        $result = $this->manualService()->submit((int) $user['id'], [
            'order_id' => (int) $data['order_id'],
            'payment_method' => (string) $data['payment_method'],
            'transaction_id' => $reference,
            'sender_number' => isset($data['sender_number']) ? Sanitizer::phone($data['sender_number']) : null,
            'note' => isset($data['note']) ? Sanitizer::multiline($data['note'], 500) : null,
        ]);

        return $this->json([
            'ok' => true,
            'manual_payment_id' => $result['id'],
            'order_number' => $result['order_number'],
            'message' => 'Payment details submitted. Your order activates once our team verifies the payment.',
        ], 201);
    }

    /** Manual submission should show the numbers the admin actually configured. */
    public function methods(): Response
    {
        $enabled = (bool) Config::get('payment.manual_enabled', true);
        $gateway = SslCommerz::make();
        $settings = SettingRepository::all(true);

        return $this->json([
            'gateway' => [
                'available' => $gateway->isConfigured(),
                'label' => 'Card / Net Banking (SSLCommerz)',
                'sandbox' => (bool) Config::get('payment.sslcommerz.sandbox', true),
            ],
            'manual' => [
                'available' => $enabled,
                'channels' => array_values(array_filter([
                    $this->channel('bkash', 'bKash', $settings),
                    $this->channel('nagad', 'Nagad', $settings),
                    $this->channel('rocket', 'Rocket', $settings),
                    $this->channel('bank', 'Bank Transfer', $settings, ['account' => $settings['bank_account'] ?? '', 'bank_name' => $settings['bank_name'] ?? '']),
                ])),
            ],
        ]);
    }

    /** @param array<string,mixed> $settings @return array<string,mixed>|null */
    private function channel(string $key, string $label, array $settings, array $extra = []): ?array
    {
        $number = (string) ($settings[$key . '_number'] ?? '');

        if ($number === '') {
            return null; // admin has not published this channel yet
        }

        return array_merge([
            'method' => $key,
            'label' => $label,
            'number' => $number,
            'kind' => (string) ($settings[$key . '_kind'] ?? 'Personal'),
        ], $extra);
    }

    private function manualService(): ManualPaymentService
    {
        return new ManualPaymentService(new PassService());
    }

    /** @return array<string,mixed>|null a gateway attempt still open on the provider side */
    private function liveAttempt(int $orderId): ?array
    {
        foreach (PaymentRepository::listForOrder($orderId) as $row) {
            if ($row['status'] === 'initiated' && strtotime((string) $row['created_at']) > time() - 1800) {
                return PaymentRepository::find((int) $row['id']);
            }
        }

        return null;
    }
}
