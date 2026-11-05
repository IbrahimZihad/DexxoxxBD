<?php

declare(strict_types=1);

/**
 * Storefront and account routes. Loaded by App::loadRoutes() with $router bound
 * to the application router.
 *
 * @var App\Core\Router $router
 */

use App\Core\App;
use App\Core\Router;

$router = App::instance()->router();

// Every page is served through the same two gates: a general per-IP window and
// the CSRF check (which is a no-op for GET/HEAD/OPTIONS).
$router->group(['middleware' => ['throttle:general', 'csrf']], static function (Router $router): void {
    $router->get('', 'HomeController@index', ['name' => 'home']);
    $router->get('plans', 'CatalogController@index', ['name' => 'plans']);
    $router->get('plans/{slug:[a-z0-9-]+}', 'CatalogController@show', ['name' => 'service.show']);

    $router->get('cart', 'CartController@index', ['name' => 'cart']);
    $router->post('cart/add', 'CartController@add', ['name' => 'cart.add', 'middleware' => ['throttle:cart']]);
    $router->post('cart/update', 'CartController@update', ['name' => 'cart.update']);
    $router->post('cart/remove', 'CartController@remove', ['name' => 'cart.remove']);
    $router->post('cart/clear', 'CartController@clear', ['name' => 'cart.clear']);
    $router->post('cart/coupon', 'CartController@applyCoupon', ['name' => 'cart.coupon', 'middleware' => ['throttle:coupon']]);
    $router->post('cart/coupon/remove', 'CartController@removeCoupon', ['name' => 'cart.coupon.remove']);

    $router->get('checkout', 'CheckoutController@index', ['name' => 'checkout']);
    $router->post('checkout', 'CheckoutController@store', ['name' => 'checkout.store', 'middleware' => ['throttle:checkout']]);

    // The payment step is where a customer declares a transaction reference.
    $router->get('order/{orderNo:[A-Za-z0-9-]+}/pay', 'PaymentController@show', ['name' => 'order.pay', 'middleware' => ['throttle:track']]);
    $router->post('order/{orderNo:[A-Za-z0-9-]+}/pay', 'PaymentController@store', ['name' => 'order.pay.store', 'middleware' => ['throttle:guest_order']]);

    $router->get('order/{orderNo:[A-Za-z0-9-]+}', 'OrderController@show', ['name' => 'order.show', 'middleware' => ['throttle:track']]);
    $router->get('track', 'OrderController@trackForm', ['name' => 'track']);
    $router->post('track', 'OrderController@track', ['name' => 'track.lookup', 'middleware' => ['throttle:track']]);

    $router->get('contact', 'PageController@contact', ['name' => 'contact']);
    $router->post('contact', 'PageController@sendContact', ['name' => 'contact.send', 'middleware' => ['throttle:contact']]);
    $router->get('faq', 'PageController@faq', ['name' => 'faq']);
    $router->get('about', 'PageController@about', ['name' => 'about']);
    $router->get('how-it-works', 'PageController@howItWorks', ['name' => 'how']);
    $router->get('terms', 'PageController@terms', ['name' => 'terms']);
    $router->get('privacy', 'PageController@privacy', ['name' => 'privacy']);
    $router->get('refund-policy', 'PageController@refund', ['name' => 'refund']);
    $router->get('robots.txt', 'PageController@robots', ['name' => 'robots']);
    $router->get('sitemap.xml', 'PageController@sitemap', ['name' => 'sitemap']);
    $router->get('health', 'HealthController@show', ['name' => 'health']);
});

// Customers may browse without an account, but the account pages need a session.
$router->group(['middleware' => ['throttle:general', 'csrf', 'auth']], static function (Router $router): void {
    $router->get('account', 'AccountController@index', ['name' => 'account']);
    $router->post('account/logout', 'AuthController@logout', ['name' => 'logout']);
});

$router->group(['middleware' => ['throttle:general', 'csrf', 'guest']], static function (Router $router): void {
    $router->get('login', 'AuthController@loginForm', ['name' => 'login']);
    $router->post('login', 'AuthController@login', ['name' => 'login.attempt', 'middleware' => ['throttle:login']]);
    $router->get('register', 'AuthController@registerForm', ['name' => 'register']);
    $router->post('register', 'AuthController@register', ['name' => 'register.attempt', 'middleware' => ['throttle:register']]);
    $router->get('forgot-password', 'AuthController@forgotForm', ['name' => 'password.forgot']);
    $router->post('forgot-password', 'AuthController@forgot', ['name' => 'password.forgot.send', 'middleware' => ['throttle:reset']]);
    // The pattern stops at [a-f0-9]+ on purpose: the router reads a constraint up
    // to the first "}", so a {64} quantifier cannot be used here.
    $router->get('reset-password/{token:[a-f0-9]+}', 'AuthController@resetForm', ['name' => 'password.reset']);
    $router->post('reset-password', 'AuthController@reset', ['name' => 'password.reset.store', 'middleware' => ['throttle:reset']]);
});

