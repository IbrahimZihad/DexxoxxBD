<?php

declare(strict_types=1);

namespace Tests\Mysql;

use App\Core\Database;
use App\Services\OrderService;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * The single most important invariant in the shop: a customer can never make
 * their own order paid. Only the staff verification step may write payment_status
 * = paid, and these tests prove it against the real MySQL schema.
 */
#[CoversClass(OrderService::class)]
final class PaymentStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->deleteOrdersMatching('TEST-');

        parent::tearDown();
    }

    public function test_an_order_starts_unpaid(): void
    {
        $id = $this->makeOrder();

        $order = $this->order($id);

        self::assertNotNull($order);
        self::assertSame('unpaid', $order['payment_status']);
        self::assertSame('pending', $order['status']);
        self::assertNull($order['verified_by']);
        self::assertNull($order['paid_at']);
    }

    public function test_declaring_a_payment_moves_the_order_to_pending_verification_only(): void
    {
        $id = $this->makeOrder();

        $result = OrderService::recordPayment($id, 'bkash', 'TEST' . random_int(100000, 999999), 1290.00, '01712345678');

        self::assertTrue($result['ok'], $result['message']);

        $order = $this->order($id);
        $payment = Database::selectOne('select * from payments where order_id = :id order by id desc limit 1', [':id' => $id]);

        self::assertSame('pending_verification', $order['payment_status']);
        self::assertNotSame('paid', $order['payment_status']);
        self::assertNull($order['verified_by']);
        self::assertNull($order['paid_at']);

        self::assertNotNull($payment);
        self::assertSame('pending', $payment['status']);
        self::assertNull($payment['verified_by']);
    }

    public function test_verification_is_the_only_way_an_order_becomes_paid(): void
    {
        $id = $this->makeOrder();
        $staff = $this->staffUser('admin');

        OrderService::recordPayment($id, 'nagad', 'TEST' . random_int(100000, 999999), 1290.00);

        $result = OrderService::verifyPayment($id, $staff, 'Checked in the bKash app.');

        self::assertTrue($result['ok'], $result['message']);

        $order = $this->order($id);

        self::assertSame('paid', $order['payment_status']);
        self::assertSame('processing', $order['status']);
        self::assertSame((string) $staff, (string) $order['verified_by']);
        self::assertNotNull($order['paid_at']);

        $payment = Database::selectOne('select * from payments where order_id = :id order by id desc limit 1', [':id' => $id]);

        self::assertSame('verified', $payment['status']);
        self::assertSame((string) $staff, (string) $payment['verified_by']);
    }

    public function test_there_is_nothing_to_verify_until_the_customer_declares(): void
    {
        $id = $this->makeOrder();

        $result = OrderService::verifyPayment($id, $this->staffUser('admin'));

        self::assertFalse($result['ok']);
        self::assertSame('unpaid', $this->order($id)['payment_status']);
    }

    public function test_a_second_declaration_on_a_paid_order_is_refused(): void
    {
        $id = $this->makeOrder();
        $staff = $this->staffUser('admin');

        OrderService::recordPayment($id, 'bkash', 'TEST' . random_int(100000, 999999), 1290.00);
        OrderService::verifyPayment($id, $staff);

        $again = OrderService::recordPayment($id, 'bkash', 'TEST' . random_int(100000, 999999), 1290.00);

        self::assertFalse($again['ok']);
        self::assertSame('paid', $this->order($id)['payment_status']);
        self::assertSame(1, (int) Database::scalar('select count(*) from payments where order_id = :id', [':id' => $id]));
    }

    public function test_a_declared_amount_can_never_change_the_total_due(): void
    {
        $id = $this->makeOrder(['total' => 4500.00, 'subtotal' => 4500.00]);

        OrderService::recordPayment($id, 'bkash', 'TEST' . random_int(100000, 999999), 1.00);

        $order = $this->order($id);

        // The mismatch is recorded for the staff to see, it is never accepted.
        self::assertSame('4500.00', (string) $order['total']);
        self::assertSame('pending_verification', $order['payment_status']);
    }

    public function test_a_duplicate_transaction_id_is_refused_across_methods_and_orders(): void
    {
        $first = $this->makeOrder();
        $second = $this->makeOrder();
        $txn = 'TESTDUP' . random_int(10000, 99999);

        self::assertTrue(OrderService::recordPayment($first, 'bkash', $txn, 1290.00)['ok']);

        // Same method, same number: a second order cannot claim the same payment.
        $clash = OrderService::recordPayment($second, 'bkash', $txn, 1290.00);

        self::assertFalse($clash['ok']);
        self::assertStringContainsString('already attached', $clash['message']);
        self::assertSame('unpaid', $this->order($second)['payment_status']);
        self::assertSame(0, (int) Database::scalar('select count(*) from payments where order_id = :id', [':id' => $second]));

        // Lowercase, padding and a stray space are the same receipt to a human.
        self::assertFalse(OrderService::recordPayment($second, 'bkash', '  ' . strtolower($txn) . ' ', 1290.00)['ok']);
    }

    public function test_the_same_transaction_id_is_allowed_on_a_different_rail(): void
    {
        $bkash = $this->makeOrder();
        $nagad = $this->makeOrder();
        $txn = 'TESTXRAIL' . random_int(10000, 99999);

        // bKash and Nagad numbers live in separate ledgers, so an identical
        // string from two different rails is not a duplicate claim.
        self::assertTrue(OrderService::recordPayment($bkash, 'bkash', $txn, 1290.00)['ok']);
        self::assertTrue(OrderService::recordPayment($nagad, 'nagad', $txn, 1290.00)['ok']);
    }

    public function test_a_blank_transaction_id_is_allowed_but_never_paid(): void
    {
        $id = $this->makeOrder();

        self::assertTrue(OrderService::recordPayment($id, 'bank', '', 1290.00)['ok']);

        $order = $this->order($id);

        self::assertSame('pending_verification', $order['payment_status']);
        self::assertNull(Database::selectOne('select txn_id from payments where order_id = :id', [':id' => $id])['txn_id']);
    }

    public function test_payment_for_an_unknown_order_is_refused(): void
    {
        $result = OrderService::recordPayment(99999999, 'bkash', 'TESTNOPE', 10.0);

        self::assertFalse($result['ok']);
    }

    public function test_rejecting_a_payment_returns_the_order_to_unpaid(): void
    {
        $id = $this->makeOrder();
        $staff = $this->staffUser('staff');

        OrderService::recordPayment($id, 'bkash', 'TEST' . random_int(100000, 999999), 1290.00);

        $result = OrderService::rejectPayment($id, $staff, 'Number does not match our statement.');

        self::assertTrue($result['ok'], $result['message']);
        self::assertSame('unpaid', $this->order($id)['payment_status']);

        // The customer can then declare it correctly.
        self::assertTrue(OrderService::recordPayment($id, 'bkash', 'TEST' . random_int(100000, 999999), 1290.00)['ok']);
        self::assertSame('pending_verification', $this->order($id)['payment_status']);
    }

    public function test_every_transition_is_written_to_the_history_table(): void
    {
        $id = $this->makeOrder();

        OrderService::recordPayment($id, 'bkash', 'TEST' . random_int(100000, 999999), 1290.00);
        OrderService::verifyPayment($id, $this->staffUser('admin'));

        $statuses = array_map(
            static fn (array $row): string => (string) $row['status'],
            Database::select('select status from order_status_history where order_id = :id order by id', [':id' => $id])
        );

        self::assertContains('pending_verification', $statuses);
        self::assertContains('paid', $statuses);
    }
}
