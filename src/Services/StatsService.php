<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\ManualPaymentRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PassRepository;
use App\Repositories\UserRepository;

final class StatsService
{
    public function overview(): array
    {
        $orders = OrderRepository::statistics();
        $users = UserRepository::counts();
        $passes = PassRepository::counts();
        $manual = ManualPaymentRepository::counts();

        $catalogue = Database::selectOne(
            "SELECT
               COALESCE(SUM(status = 'active'), 0) AS active_products,
               COALESCE(SUM(status = 'inactive'), 0) AS inactive_products,
               COALESCE(SUM(status = 'active' AND stock = 0), 0) AS out_of_stock
             FROM products"
        ) ?? [];

        $plans = Database::selectOne("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'active'), 0) AS active FROM plans") ?? [];

        return [
            'total_revenue' => round((float) ($orders['revenue'] ?? 0), 2),
            'average_order_value' => round((float) ($orders['avg_order'] ?? 0), 2),
            'total_orders' => (int) ($orders['orders'] ?? 0),
            'pending_orders' => (int) ($orders['pending'] ?? 0),
            'paid_orders' => (int) ($orders['paid'] ?? 0),
            'lost_orders' => (int) ($orders['lost'] ?? 0),
            'total_customers' => (int) ($users['total'] ?? 0) - (int) ($users['admins'] ?? 0),
            'total_admins' => (int) ($users['admins'] ?? 0),
            'suspended_users' => (int) ($users['suspended'] ?? 0),
            'active_passes' => (int) ($passes['active'] ?? 0),
            'expired_passes' => (int) ($passes['expired'] ?? 0),
            'active_products' => (int) ($catalogue['active_products'] ?? 0),
            'inactive_products' => (int) ($catalogue['inactive_products'] ?? 0),
            'out_of_stock' => (int) ($catalogue['out_of_stock'] ?? 0),
            'total_plans' => (int) ($plans['total'] ?? 0),
            'manual_pending' => (int) ($manual['pending'] ?? 0),
            'manual_approved' => (int) ($manual['approved'] ?? 0),
            'manual_rejected' => (int) ($manual['rejected'] ?? 0),
            'revenue_series' => OrderRepository::revenueSeries(14),
            'top_products' => $this->topProducts(),
        ];
    }

    private function topProducts(): array
    {
        return Database::select(
            "SELECT oi.item_name, SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status = 'paid'
             GROUP BY oi.item_name
             ORDER BY units DESC
             LIMIT 5"
        );
    }
}
