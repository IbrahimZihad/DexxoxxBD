<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class ManualPaymentRepository
{
    public static function submit(array $data): int
    {
        return Database::insert(
            'INSERT INTO manual_payments (order_id, payment_method, sender_number, transaction_id, amount, note, status)
             VALUES (?, ?, ?, ?, ?, ?, \'pending\')',
            [
                $data['order_id'],
                $data['payment_method'],
                $data['sender_number'] ?? null,
                $data['transaction_id'],
                $data['amount'],
                $data['note'] ?? null,
            ]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM manual_payments WHERE id = ? LIMIT 1', [$id]);
    }

    public static function pendingForOrder(int $orderId): ?array
    {
        return Database::selectOne('SELECT id FROM manual_payments WHERE order_id = ? AND status = \'pending\' LIMIT 1', [$orderId]);
    }

    public static function latestForOrder(int $orderId): ?array
    {
        return Database::selectOne('SELECT * FROM manual_payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$orderId]);
    }

    /** Re-using one transaction id across orders is the classic double-claim trick. */
    public static function transactionIdTaken(string $transactionId, int $method): bool
    {
        return Database::selectOne(
            'SELECT id FROM manual_payments WHERE transaction_id = ? AND payment_method = ? LIMIT 1',
            [$transactionId, $method]
        ) !== null;
    }

    public static function paginate(int $page, int $perPage, string $status = ''): array
    {
        $where = '1=1';
        $bindings = [];

        if ($status !== '') {
            $where .= ' AND mp.status = ?';
            $bindings[] = $status;
        }

        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = (int) (Database::selectOne("SELECT COUNT(*) AS c FROM manual_payments mp WHERE $where", $bindings)['c'] ?? 0);

        $rows = Database::select(
            "SELECT mp.*, o.order_number, o.total_amount, o.status AS order_status, o.customer_phone,
                    u.id AS user_id, u.name AS user_name, u.email AS user_email,
                    ru.name AS reviewed_by_name
             FROM manual_payments mp
             JOIN orders o ON o.id = mp.order_id
             JOIN users u ON u.id = o.user_id
             LEFT JOIN users ru ON ru.id = mp.reviewed_by
             WHERE $where
             ORDER BY FIELD(mp.status, 'pending', 'approved', 'rejected'), mp.created_at DESC, mp.id DESC
             LIMIT $perPage OFFSET $offset",
            $bindings
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function markReviewed(int $id, string $status, ?string $adminNote, int $adminId): int
    {
        return Database::statement(
            'UPDATE manual_payments SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ? AND status = \'pending\'',
            [$status, $adminNote, $adminId, $id]
        );
    }

    public static function counts(): array
    {
        $row = Database::selectOne(
            "SELECT COALESCE(SUM(status = 'pending'), 0) AS pending,
                    COALESCE(SUM(status = 'approved'), 0) AS approved,
                    COALESCE(SUM(status = 'rejected'), 0) AS rejected
             FROM manual_payments"
        ) ?? [];

        return array_map('intval', $row);
    }
}
