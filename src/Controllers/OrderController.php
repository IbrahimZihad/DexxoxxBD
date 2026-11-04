<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Exceptions\HttpException;
use App\Http\Controller;
use App\Http\Response;
use App\Repositories\ManualPaymentRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PassRepository;
use App\Repositories\PaymentRepository;
use App\Security\Sanitizer;
use App\Services\OrderService;

final class OrderController extends Controller
{
    public function index(): Response
    {
        $user = Auth::requireUser();
        $orderId = $this->request->int('id');

        if ($orderId > 0) {
            return $this->show();
        }

        $page = OrderRepository::paginateForUser((int) $user['id'], $this->page(), $this->perPage(20, 50));

        return $this->json([
            'orders' => $page['data'],
            'meta' => ['total' => $page['total'], 'page' => $page['page'], 'per_page' => $page['per_page']],
        ]);
    }

    public function show(): Response
    {
        $user = Auth::requireUser();
        $orderId = $this->request->int('id');
        $order = OrderRepository::findForUser($orderId, (int) $user['id']);

        if ($order === null) {
            throw new HttpException(404, 'Order not found.');
        }

        $items = OrderRepository::items($orderId);
        $passes = [];

        foreach ($items as $item) {
            foreach (PassRepository::listForOrderItem((int) $item['id']) as $pass) {
                $passes[] = $pass;
            }
        }

        $manual = ManualPaymentRepository::latestForOrder($orderId);

        return $this->json([
            'order' => $order + [
                'items' => $items,
                'passes' => $passes,
                'payments' => PaymentRepository::listForOrder($orderId),
                'manual_payment' => $manual === null ? null : [
                    'status' => $manual['status'],
                    'payment_method' => $manual['payment_method'],
                    'transaction_id' => $manual['transaction_id'],
                    'admin_note' => $manual['admin_note'],
                    'created_at' => $manual['created_at'],
                    'reviewed_at' => $manual['reviewed_at'],
                ],
            ],
        ]);
    }

    public function store(): Response
    {
        $user = Auth::requireUser();
        $data = $this->validate([
            'customer_name' => 'required|string|min:2|max:120',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|phone_bd',
        ], ['customer_name' => 'full name', 'customer_phone' => 'phone number']);

        $contact = [
            'customer_name' => Sanitizer::text($data['customer_name'], 120),
            'customer_email' => strtolower((string) $data['customer_email']),
            'customer_phone' => Sanitizer::phone($data['customer_phone']),
        ];

        $order = (new OrderService())->createFromCart((int) $user['id'], $contact, $this->request->ip());

        return $this->json($order, 201);
    }
}
