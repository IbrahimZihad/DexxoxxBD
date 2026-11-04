<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Core\Logger;
use App\Exceptions\HttpException;
use App\Http\Controller;
use App\Http\Response;
use App\Repositories\UserRepository;

final class UserController extends Controller
{
    public function index(): Response
    {
        Auth::requireAdmin();
        $page = UserRepository::paginate(
            $this->page(),
            $this->perPage(25, 100),
            (string) ($this->request->query('q') ?? '')
        );

        return $this->json([
            'users' => $page['data'],
            'meta' => ['total' => $page['total'], 'page' => $page['page'], 'per_page' => $page['per_page']],
        ]);
    }

    public function update(): Response
    {
        $admin = Auth::requireAdmin();
        $id = $this->idFromQuery();

        if ($id <= 0 || UserRepository::find($id) === null) {
            throw new HttpException(404, 'User not found.');
        }

        $data = $this->validate([
            'role' => 'nullable|in:customer,admin',
            'status' => 'nullable|in:active,suspended',
        ]);

        $role = isset($data['role']) ? (string) $data['role'] : null;
        $status = isset($data['status']) ? (string) $data['status'] : null;

        if ($role === null && $status === null) {
            throw new HttpException(422, 'Please check the highlighted fields.', [
                'role' => 'Choose a role or an account status.',
            ]);
        }

        // Never let an admin remove their own access or lock themselves out.
        if ($id === (int) $admin['id']) {
            if ($role === 'customer' || $status === 'suspended') {
                throw new HttpException(422, 'You cannot demote or suspend your own account.');
            }
        }

        if ($role === 'customer' || $status === 'suspended') {
            $target = UserRepository::find($id);

            if (($target['role'] ?? '') === 'admin' && ($target['status'] ?? '') === 'active' && $this->activeAdmins() <= 1) {
                throw new HttpException(422, 'This is the last active admin — promote someone else first.');
            }
        }

        UserRepository::updateRoleOrStatus($id, $role, $status);
        Logger::info('Admin updated user', ['target_user' => $id, 'role' => $role, 'status' => $status, 'admin_id' => (int) $admin['id']]);

        return $this->json(['ok' => true, 'user' => UserRepository::find($id)]);
    }

    private function activeAdmins(): int
    {
        $row = \App\Core\Database::selectOne("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND status = 'active'");

        return (int) ($row['c'] ?? 0);
    }
}
