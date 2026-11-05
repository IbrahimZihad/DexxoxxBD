<?php

declare(strict_types=1);

namespace Tests\Mysql;

use App\Core\Database;
use App\Services\CouponService;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Coupon maths is single-sourced in CouponService::evaluate(); the cart, the
 * checkout page and the order writer all call it, so these tests cover the whole
 * shop by covering this one function.
 */
#[CoversClass(CouponService::class)]
final class CouponEvaluationTest extends TestCase
{
    private const CODE = 'TESTPCT';

    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteOrdersMatching('TEST-');
        Database::execute('delete from coupons where code like :prefix', [':prefix' => 'TEST%']);
    }

    protected function tearDown(): void
    {
        Database::execute('delete from coupons where code like :prefix', [':prefix' => 'TEST%']);
        $this->deleteOrdersMatching('TEST-');

        parent::tearDown();
    }

    public function test_a_percent_coupon_takes_its_share_of_the_subtotal(): void
    {
        $this->makeCoupon(self::CODE, ['type' => 'percent', 'value' => 15]);

        $result = CouponService::evaluate(self::CODE, 1000.0);

        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(150.0, $result['discount']);
        self::assertSame(self::CODE, $result['code']);
    }

    public function test_a_fixed_coupon_takes_the_amount_and_never_more_than_the_order(): void
    {
        $this->makeCoupon('TESTFLAT', ['type' => 'fixed', 'value' => 100]);

        self::assertSame(100.0, CouponService::evaluate('TESTFLAT', 800.0)['discount']);
        // A ৳100 voucher on a ৳60 order cannot make the total negative.
        self::assertSame(60.0, CouponService::evaluate('TESTFLAT', 60.0)['discount']);
    }

    public function test_an_inactive_coupon_is_not_applied(): void
    {
        $this->makeCoupon('TESTOFF', ['is_active' => 0]);

        $result = CouponService::evaluate('TESTOFF', 1000.0);

        self::assertFalse($result['ok']);
        self::assertSame(0.0, $result['discount']);
    }

    public function test_an_expired_coupon_is_not_applied(): void
    {
        $this->makeCoupon('TESTOLD', ['expires_at' => date('Y-m-d H:i:s', time() - 3600)]);

        self::assertFalse(CouponService::evaluate('TESTOLD', 1000.0)['ok']);

        // A coupon expiring in an hour is still live.
        $this->makeCoupon('TESTSOON', ['expires_at' => date('Y-m-d H:i:s', time() + 3600)]);

        self::assertTrue(CouponService::evaluate('TESTSOON', 1000.0)['ok']);
    }

    public function test_a_coupon_at_its_usage_cap_is_refused(): void
    {
        $this->makeCoupon('TESTCAP', ['max_uses' => 3, 'used_count' => 3]);

        self::assertFalse(CouponService::evaluate('TESTCAP', 1000.0)['ok']);

        $this->makeCoupon('TESTNEAR', ['max_uses' => 3, 'used_count' => 2]);

        self::assertTrue(CouponService::evaluate('TESTNEAR', 1000.0)['ok']);
    }

    public function test_max_uses_of_zero_means_unlimited(): void
    {
        $this->makeCoupon('TESTUNL', ['max_uses' => 0, 'used_count' => 4000]);

        self::assertTrue(CouponService::evaluate('TESTUNL', 1000.0)['ok']);
    }

    public function test_the_minimum_order_is_enforced(): void
    {
        $this->makeCoupon('TESTMIN', ['min_order' => 2000]);

        self::assertFalse(CouponService::evaluate('TESTMIN', 1999.0)['ok']);
        self::assertTrue(CouponService::evaluate('TESTMIN', 2000.0)['ok']);
    }

    public function test_the_per_customer_limit_counts_this_email_only(): void
    {
        $this->makeCoupon('TESTONCE', ['per_customer_limit' => 1]);

        $email = 'test-customer-' . uniqid() . '@example.com';

        $id = $this->makeOrder(['coupon_code' => 'TESTONCE', 'email' => $email, 'status' => 'completed']);

        self::assertFalse(CouponService::evaluate('TESTONCE', 1000.0, $email)['ok'], 'The same email is capped.');
        self::assertTrue(CouponService::evaluate('TESTONCE', 1000.0, 'someone-else@example.com')['ok']);

        // A cancelled order gave the code back, so it must not consume the quota.
        Database::execute('update orders set status = :s where id = :id', [':s' => 'cancelled', ':id' => $id]);

        self::assertTrue(CouponService::evaluate('TESTONCE', 1000.0, $email)['ok']);
    }

    public function test_an_unknown_code_fails_closed(): void
    {
        $result = CouponService::evaluate('TEST-DOES-NOT-EXIST', 1000.0);

        self::assertFalse($result['ok']);
        self::assertSame(0.0, $result['discount']);
    }

    public function test_a_blank_or_absurd_code_never_reaches_the_database(): void
    {
        self::assertFalse(CouponService::evaluate('   ', 1000.0)['ok']);
        self::assertFalse(CouponService::evaluate(str_repeat('A', 200), 1000.0)['ok']);

        // Punctuation is stripped, so these are all the same code as TESTPCT.
        $this->makeCoupon(self::CODE);

        self::assertTrue(CouponService::evaluate(' test-pct ', 1000.0)['ok']);
        self::assertTrue(CouponService::evaluate('testpct', 1000.0)['ok']);
    }

    public function test_a_zero_value_coupon_reports_that_it_does_not_discount(): void
    {
        $this->makeCoupon('TESTZERO', ['type' => 'percent', 'value' => 0]);

        $result = CouponService::evaluate('TESTZERO', 1000.0);

        self::assertFalse($result['ok']);
        self::assertStringContainsString('does not reduce', $result['message']);
    }

    public function test_sql_injection_in_a_coupon_code_is_inert(): void
    {
        $this->makeCoupon(self::CODE);

        $attack = "' or 1=1 -- ";

        $result = CouponService::evaluate($attack, 1000.0);

        self::assertFalse($result['ok']);
        self::assertSame(1, (int) Database::scalar('select count(*) from coupons'));
    }

    public function test_record_usage_increments_and_release_gives_it_back(): void
    {
        $this->makeCoupon('TESTUSE', ['used_count' => 1, 'max_uses' => 5]);

        $coupon = new \App\Models\Coupon();

        $coupon->recordUsage('TESTUSE');

        self::assertSame(2, (int) Database::scalar('select used_count from coupons where code = :c', [':c' => 'TESTUSE']));

        $coupon->releaseUsage('TESTUSE');

        self::assertSame(1, (int) Database::scalar('select used_count from coupons where code = :c', [':c' => 'TESTUSE']));

        // Releasing past zero stops at zero instead of going negative.
        $coupon->releaseUsage('TESTUSE');
        $coupon->releaseUsage('TESTUSE');

        self::assertSame(0, (int) Database::scalar('select used_count from coupons where code = :c', [':c' => 'TESTUSE']));
    }
}
