<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\MailService;
use PDOException;

/**
 * Customer sign-in, registration and password resets.
 *
 * Three rules shape every action here:
 *  - a form never says whether an email exists, so a wrong password, an unknown
 *    address and a disabled account all answer with the same wording;
 *  - a reset token is 32 random bytes, mailed in full and stored only as its
 *    SHA-256 hash (PasswordReset::issue does the hashing);
 *  - nothing that reaches a log or a page is a password.
 */
final class AuthController extends Controller
{
    /**
     * One sentence for every failed sign-in. A disabled account is answered with
     * exactly this too, so the form cannot be used to probe which addresses are
     * registered or blocked.
     */
    private const BAD_CREDENTIALS = 'Email or password is wrong. Forgot it? Use the reset link.';

    /** Same wording for a token that is unknown, used or expired. */
    private const BAD_TOKEN = 'That reset link is not valid any more. Request a new one and check the sender.';

    private const RESET_LINK_LIFETIME = 'one hour';

    /**
     * Password policy, in one place so the register form, the reset form and the
     * checklist printed in the views can never drift apart:
     * 8-200 characters, at least one letter and at least one digit.
     */
    private const PASSWORD_RULES = 'required|min:8|max:200|confirmed|regex:/^(?=.*[A-Za-z])(?=.*\d).{8,200}$/';

