<?php

declare(strict_types=1);

/**
 * JSON endpoints used by the frontend fetch calls. They are read-only, rate
 * limited, and return the smallest shape each widget needs.
 *
 * @var App\Core\Router $router
 */

use App\Core\App;
use App\Core\Router;

$router = App::instance()->router();

$router->group(['prefix' => 'api', 'namespace' => 'App\Controllers', 'middleware' => ['throttle:general', 'csrf']], static function (Router $router): void {
    // Header search suggestions.
    $router->get('suggest', 'ApiController@suggest', ['name' => 'api.suggest', 'middleware' => ['throttle:cart']]);

    // "Has my payment been verified yet?" poll on the payment page. It answers
    // with statuses only - never credentials - and only for an order this
    // browser is allowed to see.
    $router->get('order/{orderNo:[A-Za-z0-9-]+}/status', 'ApiController@orderStatus', [
        'name' => 'api.order.status',
        'middleware' => ['throttle:track'],
    ]);
});