// Staff area. Role gates live in middleware, never in the view.
$router->group([
    'prefix' => 'admin',
    'namespace' => 'App\Controllers\Admin',
    'middleware' => ['throttle:general', 'csrf', 'staff'],
], static function (Router $router): void {
    $router->get('', 'DashboardController@index', ['name' => 'admin.dashboard']);
    $router->post('logout', 'AuthController@logout', ['name' => 'admin.logout']);

    $router->get('orders', 'OrderController@index', ['name' => 'admin.orders']);
    $router->get('orders/{id:\d+}', 'OrderController@show', ['name' => 'admin.order.show']);
    $router->post('orders/{id:\d+}/status', 'OrderController@setStatus', ['name' => 'admin.order.status']);
    $router->post('orders/{id:\d+}/verify-payment', 'OrderController@verifyPayment', ['name' => 'admin.order.verify']);
    $router->post('orders/{id:\d+}/reject-payment', 'OrderController@rejectPayment', ['name' => 'admin.order.reject']);
    $router->post('orders/{id:\d+}/cancel', 'OrderController@cancel', ['name' => 'admin.order.cancel']);
    $router->post('items/{id:\d+}/deliver', 'OrderController@deliverItem', ['name' => 'admin.item.deliver']);
    $router->post('items/{id:\d+}/undo', 'OrderController@undoDelivery', ['name' => 'admin.item.undo']);

    $router->get('services', 'ServiceController@index', ['name' => 'admin.services']);
    $router->post('services', 'ServiceController@store', ['name' => 'admin.service.store', 'middleware' => ['owner']]);
    $router->post('services/{id:\d+}', 'ServiceController@update', ['name' => 'admin.service.update', 'middleware' => ['owner']]);
    $router->post('services/{id:\d+}/delete', 'ServiceController@destroy', ['name' => 'admin.service.delete', 'middleware' => ['owner']]);

    $router->get('plans', 'PlanController@index', ['name' => 'admin.plans']);
    $router->post('plans', 'PlanController@store', ['name' => 'admin.plan.store']);
    $router->post('plans/{id:\d+}', 'PlanController@update', ['name' => 'admin.plan.update']);
    $router->post('plans/{id:\d+}/delete', 'PlanController@destroy', ['name' => 'admin.plan.delete', 'middleware' => ['owner']]);

    $router->get('coupons', 'CouponController@index', ['name' => 'admin.coupons', 'middleware' => ['owner']]);
    $router->post('coupons', 'CouponController@store', ['name' => 'admin.coupon.store', 'middleware' => ['owner']]);
    $router->post('coupons/{id:\d+}', 'CouponController@update', ['name' => 'admin.coupon.update', 'middleware' => ['owner']]);
    $router->post('coupons/{id:\d+}/delete', 'CouponController@destroy', ['name' => 'admin.coupon.delete', 'middleware' => ['owner']]);

    $router->get('messages', 'MessageController@index', ['name' => 'admin.messages']);
    $router->post('messages/{id:\d+}/answer', 'MessageController@answer', ['name' => 'admin.message.answer']);

    $router->get('reviews', 'ContentController@testimonials', ['name' => 'admin.testimonials']);
    $router->post('reviews', 'ContentController@storeTestimonial', ['name' => 'admin.testimonial.store']);
    $router->post('reviews/{id:\d+}', 'ContentController@updateTestimonial', ['name' => 'admin.testimonial.update']);
    $router->post('reviews/{id:\d+}/delete', 'ContentController@deleteTestimonial', ['name' => 'admin.testimonial.delete', 'middleware' => ['owner']]);
    $router->get('faqs', 'ContentController@faqs', ['name' => 'admin.faqs']);
    $router->post('faqs', 'ContentController@storeFaq', ['name' => 'admin.faq.store']);
    $router->post('faqs/{id:\d+}', 'ContentController@updateFaq', ['name' => 'admin.faq.update']);
    $router->post('faqs/{id:\d+}/delete', 'ContentController@deleteFaq', ['name' => 'admin.faq.delete', 'middleware' => ['owner']]);

    // Everything that changes how the shop behaves or who can sign in.
    $router->get('settings', 'SettingController@index', ['name' => 'admin.settings', 'middleware' => ['owner']]);
    $router->post('settings', 'SettingController@update', ['name' => 'admin.settings.update', 'middleware' => ['owner']]);
    $router->get('team', 'UserController@index', ['name' => 'admin.team', 'middleware' => ['owner']]);
    $router->post('team', 'UserController@store', ['name' => 'admin.team.store', 'middleware' => ['owner']]);
    $router->post('team/{id:\d+}', 'UserController@update', ['name' => 'admin.team.update', 'middleware' => ['owner']]);
    $router->post('team/{id:\d+}/delete', 'UserController@destroy', ['name' => 'admin.team.delete', 'middleware' => ['owner']]);
    $router->get('customers', 'UserController@customers', ['name' => 'admin.customers', 'middleware' => ['owner']]);
    $router->get('audit', 'AuditController@index', ['name' => 'admin.audit', 'middleware' => ['owner']]);
    $router->get('delivery', 'DashboardController@deliveryQueue', ['name' => 'admin.delivery']);
});

// The staff login is outside the role gate but still inside CSRF + throttling.
$router->group(['prefix' => 'admin', 'namespace' => 'App\Controllers\Admin', 'middleware' => ['throttle:general', 'csrf', 'guest']], static function (Router $router): void {
    $router->get('login', 'AuthController@loginForm', ['name' => 'admin.login']);
    $router->post('login', 'AuthController@login', ['name' => 'admin.login.attempt', 'middleware' => ['throttle:login']]);
});
