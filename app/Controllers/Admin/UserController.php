<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;

/**
 * People who can sign in (the team) and the customer directory.
 *
 * Every route here is owner-gated in routes/web.php, so the actor is already an
 * administrator. The judgement this controller owns is about not locking the shop
 * out of itself:
 *  - an admin can never delete or deactivate their own account;
 *  - no change (role drop, deactivate, delete) may remove the last active
 *    administrator;
 *  - a password is hashed with Auth::hash() and never stored, echoed, logged or
 *    carried in a hidden field.
 */
final class UserController extends Controller
{
    private const ROLES = ['customer', 'staff', 'admin'];

    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        unset($request);

        $users = new User();

        return $this->view('admin.team', [
            'staff' => $users->staffQuery()->get(),
            'currentUserId' => (int) (Auth::id() ?? 0),
            'adminCount' => $this->activeAdminCount(),
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, $this->rules(0), $this->labels());

        $users = new User();
        $email = mb_strtolower(trim((string) $data['email']));

        if ($this->emailTaken($email, 0)) {
            return $this->refuse('team', 'That email is already used by another account.');
        }

        $password = (string) $request->input('password', '');

        $id = $users->create([
            'name' => (string) $data['name'],
            'email' => $email,
            'phone' => $this->nullable((string) ($data['phone'] ?? '')),
            'password_hash' => Auth::hash($password),
            'role' => (string) $data['role'],
            'is_active' => (int) ($data['is_active'] ?? 0),
        ]);

        // The role name only; never the password.
        AuditLog::record('user.created', 'user', $id, sprintf(
            'Created %s account "%s".',
            (string) $data['role'],
            $email
        ));

        return $this->redirect(route('admin.team'), 'success', sprintf('%s account added.', ucfirst((string) $data['role'])));
    }

    public function update(Request $request, string $id): Response
    {
        $userId = (int) $id;
        $users = new User();
        $target = $users->find($userId);

        if ($target === null) {
            throw new HttpException(404, 'That account does not exist.');
        }

        $data = $this->validate($request, $this->rules($userId), $this->labels());

        $email = mb_strtolower(trim((string) $data['email']));
        if ($this->emailTaken($email, $userId)) {
            return $this->refuse('team', 'That email is already used by another account.');
        }

        $newRole = (string) $data['role'];
        $newActive = (int) ($data['is_active'] ?? 0);
        $isSelf = $userId === (int) (Auth::id() ?? 0);

        // Self-lockout guard.
        if ($isSelf && $newActive !== 1) {
            return $this->refuse('team', 'You cannot deactivate your own account. Ask another administrator.');
        }

        // Last-admin guard: refuse any change that would take an active admin out
        // of the active-admin pool when they are the only one left.
        $wasActiveAdmin = (string) $target['role'] === 'admin' && (int) $target['is_active'] === 1;
        $staysActiveAdmin = $newRole === 'admin' && $newActive === 1;

        if ($wasActiveAdmin && !$staysActiveAdmin && $this->activeAdminCount() <= 1) {
            return $this->refuse('team', 'This is the last active administrator. Promote or activate another admin before changing this one.');
        }

        $users->update($userId, [
            'name' => (string) $data['name'],
            'email' => $email,
            'phone' => $this->nullable((string) ($data['phone'] ?? '')),
            'role' => $newRole,
            'is_active' => $newActive,
        ]);

        // A password is only rewritten when a new one was actually submitted.
        $password = (string) $request->input('password', '');
        if ($password !== '') {
            $users->updatePasswordHash($userId, Auth::hash($password));
        }

        AuditLog::record('user.updated', 'user', $userId, sprintf(
            'Updated account "%s" to role "%s", %s.%s',
            $email,
            $newRole,
            $newActive === 1 ? 'active' : 'disabled',
            $password !== '' ? ' Password reset.' : ''
        ));

        return $this->redirect(route('admin.team'), 'success', 'The account was saved.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $userId = (int) $id;
        $users = new User();
        $target = $users->find($userId);

        if ($target === null) {
            throw new HttpException(404, 'That account does not exist.');
        }

        if ((string) $request->input('confirm', '') !== '1') {
            return $this->refuse('team', 'Tick the confirmation box before deleting an account.');
        }

        if ($userId === (int) (Auth::id() ?? 0)) {
            return $this->refuse('team', 'You cannot delete your own account.');
        }

        if ((string) $target['role'] === 'admin' && (int) $target['is_active'] === 1 && $this->activeAdminCount() <= 1) {
            return $this->refuse('team', 'This is the last active administrator. Promote another admin before deleting this one.');
        }

        $email = (string) ($target['email'] ?? '');
        $users->delete($userId);

        AuditLog::record('user.deleted', 'user', $userId, sprintf('Deleted account "%s".', $email));

        return $this->redirect(route('admin.team'), 'success', 'The account was deleted.');
    }

    public function customers(Request $request): Response
    {
        $search = mb_substr(trim((string) $request->queryValue('search', '')), 0, 120);
        $page = max(1, (int) $request->queryValue('page', 1));

        $users = new User();
        $orders = new Order();
        $paging = $users->paginateCustomers(['role' => 'customer', 'search' => $search], $page, self::PER_PAGE);

        $rows = [];
        foreach ($paging['items'] as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            $summary = $users->orderSummary($rowId);
            $last = $orders->forUser($rowId, 1)[0] ?? null;

            $row['orders_count'] = $summary['orders'];
            $row['lifetime_spend'] = $summary['spent'];
            $row['last_order'] = $last['created_at'] ?? null;
            $rows[] = $row;
        }

        $paging['items'] = $rows;

        return $this->view('admin.customers', [
            'paging' => $paging,
            'search' => $search,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function rules(int $ignoreId): array
    {
        $rules = [
            'name' => 'required|max:120',
            'email' => 'required|email|max:180|lower',
            'phone' => 'phone_bd|max:20',
            'role' => 'required|in:' . implode(',', self::ROLES),
            'is_active' => 'boolean',
        ];

        // A password is mandatory on create; on update it is only validated when
        // one was actually typed. The unique email check runs in the controller so
        // it compares the lower-cased value we store.
        $rules['password'] = $ignoreId === 0 ? 'required|min:8|max:200|confirmed' : 'min:8|max:200|confirmed';

        return $rules;
    }

    /** @return array<string, string> */
    private function labels(): array
    {
        return [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'role' => 'Role',
            'password' => 'Password',
        ];
    }

    /** Case-insensitive email uniqueness, on the value we are about to store. */
    private function emailTaken(string $email, int $ignoreId): bool
    {
        $query = (new User())->query()->where('email', $email);

        if ($ignoreId > 0) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function activeAdminCount(): int
    {
        return (new User())->query()->where('role', 'admin')->where('is_active', 1)->count();
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param 'team'|'customers' $page
     */
    private function refuse(string $page, string $message): Response
    {
        return $this->redirect(route('admin.' . $page), 'error', $message);
    }
}
