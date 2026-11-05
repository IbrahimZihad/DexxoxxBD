<?php

declare(strict_types=1);

namespace Tests\Mysql;

use App\Controllers\ApiController;
use App\Core\Database;
use App\Core\Response;
use App\Middleware\CsrfMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * The two gates that stand between a stranger and somebody else's order: the
 * double-submit CSRF token on every state-changing request, and the session
 * scoping on the status poll that the payment page keeps hitting.
 */
#[CoversClass(CsrfMiddleware::class)]
#[CoversClass(ApiController::class)]
final class RequestGateTest extends TestCase
{
    private int $reached = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reached = 0;
        $_SESSION = [];
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    protected function tearDown(): void
    {
        $this->deleteOrdersMatching('TEST-');
        $_SESSION = [];

        parent::tearDown();
    }

    private function next(): \Closure
    {
        return function (\App\Core\Request $request): Response {
            $this->reached++;

            return Response::json(['ok' => true, 'passed' => true]);
        };
    }

    public function test_safe_methods_are_never_blocked(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            self::assertSame(200, (new CsrfMiddleware())->handle($this->request($method), $this->next())->status());
        }

        self::assertSame(3, $this->reached);
    }

    public function test_a_post_without_a_token_is_refused_and_the_handler_never_runs(): void
    {
        $response = (new CsrfMiddleware())->handle($this->request('POST', 'cart/add', ['plan_id' => 1]), $this->next());

        self::assertSame(403, $response->status());
        self::assertSame(0, $this->reached);
    }

    public function test_a_forged_or_truncated_token_is_refused(): void
    {
        $real = $_SESSION['_csrf'];

        foreach ([$real . 'x', substr($real, 0, 63), str_repeat('0', 64), '', 'Bearer ' . $real] as $bad) {
            $response = (new CsrfMiddleware())->handle(
                $this->request('POST', 'checkout', ['_token' => $bad]),
                $this->next()
            );

            self::assertSame(403, $response->status(), 'Token "' . substr($bad, 0, 8) . '" must be refused.');
        }

        self::assertSame(0, $this->reached);
    }

    public function test_the_session_token_is_rotated_after_a_failure(): void
    {
        $stale = $_SESSION['_csrf'];

        (new CsrfMiddleware())->handle($this->request('POST', 'cart/add', ['_token' => $stale . 'x']), $this->next());

        self::assertNotSame($stale, $_SESSION['_csrf']);
        self::assertSame(200, (new CsrfMiddleware())->handle(
            $this->request('POST', 'cart/add', ['_token' => $_SESSION['_csrf']]),
            $this->next()
        )->status(), 'The fresh token from the next page load must work.');
    }

    public function test_the_header_carries_the_token_just_as_well_as_the_field(): void
    {
        $token = $_SESSION['_csrf'];

        self::assertSame(200, (new CsrfMiddleware())->handle(
            $this->request('POST', 'cart/add', [], ['x-csrf-token' => $token]),
            $this->next()
        )->status());

        self::assertSame(200, (new CsrfMiddleware())->handle(
            $this->request('POST', 'cart/add', ['_token' => $token]),
            $this->next()
        )->status());

        self::assertSame(2, $this->reached);
    }

    public function test_a_json_request_refused_for_csrf_answers_json(): void
    {
        $response = (new CsrfMiddleware())->handle(
            $this->request('POST', 'cart/add', [], ['accept' => 'application/json']),
            $this->next()
        );

        self::assertSame(403, $response->status());

        $payload = json_decode($response->body(), true);

        self::assertIsArray($payload);
        self::assertFalse($payload['ok']);
    }

    public function test_the_status_poll_only_answers_for_orders_this_session_opened(): void
    {
        $mine = $this->makeOrder();
        $mineNo = (string) $this->order($mine)['order_no'];

        $theirs = $this->makeOrder();
        $theirsNo = (string) $this->order($theirs)['order_no'];

        $_SESSION['viewed_orders'] = [$mineNo];

        $request = new \App\Core\Request(
            'GET',
            'api/order/' . $mineNo . '/status',
            [],
            [],
            ['accept' => 'application/json'],
            '203.0.113.9',
            '/DexxoxxBD/public',
            'localhost',
            false
        );

        // The route binds {orderNo}; the controller reads it as a parameter.
        $allowed = (new ApiController())->orderStatus($request->withParameters(['orderNo' => $mineNo]));

        self::assertSame(200, $allowed->status());

        $stranger = (new ApiController())->orderStatus($request->withParameters(['orderNo' => $theirsNo]));

        self::assertSame(403, $stranger->status());
        self::assertStringNotContainsString((string) $this->order($theirs)['email'], $stranger->body());

        // Guessing the number is not enough; it has to be in this session's list.
        $unknown = (new ApiController())->orderStatus($request->withParameters(['orderNo' => 'TEST-NOT-SEEN']));

        self::assertSame(403, $unknown->status());

        $blank = (new ApiController())->orderStatus($request->withParameters(['orderNo' => '']));

        self::assertSame(403, $blank->status());
    }

    public function test_the_status_poll_never_returns_the_secret_fields(): void
    {
        $id = $this->makeOrder();
        $orderNo = (string) $this->order($id)['order_no'];

        Database::execute(
            'update orders set delivery_request = :note, note = :comment, phone = :phone where id = :id',
            [
                ':note' => \App\Core\Crypto::encrypt('mail: victim@example.com pass: MustNotLeak'),
                ':comment' => 'send to my gmail',
                ':phone' => '01712345678',
                ':id' => $id,
            ]
        );

        $_SESSION['viewed_orders'] = [$orderNo];

        $request = new \App\Core\Request(
            'GET',
            'api/order/' . $orderNo . '/status',
            [],
            [],
            ['accept' => 'application/json'],
            '203.0.113.9',
            '/DexxoxxBD/public',
            'localhost',
            false
        );

        $response = (new ApiController())->orderStatus($request->withParameters(['orderNo' => $orderNo]));
        $payload = json_decode($response->body(), true);

        self::assertSame(200, $response->status());
        self::assertSame(
            ['ok', 'status', 'payment_status', 'fulfillment_status', 'status_label', 'paid'],
            array_keys((array) $payload),
            'The poll may only carry statuses.'
        );
        self::assertStringNotContainsString('MustNotLeak', $response->body());
        self::assertStringNotContainsString('01712345678', $response->body());
        self::assertFalse($payload['paid']);
    }
}
