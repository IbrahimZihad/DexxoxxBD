<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\RateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RateLimiter::class)]
final class RateLimiterTest extends TestCase
{
    private string $identity;

    protected function setUp(): void
    {
        parent::setUp();

        // A per-test identity keeps runs independent of each other and of the
        // buckets the live site is using.
        $this->identity = 'phpunit-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('phpunit_test', $this->identity);

        parent::tearDown();
    }

    public function test_it_allows_exactly_the_configured_number_of_attempts(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $result = RateLimiter::attempt('phpunit_test', $this->identity, 5, 900);

            self::assertTrue($result['allowed'], 'Attempt ' . $attempt . ' should be allowed');
        }

        self::assertSame(0, $result['remaining']);
    }

    public function test_it_blocks_after_the_limit_and_reports_a_retry_delay(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            RateLimiter::attempt('phpunit_test', $this->identity, 5, 60);
        }

        $blocked = RateLimiter::attempt('phpunit_test', $this->identity, 5, 60);

        self::assertFalse($blocked['allowed']);
        self::assertGreaterThanOrEqual(1, $blocked['retry_after']);
        self::assertLessThanOrEqual(60, $blocked['retry_after']);
    }

    public function test_hammering_does_not_extend_the_lockout(): void
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $result = RateLimiter::attempt('phpunit_test', $this->identity, 3, 45);
        }

        // The window is made of the 3 newest timestamps, so retry_after keeps
        // shrinking towards the oldest one rather than being pushed back.
        self::assertFalse($result['allowed']);
        self::assertLessThanOrEqual(45, $result['retry_after']);
    }

    public function test_identities_are_isolated(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            RateLimiter::attempt('phpunit_test', $this->identity, 3, 60);
        }

        self::assertFalse(RateLimiter::attempt('phpunit_test', $this->identity, 3, 60)['allowed']);
        self::assertTrue(RateLimiter::attempt('phpunit_test', $this->identity . '-other', 3, 60)['allowed']);

        RateLimiter::clear('phpunit_test', $this->identity . '-other');
    }

    public function test_buckets_are_isolated(): void
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            RateLimiter::attempt('phpunit_bucket_a', $this->identity, 2, 60);
        }

        self::assertFalse(RateLimiter::attempt('phpunit_bucket_a', $this->identity, 2, 60)['allowed']);
        self::assertTrue(RateLimiter::attempt('phpunit_bucket_b', $this->identity, 2, 60)['allowed']);

        RateLimiter::clear('phpunit_bucket_a', $this->identity);
        RateLimiter::clear('phpunit_bucket_b', $this->identity);
    }

    public function test_clear_resets_the_window_after_a_success(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            RateLimiter::attempt('phpunit_test', $this->identity, 3, 60);
        }

        RateLimiter::clear('phpunit_test', $this->identity);

        self::assertSame(3, RateLimiter::remaining('phpunit_test', $this->identity, 3, 60));
        self::assertTrue(RateLimiter::attempt('phpunit_test', $this->identity, 3, 60)['allowed']);
    }

    public function test_remaining_counts_only_attempts_inside_the_window(): void
    {
        RateLimiter::attempt('phpunit_test', $this->identity, 5, 60);

        self::assertSame(4, RateLimiter::remaining('phpunit_test', $this->identity, 5, 60));
        // A window already in the past has expired, so the budget is whole again.
        self::assertSame(5, RateLimiter::remaining('phpunit_test', $this->identity, 5, -60));
    }
}
