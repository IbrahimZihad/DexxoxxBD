<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;

/**
 * The payment step: show the exact amount and the merchant number, take the
 * reference the customer claims to have sent, and stop there.
 *
 * Nothing in this controller can mark an order paid. It calls
 * OrderService::recordPayment(), which writes a pending payment row and moves
 * the order to pending_verification; only the admin verify step can go further.
 */
final class PaymentController extends Controller
{
    public function show(Request $request): Response
    {
        $order = $this->openOrder($request);

        if ($order === null) {
            return $this->refuse($request);
        }

        $rails = $this->railCards();
        $selected = $this->selectedRail($rails);
        $payments = (array) ($order['payments'] ?? []);
        $declared = $payments === [] ? null : (array) $payments[0];

        return $this->view('pages.pay', [
            'order' => $order,
            'items' => (array) ($order['items'] ?? []),
            'payments' => $payments,
            'declared' => $declared,
            'rails' => $rails,
            'selected' => $selected,
            'instruction' => setting('payment.instruction', 'Send the exact amount, then paste the TrxID below.'),
        ]);
    }

    public function store(Request $request): Response
    {
        $order = $this->openOrder($request);

        if ($order === null) {
            return $this->refuse($request);
        }

        $data = $this->validate($request, [
            'method' => 'required|max:24',
            'txn_id' => 'max:60',
            'sender_number' => 'max:20',
        ], ['method' => 'Payment method', 'txn_id' => 'Transaction ID', 'sender_number' => 'Sender number']);

        $method = (string) $data['method'];
        $txn = mb_strtoupper(trim((string) ($data['txn_id'] ?? '')));
        $sender = trim((string) ($data['sender_number'] ?? ''));

        if (!PaymentService::keyIsValid($method)) {
            return $this->declare($request, $order, false, 'Choose one of the payment methods listed on this page.');
        }

        $label = PaymentService::label($method);

        if (PaymentService::requiresTxn($method) && $txn === '') {
            return $this->declare(
                $request,
                $order,
                false,
                'Paste the ' . $label . ' reference so we can find your payment - orders without one cannot be verified.'
            );
        }

        if ($txn !== '' && !PaymentService::txnLooksReal($method, $txn)) {
            $hint = (string) (PaymentService::method($method)['txn_hint'] ?? '');

            return $this->declare(
                $request,
                $order,
                false,
                'That reference does not look like a ' . $label . ' one.' . ($hint === '' ? '' : ' ' . $hint)
            );
        }

        if ((string) $order['payment_status'] === 'paid') {
            return $this->declare($request, $order, false, 'This order is already verified as paid. We are working on delivery.');
        }

        $latest = (array) (($order['payments'] ?? []) === [] ? [] : (array) $order['payments'][0]);

        if ((string) ($latest['status'] ?? '') === 'pending'
            && mb_strtoupper(trim((string) ($latest['txn_id'] ?? ''))) === $txn
            && $txn !== '') {
            return $this->declare($request, $order, false, 'That reference is already recorded for this order and waiting for verification.');
        }

        $result = OrderService::recordPayment(
            (int) $order['id'],
            $method,
            $txn,
            (float) $order['total'],
            $sender
        );

        $ok = (bool) $result['ok'];
        $message = (string) $result['message'];

        if (!$ok) {
            return $this->declare($request, $order, false, $message);
        }

        $this->rememberOrder((string) $order['order_no']);

        return $this->declare($request, $order, true, $message, route('order.show', ['orderNo' => (string) $order['order_no']]));
    }

    /**
     * Single exit for every outcome of the payment step so the JSON contract and
     * the flash-message contract stay in one place.
     */
    private function declare(Request $request, array $order, bool $ok, string $message, string $redirect = ''): Response
    {
        if ($request->wantsJson()) {
            $payload = ['ok' => $ok, 'message' => $message];

            if ($ok) {
                $fresh = (new Order())->withItems((string) $order['order_no']);

                if ($fresh !== null) {
                    $payload['status_html'] = view()->html('pages.partials.order-status-inner', ['order' => $fresh]);
                }

                if ($redirect !== '') {
                    $payload['redirect'] = $redirect;
                }
            }

            return $this->json($payload, $ok ? 200 : 422);
        }

        Session::flash($ok ? 'success' : 'error', $message);

        return $this->redirect($redirect === '' ? route('order.pay', ['orderNo' => (string) $order['order_no']]) : $redirect);
    }

    /**
     * Load the order and prove this browser is allowed to see it: either it
     * placed the order (or tracked it with the right email) in this session, or
     * the signed-in account owns it.
     *
     * @return array<string, mixed>|null
     */
    private function openOrder(Request $request): ?array
    {
        $order = (new Order())->withItems($request->parameter('orderNo'));

        if ($order === null) {
            return null;
        }

        $orderNo = (string) $order['order_no'];
        $seen = array_values(array_filter(
            (array) Session::get('viewed_orders', []),
            static fn (mixed $value): bool => is_string($value)
        ));

        if (in_array($orderNo, $seen, true)) {
            return $order;
        }

        $user = auth_user();

        if ($user !== null
            && mb_strtolower(trim((string) ($user['email'] ?? ''))) === mb_strtolower((string) $order['email'])) {
            $this->rememberOrder($orderNo);

            return $order;
        }

        return null;
    }

    private function refuse(Request $request): Response
    {
        $message = 'We could not open that order here. Confirm it is yours by tracking it with the email you ordered with.';

        if ($request->wantsJson()) {
            return $this->json(['ok' => false, 'message' => $message], 403);
        }

        Session::flash('warning', $message);

        return $this->redirect(route('track'));
    }

    private function rememberOrder(string $orderNo): void
    {
        if ($orderNo === '') {
            return;
        }

        $seen = array_values(array_filter(
            (array) Session::get('viewed_orders', []),
            static fn (mixed $value): bool => is_string($value)
        ));

        if (!in_array($orderNo, $seen, true)) {
            $seen[] = $orderNo;
            Session::set('viewed_orders', array_slice($seen, -25));
        }
    }

    /**
     * The rail picked at checkout, if it is still offered; otherwise the first
     * available one, so the page never opens with nothing selected.
     *
     * @param list<array<string, mixed>> $rails
     */
    private function selectedRail(array $rails): string
    {
        $chosen = strtoupper(trim((string) Session::get('checkout_method', '')));

        foreach ($rails as $rail) {
            if ((string) $rail['key'] === $chosen) {
                return $chosen;
            }
        }

        return (string) ($rails[0]['key'] ?? '');
    }

    /** @return list<array<string, mixed>> */
    private function railCards(): array
    {
        $cards = [];

        foreach (PaymentService::available() as $key => $rail) {
            $numberKey = (string) ($rail['number_key'] ?? '');

            $cards[] = [
                'key' => (string) $key,
                'label' => (string) ($rail['label'] ?? $key),
                'kind' => (string) ($rail['kind'] ?? 'manual'),
                'color' => (string) ($rail['color'] ?? '#6a31e0'),
                'instructions' => (string) ($rail['instructions'] ?? ''),
                'number' => PaymentService::numberFor($numberKey),
                'number_label' => (string) ($rail['number_label'] ?? ''),
                'txn_label' => (string) ($rail['txn_label'] ?? 'Transaction ID'),
                'txn_hint' => (string) ($rail['txn_hint'] ?? ''),
                'requires_txn' => PaymentService::requiresTxn((string) $key),
            ];
        }

        return $cards;
    }
}
