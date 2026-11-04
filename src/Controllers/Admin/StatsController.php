<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Http\Controller;
use App\Http\Response;
use App\Repositories\OrderRepository;
use App\Services\MaintenanceService;
use App\Services\StatsService;

final class StatsController extends Controller
{
    public function overview(): Response
    {
        Auth::requireAdmin();
        $stats = (new StatsService())->overview();
        $recent = OrderRepository::paginateForAdmin(1, 8);

        return $this->json($stats + ['recent_orders' => $recent['data']]);
    }

    /** Cron and the admin "run housekeeping" button share this entry point. */
    public function sweep(): Response
    {
        Auth::requireAdmin();

        return $this->json(['ok' => true] + (new MaintenanceService())->run());
    }
}
