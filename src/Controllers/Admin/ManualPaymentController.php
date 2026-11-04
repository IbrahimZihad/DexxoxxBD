<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Exceptions\HttpException;
use App\Http\Controller;
use App\Http\Response;
use App\Repositories\ManualPaymentRepository;
use App\Security\Sanitizer;
use App\Services\ManualPaymentService;
use App\Services\PassService;

final class ManualPaymentController extends Controller
{
    public function index(): Response
    {
        Auth::requireAdmin();
        $status = (string) ($this->request->query('status') ?? '');

        if ($status !== '' && !in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new HttpException(422, 'Unknown review status.');
        }

        $page = ManualPaymentRepository::paginate($this->page(), $this->perPage(25, 100), $status);

        return $this->json([
            'payments' => $page['data'],
            'counts' => ManualPaymentRepository::counts(),
            'meta' => ['total' => $page['total'], 'page' => $page['page'], 'per_page' => $page['per_page']],
        ]);
    }

    public function review(): Response
    {
        $admin = Auth::requireAdmin();
        $id = $this->idFromQuery();
        $data = $this->validate([
            'action' => 'required|in:approve,reject',
            'admin_note' => 'nullable|string|max:500',
        ], ['action' => 'decision']);

        if ($id <= 0 || ManualPaymentRepository::find($id) === null) {
            throw new HttpException(404, 'Payment record not found.');
        }

        $note = isset($data['admin_note']) ? Sanitizer::multiline($data['admin_note'], 500) : null;
        $result = $this->service()->review($id, (string) $data['action'], $note !== '' ? $note : null, (int) $admin['id']);

        return $this->json([
            'ok' => true,
            'status' => $result['status'],
            'order_id' => $result['order_id'],
            'message' => $result['status'] === 'approved'
                ? 'Payment approved — passes issued to the customer.'
                : 'Payment rejected and the order closed.',
        ]);
    }

    private function service(): ManualPaymentService
    {
        return new ManualPaymentService(new PassService());
    }
}
