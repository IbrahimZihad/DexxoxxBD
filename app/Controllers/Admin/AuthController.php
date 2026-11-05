<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;

/**
 * Staff sign-in for the /admin area.
 *
 * The customer login and this one are separate on purpose: a customer who knows
 * a staff password still has to come through this door, and an admin who signs in
 * here is recorded in the audit trail. Every failure answers with one generic
 * sentence, so the form cannot be used to probe which addresses exist or who is
 * allowed in the panel.
 */
final class AuthController extends Controller
{
    /** Same wording for a wrong password, an unknown email, a disabled account and a customer account. */
    private const BAD_CREDENTIALS = 'Email or password is wrong. Only staff accounts can open the admin panel.';

    public function loginForm(Request $request): Response
    {
        return $this->view('admin.login', [
            'reason' => trim((string) $request->queryValue('reason', '')),
        ]);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email|max:180',
            // No length floor: a short typo has to fail exactly like a wrong
            // password, or the form leaks the password policy.
            'password' => 'required|max:200',
        ], ['email' => 'Email', 'password' => 'Password']);

        $email = mb_strtolower((string) $data['email']);
        $result = Auth::attempt($email, (string) $request->input('password', ''));

        if ($result['ok'] !== true) {
            // Auth::attempt already logged the failed verification; this line
            // records that the attempt was aimed at the staff door.
            Logger::security('admin_login_failed', [
                'email' => mb_substr($email, 0, 120),
                'code' => (string) $result['code'],
                'ip' => $request->ip(),
            ]);

            Session::flashInput(['email' => $email]);

            return $this->redirect(route('admin.login'), 'error', self::BAD_CREDENTIALS);
        }

        if (!Auth::isStaff()) {
            // A customer password is not a staff password. The session Auth::attempt
            // just opened is torn down before the response leaves, so no customer
            // can end up browsing /admin - and the wording stays the generic one.
            Logger::security('admin_login_refused_customer', [
                'user_id' => Auth::id(),
                'role' => Auth::role(),
                'ip' => $request->ip(),
            ]);

            Auth::logout();

            return $this->redirect(route('admin.login'), 'error', self::BAD_CREDENTIALS);
        }

        $userId = (int) Auth::id();
        $user = Auth::user();

        AuditLog::record(
            'admin.login',
            'user',
            $userId,
            sprintf(
                '%s (%s) signed in to the admin panel.',
                (string) ($user['email'] ?? 'unknown'),
                (string) ($user['role'] ?? 'staff')
            )
        );

        $firstName = trim(explode(' ', (string) ($user['name'] ?? ''))[0]);

        return $this->redirect(
            route('admin.dashboard'),
            'success',
            $firstName === '' ? 'Signed in to the admin panel.' : 'Signed in. Welcome back, ' . $firstName . '.'
        );
    }

    public function logout(Request $request): Response
    {
        $user = Auth::user();

        // Recorded while the identity is still readable; the route is POST-only
        // and behind the csrf + staff gates, so a cross-site link cannot sign
        // anybody out.
        AuditLog::record(
            'admin.logout',
            'user',
            (int) ($user['id'] ?? 0),
            sprintf('%s left the admin panel.', (string) ($user['email'] ?? 'unknown'))
        );

        Auth::logout();
        Session::flush();

        // flush() empties the session; the flash is written afterwards so it
        // survives on the rotated id instead of being destroyed with it.
        Session::flash('success', 'Signed out of the admin panel.');

        return Response::redirect(route('admin.login'));
    }
}
