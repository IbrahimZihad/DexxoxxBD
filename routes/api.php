<?php

/**
 * AJAX endpoints for the storefront.
 *
 * These are read-only and rate limited: the type-ahead is the one URL a crawler
 * or a script could hammer, and it is the cheapest to serve, so it gets the
 * tightest budget. Responses go through the same session and locale pipeline as
 * a page request so a signed-in customer sees the same prices they see in HTML.
 */

declare(strict_types=1);

use App\Core\Router;

/** @var Router $router */

$router->group([
    'namespace' => 'App\\Controllers',
    'prefix' => 'api',
    'middleware' => ['web', 'locale', 'throttle:60,60'],
], static function (Router $router): void {
    $router->get('search/suggest', 'ApiController@suggest')->name('api.suggest');
    $router->get('products/{slug:[a-z0-9-]+}/quick-view', 'ProductController@quickView')
        ->name('api.quick-view');
});
