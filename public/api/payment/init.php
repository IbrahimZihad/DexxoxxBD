<?php
declare(strict_types=1);

/** Thin dispatcher - routing and guards live in routes/api.php. */
$kernel = require dirname(__DIR__, 3) . '/src/bootstrap.php';

$kernel->handle('payment/init.php')->send();
