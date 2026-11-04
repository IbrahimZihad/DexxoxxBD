<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Models\ProductVariation;
use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;

/**
 * Cart resolution and totals.
 *
 * Guests get a cart keyed by a random session token (never the raw session id);
 * signed-in customers get a persistent cart that is merged with their guest
 * cart exactly once at login. All money is recomputed from MySQL on every call -
 * the browser never contributes a price.
 */
final class CartService
{
    private const SESSION_TOKEN_KEY = 'cart_token';

    private CartRepository $carts;

    private ProductRepository $products;

    private ProductVariation $variations;

    public function __construct()
    {
        $this->carts = new CartRepository();
        $this->products = new ProductRepository();
        $this->variations = new ProductVariation();
    }

    /** @return array<string, mixed> */
    public function currentCart(bool $create = false): array
    {
        $userId = Auth::instance()->id();

        if ($userId !== null) {
            $cart = $this->carts->findForUser($userId);
            if ($cart === null && $create) {
                $cart = $this->carts->createForUser($userId);
            }
            return $cart ?? [];
        }

        $token = $this->guestToken($create);
        if ($token === null) {
            return [];
        }

        $cart = $this->carts->findForToken($token);
        if ($cart === null && $create) {
            $cart = $this->carts->createForToken($token);
        }

        return $cart ?? [];
    }

    private function guestToken(bool $create): ?string
    {
        $token = Session::get(self::SESSION_TOKEN_KEY);
        if (is_string($token) && preg_match('/^[a-f0-9]{40}$/', $token) === 1) {
            return $token;
        }

        if (!$create) {
            return null;
        }

        $token = bin2hex(random_bytes(20));
        Session::set(self::SESSION_TOKEN_KEY, $token);
        return $token;
    }

    /**
     * @return array{ok: bool, message: string, remaining: ?int}
     */
    public function add(int $productId, int $variationId, int $quantity = 1): array
    {
        $quantity = max(1, $quantity);
        $cart = $this->currentCart(true);
        if ($cart === []) {
            return [
                'ok' => false,
                'message' => 'We could not start a cart for you. Please sign in and retry.',
                'remaining' => null,
            ];
        }

        $product = $this->products->findPublishable($productId);
        if ($product === null) {
            return ['ok' => false, 'message' => 'That product is no longer on sale.', 'remaining' => null];
        }

        $variation = $this->variations->findSellableForProduct($variationId, $productId);
        if ($variation === null) {
            return ['ok' => false, 'message' => 'That plan is not available for this product.', 'remaining' => null];
        }

        $stock = $this->availableStock($variation);
        if ($stock < $quantity) {
            return [
                'ok' => false,
                'message' => $stock <= 0
                    ? 'That plan is out of stock right now. Our team will restock it shortly.'
                    : 'Only ' . $stock . ' seat(s) of that plan are still available.',
                'remaining' => $stock,
            ];
        }

        $maximumPerCart = (int) Config::get('shop.max_per_cart', 5);
        $existing = $this->carts->findItem((int) $cart['id'], $variationId);
        $currentQuantity = (int) ($existing['quantity'] ?? 0);

        if ($currentQuantity + $quantity > $maximumPerCart) {
            return [
                'ok' => false,
                'message' => 'You can hold up to ' . $maximumPerCart . ' of the same plan in one cart.',
                'remaining' => max(0, $maximumPerCart - $currentQuantity),
            ];
        }

        $this->carts->upsertItem(
            (int) $cart['id'],
            $productId,
            $variationId,
            $currentQuantity + $quantity,
            (float) $variation['effective_price'],
            (string) $product['name'],
            (string) ($variation['label'] ?? ''),
            (string) $variation['sku']
        );

        $this->carts->touch((int) $cart['id']);

        return [
            'ok' => true,
            'message' => $product['name'] . ' (' . ($variation['label'] ?? 'Standard') . ') was added to your cart.',
            'remaining' => $stock - ($currentQuantity + $quantity),
        ];
    }

    /** @return array{ok: bool, message: string} */
    public function updateQuantity(int $itemId, int $quantity): array
    {
        $cart = $this->currentCart();
        if ($cart === []) {
            return ['ok' => false, 'message' => 'Your cart is empty.'];
        }

        $item = $this->carts->findItemById((int) $cart['id'], $itemId);
        if ($item === null) {
            return ['ok' => false, 'message' => 'That cart line no longer exists.'];
        }

        if ($quantity < 1) {
            return $this->remove($itemId);
        }

        $variation = $this->variations->find((int) $item['variation_id']);
        if ($variation === null) {
            return ['ok' => false, 'message' => 'That plan is no longer offered.'];
        }

        $stock = $this->availableStock($variation);
        if ($stock < $quantity) {
            return ['ok' => false, 'message' => 'Only ' . $stock . ' of that plan are available.'];
        }

        $this->carts->setItemQuantity($itemId, $quantity, (float) $variation['effective_price']);
        $this->carts->touch((int) $cart['id']);

        return ['ok' => true, 'message' => 'Quantity updated.'];
    }

