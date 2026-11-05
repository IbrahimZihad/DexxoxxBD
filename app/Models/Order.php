<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Order extends Model
{
    protected string $table = 'orders';

    protected array $fillable = [
        'order_no', 'user_id', 'status', 'payment_status', 'fulfillment_status',
        'subtotal', 'discount', 'total', 'coupon_code', 'customer_name', 'email',
        'phone', 'note', 'ip_address', 'paid_at', 'created_at', 'updated_at',
    ];

    /**
     * Human-readable, guessable order number: ZS-YYMM-####-## so a customer can
     * read it over the phone. Uniqueness is enforced by the column, and this
     * loop just keeps trying until it lands on a free one.
     */
    public function nextOrderNumber(): string
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $candidate = sprintf(
                'ZS-%s-%s%s',
                date('ym'),
                date('d'),
                strtoupper(substr(bin2hex(random_bytes(3)), 0, 4))
            );

            if (!$this->query()->where('order_no', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'ZS-' . date('ymdHis') . '-' . random_int(100, 999);
    }

    public function findByOrderNo(string $orderNo): ?array
    {
        return $this->query()->where('order_no', strtoupper(trim($orderNo)))->first();
    }

    /** @return array<string, mixed>|null */
    public function withItems(string $orderNo): ?array
    {
        $order = $this->findByOrderNo($orderNo);

        if ($order === null) {
            return null;
        }

        $order['items'] = (new OrderItem())->forOrder((int) $order['id']);
        $order['payments'] = (new Payment())->forOrder((int) $order['id']);
        $order['history'] = Database::select(
            'select * from order_status_history where order_id = :id order by created_at asc, id asc',
            [':id' => (int) $order['id']]
        );

        return $order;
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function paginateAdmin(array $filters, int $page, int $perPage = 20): array
    {
        $query = $this->query()->orderBy(['id' => 'desc']);

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (string) $filters['status']);
        }

        if (($filters['payment_status'] ?? '') !== '') {
            $query->where('payment_status', (string) $filters['payment_status']);
        }

        if (($filters['search'] ?? '') !== '') {
            $query->search(['order_no', 'customer_name', 'email', 'phone'], (string) $filters['search']);
        }

        return $query->paginate($page, $perPage);
    }

    /** @return list<array<string, mixed>> */
    public function forUser(int $userId, int $limit = 25): array
    {
        return $this->query()
            ->where('user_id', $userId)
            ->orderBy(['id' => 'desc'])
            ->limit($limit)
            ->get();
    }

    /** Orders a guest paid for with the same email, so tracking is useful. */
    public function forEmail(string $email, int $limit = 10): array
    {
        return $this->query()->where('email', mb_strtolower(trim($email)))
            ->orderBy(['id' => 'desc'])->limit($limit)->get();
    }

    public function markPaymentVerified(int $orderId, int $staffId): void
    {
        Database::execute(
            'update orders set payment_status = :paid, paid_at = now(), verified_by = :who, updated_at = now()
              where id = :id',
            [':paid' => 'paid', ':who' => $staffId, ':id' => $orderId]
        );
    }

    public function changeStatus(int $orderId, string $status): void
    {
        $this->query()->where('id', $orderId)->update([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{today_orders: int, today_revenue: float, pending_payments: int,
     *               awaiting_delivery: int, month_revenue: float, month_orders: int,
     *               total_orders: int, conversion_guests: int}
     */
    public function dashboard(): array
    {
        $today = Database::selectOne(
            "select count(*) as orders,
                    coalesce(sum(case when payment_status = 'paid' then total else 0 end), 0) as revenue
               from orders where created_at >= :from",
            [':from' => date('Y-m-d 00:00:00')]
        );

        $month = Database::selectOne(
            "select count(*) as orders,
                    coalesce(sum(case when payment_status = 'paid' then total else 0 end), 0) as revenue
               from orders where created_at >= :from",
            [':from' => date('Y-m-01 00:00:00')]
        );

        return [
            'today_orders' => (int) ($today['orders'] ?? 0),
            'today_revenue' => (float) ($today['revenue'] ?? 0),
            'month_orders' => (int) ($month['orders'] ?? 0),
            'month_revenue' => (float) ($month['revenue'] ?? 0),
            'pending_payments' => $this->query()->where('payment_status', 'pending_verification')->count(),
            'awaiting_delivery' => (new OrderItem())->pendingDeliveryCount(),
            'total_orders' => $this->countAll(),
            'conversion_guests' => $this->query()->whereNull('user_id')->count(),
        ];
    }

    /**
     * Revenue per day for the dashboard sparkline, paid orders only.
     *
     * @return list<array{day: string, revenue: float, orders: int}>
     */
    public function lastDaysSeries(int $days = 14): array
    {
        $rows = Database::select(
            "select date_format(created_at, '%Y-%m-%d') as day,
                    count(*) as orders,
                    coalesce(sum(total), 0) as revenue
               from orders
              where created_at >= :from and payment_status <> :unpaid
              group by day order by day asc",
            [':from' => date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days')), ':unpaid' => 'unpaid']
        );

        $series = [];

        foreach ($rows as $row) {
            $series[(string) $row['day']] = [
                'day' => (string) $row['day'],
                'revenue' => (float) $row['revenue'],
                'orders' => (int) $row['orders'],
            ];
        }

        $complete = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $complete[] = $series[$day] ?? ['day' => $day, 'revenue' => 0.0, 'orders' => 0];
        }

        return $complete;
    }

    /** Top-selling services for the dashboard. @return list<array<string, mixed>> */
    public function bestSellers(int $limit = 5): array
    {
        return Database::select(
            'select oi.service_name, count(*) as times, coalesce(sum(oi.line_total), 0) as revenue
               from order_items oi
               inner join orders o on o.id = oi.order_id
              where o.payment_status = :paid
              group by oi.service_name
              order by times desc
              limit ' . max(1, (int) $limit),
            [':paid' => 'paid']
        );
    }

    /** Recent activity feed. @return list<array<string, mixed>> */
    public function recent(int $limit = 8): array
    {
        return $this->query()->orderBy(['id' => 'desc'])->limit($limit)->get();
    }

    /** Orders that are still waiting on payment and have gone stale. */
    public function expirePending(int $minutes): int
    {
        $rows = Database::execute(
            "update orders
                set status = :cancelled, payment_status = :unpaid, updated_at = now()
              where status = :pending
                and payment_status = :unpaid2
                and created_at < :deadline",
            [
                ':cancelled' => 'cancelled',
                ':unpaid' => 'unpaid',
                ':pending' => 'pending',
                ':unpaid2' => 'unpaid',
                ':deadline' => date('Y-m-d H:i:s', time() - max(5, $minutes) * 60),
            ]
        );

        return $rows;
    }
}
