<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (!is_file($autoload)) {
    throw new RuntimeException('Dependencies are not installed. Run composer install.');
}

require $autoload;
