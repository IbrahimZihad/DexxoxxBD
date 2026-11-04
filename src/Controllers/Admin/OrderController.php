<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Exceptions\HttpException;
use App\Http\Controller;
use App\Http\Response;
use App\Repositories\ManualPaymentRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PassRepository;
use App\Repositories\PaymentRepository;
use App\Services\OrderService;
use App\Services\PassService;

final class OrderController extends Controller
{
    private const STATUSES = ['pending', 'paid', 'failed', 'cancelled', 'expired'];

    public function index(): Response
    {
        Auth::requireAdmin();
        $status = (string) ($this->request->query('status') ?? '');
        $search = (string) ($this->request->query('q') ?? '');

        if ($status !== '' && !in_array($status, self::STATUSES, true)) {
            throw new HttpException(422, 'Unknown order status.');
        }

        $page = OrderRepository::paginateForAdmin($this->page(), $this->perPage(25, 100), $status, $search);

        return $this->json([
            'orders' => $page['data'],
            'meta' => ['total' => $page['total'], 'page' => $page['page'], 'per_page' => $page['per_page']],
        ]);
    }

    public function show(): Response
    {
        Auth::requireAdmin();
        $id = $this->idFromQuery();
        $order = OrderRepository::findForAdmin($id);

        if ($order === null) {
            throw new HttpException(404, 'Order not found.');
        }

        $manual = ManualPaymentRepository::latestForOrder($id);

        return $this->json([
            'order' => $order + [
                'items' => OrderRepository::items($id),
                'payments' => PaymentRepository::listForOrder($id),
                'manual_payment' => $manual,
                'passes' => $this->passesFor($id),
            ],
        ]);
    }

    /**
     * Manual settlement by an admin. Marking an order paid issues passes; marking
     * it failed/cancelled gives the reserved stock back to the catalogue.
     */
    public function updateStatus(): Response
    {
        $admin = Auth::requireAdmin();
        $id = $this->idFromQuery();
        $data = $this->validate(['status' => 'required|in:' . implode(',', self::STATUSES)]);
        $status = (string) $data['status'];

        $order = OrderRepository::findForAdmin($id);

        if ($order === null) {
            throw new HttpException(404, 'Order not found.');
        }

        if ($order['status'] === $status) {
            return $this->json(['ok' => true, 'changed' => false]);
        }

        if ($status === 'pending') {
            throw new HttpException(422, 'An order cannot be reopened once it has been settled or closed.');
        }

        if ($status === 'paid') {
            $granted = (new PassService())->grantForOrder($id, 'admin');

            return $this->json([
                'ok' => true,
                'changed' => $granted,
                'message' => $granted ? 'Order marked paid and passes issued.' : 'Order was already settled.',
            ]);
        }

        (new OrderService())->release($id, $status);

        \App\Core\Logger::info('Admin closed order', ['order_id' => $id, 'status' => $status, 'admin_id' => (int) $admin['id']]);

        return $this->json(['ok' => true, 'changed' => true]);
    }

    private function passesFor(int $orderId): array
    {
        $passes = [];

        foreach (OrderRepository::items($orderId) as $item) {
            foreach (PassRepository::listForOrderItem((int) $item['id']) as $pass) {
                $passes[] = $pass;
            }
        }

        return $passes;
    }
}
