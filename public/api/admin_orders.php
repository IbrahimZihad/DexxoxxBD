<?php
declare(strict_types=1);

/** Thin dispatcher - routing and guards live in routes/api.php. */
$kernel = require dirname(__DIR__, 2) . '/src/bootstrap.php';

$kernel->handle('admin_orders.php')->send();