    public function loginForm(Request $request): Response
    {
        return $this->view('auth.login', [
            'reason' => trim((string) $request->queryValue('reason', '')),
        ]);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email|max:180',
            // No minimum length on purpose: a short typo must fail like a wrong
            // password, not like "too short", or the form leaks the policy.
            'password' => 'required|max:200',
        ], ['email' => 'Email', 'password' => 'Password']);

        $result = Auth::attempt((string) $data['email'], (string) $request->input('password', ''));

        if (!$result['ok']) {
            // 'inactive' is folded into the same generic message; Auth::attempt
            // never logs in a disabled account, so there is nothing to undo.
            Session::flashInput(['email' => (string) $data['email']]);

            return $this->redirect(route('login'), 'error', self::BAD_CREDENTIALS);
        }

        $user = auth_user();
        $firstName = trim(explode(' ', (string) ($user['name'] ?? ''))[0]);

        return $this->redirect(
            $this->targetAfterLogin(),
            'success',
            $firstName === '' ? 'Welcome back.' : 'Welcome back, ' . $firstName . '.'
        );
    }

    public function registerForm(Request $request): Response
    {
        return $this->view('auth.register', []);
    }

    public function register(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:120',
            'email' => 'required|email|max:180|unique:users,email',
            'phone' => 'required|phone_bd|max:20',
            'password' => self::PASSWORD_RULES,
            'accept_terms' => 'required|in:1',
            'marketing_opt_in' => 'boolean',
        ], [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'password' => 'Password',
            'accept_terms' => 'The terms',
        ]);

        $users = new User();
        $optIn = (int) ($data['marketing_opt_in'] ?? 0) === 1 ? 1 : 0;

        try {
            $userId = $users->register(
                (string) $data['name'],
                (string) $data['email'],
                (string) $data['phone'],
                (string) $request->input('password', '')
            );
        } catch (PDOException $exception) {
            // The unique index on users.email is the tie-breaker when two tabs
            // race the same address; answer like the uniqueness rule did.
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            Logger::warning('register_email_taken', ['path' => $request->path()]);
            Session::flashInput(['name' => (string) $data['name'], 'email' => (string) $data['email'], 'phone' => (string) $data['phone']]);

            return $this->redirect(route('register'), 'error', 'That email already has an account. Try signing in instead.');
        }

        if ($userId <= 0) {
            return $this->redirect(route('register'), 'error', 'We could not create the account just now. Please try again in a moment.');
        }

        // register() does not carry the marketing flag; it is a fillable column,
        // so it is written through the model rather than by hand.
        $users->update($userId, ['marketing_opt_in' => $optIn]);

        // Auth::login rotates the session id, so a token fixed before this
        // request cannot survive into the signed-in session.
        Auth::login($userId);

        return $this->redirect(
            $this->targetAfterLogin(),
            'success',
            'Your account is ready. Orders you place with this email land here automatically.'
        );
    }

    public function logout(Request $request): Response
    {
        // The route is POST-only and inside the csrf + auth middleware, so a
        // cross-site link can never sign somebody out.
        Auth::logout();
        Session::flush();

        // flush() empties the session (cart, tracked order numbers, everything
        // the previous identity left behind); the flash is written afterwards so
        // it survives on the rotated id instead of being destroyed with it.
        Session::flash('success', 'Signed out. Your cart and session data were cleared.');

        return Response::redirect(route('home'));
    }

    public function forgotForm(Request $request): Response
    {
        return $this->view('auth.forgot', []);
    }

    public function forgot(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email|max:180',
        ], ['email' => 'Email']);

        $email = mb_strtolower(trim((string) $data['email']));
        $user = (new User())->findByEmail($email);

        // Both branches answer with the same sentence and the same redirect.
        if ($user !== null && (int) ($user['is_active'] ?? 0) === 1) {
            $token = bin2hex(random_bytes(32));

            PasswordReset::issue($email, $token, $request->ip());
            MailService::resetLink($email, route('password.reset', ['token' => $token]), (string) ($user['name'] ?? ''));
        }

        return $this->redirect(
            route('password.forgot'),
            'success',
            'If that address has an account, a reset link is on its way. It expires in ' . self::RESET_LINK_LIFETIME . '.'
        );
    }

    public function resetForm(Request $request, string $token = ''): Response
    {
        $token = strtolower(trim($token));

        if (!$this->resetRowFor($token)) {
            return $this->redirect(route('password.forgot'), 'error', self::BAD_TOKEN);
        }

        return $this->view('auth.reset', ['token' => $token]);
    }

    public function reset(Request $request): Response
    {
        $data = $this->validate($request, [
            'token' => 'required|min:64|max:64',
            'password' => self::PASSWORD_RULES,
        ], ['token' => 'Reset link', 'password' => 'New password']);

        $token = strtolower(trim((string) $data['token']));
        $row = $this->resetRowFor($token);

        if ($row === null) {
            return $this->redirect(route('password.forgot'), 'error', self::BAD_TOKEN);
        }

        $email = mb_strtolower(trim((string) $row['email']));
        $users = new User();
        $user = $users->findByEmail($email);

        if ($user === null) {
            PasswordReset::consume((int) $row['id']);

            return $this->redirect(route('password.forgot'), 'error', self::BAD_TOKEN);
        }

        PasswordReset::consume((int) $row['id']);

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            // Whoever controls that mailbox asked for this link, so telling them
            // the account is switched off is useful and does not leak anything
            // to a stranger. The password is left untouched.
            return $this->redirect(route('login'), 'error', 'This account is disabled. Ask an administrator to re-enable it.');
        }

        $users->updatePasswordHash((int) $user['id'], Auth::hash((string) $request->input('password', '')));

        return $this->redirect(route('login'), 'success', 'Password changed. Sign in with the new one.');
    }

    /**
     * The unspent, unexpired row behind a token, or null. The format check runs
     * first so a random string never reaches the database as a lookup.
     *
     * @return array<string, mixed>|null
     */
    private function resetRowFor(string $token): ?array
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
            return null;
        }

        return PasswordReset::findValid($token);
    }

    /**
     * Where to send someone who has just signed in: the page they were trying to
     * reach, the page that carried the form, or the account area.
     */
    private function targetAfterLogin(): string
    {
        $intended = (string) Session::get('intended_url', '');
        Session::forget('intended_url');

        return $this->localUrl($intended)
            ?? $this->localUrl(App::instance()->request()->referer())
            ?? route('account');
    }

    /**
     * A URL that really belongs to this app, as a root-relative path, or null.
     * Anything else - another host, another app in the same XAMPP htdocs, a URL
     * with a CR/LF in it - is dropped so a POST can never become a redirect to
     * somebody else's page.
     */
    private function localUrl(string $url): ?string
    {
        if ($url === '' || preg_match('/[\r\n\t]/', $url) === 1) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if ($host !== strtolower(App::instance()->request()->host())) {
            return null;
        }

        $base = App::instance()->request()->basePath();

        if ($base !== '' && $path !== $base && !str_starts_with($path, $base . '/')) {
            return null;
        }

        if ($path === '') {
            return null;
        }

        $query = (string) parse_url($url, PHP_URL_QUERY);

        return $path . ($query === '' ? '' : '?' . $query);
    }
}
