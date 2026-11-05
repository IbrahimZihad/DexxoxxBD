<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Order;
use App\Services\PaymentService;

/**
 * Order pages a customer can open without an account.
 *
 * Two rules shape everything here: an order number on its own proves nothing
 * (the email has to match before a single detail is shown), and a wrong answer
 * always produces the same generic message so this page cannot be used to find
 * out whether an order number exists.
 */
final class OrderController extends Controller
{
    private const GENERIC_FAILURE = 'We could not find an order with that number and email. Check both and try again - or send us the number on WhatsApp and we will look it up for you.';

    public function show(Request $request): Response
    {
        $orderNo = strtoupper(trim($request->parameter('orderNo')));
        $order = $orderNo === '' ? null : (new Order())->withItems($orderNo);

        if ($order === null || !$this->owns($order)) {
            // Same page, same shape as an unknown number: nothing is leaked.
            return $this->view('pages.order', ['order' => null, 'orderNo' => $orderNo], 404);
        }

        $this->rememberOrder($orderNo);

        return $this->view('pages.order', [
            'order' => $order,
            'orderNo' => $orderNo,
            'items' => (array) ($order['items'] ?? []),
            'payments' => (array) ($order['payments'] ?? []),
            'history' => (array) ($order['history'] ?? []),
            'whatsappUrl' => wa_link(PaymentService::whatsappSummary(
                $this->summaryOrder($order, (array) ($order['payments'] ?? [])),
                (array) ($order['items'] ?? [])
            )),
        ]);
    }

    public function trackForm(Request $request): Response
    {
        $preset = trim((string) $request->queryValue('order_no', ''));

        return $this->view('pages.track', [
            'order' => null,
            'orderNo' => $preset === '' ? '' : strtoupper($preset),
            'email' => '',
            'failure' => '',
        ]);
    }

    public function track(Request $request): Response
    {
        $data = $this->validate($request, [
            'order_no' => 'required|min:6|max:40',
            'email' => 'required|email',
        ], ['order_no' => 'Order number', 'email' => 'Email']);

        $orderNo = strtoupper(trim((string) $data['order_no']));
        $email = mb_strtolower(trim((string) $data['email']));
        $order = (new Order())->withItems($orderNo);

        // Both halves have to line up; a mismatch answers exactly like an
        // unknown number (and with the same 200 status) so nothing is revealed.
        if ($order === null || !hash_equals($email, mb_strtolower((string) $order['email']))) {
            return $this->view('pages.track', [
                'order' => null,
                'orderNo' => $orderNo,
                'email' => (string) $data['email'],
                'failure' => self::GENERIC_FAILURE,
            ]);
        }

        $this->rememberOrder($orderNo);
        $payments = (array) ($order['payments'] ?? []);

        return $this->view('pages.track', [
            'order' => $order,
            'orderNo' => (string) $order['order_no'],
            'email' => '',
            'failure' => '',
            'items' => (array) ($order['items'] ?? []),
            'payments' => $payments,
            'whatsappUrl' => wa_link(PaymentService::whatsappSummary(
                $this->summaryOrder($order, $payments),
                (array) ($order['items'] ?? [])
            )),
        ]);
    }

    /**
     * Already seen in this session (placed, paid or tracked), or the signed-in
     * account uses the same email the order was written with.
     *
     * @param array<string, mixed> $order
     */
    private function owns(array $order): bool
    {
        $seen = array_values(array_filter(
            (array) Session::get('viewed_orders', []),
            static fn (mixed $value): bool => is_string($value)
        ));

        if (in_array((string) $order['order_no'], $seen, true)) {
            return true;
        }

        $user = auth_user();

        if ($user === null) {
            return false;
        }

        $matches = mb_strtolower(trim((string) ($user['email'] ?? ''))) === mb_strtolower((string) $order['email']);

        if ($matches) {
            $this->rememberOrder((string) $order['order_no']);
        }

        return $matches;
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
     * PaymentService::whatsappSummary() reads payment_method / txn_id off the
     * order row, so the newest payment is folded in before the message is built.
     *
     * @param array<string, mixed>        $order
     * @param list<array<string, mixed>>  $payments
     * @return array<string, mixed>
     */
    private function summaryOrder(array $order, array $payments): array
    {
        $latest = $payments === [] ? [] : (array) $payments[0];

        return $order + [
            'payment_method' => (string) ($latest['method'] ?? ''),
            'txn_id' => (string) ($latest['txn_id'] ?? ''),
        ];
    }
}
