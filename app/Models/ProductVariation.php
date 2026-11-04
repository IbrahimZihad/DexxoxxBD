<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * One purchasable plan of a product (1 month, 3 months, family seat, ...).
 * `stock` of -1 means "not stock limited" and is used by manual delivery.
 */
final class ProductVariation extends Model
{
    protected string $table = 'product_variations';

    public const DELIVERY_AUTO_KEY = 'auto_key';

    public const DELIVERY_AUTO_CODE = 'auto_code';

    public const DELIVERY_MANUAL = 'manual';

    public const DELIVERY_ACCOUNT = 'account_transfer';

    public const UNLIMITED_STOCK = -1;

    /** @return array<string, mixed>|null */
    public function findSellableForProduct(int $variationId, int $productId): ?array
    {
        return $this->db()->selectOne(
            'SELECT v.*, v.price AS effective_price, p.name AS product_name, p.slug AS product_slug,
                    p.image AS product_image, p.category_id, p.brand_id
             FROM product_variations v
             INNER JOIN products p ON p.id = v.product_id
             WHERE v.id = :vid AND v.product_id = :pid
               AND v.status = 1 AND v.deleted_at IS NULL
               AND p.status = \'published\' AND p.deleted_at IS NULL',
            ['vid' => $variationId, 'pid' => $productId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function listForProduct(int $productId): array
    {
        $rows = $this->db()->select(
            'SELECT v.*, v.price AS effective_price,
                    (SELECT COUNT(*) FROM digital_inventory di
                      WHERE di.variation_id = v.id AND di.status = \'available\'
                        AND (di.expires_at IS NULL OR di.expires_at > NOW())) AS inventory_count
             FROM product_variations v
             WHERE v.product_id = :pid AND v.status = 1 AND v.deleted_at IS NULL
             ORDER BY v.sort_order ASC, v.price ASC, v.id ASC',
            ['pid' => $productId]
        );

        foreach ($rows as &$row) {
            $row['available_stock'] = $this->stockFor($row, (int) $row['inventory_count']);
            $row['percent_off'] = percent_off($row['compare_price'] ?? $row['price'], $row['price']);
            $row['duration_label'] = $this->durationLabel((int) $row['duration_days'], (string) ($row['duration_label'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    /** @param array<string, mixed> $variation */
    public function stockFor(array $variation, ?int $inventoryCount = null): int
    {
        $stock = (int) ($variation['stock'] ?? 0);
        if ($stock === self::UNLIMITED_STOCK) {
            return self::UNLIMITED_STOCK;
        }

        $method = (string) ($variation['delivery_method'] ?? self::DELIVERY_MANUAL);
        if ($inventoryCount === null && in_array($method, [self::DELIVERY_AUTO_KEY, self::DELIVERY_AUTO_CODE], true)) {
            $inventoryCount = (int) $this->db()->scalar(
                'SELECT COUNT(*) FROM digital_inventory
                 WHERE variation_id = :vid AND status = \'available\'
                   AND (expires_at IS NULL OR expires_at > NOW())',
                ['vid' => (int) $variation['id']]
            );
        }

        if ($inventoryCount !== null && in_array($method, [self::DELIVERY_AUTO_KEY, self::DELIVERY_AUTO_CODE], true)) {
            return min($stock, $inventoryCount);
        }

        return max(0, $stock);
    }

    public function durationLabel(int $days, string $stored = ''): string
    {
        if ($stored !== '') {
            return $stored;
        }
        return match (true) {
            $days >= 3650 => 'Lifetime',
            $days >= 365 => round($days / 365, 1) . ' Year' . ($days > 365 ? 's' : ''),
            $days >= 30 => (int) round($days / 30) . ' Month' . ($days > 30 ? 's' : ''),
            $days >= 7 => (int) round($days / 7) . ' Week' . ($days > 7 ? 's' : ''),
            default => $days . ' Day' . ($days === 1 ? '' : 's'),
        };
    }

    /** @return array<string, mixed>|null */
    public function cheapestSellable(int $productId): ?array
    {
        return $this->db()->selectOne(
            'SELECT v.*, v.price AS effective_price FROM product_variations v
             WHERE v.product_id = :pid AND v.status = 1 AND v.deleted_at IS NULL
               AND (v.stock > 0 OR v.stock = -1)
             ORDER BY v.price ASC LIMIT 1',
            ['pid' => $productId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function allForAdmin(int $productId): array
    {
        return $this->db()->select(
            'SELECT v.*,
                    (SELECT COUNT(*) FROM digital_inventory di
                      WHERE di.variation_id = v.id AND di.status = \'available\') AS inventory_available,
                    (SELECT COUNT(*) FROM digital_inventory di
                      WHERE di.variation_id = v.id AND di.status = \'assigned\') AS inventory_assigned
             FROM product_variations v
             WHERE v.product_id = :pid
             ORDER BY v.deleted_at IS NULL DESC, v.sort_order ASC, v.id ASC',
            ['pid' => $productId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function lowStock(int $threshold = 5, int $limit = 20): array
    {
        return $this->db->select(
            'SELECT v.id, v.sku, v.label, v.stock, p.name AS product_name, p.slug AS product_slug
             FROM product_variations v
             INNER JOIN products p ON p.id = v.product_id
             WHERE v.deleted_at IS NULL AND v.status = 1 AND p.deleted_at IS NULL
               AND v.stock BETWEEN 0 AND ' . max(0, (int) $threshold) . '
             ORDER BY v.stock ASC, p.name ASC
             LIMIT ' . max(1, min(200, $limit))
        );
    }

    public function releaseStock(int $variationId, int $quantity): void
    {
        $this->db()->statement(
            'UPDATE product_variations
             SET stock = IF(stock = -1, -1, stock + :quantity), updated_at = NOW()
             WHERE id = :id AND stock <> -1',
            ['quantity' => $quantity, 'id' => $variationId]
        );
    }

    /**
     * Atomically reserve stock inside a transaction. Returns false when the
     * variation cannot cover the requested quantity, so checkout can abort.
     */
    public function reserveStock(int $variationId, int $quantity): bool
    {
        $affected = $this->db->statement(
            'UPDATE product_variations
             SET stock = stock - :quantity, updated_at = NOW()
             WHERE id = :id AND stock <> -1 AND stock >= :quantity',
            ['quantity' => $quantity, 'id' => $variationId]
        );

        if ($affected === 1) {
            return true;
        }

        // Unlimited-stock plans never decrement but must still be validated.
        $unlimited = (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM product_variations WHERE id = :id AND stock = -1',
            ['id' => $variationId]
        );

        return $unlimited === 1;
    }

    public function incrementSales(int $variationId, int $quantity): void
    {
        $this->db()->statement(
            'UPDATE product_variations SET sales_count = sales_count + :quantity WHERE id = :id',
            ['quantity' => $quantity, 'id' => $variationId]
        );
    }
}
