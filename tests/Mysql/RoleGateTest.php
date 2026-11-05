<?php

declare(strict_types=1);

namespace Tests\Mysql;

use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\OwnerMiddleware;
use App\Middleware\StaffMiddleware;
use App\Exceptions\HttpException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Authorization is enforced here, in the middleware, before a controller runs -
 * so hiding a button in a template is never the control. These cases cover the
 * three gates from loosest to tightest against real user rows.
 */
#[CoversClass(StaffMiddleware::class)]
#[CoversClass(OwnerMiddleware::class)]
final class RoleGateTest extends TestCase
{
    private int $passCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passCount = 0;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Database::execute('delete from users where email like :prefix', [':prefix' => 'test-%@zapsub.test']);
        $_SESSION = [];

        $this->signIn(null);

        parent::tearDown();
    }

    /** The pipeline tail: it only runs when the gate let the request through. */
    private function next(): \Closure
    {
        return function (\App\Core\Request $request): \App\Core\Response {
            $this->passCount++;

            return \App\Core\Response::json(['ok' => true, 'passed' => true]);
        };
    }

    public function test_anonymous_is_redirected_to_the_admin_login(): void
    {
        $this->signIn(null);

        $response = (new StaffMiddleware())->handle($this->request(), $this->next());

        self::assertSame(302, $response->status());
        self::assertStringContainsString('admin/login', $response->header('Location') ?? '');
        self::assertSame(0, $this->passCount, 'The controller must not run.');
    }

    public function test_anonymous_json_request_gets_401_not_a_redirect(): void
    {
        $this->signIn(null);

        $response = (new StaffMiddleware())->handle(
            $this->request('GET', 'admin/orders', [], ['accept' => 'application/json']),
            $this->next()
        );

        self::assertSame(401, $response->status());
        self::assertSame(0, $this->passCount);
    }

    public function test_a_customer_cannot_reach_the_staff_area(): void
    {
        $this->signIn($this->makeUser('customer'));

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        (new StaffMiddleware())->handle($this->request(), $this->next());
    }

    public function test_staff_passes_the_staff_gate(): void
    {
        $this->signIn($this->makeUser('staff'));

        $response = (new StaffMiddleware())->handle($this->request(), $this->next());

        self::assertSame(200, $response->status());
        self::assertSame(1, $this->passCount);
    }

    public function test_admin_passes_the_staff_gate(): void
    {
        $this->signIn($this->makeUser('admin'));

        self::assertSame(200, (new StaffMiddleware())->handle($this->request(), $this->next())->status());
        self::assertSame(1, $this->passCount);
    }

    public function test_staff_is_refused_the_owner_only_actions(): void
    {
        $this->signIn($this->makeUser('staff'));

        try {
            (new OwnerMiddleware())->handle($this->request('POST', 'admin/settings'), $this->next());
            self::fail('A staff account must not pass the admin-only gate.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->status());
        }

        self::assertSame(0, $this->passCount);
    }

    public function test_admin_passes_the_owner_gate(): void
    {
        $this->signIn($this->makeUser('admin'));

        self::assertSame(200, (new OwnerMiddleware())->handle($this->request('POST', 'admin/settings'), $this->next())->status());
    }

    public function test_a_deactivated_account_loses_access_mid_session(): void
    {
        $id = $this->makeUser('staff');
        $this->signIn($id);

        self::assertSame(200, (new StaffMiddleware())->handle($this->request(), $this->next())->status());

        Database::execute('update users set is_active = 0 where id = :id', [':id' => $id]);
        $this->signIn($id);

        // No 403 and no pass: the identity is gone, so the gate treats this as
        // signed out and sends the browser to the login page.
        $response = (new StaffMiddleware())->handle($this->request(), $this->next());

        self::assertSame(302, $response->status());
        self::assertSame(1, $this->passCount, 'Only the earlier staff request reached the controller.');
        self::assertNull(Database::selectOne('select id from users where id = :id and is_active = 1', [':id' => $id]));
    }

    public function test_a_customer_is_refused_both_gates(): void
    {
        $id = $this->makeUser('customer');
        $this->signIn($id);

        // The gates are allow-lists, so anything that is not literally staff or
        // admin is refused; the role column is an enum, so there is no fourth
        // value to smuggle in.
        foreach ([StaffMiddleware::class, OwnerMiddleware::class] as $gate) {
            try {
                (new $gate())->handle($this->request(), $this->next());
                self::fail($gate . ' must not let a customer through.');
            } catch (HttpException $exception) {
                self::assertSame(403, $exception->status());
            }
        }

        self::assertSame(0, $this->passCount);
    }

    public function test_auth_gate_requires_any_signed_in_user(): void
    {
        $this->signIn(null);

        self::assertSame(302, (new AuthMiddleware())->handle($this->request('GET', 'account'), $this->next())->status());
        self::assertSame(0, $this->passCount);

        $this->signIn($this->makeUser('customer'));

        self::assertSame(200, (new AuthMiddleware())->handle($this->request('GET', 'account'), $this->next())->status());
        self::assertSame(1, $this->passCount);
    }

    public function test_guest_gate_bounces_a_signed_in_user_away_from_login(): void
    {
        $this->signIn($this->makeUser('customer'));

        self::assertSame(302, (new GuestMiddleware())->handle($this->request('GET', 'login'), $this->next())->status());
        self::assertSame(0, $this->passCount);

        $this->signIn(null);

        self::assertSame(200, (new GuestMiddleware())->handle($this->request('GET', 'login'), $this->next())->status());
    }

    /** @return int the new user id */
    private function makeUser(string $role): int
    {
        return $this->insert('users', [
            'name' => 'Gate ' . $role,
            'email' => 'test-' . $role . '-' . uniqid('', true) . '@zapsub.test',
            'phone' => '01712345678',
            'password_hash' => \App\Core\Auth::hash('a-sufficiently-long-passphrase'),
            'role' => $role,
            'is_active' => 1,
            'marketing_opt_in' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
