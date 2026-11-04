<?php
declare(strict_types=1);

/**
 * Cron entry point — expires stale pending orders (returning their stock) and
 * lapses passes. Idempotent, so running it every few minutes is harmless.
 *
 * Add to crontab (every five minutes):
 *   0,5,10,15,20,25,30,35,40,45,50,55 * * * *  php /path/to/DexxoxxBD/bin/sweep.php
 */

use App\Services\MaintenanceService;

['database' => $database] = require dirname(__DIR__) . '/database/cli-boot.php';

$result = (new MaintenanceService())->run();

printf(
    "[%s] %s: %d order(s) expired, %d pass(es) lapsed\n",
    date('Y-m-d H:i:s'),
    $database,
    $result['orders_expired'],
    $result['passes_expired']
);
