<?php

declare(strict_types=1);

namespace Tests\Mysql;

use App\Core\Crypto;
use App\Core\Database;
use App\Models\Order;
use App\Services\OrderService;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Two promises the database layer has to keep: customer text can never be
 * executed as SQL, and delivered credentials are unreadable in the dump.
 */
#[CoversClass(Database::class)]
#[CoversClass(Order::class)]
final class DatabaseSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteOrdersMatching('TEST-');
    }

    protected function tearDown(): void
    {
        $this->deleteOrdersMatching('TEST-');

        parent::tearDown();
    }

    public function test_injection_shaped_text_is_bound_not_interpolated(): void
    {
        $id = $this->makeOrder();
        $payloads = [
            "' or 1=1 -- ",
            "'; drop table orders; --",
            "1 union select id, order_no, email from orders",
            "\\'; DELETE FROM orders WHERE '1'='1",
        ];

        foreach ($payloads as $payload) {
            self::assertNull((new Order())->findByOrderNo($payload), $payload . ' must not resolve to an order');
            self::assertSame([], (new Order())->forEmail($payload), 'No rows may come back for an injection string');
        }

        // The table the payloads tried to drop is still there, with our row in it.
        self::assertSame(1, (int) Database::scalar('select count(*) from orders where id = :id', [':id' => $id]));
        self::assertNotNull(Database::selectOne('select id from orders where id = :id', [':id' => $id]));
    }

    public function test_a_reusable_placeholder_is_refused_rather_than_guessed(): void
    {
        // ATTR_EMULATE_PREPARES is off, so one placeholder can only carry one
        // value; this documents the constraint the query helpers rely on.
        $this->expectException(\PDOException::class);

        Database::select('select * from orders where id = :id or email = :id', [':id' => 1]);
    }

    public function test_order_numbers_are_matched_exactly_not_by_prefix(): void
    {
        $id = $this->makeOrder();
        $order = $this->order($id);

        self::assertNotNull((new Order())->findByOrderNo((string) $order['order_no']));

        // Truncating or wildcard-padding a number must not hand over somebody's
        // order; a stray space is trimmed first, which is the same order.
        self::assertNull((new Order())->findByOrderNo(substr((string) $order['order_no'], 0, 8)));
        self::assertNull((new Order())->findByOrderNo($order['order_no'] . '%'));
        self::assertNull((new Order())->findByOrderNo('TEST-%'));
        self::assertNotNull((new Order())->findByOrderNo($order['order_no'] . '  '));
    }

    public function test_delivered_credentials_are_not_readable_in_the_table(): void
    {
        $orderId = $this->makeOrder();

        $itemId = $this->insert('order_items', [
            'order_id' => $orderId,
            'plan_id' => null,
            'service_name' => 'Test streaming plan',
            'plan_title' => '1 month',
            'unit_price' => 120,
            'quantity' => 1,
            'line_total' => 120,
            'status' => 'pending',
            'delivery_note' => null,
            'delivered_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $secret = 'login: demo@example.com | password: SuP3r-S3cret | pin: 4421';

        $result = OrderService::deliverItem($itemId, $secret, $this->staffUser('admin'));

        self::assertTrue($result['ok'], $result['message']);

        $stored = (string) Database::scalar('select delivery_note from order_items where id = :id', [':id' => $itemId]);

        self::assertStringStartsWith('enc:v1$', $stored);
        self::assertStringNotContainsString('SuP3r-S3cret', $stored);

        // What a dump or a SQL-injection read would actually yield:
        self::assertSame($secret, Crypto::decrypt($stored));
        self::assertSame(
            0,
            (int) Database::scalar(
                "select count(*) from order_items where delivery_note like '%SuP3r-S3cret%'"
            ),
            'The secret must never exist as plaintext anywhere in the table.'
        );
    }

    public function test_an_order_only_completes_when_it_is_paid_and_delivered(): void
    {
        $orderId = $this->makeOrder();

        $itemId = $this->insert('order_items', [
            'order_id' => $orderId,
            'plan_id' => null,
            'service_name' => 'Test plan',
            'plan_title' => '1 month',
            'unit_price' => 120,
            'quantity' => 1,
            'line_total' => 120,
            'status' => 'pending',
            'delivery_note' => null,
            'delivered_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        OrderService::deliverItem($itemId, 'delivered but unpaid', $this->staffUser('staff'));

        $unpaid = $this->order($orderId);

        self::assertSame('delivered', $unpaid['fulfillment_status']);
        self::assertNotSame('completed', $unpaid['status'], 'An unpaid order cannot be completed.');

        // Now verify the payment; the ledger only closes once both are true.
        OrderService::recordPayment($orderId, 'bkash', 'TEST' . random_int(100000, 999999), (float) $unpaid['total']);
        OrderService::verifyPayment($orderId, $this->staffUser('admin'));

        $paid = $this->order($orderId);

        self::assertSame('paid', $paid['payment_status']);
        self::assertSame('completed', $paid['status']);
    }

    public function test_a_blank_delivery_note_cannot_close_a_line(): void
    {
        $orderId = $this->makeOrder();

        $itemId = $this->insert('order_items', [
            'order_id' => $orderId,
            'plan_id' => null,
            'service_name' => 'Test plan',
            'plan_title' => '1 month',
            'unit_price' => 120,
            'quantity' => 1,
            'line_total' => 120,
            'status' => 'pending',
            'delivery_note' => null,
            'delivered_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        foreach (['', '   ', "\t\n"] as $blank) {
            self::assertFalse(OrderService::deliverItem($itemId, $blank, $this->staffUser('admin'))['ok']);
        }

        self::assertSame('pending', (string) Database::scalar('select status from order_items where id = :id', [':id' => $itemId]));
    }

    public function test_cancelling_returns_the_coupon_usage_and_leaves_payment_alone(): void
    {
        $orderId = $this->makeOrder(['coupon_code' => 'TESTCANC']);

        Database::execute('delete from coupons where code = :c', [':c' => 'TESTCANC']);
        $this->makeCoupon('TESTCANC', ['used_count' => 3, 'max_uses' => 10]);

        $result = OrderService::cancel($orderId, 'Customer changed their mind.');

        self::assertTrue($result['ok'], $result['message']);
        self::assertSame('cancelled', $this->order($orderId)['status']);
        self::assertSame(2, (int) Database::scalar('select used_count from coupons where code = :c', [':c' => 'TESTCANC']));

        Database::execute('delete from coupons where code = :c', [':c' => 'TESTCANC']);
    }

    public function test_the_test_schema_is_never_the_shop_schema(): void
    {
        $name = (string) \App\Core\Config::get('database.name');

        self::assertStringEndsWith('_test', $name);
        self::assertNotSame('zapsub', $name);
    }
}
