<?php

declare(strict_types=1);

return [
    // file | none  (redis-ready: the driver interface is CacheService)
    'driver' => env('CACHE_DRIVER', 'file'),
    'path' => base_path('storage/cache'),

    'ttl' => [
        'settings' => 900,
        'catalog_home' => 300,
        'category_tree' => 600,
        'product_detail' => 120,
        'sitemap' => 1800,
        'statistics' => 60,
    ],

    'prefix' => 'dsm',
];
