<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\CartService;

/**
 * The session cart. Every mutator answers in whichever language the browser
 * asked for: JSON for the fetch calls made by app.js (with the rendered
 * fragments it swaps straight into the page) or a redirect plus a flash for a
 * plain form post, which is what a customer without JavaScript gets.
 *
 * Prices are never touched here - CartService reads them from MySQL, so the
 * only thing this controller passes around is plan ids.
 */
final class CartController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('pages.cart', [
            'lines' => CartService::lines(),
            'totals' => CartService::totals(),
        ]);
    }

    public function add(Request $request): Response
    {
        // A plan is one subscription; there is deliberately no quantity field.
        $data = $this->validate($request, [
            'plan_id' => 'required|integer|min:1',
        ], ['plan_id' => 'Plan']);

        $result = CartService::add((int) $data['plan_id']);

        if ($request->wantsJson()) {
            // Add-to-cart buttons live on catalogue pages, where there is no
            // cart markup to refresh: only the header badge needs updating.
            return $this->json([
                'ok' => (bool) $result['ok'],
                'message' => (string) $result['message'],
                'count' => (int) $result['count'],
            ], $result['ok'] ? 200 : 422);
        }

        Session::flash($result['ok'] ? 'success' : 'error', (string) $result['message']);

        return $this->back(route('cart'));
    }

    public function update(Request $request): Response
    {
        $data = $this->validate($request, [
            'plan_id' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:0|max:50',
        ], ['plan_id' => 'Plan', 'quantity' => 'Quantity']);

        // Zero still removes a line; a positive value is normalised to the one
        // subscription that a plan represents.
        $result = CartService::update((int) $data['plan_id'], (int) $data['quantity']);

        return $this->respond((bool) $result['ok'], (string) $result['message'], $request);
    }

    public function remove(Request $request): Response
    {
        $data = $this->validate($request, [
            'plan_id' => 'required|integer|min:1',
        ], ['plan_id' => 'Plan']);

        $planId = (int) $data['plan_id'];
        $wasInCart = false;

        foreach (CartService::raw() as $line) {
            if ((int) $line['plan_id'] === $planId) {
                $wasInCart = true;
            }
        }

        if (!$wasInCart) {
            return $this->respond(false, 'That subscription is not in your selection.', $request);
        }

        CartService::remove($planId);

        return $this->respond(true, 'Subscription removed.', $request);
    }

    public function clear(Request $request): Response
    {
        if (CartService::lines() === []) {
            return $this->respond(false, 'No subscriptions are selected yet.', $request);
        }

        CartService::clear();

        return $this->respond(true, 'Selection cleared.', $request);
    }

    public function applyCoupon(Request $request): Response
    {
        $data = $this->validate($request, [
            'code' => 'required|min:3|max:32|upper',
        ], ['code' => 'Coupon code']);

        $email = CartService::customerEmail();
        $result = CartService::applyCoupon((string) $data['code'], $email === null ? '' : $email);

        return $this->respond((bool) $result['ok'], (string) $result['message'], $request);
    }

    public function removeCoupon(Request $request): Response
    {
        if (CartService::coupon() === '') {
            return $this->respond(false, 'There is no coupon on this selection.', $request);
        }

        CartService::forgetCoupon();

        return $this->respond(true, 'Coupon removed.', $request);
    }

    /**
     * One exit for every mutator, so the JSON shape stays identical and a
     * non-JS post always lands back on real pages with a flash message.
     */
    private function respond(bool $ok, string $message, Request $request): Response
    {
        if ($request->wantsJson()) {
            $payload = [
                'ok' => $ok,
                'message' => $message,
                'count' => CartService::count(),
            ];

            if ($ok) {
                $payload += $this->fragments();

                // A cart that just emptied needs the real empty state (and a
                // disabled checkout button), so let the browser reload the page.
                if (CartService::lines() === []) {
                    $payload['redirect'] = route('cart');
                }
            }

            return $this->json($payload, $ok ? 200 : 422);
        }

        Session::flash($ok ? 'success' : 'error', $message);

        return $this->back(route('cart'));
    }

    /**
     * The three swappable regions of the cart page. They are rendered from the
     * same template files the page itself uses, so a fetch answer can never
     * drift from a full page load.
     *
     * @return array{lines_html: string, total_text: string, coupon_html: string}
     */
    private function fragments(): array
    {
        $lines = CartService::lines();
        $totals = CartService::totals();

        return [
            'lines_html' => view()->html('pages.partials.cart-lines', ['lines' => $lines]),
            'total_text' => view()->html('pages.partials.cart-total', ['totals' => $totals, 'lines' => $lines]),
            'coupon_html' => view()->html('pages.partials.cart-coupon', ['totals' => $totals]),
        ];
    }
}
