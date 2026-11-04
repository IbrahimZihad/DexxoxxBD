<?php
declare(strict_types=1);

/**
 * API routing table. Keys are the dispatcher files under public/api/, values map
 * either an ?action= value or an HTTP verb onto a controller method.
 *
 *   handler  => [Controller::class, 'method']
 *   methods  => verbs the route answers (defaults to the key when the key is a verb)
 *   auth     => null | 'user' | 'admin'
 *   csrf     => verify X-CSRF-Token on unsafe requests
 *   throttle => bucket name from config.php rate_limits
 */

use App\Controllers\Admin\ManualPaymentController;
use App\Controllers\Admin\OrderController as AdminOrderController;
use App\Controllers\Admin\StatsController;
use App\Controllers\Admin\UserController;
use App\Controllers\AuthController;
use App\Controllers\CallbackController;
use App\Controllers\CartController;
use App\Controllers\CategoryController;
use App\Controllers\OrderController;
use App\Controllers\PassController;
use App\Controllers\PaymentController;
use App\Controllers\PlanController;
use App\Controllers\ProductController;
use App\Controllers\SettingController;

$read = ['auth' => null, 'csrf' => false, 'throttle' => 'read'];

/**
 * Gateway callbacks carry no session and no CSRF token. They accept both the
 * buyer's GET return and the gateway's POST, so each verb gets its own entry.
 */
$callback = static fn (array $handler): array => [
    'GET' => ['handler' => $handler, 'methods' => ['GET'], 'throttle' => 'default'],
    'POST' => ['handler' => $handler, 'methods' => ['POST'], 'throttle' => 'default'],
];

