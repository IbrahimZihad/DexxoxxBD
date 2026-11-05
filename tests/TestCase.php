<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Config;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Request;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;

/**
 * Shared fixture helpers for the suite.
 *
 * Everything here writes into the *_test schema only (tests/bootstrap.php pins
 * APP_ENV=testing) and every fixture row is marked TEST- so a run can clean up
 * after itself without touching anything else in that database.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!str_ends_with((string) Config::get('database.name', ''), '_test')) {
            $this->fail('Tests must run against a *_test database, not ' . Config::get('database.name'));
        }
    }

    /** @param array<string, mixed> $row */
    protected function insert(string $table, array $row): int
    {
        $binds = [];
        $columns = [];

        foreach ($row as $column => $value) {
            $columns[] = $column;
            $binds[':' . $column] = $value;
        }

        Database::run(
            'insert into ' . $table . ' (' . implode(', ', $columns) . ') values ('
            . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $binds
        );

        return Database::lastInsertId();
    }

    /** @return array<string, mixed>|null */
    protected function order(string|int $idOrOrderNo): ?array
    {
        return is_int($idOrOrderNo)
            ? Database::selectOne('select * from orders where id = :id', [':id' => $idOrOrderNo])
            : Database::selectOne('select * from orders where order_no = :no', [':no' => $idOrOrderNo]);
    }

    protected function staffUser(string $role = 'admin'): int
    {
        $email = 'test-' . $role . '-' . uniqid() . '@zapsub.test';

        return $this->insert('users', [
            'name' => 'Test ' . ucfirst($role),
            'email' => $email,
            'phone' => '01712345678',
            'password_hash' => password_hash('a-long-enough-passphrase', PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => 1,
            'marketing_opt_in' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * An order row on its own, without the cart service that normally builds it.
     *
     * @param array<string, mixed> $overrides
     */
    protected function makeOrder(array $overrides = []): int
    {
        $orderNo = 'TEST-' . strtoupper(bin2hex(random_bytes(5)));
        $total = (float) ($overrides['total'] ?? 1290.0);

        $id = $this->insert('orders', array_merge([
            'order_no' => $orderNo,
            'user_id' => null,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unassigned',
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'coupon_code' => null,
            'customer_name' => 'Test Customer',
            'email' => 'test-' . substr($orderNo, 5) . '@example.com',
            'phone' => '01712345678',
            'note' => null,
            'delivery_request' => Crypto::encrypt('test fixture'),
            'ip_address' => '127.0.0.1',
            'verified_by' => null,
            'paid_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));

        return $id;
    }

    /**
     * A coupon row. Type is percent or fixed; 0 means unlimited for the caps.
     *
     * @param array<string, mixed> $overrides
     */
    protected function makeCoupon(string $code, array $overrides = []): int
    {
        return $this->insert('coupons', array_merge([
            'code' => $code,
            'type' => 'percent',
            'value' => 10,
            'min_order' => 0,
            'max_uses' => 0,
            'used_count' => 0,
            'per_customer_limit' => 0,
            'expires_at' => null,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    protected function deleteOrdersMatching(string $prefix): void
    {
        Database::execute(
            'delete from orders where order_no like :prefix',
            [':prefix' => $prefix . '%']
        );
    }

    /** @param array<string, mixed> $body @param array<string, string> $headers */
    protected function request(
        string $method = 'GET',
        string $path = 'admin/orders',
        array $body = [],
        array $headers = [],
        string $ip = '203.0.113.9'
    ): Request {
        return new Request($method, $path, [], $body, $headers, $ip, '/DexxoxxBD/public', 'localhost', false);
    }

    /**
     * Pretend to be signed in without going through Auth::login(), which needs a
     * real session and would rotate cookies on every call.
     */
    protected function signIn(?int $userId): void
    {
        if ($userId === null) {
            unset($_SESSION['user_id']);
        } else {
            $_SESSION['user_id'] = $userId;
        }

        // Auth memoises the user for the whole request; the test process is one
        // long "request", so the memo has to be dropped between cases.
        $reflection = new ReflectionClass(\App\Core\Auth::class);

        foreach (['cachedUser' => null, 'resolved' => false] as $property => $value) {
            $member = $reflection->getProperty($property);
            $member->setValue(null, $value);
        }
    }
}