    /** @return array{ok: bool, message: string} */
    public function switchVariation(int $itemId, int $variationId): array
    {
        $cart = $this->currentCart();
        if ($cart === []) {
            return ['ok' => false, 'message' => 'Your cart is empty.'];
        }

        $item = $this->carts->findItemById((int) $cart['id'], $itemId);
        if ($item === null) {
            return ['ok' => false, 'message' => 'That cart line no longer exists.'];
        }

        $variation = $this->variations->findSellableForProduct($variationId, (int) $item['product_id']);
        if ($variation === null) {
            return ['ok' => false, 'message' => 'That plan is not available for this product.'];
        }

        if ($this->availableStock($variation) < (int) $item['quantity']) {
            return ['ok' => false, 'message' => 'Not enough stock on that plan for the quantity you chose.'];
        }

        $duplicate = $this->carts->findItem((int) $cart['id'], $variationId);
        if ($duplicate !== null && (int) $duplicate['id'] !== $itemId) {
            $this->carts->increaseItem(
                (int) $duplicate['id'],
                (int) $item['quantity'],
                (float) $variation['effective_price']
            );
            $this->carts->deleteItem($itemId);
        } else {
            $this->carts->swapItemVariation(
                $itemId,
                $variationId,
                (string) $variation['sku'],
                (string) ($variation['label'] ?? ''),
                (float) $variation['effective_price']
            );
        }

        $this->carts->touch((int) $cart['id']);
        return ['ok' => true, 'message' => 'Plan changed to ' . ($variation['label'] ?? 'the selected option') . '.'];
    }

    /** @return array{ok: bool, message: string} */
    public function remove(int $itemId): array
    {
        $cart = $this->currentCart();
        if ($cart === []) {
            return ['ok' => false, 'message' => 'Your cart is empty.'];
        }

        $deleted = $this->carts->deleteItemForCart((int) $cart['id'], $itemId);
        $this->carts->touch((int) $cart['id']);

        return $deleted > 0
            ? ['ok' => true, 'message' => 'Removed from your cart.']
            : ['ok' => false, 'message' => 'That item was already removed.'];
    }

    public function clear(): void
    {
        $cart = $this->currentCart();
        if ($cart !== []) {
            $this->carts->clearItems((int) $cart['id']);
            $this->carts->setCoupon((int) $cart['id'], null);
        }
    }

    public function countItems(): int
    {
        $cart = $this->currentCart();
        return $cart === [] ? 0 : $this->carts->countUnits((int) $cart['id']);
    }

