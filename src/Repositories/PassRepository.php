<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PassRepository
{
    /** Ambiguous characters (0/O, 1/I) are excluded so codes survive phone support. */
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public static function generateCode(): string
    {
        $blocks = [];

        for ($block = 0; $block < 2; $block++) {
            $chars = '';

            for ($i = 0; $i < 4; $i++) {
                $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $blocks[] = $chars;
        }

        return 'DXB-' . implode('-', $blocks);
    }

    public static function insert(PDO $pdo, array $pass): ?string
    {
        $statement = $pdo->prepare(
            'INSERT INTO user_subscriptions (user_id, order_item_id, item_type, item_name, pass_code, starts_at, ends_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'active\')'
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = self::generateCode();

            try {
                $statement->execute([
                    $pass['user_id'],
                    $pass['order_item_id'],
                    $pass['item_type'],
                    $pass['item_name'],
                    $code,
                    $pass['starts_at'],
                    $pass['ends_at'],
                ]);

                return $code;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        return null;
    }

    public static function listForUser(int $userId, bool $includeExpired = true): array
    {
        $sql = 'SELECT id, item_type, item_name, pass_code, starts_at, ends_at, status, created_at, order_item_id
                FROM user_subscriptions WHERE user_id = ?';

        if (!$includeExpired) {
            $sql .= " AND status = 'active'";
        }

        return Database::select($sql . ' ORDER BY status = \'active\' DESC, ends_at DESC, id DESC', [$userId]);
    }

    public static function listForOrderItem(int $orderItemId): array
    {
        return Database::select(
            'SELECT id, item_type, item_name, pass_code, starts_at, ends_at, status
             FROM user_subscriptions WHERE order_item_id = ? ORDER BY id ASC',
            [$orderItemId]
        );
    }

    public static function expireDue(?int $userId = null): int
    {
        $sql = "UPDATE user_subscriptions SET status = 'expired' WHERE status = 'active' AND ends_at < CURDATE()";
        $bindings = [];

        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $bindings[] = $userId;
        }

        return Database::statement($sql, $bindings);
    }

    public static function revoke(int $id, int $adminId): int
    {
        return Database::statement(
            "UPDATE user_subscriptions SET status = 'revoked', revoked_by = ?, revoked_at = NOW()
             WHERE id = ? AND status <> 'revoked'",
            [$adminId, $id]
        );
    }

    public static function counts(): array
    {
        $row = Database::selectOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'active' AND ends_at >= CURDATE()), 0) AS active,
                    COALESCE(SUM(status = 'expired'), 0) AS expired,
                    COALESCE(SUM(status = 'revoked'), 0) AS revoked
             FROM user_subscriptions"
        ) ?? [];

        return array_map('intval', $row);
    }

    public static function paginateForAdmin(int $page, int $perPage, string $status = ''): array
    {
        $where = '1=1';
        $bindings = [];

        if ($status === 'expiring') {
            // A UI bucket, resolved in SQL so paging stays truthful.
            $where .= " AND us.status = 'active' AND us.ends_at <= (CURDATE() + INTERVAL 7 DAY)";
        } elseif ($status !== '') {
            $where .= ' AND us.status = ?';
            $bindings[] = $status;
        }

        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = (int) (Database::selectOne("SELECT COUNT(*) AS c FROM user_subscriptions us WHERE $where", $bindings)['c'] ?? 0);
        $rows = Database::select(
            "SELECT us.id, us.item_type, us.item_name, us.pass_code, us.starts_at, us.ends_at, us.status, us.created_at,
                    u.id AS user_id, u.name AS user_name, u.email AS user_email
             FROM user_subscriptions us
             JOIN users u ON u.id = us.user_id
             WHERE $where
             ORDER BY us.created_at DESC, us.id DESC LIMIT $perPage OFFSET $offset",
            $bindings
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }
}
