<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CartService;

/**
 * The shopping cart: add, re-quantity, remove, empty and coupon handling.
 *
 * Every mutation answers with the recomputed header count so the browser badge
 * matches MySQL. Prices are never taken from the request - CartService reloads
 * each variation and clamps the quantity to live stock.
 */
final class CartController extends Controller
{
    private CartService $cart;

    public function __construct()
    {
        $this->cart = App::cart();
    }

    public function index(): Response
    {
        $summary = $this->cart->lines();
        $totals = $this->totals($summary);

        return $this->view('cart.index', [
            'cart' => $this->cart->currentCart(),
            'summary' => $summary,
            'totals' => $totals,
            'coupon' => $this->cart->appliedCoupon(),
            'canCheckout' => $summary['lines'] !== [],
        ]);
    }

    public function add(Request $request): Response
    {
        $data = $this->validate($request, [
            'product_id' => 'required|integer|min:1',
            'variation_id' => 'required|integer|min:1',
            'quantity' => 'nullable|integer|min:1|max:' . $this->maximumUnits(),
        ]);

        $result = $this->cart->add(
            (int) $data['product_id'],
            (int) $data['variation_id'],
            (int) ($data['quantity'] ?? 1)
        );

        if (!$result['ok']) {
            return $this->respond($request, false, $result['message'], 422);
        }

        return $this->respond($request, true, $result['message']);
    }

    public function update(Request $request): Response
    {
        $data = $this->validate($request, [
            'item_id' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:0|max:' . $this->maximumUnits(),
        ]);

        $result = $this->cart->updateQuantity((int) $data['item_id'], (int) $data['quantity']);

        return $result['ok']
            ? $this->respond($request, true, $result['message'])
            : $this->respond($request, false, $result['message'], 422);
    }

    public function remove(Request $request): Response
    {
        $data = $this->validate($request, ['item_id' => 'required|integer|min:1']);

        $result = $this->cart->remove((int) $data['item_id']);

        return $result['ok']
            ? $this->respond($request, true, $result['message'])
            : $this->respond($request, false, $result['message'], 422);
    }

    public function clear(Request $request): Response
    {
        $this->cart->clear();

        return $this->respond($request, true, t('cart.cleared'));
    }

    public function applyCoupon(Request $request): Response
    {
        $data = $this->validate($request, [
            'code' => 'required|string|max:60',
        ]);

        $result = $this->cart->applyCoupon((string) $data['code']);

        return $result['ok']
            ? $this->respond($request, true, $result['message'])
            : $this->respond($request, false, $result['message'], 422);
    }

    public function removeCoupon(Request $request): Response
    {
        $this->cart->removeCoupon();

        return $this->respond($request, true, t('cart.coupon_removed'));
    }

    /**
     * Server-side money for the cart page (and, later, the same maths is used by
     * checkout before an order row is written).
     *
     * @param array{lines: list<array<string, mixed>>, subtotal: float, item_count: int, removed: list<string>} $summary
     * @return array{subtotal: float, discount: float, delivery: float, total: float, item_count: int}
     */
    private function totals(array $summary): array
    {
        $subtotal = (float) $summary['subtotal'];
        $discount = 0.0;

        $coupon = $this->cart->appliedCoupon();
        if ($coupon !== null) {
            $discount = App::coupon()->discountFor($coupon, $summary['lines']);
        }

        $delivery = $subtotal > 0 ? (float) Config::get('shop.delivery_fee', 0) : 0.0;
        $total = max(0.0, $subtotal - $discount) + $delivery;

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'delivery' => round($delivery, 2),
            'total' => round($total, 2),
            'item_count' => (int) $summary['item_count'],
        ];
    }

    private function maximumUnits(): int
    {
        return max(1, (int) Config::get('shop.max_per_cart', 5));
    }

    /**
     * Fetch calls get JSON, a browser without JavaScript gets a redirect back to
     * the page it posted from. Both paths report the same outcome.
     */
    private function respond(Request $request, bool $ok, string $message, int $status = 200): Response
    {
        $count = $this->cart->countItems();

        if ($request->wantsJson()) {
            if (!$ok) {
                return $this->fail($message, $status, ['data' => ['count' => $count]]);
            }

            return $this->ok(['count' => $count], $message);
        }

        flash($ok ? 'success' : 'danger', $message);

        return $request->referer() === '' ? $this->redirect('cart') : $this->back();
    }
}
