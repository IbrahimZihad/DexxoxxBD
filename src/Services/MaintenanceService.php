<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\OrderRepository;
use App\Repositories\PassRepository;

/**
 * Housekeeping that must happen whether or not anyone remembers to run it:
 * pending orders hold stock, and passes hold access, until they lapse.
 * Safe to run from cron every few minutes — every step is idempotent.
 */
final class MaintenanceService
{
    public function run(int $limit = 200): array
    {
        $released = 0;
        $orderService = new OrderService();

        foreach (OrderRepository::stalePending($limit) as $order) {
            $orderService->release((int) $order['id'], 'expired');
            $released++;
        }

        $expiredPasses = PassRepository::expireDue();

        if ($released > 0 || $expiredPasses > 0) {
            Logger::info('Maintenance sweep', ['orders_expired' => $released, 'passes_expired' => $expiredPasses]);
        }

        return [
            'orders_expired' => $released,
            'passes_expired' => $expiredPasses,
        ];
    }
}
