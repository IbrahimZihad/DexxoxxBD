<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Order;
use App\Services\CartService;
use App\Services\MailService;
use App\Services\OrderService;
use App\Services\PaymentService;

/**
 * Checkout: read the basket, ask for the details a human needs to deliver, and
 * hand the whole thing to OrderService.
 *
 * The amount a customer pays is whatever the server computes at write time - the
 * form carries no price at all. OrderService::fromCart() also empties the cart
 * (and forgets the coupon) inside the same transaction as the order rows.
 */
final class CheckoutController extends Controller
{
    public function index(Request $request): Response
    {
        $lines = CartService::lines();

        if ($lines === []) {
            Session::flash('warning', 'Your cart is empty - add a plan first and checkout will open.');

            return $this->redirect(route('cart'));
        }

        return $this->view('pages.checkout', [
            'lines' => $lines,
            'totals' => CartService::totals(),
            'rails' => $this->railCards(),
            'customer' => $this->customerDefaults(),
        ]);
    }

    public function store(Request $request): Response
    {
        $rails = PaymentService::available();

        $data = $this->validate($request, [
            'name' => 'required|min:2|max:120',
            'email' => 'required|email',
            'phone' => 'required|phone_bd|max:20',
            'note' => 'max:500',
            'requirement' => 'max:300',
            'method' => 'required|in:' . implode(',', array_keys($rails)),
        ], [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'note' => 'Note',
            'requirement' => 'Delivery request',
            'method' => 'Payment method',
        ]);

        if (CartService::lines() === []) {
            $message = 'Your cart is empty - add a plan before checking out.';

            if ($request->wantsJson()) {
                return $this->json(['ok' => false, 'message' => $message, 'count' => 0], 422);
            }

            Session::flash('error', $message);

            return $this->redirect(route('cart'));
        }

        $user = auth_user();

        // Validator::phone_bd() normalises the copy it validates against but the
        // value it returns keeps the dashes, so the stored number is tidied here.
        $phone = (string) preg_replace('/[\s()-]/', '', (string) $data['phone']);

        $result = OrderService::fromCart([
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'phone' => $phone,
            'note' => (string) ($data['note'] ?? ''),
            'requirement' => (string) ($data['requirement'] ?? ''),
        ], [
            'ip' => $request->ip(),
            'user_id' => $user === null ? null : (int) $user['id'],
        ]);

        if (!$result['ok']) {
            $message = (string) $result['message'];

            if ($request->wantsJson()) {
                return $this->json(['ok' => false, 'message' => $message], 422);
            }

            Session::flash('error', $message);

            return $this->redirect(route('checkout'));
        }

        $orderNo = (string) $result['order_no'];

        // Remembered for two reasons: the payment page must only open for a
        // browser that really placed (or tracked) this order, and the status
        // poll has to know this session is allowed to ask about it.
        $this->rememberOrder($orderNo);
        Session::set('last_order_no', $orderNo);
        Session::set('checkout_method', (string) $data['method']);

        $order = (new Order())->withItems($orderNo);

        if ($order !== null) {
            MailService::orderPlaced($order, (array) ($order['items'] ?? []));
        }

        $payUrl = route('order.pay', ['orderNo' => $orderNo]);
        $message = 'Order ' . $orderNo . ' is reserved. Send the amount and submit your reference.';

        if ($request->wantsJson()) {
            return $this->json(['ok' => true, 'message' => $message, 'count' => 0, 'redirect' => $payUrl]);
        }

        Session::flash('success', $message);

        return $this->redirect($payUrl);
    }

    /**
     * Payment rails prepared for the view: label, brand colour, merchant number
     * and the reference format hint, all read from config + settings.
     *
     * @return list<array<string, mixed>>
     */
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

    /** @return array{name: string, email: string, phone: string} */
    private function customerDefaults(): array
    {
        $user = auth_user();

        return [
            'name' => $user === null ? '' : (string) ($user['name'] ?? ''),
            'email' => $user === null ? '' : (string) ($user['email'] ?? ''),
            'phone' => $user === null ? '' : (string) ($user['phone'] ?? ''),
        ];
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
}