return [
    'auth.php' => [
        'token' => ['handler' => [AuthController::class, 'token']] + $read,
        'me' => ['handler' => [AuthController::class, 'me']] + $read,
        'login' => [
            'handler' => [AuthController::class, 'login'],
            'methods' => ['POST'],
            'csrf' => true,
            'throttle' => 'auth.login',
        ],
        'register' => [
            'handler' => [AuthController::class, 'register'],
            'methods' => ['POST'],
            'csrf' => true,
            'throttle' => 'auth.register',
        ],
        'logout' => [
            'handler' => [AuthController::class, 'logout'],
            'methods' => ['POST'],
            'auth' => 'user',
            'csrf' => true,
            'throttle' => 'read',
        ],
    ],

    'products.php' => [
        'GET' => ['handler' => [ProductController::class, 'index']] + $read,
        'POST' => [
            'handler' => [ProductController::class, 'store'],
            'auth' => 'admin',
            'csrf' => true,
            'throttle' => 'default',
        ],
        'PUT' => [
            'handler' => [ProductController::class, 'update'],
            'auth' => 'admin',
            'csrf' => true,
            'throttle' => 'default',
        ],
        'DELETE' => [
            'handler' => [ProductController::class, 'destroy'],
            'auth' => 'admin',
            'csrf' => true,
            'throttle' => 'default',
        ],
    ],

    'plans.php' => [
        'GET' => ['handler' => [PlanController::class, 'index']] + $read,
        'POST' => [
            'handler' => [PlanController::class, 'store'],
            'auth' => 'admin',
            'csrf' => true,
            'throttle' => 'default',
        ],
        'PUT' => [
            'handler' => [PlanController::class, 'update'],
            'auth' => 'admin',
            'csrf' => true,
            'throttle' => 'default',
        ],
        'DELETE' => [
            'handler' => [PlanController::class, 'destroy'],
            'auth' => 'admin',
            'csrf' => true,
            'throttle' => 'default',
        ],
    ],

    'categories.php' => [
        'GET' => ['handler' => [CategoryController::class, 'index']] + $read,
    ],

    'settings.php' => [
        'GET' => ['handler' => [SettingController::class, 'publicIndex']] + $read,
    ],

    'admin_settings.php' => [
        'GET' => ['handler' => [SettingController::class, 'adminIndex'], 'auth' => 'admin', 'throttle' => 'read'],
        'PUT' => [
            'handler' => [SettingController::class, 'update'],
            'auth' => 'admin',
            'csrf' => true,
            'throttle' => 'default',
        ],
    ],

    'cart.php' => [
        'GET' => ['handler' => [CartController::class, 'index'], 'auth' => 'user', 'throttle' => 'read'],
        'POST' => ['handler' => [CartController::class, 'store'], 'auth' => 'user', 'csrf' => true, 'throttle' => 'default'],
        'PUT' => ['handler' => [CartController::class, 'update'], 'auth' => 'user', 'csrf' => true, 'throttle' => 'default'],
        'DELETE' => ['handler' => [CartController::class, 'destroy'], 'auth' => 'user', 'csrf' => true, 'throttle' => 'default'],
    ],

    'orders.php' => [
        'GET' => ['handler' => [OrderController::class, 'index'], 'auth' => 'user', 'throttle' => 'read'],
        'POST' => [
            'handler' => [OrderController::class, 'store'],
            'auth' => 'user',
            'csrf' => true,
            'throttle' => 'orders.create',
        ],
    ],

    'passes.php' => [
        'GET' => ['handler' => [PassController::class, 'index'], 'auth' => 'user', 'throttle' => 'read'],
    ],

    'admin_passes.php' => [
        'GET' => ['handler' => [PassController::class, 'adminIndex'], 'auth' => 'admin', 'throttle' => 'read'],
        'PUT' => ['handler' => [PassController::class, 'revoke'], 'auth' => 'admin', 'csrf' => true, 'throttle' => 'default'],
    ],

    'payment/methods.php' => [
        'GET' => ['handler' => [PaymentController::class, 'methods']] + $read,
    ],

    'payment/init.php' => [
        'POST' => [
            'handler' => [PaymentController::class, 'init'],
            'auth' => 'user',
            'csrf' => true,
            'throttle' => 'payment.init',
        ],
    ],

    'payment/manual.php' => [
        'POST' => [
            'handler' => [PaymentController::class, 'manual'],
            'auth' => 'user',
            'csrf' => true,
            'throttle' => 'payment.manual',
        ],
    ],

    // Gateway-facing endpoints: no session, no CSRF. The buyer returns by GET,
    // the gateway posts form-encoded, and IPN is server-to-server.
    'payment/success.php' => $callback([CallbackController::class, 'success']),
    'payment/fail.php' => $callback([CallbackController::class, 'fail']),
    'payment/cancel.php' => $callback([CallbackController::class, 'cancel']),

    'payment/ipn.php' => [
        'POST' => ['handler' => [CallbackController::class, 'ipn'], 'throttle' => 'default'],
    ],

    'admin_orders.php' => [
        'GET' => ['handler' => [AdminOrderController::class, 'index'], 'auth' => 'admin', 'throttle' => 'read'],
        'PUT' => ['handler' => [AdminOrderController::class, 'updateStatus'], 'auth' => 'admin', 'csrf' => true, 'throttle' => 'default'],
    ],

    'admin_stats.php' => [
        'GET' => ['handler' => [StatsController::class, 'overview'], 'auth' => 'admin', 'throttle' => 'read'],
        'POST' => ['handler' => [StatsController::class, 'sweep'], 'auth' => 'admin', 'csrf' => true, 'throttle' => 'default'],
    ],

    'users.php' => [
        'GET' => ['handler' => [UserController::class, 'index'], 'auth' => 'admin', 'throttle' => 'read'],
        'PUT' => ['handler' => [UserController::class, 'update'], 'auth' => 'admin', 'csrf' => true, 'throttle' => 'default'],
    ],

    'admin_manual_payments.php' => [
        'GET' => ['handler' => [ManualPaymentController::class, 'index'], 'auth' => 'admin', 'throttle' => 'read'],
        'PUT' => ['handler' => [ManualPaymentController::class, 'review'], 'auth' => 'admin', 'csrf' => true, 'throttle' => 'default'],
    ],
];
