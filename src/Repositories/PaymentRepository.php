<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class PaymentRepository
{
    public static function initiate(int $orderId, string $tranId, float $amount, string $currency): int
    {
        return Database::insert(
            "INSERT INTO payments (order_id, tran_id, amount, currency, status) VALUES (?, ?, ?, ?, 'initiated')",
            [$orderId, $tranId, $amount, $currency]
        );
    }

    public static function findByTranId(string $tranId): ?array
    {
        return Database::selectOne('SELECT * FROM payments WHERE tran_id = ? LIMIT 1', [$tranId]);
    }

    public static function find(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM payments WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * Records the gateway's own verdict. The raw payload is stored for audit but
     * is never echoed back to a browser.
     */
    public static function markValid(int $id, array $gateway): void
    {
        Database::statement(
            "UPDATE payments
             SET status = 'valid', val_id = ?, card_type = ?, bank_tran_id = ?, store_id = ?,
                 gateway_payload = ?, validated_at = NOW(), failure_reason = NULL
             WHERE id = ?",
            [
                $gateway['val_id'] ?? null,
                $gateway['card_type'] ?? null,
                $gateway['bank_tran_id'] ?? null,
                $gateway['store_id'] ?? null,
                json_encode($gateway, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $id,
            ]
        );
    }

    public static function markFailed(int $id, string $reason, array $gateway = []): void
    {
        Database::statement(
            'UPDATE payments SET status = \'failed\', failure_reason = ?, gateway_payload = ? WHERE id = ?',
            [mb_substr($reason, 0, 190), json_encode($gateway, JSON_UNESCAPED_UNICODE) ?: null, $id]
        );
    }

    public static function markCancelled(int $id): void
    {
        Database::statement("UPDATE payments SET status = 'cancelled' WHERE id = ?", [$id]);
    }

    public static function listForOrder(int $orderId): array
    {
        return Database::select(
            'SELECT id, tran_id, amount, currency, status, card_type, bank_tran_id, created_at, validated_at
             FROM payments WHERE order_id = ? ORDER BY id DESC',
            [$orderId]
        );
    }

    public static function pendingCount(): int
    {
        return (int) (Database::selectOne("SELECT COUNT(*) AS c FROM payments WHERE status = 'initiated'")['c'] ?? 0);
    }
}
