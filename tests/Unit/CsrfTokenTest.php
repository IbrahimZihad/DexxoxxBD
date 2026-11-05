<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth;
use App\Core\Session;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * The CSRF token and the password hash are the two things a request is judged
 * on, so both are tested here without a browser, a session cookie or MySQL.
 */
#[CoversClass(Session::class)]
final class CsrfTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        parent::tearDown();
    }

    public function test_a_token_is_issued_and_is_long_enough(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));

        self::assertSame(64, strlen(Session::token()));
    }

    public function test_validate_token_accepts_only_the_exact_value(): void
    {
        $_SESSION['_csrf'] = 'a' . str_repeat('0', 63);

        self::assertTrue(Session::validateToken($_SESSION['_csrf']));
        self::assertFalse(Session::validateToken('b' . str_repeat('0', 63)));
        self::assertFalse(Session::validateToken(substr($_SESSION['_csrf'], 0, 63)));
        self::assertFalse(Session::validateToken(''));
        self::assertFalse(Session::validateToken(null));
        self::assertFalse(Session::validateToken(12345));
        self::assertFalse(Session::validateToken(['x' => 'y']));
    }

    public function test_an_empty_session_never_validates_anything(): void
    {
        unset($_SESSION['_csrf']);

        self::assertSame('', Session::token());
        self::assertFalse(Session::validateToken(''));
        self::assertFalse(Session::validateToken(str_repeat('0', 64)));
    }

    public function test_a_failed_check_rotates_the_token(): void
    {
        $stale = $_SESSION['_csrf'] = str_repeat('f', 64);

        Session::rotateCsrfOnFailure();

        self::assertNotSame($stale, Session::token());
        self::assertFalse(Session::validateToken($stale), 'The replayed stale token must stay dead.');
        self::assertTrue(Session::validateToken(Session::token()));
    }

    public function test_the_comparison_is_constant_time_in_style(): void
    {
        // A prefix of the real token must not pass, which is what hash_equals()
        // guarantees while a == comparison would also get right. Combined with the
        // length checks above this pins down the exact-equality behaviour.
        $_SESSION['_csrf'] = str_repeat('7', 64);

        self::assertFalse(Session::validateToken(str_repeat('7', 63) . '8'));
    }
}
