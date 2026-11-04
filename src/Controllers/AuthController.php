<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Session;
use App\Core\Config;
use App\Exceptions\AuthenticationException;
use App\Exceptions\HttpException;
use App\Http\Controller;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Security\Hash;
use App\Security\Sanitizer;

final class AuthController extends Controller
{
    /**
     * A real cost-12 bcrypt digest of a throwaway string. Unknown emails are
     * verified against it so a login attempt always costs the same hash time.
     */
    private const DUMMY_HASH = '$2y$12$NyAq5/gT2P11vK77XSx5X.gIODaGMtoMDc4iHjM4s8/SgjHQRCpqC';

    public function token(): Response
    {
        return $this->json(['csrf_token' => Session::csrfToken()]);
    }

    public function register(): Response
    {
        $data = $this->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email',
            'password' => 'required|string|min:' . $this->minimumPasswordLength() . '|max:200',
            'phone' => 'nullable|phone_bd',
        ], ['name' => 'name', 'password' => 'password']);

        $email = strtolower((string) $data['email']);

        if (UserRepository::emailExists($email)) {
            throw new HttpException(409, 'An account with this email already exists.');
        }

        $userId = UserRepository::create(
            Sanitizer::text($data['name'], 120),
            $email,
            Hash::make((string) $data['password']),
            isset($data['phone']) && $data['phone'] !== null ? Sanitizer::phone($data['phone']) : null
        );

        // Registration is an unauthenticated write, so the pre-login CSRF token is
        // rotated together with the session id at login.
        Session::login($userId);

        $user = UserRepository::find($userId);

        if ($user === null) {
            throw new HttpException(500, 'Account created but could not be loaded. Please sign in.');
        }

        return $this->json(['user' => $user, 'csrf_token' => Session::csrfToken()], 201);
    }

    public function login(): Response
    {
        $data = $this->validate([
            'email' => 'required|email',
            'password' => 'required|string|max:200',
        ]);

        $user = UserRepository::findByEmailWithHash(strtolower((string) $data['email']));
        $password = (string) $data['password'];

        // Verify against a placeholder hash when the account is unknown so that a
        // timing difference cannot be used to enumerate registered emails.
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $ok = Hash::verify($password, $hash);

        if ($user === null || !$ok) {
            throw new AuthenticationException('Incorrect email or password.');
        }

        if ($user['status'] !== 'active') {
            throw new HttpException(403, 'This account has been suspended.');
        }

        if (Config::get('security.rehash_on_login') && Hash::needsRehash((string) $user['password_hash'])) {
            UserRepository::updatePassword((int) $user['id'], Hash::make($password));
        }

        Session::login((int) $user['id']);
        UserRepository::touchLogin((int) $user['id']);

        $profile = UserRepository::find((int) $user['id']);

        return $this->json([
            'user' => $profile,
            'csrf_token' => Session::csrfToken(),
        ]);
    }

    public function logout(): Response
    {
        Session::logout();

        return $this->json(['ok' => true]);
    }

    public function me(): Response
    {
        $user = Auth::user();

        if ($user === null) {
            throw new AuthenticationException('Not signed in.');
        }

        return $this->json([
            'user' => $user,
            'csrf_token' => Session::csrfToken(),
            'is_admin' => Auth::isAdmin(),
        ]);
    }

    private function minimumPasswordLength(): int
    {
        return max(8, (int) Config::get('security.password_min_length', 8));
    }
}
