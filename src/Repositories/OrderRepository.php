<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class OrderRepository
{
    private const BASE = 'SELECT o.*, u.name AS user_name, u.email AS user_email
        FROM orders o
        JOIN users u ON u.id = o.user_id';

    public static function create(array $order): int
    {
        return Database::insert(
            'INSERT INTO orders (order_number, user_id, subtotal, discount_amount, total_amount, status,
                    payment_method, customer_name, customer_email, customer_phone, ip_address, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            [
                $order['order_number'],
                $order['user_id'],
                $order['subtotal'],
                $order['discount_amount'] ?? 0,
                $order['total_amount'],
                $order['status'] ?? 'pending',
                $order['payment_method'] ?? 'sslcommerz',
                $order['customer_name'],
                $order['customer_email'],
                $order['customer_phone'],
                $order['ip_address'] ?? null,
                (int) $order['pending_ttl_minutes'],
            ]
        );
    }

    public static function addItem(PDO $pdo, int $orderId, array $item): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO order_items (order_id, item_type, product_id, plan_id, item_name, unit_price,
                    quantity, duration_days, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $orderId,
            $item['item_type'],
            $item['product_id'],
            $item['plan_id'],
            $item['item_name'],
            $item['unit_price'],
            $item['quantity'],
            $item['duration_days'],
            $item['line_total'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @return list<array<string,mixed>> */
    public static function items(int $orderId): array
    {
        return Database::select('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC', [$orderId]);
    }

    /** Row lock — used by every code path that changes payment state. */
    public static function lock(PDO $pdo, int $orderId): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $statement->execute([$orderId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public static function findForUser(int $orderId, int $userId): ?array
    {
        return Database::selectOne('SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1', [$orderId, $userId]);
    }

    public static function findByNumberForUser(string $orderNumber, int $userId): ?array
    {
        return Database::selectOne('SELECT * FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1', [$orderNumber, $userId]);
    }

    public static function findForAdmin(int $orderId): ?array
    {
        return Database::selectOne(self::BASE . ' WHERE o.id = ? LIMIT 1', [$orderId]);
    }

    public static function paginateForUser(int $userId, int $page, int $perPage): array
    {
        $total = (int) (Database::selectOne('SELECT COUNT(*) AS c FROM orders WHERE user_id = ?', [$userId])['c'] ?? 0);
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT o.id, o.order_number, o.subtotal, o.total_amount, o.status, o.payment_method,
                    o.created_at, o.paid_at, o.expires_at,
                    (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
             FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC, o.id DESC LIMIT $perPage OFFSET $offset",
            [$userId]
        );

        foreach ($rows as &$row) {
            $row['item_count'] = (int) $row['item_count'];
        }
        unset($row);

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function paginateForAdmin(int $page, int $perPage, string $status = '', string $search = ''): array
    {
        $where = '1=1';
        $bindings = [];

        if ($status !== '') {
            $where .= ' AND o.status = ?';
            $bindings[] = $status;
        }

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $where .= ' AND (o.order_number LIKE ? OR u.email LIKE ? OR u.name LIKE ?)';
            $bindings[] = $like;
            $bindings[] = $like;
            $bindings[] = $like;
        }

        $total = (int) (Database::selectOne(
            'SELECT COUNT(*) AS c FROM orders o JOIN users u ON u.id = o.user_id WHERE ' . $where,
            $bindings
        )['c'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            self::BASE . " WHERE $where ORDER BY o.created_at DESC, o.id DESC LIMIT $perPage OFFSET $offset",
            $bindings
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function setMethod(int $orderId, string $method): void
    {
        Database::statement('UPDATE orders SET payment_method = ? WHERE id = ?', [$method, $orderId]);
    }

    /** @return list<array<string,mixed>> pending orders whose reservation has lapsed */
    public static function stalePending(int $limit = 100): array
    {
        return Database::select(
            "SELECT id, order_number, user_id FROM orders WHERE status = 'pending' AND expires_at < NOW() ORDER BY id ASC LIMIT $limit"
        );
    }

    public static function statistics(): array
    {
        $row = Database::selectOne(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) AS revenue,
                COUNT(*) AS orders,
                COALESCE(SUM(status = 'pending'), 0) AS pending,
                COALESCE(SUM(status = 'paid'), 0) AS paid,
                COALESCE(SUM(status IN ('failed','cancelled')), 0) AS lost,
                COALESCE(AVG(CASE WHEN status = 'paid' THEN total_amount END), 0) AS avg_order
             FROM orders"
        ) ?? [];

        return array_map('floatval', $row);
    }

    public static function revenueSeries(int $days = 14): array
    {
        return Database::select(
            "SELECT DATE(created_at) AS day,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) AS revenue,
                    COUNT(*) AS orders
             FROM orders
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            [$days]
        );
    }
}
