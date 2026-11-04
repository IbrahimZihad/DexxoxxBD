<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Core\Config;
use App\Exceptions\HttpException;
use App\Http\Controller;
use App\Http\Response;
use App\Repositories\CartRepository;
use App\Repositories\PlanRepository;
use App\Repositories\ProductRepository;

final class CartController extends Controller
{
    public function index(): Response
    {
        return $this->json($this->snapshot());
    }

    public function store(): Response
    {
        $user = Auth::requireUser();
        $data = $this->validate([
            'type' => 'required|in:product,plan',
            'id' => 'required|integer|min:1',
            'quantity' => 'nullable|integer|min:1|max:' . (int) Config::get('orders.max_quantity_per_item', 20),
        ]);

        $type = (string) $data['type'];
        $id = (int) $data['id'];
        $quantity = (int) ($data['quantity'] ?? 1);

        $this->assertPurchasable($type, $id, $quantity);

        if (CartRepository::countItems((int) $user['id']) + $quantity > (int) Config::get('orders.max_cart_items', 50)) {
            throw new HttpException(422, 'Your cart cannot hold more than 50 items.');
        }

        $existing = CartRepository::existing((int) $user['id'], $type, $id);

        if ($existing !== null) {
            $target = (int) $existing['quantity'] + $quantity;
            $this->assertPurchasable($type, $id, $target);
            CartRepository::bump((int) $existing['id'], $target);
        } else {
            CartRepository::add((int) $user['id'], $type, $id, $quantity);
        }

        return $this->json($this->snapshot(), 201);
    }

    public function update(): Response
    {
        $user = Auth::requireUser();
        $cartItemId = $this->idFromQuery();
        $data = $this->validate(['quantity' => 'required|integer|min:1|max:' . (int) Config::get('orders.max_quantity_per_item', 20)]);

        $row = CartRepository::find((int) $user['id'], $cartItemId);

        if ($row === null) {
            throw new HttpException(404, 'That cart item does not exist.');
        }

        $this->assertPurchasable((string) $row['item_type'], (int) ($row['product_id'] ?? $row['plan_id']), (int) $data['quantity']);
        CartRepository::bump($cartItemId, (int) $data['quantity']);

        return $this->json($this->snapshot());
    }

    public function destroy(): Response
    {
        $user = Auth::requireUser();
        $cartItemId = $this->idFromQuery();

        if ($cartItemId > 0) {
            if (CartRepository::remove((int) $user['id'], $cartItemId) !== 1) {
                throw new HttpException(404, 'That cart item does not exist.');
            }
        } else {
            CartRepository::clear((int) $user['id']);
        }

        return $this->json($this->snapshot());
    }

    private function assertPurchasable(string $type, int $id, int $quantity): void
    {
        if ($type === 'product') {
            $product = ProductRepository::find($id, true);

            if ($product === null) {
                throw new HttpException(404, 'This item is not available.');
            }

            if ((int) $product['stock'] < $quantity) {
                throw new HttpException(422, sprintf('Only %s of %s left in stock.', $product['stock'], $product['name']));
            }

            return;
        }

        if (PlanRepository::find($id, true) === null) {
            throw new HttpException(404, 'This pass is not available.');
        }
    }

    /** @return array<string,mixed> */
    private function snapshot(): array
    {
        $user = Auth::requireUser();
        $totals = CartRepository::totals((int) $user['id']);

        return [
            'items' => $totals['items'],
            'subtotal' => $totals['subtotal'],
            'total' => $totals['subtotal'],
            'count' => $totals['count'],
        ];
    }
}
