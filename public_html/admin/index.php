<?php

declare(strict_types=1);

$scriptName = '/index.php';
$requestUri = '/admin';
$_SERVER['SCRIPT_NAME'] = $scriptName;
$_SERVER['PHP_SELF'] = $scriptName;
$_SERVER['REQUEST_URI'] = $requestUri;

require dirname(__DIR__) . '/index.php';
