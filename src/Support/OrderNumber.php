<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

final class OrderNumber
{
    public static function generate(): string
    {
        $prefix = 'DXB-' . date('ymd') . '-';

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = $prefix . strtoupper(bin2hex(random_bytes(3)));

            if (Database::selectOne('SELECT 1 AS x FROM orders WHERE order_number = ? LIMIT 1', [$candidate]) === null) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not allocate an order number.');
    }

    public static function transactionId(): string
    {
        // SSLCommerz caps tran_id length and requires it to be unique per attempt.
        return 'DXB' . date('ymdHis') . strtoupper(bin2hex(random_bytes(4)));
    }
}
