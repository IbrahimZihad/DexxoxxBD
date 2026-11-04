<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Data access for carts and cart lines. Snapshots (name/plan/sku/unit price)
 * are stored on the line so a later catalog edit cannot rewrite history.
 */
final class CartRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** @return array<string, mixed>|null */
    public function findForUser(int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM carts WHERE user_id = :uid AND merged_at IS NULL ORDER BY id DESC LIMIT 1',
            ['uid' => $userId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findForToken(string $token): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM carts WHERE guest_token = :token AND merged_at IS NULL ORDER BY id DESC LIMIT 1',
            ['token' => $token]
        );
    }

    /** @return array<string, mixed> */
    public function createForUser(int $userId): array
    {
        $this->db->statement(
            'UPDATE carts SET merged_at = NOW() WHERE user_id = :uid AND merged_at IS NULL',
            ['uid' => $userId]
        );

        $id = $this->db->insert('carts', [
            'user_id' => $userId,
            'guest_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->requireById($id);
    }

    /** @return array<string, mixed> */
    public function createForToken(string $token): array
    {
        $id = $this->db->insert('carts', [
            'user_id' => null,
            'guest_token' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->requireById($id);
    }

    /** @return array<string, mixed> */
    private function requireById(int $id): array
    {
        $cart = $this->db->selectOne('SELECT * FROM carts WHERE id = :id', ['id' => $id]);
        if ($cart === null) {
            throw new \RuntimeException('Cart could not be created.');
        }
        return $cart;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM carts WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findItem(int $cartId, int $variationId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM cart_items WHERE cart_id = :cart AND variation_id = :variation LIMIT 1',
            ['cart' => $cartId, 'variation' => $variationId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findItemById(int $cartId, int $itemId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM cart_items WHERE id = :item AND cart_id = :cart LIMIT 1',
            ['item' => $itemId, 'cart' => $cartId]
        );
    }

    public function upsertItem(
        int $cartId,
        int $productId,
        int $variationId,
        int $quantity,
        float $unitPrice,
        string $productName,
        string $variationLabel,
        string $sku
    ): void {
        $existing = $this->findItem($cartId, $variationId);

        if ($existing !== null) {
            $this->db->statement(
                'UPDATE cart_items
                 SET quantity = :quantity, unit_price = :price, product_name = :name,
                     variation_label = :label, sku = :sku, updated_at = NOW()
                 WHERE id = :id',
                [
                    'quantity' => $quantity,
                    'price' => $unitPrice,
                    'name' => $productName,
                    'label' => $variationLabel,
                    'sku' => $sku,
                    'id' => (int) $existing['id'],
                ]
            );
            return;
        }

        $this->db->insert('cart_items', [
            'cart_id' => $cartId,
            'product_id' => $productId,
            'variation_id' => $variationId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'product_name' => $productName,
            'variation_label' => $variationLabel,
            'sku' => $sku,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function setItemQuantity(int $itemId, int $quantity, float $unitPrice): void
    {
        $this->db->statement(
            'UPDATE cart_items SET quantity = :quantity, unit_price = :price, updated_at = NOW() WHERE id = :id',
            ['quantity' => max(1, $quantity), 'price' => $unitPrice, 'id' => $itemId]
        );
    }

    public function increaseItem(int $itemId, int $by, float $unitPrice): void
    {
        $this->db->statement(
            'UPDATE cart_items SET quantity = quantity + :by, unit_price = :price, updated_at = NOW() WHERE id = :id',
            ['by' => $by, 'price' => $unitPrice, 'id' => $itemId]
        );
    }

    public function swapItemVariation(int $itemId, int $variationId, string $sku, string $label, float $unitPrice): void
    {
        $this->db->statement(
            'UPDATE cart_items
             SET variation_id = :variation, sku = :sku, variation_label = :label,
                 unit_price = :price, updated_at = NOW()
             WHERE id = :id',
            [
                'variation' => $variationId,
                'sku' => $sku,
                'label' => $label,
                'price' => $unitPrice,
                'id' => $itemId,
            ]
        );
    }

    public function deleteItem(int $itemId): int
    {
        return $this->db->statement('DELETE FROM cart_items WHERE id = :id', ['id' => $itemId]);
    }

    public function deleteItemForCart(int $cartId, int $itemId): int
    {
        return $this->db->statement(
            'DELETE FROM cart_items WHERE id = :item AND cart_id = :cart',
            ['item' => $itemId, 'cart' => $cartId]
        );
    }

    public function clearItems(int $cartId): int
    {
        return $this->db->statement('DELETE FROM cart_items WHERE cart_id = :cart', ['cart' => $cartId]);
    }

    /** @return list<array<string, mixed>> */
    public function items(int $cartId): array
    {
        return $this->db->select(
            'SELECT * FROM cart_items WHERE cart_id = :cart ORDER BY created_at ASC, id ASC',
            ['cart' => $cartId]
        );
    }

    public function countUnits(int $cartId): int
    {
        return (int) $this->db->scalar(
            'SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = :cart',
            ['cart' => $cartId]
        );
    }

    public function setCoupon(int $cartId, ?string $code): void
    {
        $this->db->statement(
            'UPDATE carts SET coupon_code = :code, updated_at = NOW() WHERE id = :id',
            ['code' => $code, 'id' => $cartId]
        );
    }

    public function touch(int $cartId): void
    {
        $this->db->statement(
            'UPDATE carts SET updated_at = NOW() WHERE id = :id',
            ['id' => $cartId]
        );
    }

    public function markMerged(int $cartId): void
    {
        $this->db->statement(
            'UPDATE carts SET merged_at = NOW(), user_id = NULL WHERE id = :id',
            ['id' => $cartId]
        );
    }

    /** Abandoned guest carts older than the cutoff (cleanup cron). */
    public function pruneAbandoned(int $days): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

        return $this->db->statement(
            'DELETE FROM carts
             WHERE user_id IS NULL AND merged_at IS NULL AND updated_at < :cutoff
               AND NOT EXISTS (SELECT 1 FROM cart_items ci WHERE ci.cart_id = carts.id)',
            ['cutoff' => $cutoff]
        );
    }

    /** @return list<array<string, mixed>> carts still holding lines to drop */
    public function abandonedWithLines(int $days, int $limit = 200): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

        return $this->db->select(
            'SELECT c.id, c.user_id, c.updated_at, COUNT(ci.id) AS lines, COALESCE(SUM(ci.quantity), 0) AS units
             FROM carts c
             INNER JOIN cart_items ci ON ci.cart_id = c.id
             WHERE c.merged_at IS NULL AND c.updated_at < :cutoff
             GROUP BY c.id
             ORDER BY c.updated_at ASC
             LIMIT ' . max(1, min(1000, $limit)),
            ['cutoff' => $cutoff]
        );
    }
}