    /**
     * Lines with fresh server-side pricing plus stock validity. Any line whose
     * product was unpublished or whose stock ran out is reported separately so
     * the UI can tell the customer rather than silently dropping it.
     *
     * @return array{
     *   lines: list<array<string, mixed>>,
     *   removed: list<string>,
     *   subtotal: float,
     *   item_count: int
     * }
     */
    public function lines(): array
    {
        $cart = $this->currentCart();
        if ($cart === []) {
            return ['lines' => [], 'removed' => [], 'subtotal' => 0.0, 'item_count' => 0];
        }

        $lines = [];
        $removed = [];
        $subtotal = 0.0;
        $unitCount = 0;

        foreach ($this->carts->items((int) $cart['id']) as $item) {
            $product = $this->products->findPublishable((int) $item['product_id']);
            $variation = $this->variations->findSellableForProduct(
                (int) $item['variation_id'],
                (int) $item['product_id']
            );

            if ($product === null || $variation === null) {
                $this->carts->deleteItem((int) $item['id']);
                $removed[] = (string) $item['product_name'];
                continue;
            }

            $available = $this->availableStock($variation);
            if ($available <= 0) {
                $this->carts->deleteItem((int) $item['id']);
                $removed[] = (string) $product['name'] . ' - ' . (string) ($variation['label'] ?? '');
                continue;
            }

            $quantity = min((int) $item['quantity'], $available);
            if ($quantity !== (int) $item['quantity']) {
                $this->carts->setItemQuantity((int) $item['id'], $quantity, (float) $variation['effective_price']);
            }

            $unitPrice = (float) $variation['effective_price'];
            $lineTotal = round($unitPrice * $quantity, 2);

            $lines[] = [
                'id' => (int) $item['id'],
                'product_id' => (int) $item['product_id'],
                'product_slug' => (string) $product['slug'],
                'variation_id' => (int) $item['variation_id'],
                'name' => (string) $product['name'],
                'plan' => (string) ($variation['label'] ?? ''),
                'sku' => (string) $variation['sku'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'delivery_method' => (string) ($variation['delivery_method'] ?? 'manual'),
                'duration_days' => (int) ($variation['duration_days'] ?? 0),
                'image' => (string) ($product['image'] ?? ''),
            ];

            $subtotal += $lineTotal;
            $unitCount += $quantity;
        }

        return [
            'lines' => $lines,
            'removed' => $removed,
            'subtotal' => round($subtotal, 2),
            'item_count' => $unitCount,
        ];
    }

    /**
     * Stock for a variation: license-key inventory when the plan is delivered
     * automatically, otherwise the manually maintained counter.
     */
    public function availableStock(array $variation): int
    {
        $method = (string) ($variation['delivery_method'] ?? 'manual');

        if ($method === 'auto_key' || $method === 'auto_code') {
            $count = (int) db()->scalar(
                'SELECT COUNT(*) FROM digital_inventory
                 WHERE variation_id = :vid AND status = \'available\' AND (expires_at IS NULL OR expires_at > NOW())',
                ['vid' => (int) $variation['id']]
            );
            // Unlimited sentinel: stock -1 means "managed by inventory only".
            if ((int) $variation['stock'] === -1) {
                return $count;
            }
            return min($count, (int) $variation['stock']);
        }

        $stock = (int) ($variation['stock'] ?? 0);
        return $stock === -1 ? 999 : max(0, $stock);
    }

    /**
     * Merge a guest cart into the customer's persistent cart at login.
     * Quantities combine; the guest cart is marked merged so it cannot be
     * merged twice.
     */
    public function mergeGuestCart(int $userId): int
    {
        $token = Session::get(self::SESSION_TOKEN_KEY);
        if (!is_string($token) || !preg_match('/^[a-f0-9]{40}$/', $token)) {
            return 0;
        }

        $guestCart = $this->carts->findForToken($token);
        if ($guestCart === null || ($guestCart['merged_at'] ?? null) !== null) {
            return 0;
        }

        $userCart = $this->carts->findForUser($userId) ?? $this->carts->createForUser($userId);
        $guestItems = $this->carts->items((int) $guestCart['id']);

        if ($guestItems === []) {
            $this->carts->markMerged((int) $guestCart['id']);
            Session::forget(self::SESSION_TOKEN_KEY);
            return 0;
        }

        $moved = 0;
        db()->transaction(function () use ($guestItems, $userCart, $guestCart, &$moved): void {
            foreach ($guestItems as $item) {
                $existing = $this->carts->findItem((int) $userCart['id'], (int) $item['variation_id']);
                if ($existing !== null) {
                    $this->carts->increaseItem(
                        (int) $existing['id'],
                        (int) $item['quantity'],
                        (float) $existing['unit_price']
                    );
                } else {
                    $this->carts->upsertItem(
                        (int) $userCart['id'],
                        (int) $item['product_id'],
                        (int) $item['variation_id'],
                        (int) $item['quantity'],
                        (float) $item['unit_price'],
                        (string) $item['product_name'],
                        (string) $item['variation_label'],
                        (string) $item['sku']
                    );
                }
                $moved++;
            }

            $this->carts->clearItems((int) $guestCart['id']);
            $this->carts->markMerged((int) $guestCart['id']);
        });

        Session::forget(self::SESSION_TOKEN_KEY);
        return $moved;
    }

    public function appliedCoupon(): ?array
    {
        $cart = $this->currentCart();
        if ($cart === [] || empty($cart['coupon_code'])) {
            return null;
        }

        $coupon = db()->selectOne(
            'SELECT * FROM coupons WHERE code = :code AND status = 1 LIMIT 1',
            ['code' => (string) $cart['coupon_code']]
        );

        if ($coupon === null || !CouponService::stillValid($coupon)) {
            $this->carts->setCoupon((int) $cart['id'], null);
            return null;
        }

        return $coupon;
    }

    /** @return array{ok: bool, message: string, discount?: float} */
    public function applyCoupon(string $code): array
    {
        $cart = $this->currentCart(true);
        if ($cart === []) {
            return ['ok' => false, 'message' => 'Add a product before applying a coupon.'];
        }

        $result = App::coupon()->apply($code, $cart);
        if ($result['ok']) {
            $this->carts->setCoupon((int) $cart['id'], strtoupper(trim($code)));
        }

        return $result;
    }

    public function removeCoupon(): void
    {
        $cart = $this->currentCart();
        if ($cart !== []) {
            $this->carts->setCoupon((int) $cart['id'], null);
        }
    }
}
