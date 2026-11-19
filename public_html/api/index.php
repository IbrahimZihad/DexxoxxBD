<?php

declare(strict_types=1);

$scriptName = '/index.php';
$_SERVER['SCRIPT_NAME'] = $scriptName;
$_SERVER['PHP_SELF'] = $scriptName;
$_SERVER['REQUEST_URI'] = '/api';

require dirname(__DIR__, 2) . '/public_html/index.php';
