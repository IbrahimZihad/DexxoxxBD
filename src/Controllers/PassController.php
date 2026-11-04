<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Exceptions\HttpException;
use App\Http\Controller;
use App\Http\Response;
use App\Repositories\PassRepository;

final class PassController extends Controller
{
    public function index(): Response
    {
        $user = Auth::requireUser();
        PassRepository::expireDue((int) $user['id']);
        $passes = PassRepository::listForUser((int) $user['id']);

        return $this->json([
            'passes' => array_map($this->shape(...), $passes),
            'summary' => [
                'active' => count(array_filter($passes, static fn ($p) => $p['status'] === 'active')),
                'expiring_soon' => count(array_filter(
                    $passes,
                    static fn ($p) => $p['status'] === 'active' && strtotime((string) $p['ends_at']) < strtotime('+7 days')
                )),
            ],
        ]);
    }

    public function adminIndex(): Response
    {
        Auth::requireAdmin();
        $status = $this->oneOf(['active', 'expiring', 'expired', 'revoked'], 'status');

        PassRepository::expireDue();
        $page = PassRepository::paginateForAdmin($this->page(), $this->perPage(25, 100), $status);

        return $this->json([
            'passes' => array_map($this->shape(...), $page['data']),
            'meta' => ['total' => $page['total'], 'page' => $page['page'], 'per_page' => $page['per_page']],
        ]);
    }

    public function revoke(): Response
    {
        $admin = Auth::requireAdmin();
        $id = $this->idFromQuery();

        if (PassRepository::revoke($id, (int) $admin['id']) !== 1) {
            throw new HttpException(404, 'Pass not found or already revoked.');
        }

        return $this->json(['ok' => true]);
    }

    /** @param array<string,mixed> $pass @return array<string,mixed> */
    private function shape(array $pass): array
    {
        $ends = (string) ($pass['ends_at'] ?? '');
        $daysLeft = $ends === '' ? null : (int) floor((strtotime($ends . ' 23:59:59') - time()) / 86400);

        return [
            'id' => (int) $pass['id'],
            'item_type' => $pass['item_type'],
            'item_name' => (string) $pass['item_name'],
            'pass_code' => (string) $pass['pass_code'],
            'starts_at' => $pass['starts_at'],
            'ends_at' => $ends ?: null,
            'status' => (string) $pass['status'],
            'days_left' => $pass['status'] === 'active' ? max(0, (int) $daysLeft) : null,
            'created_at' => $pass['created_at'] ?? null,
            'user_name' => $pass['user_name'] ?? null,
            'user_email' => $pass['user_email'] ?? null,
        ];
    }
}
