<?php

declare(strict_types=1);

/**
 * Storefront routes.
 *
 * Everything shared by browser requests lives in one group: session, locale and
 * the CSRF check. CSRF is a no-op for GET, so listing it on the group means a
 * future POST route is protected by default instead of by memory.
 */

use App\Core\Router;

/** @var Router $router */

$router->group([
    'namespace' => 'App\\Controllers',
    'middleware' => ['web', 'locale', 'csrf'],
], static function (Router $router): void {
    $router->get('', 'HomeController@index')->name('home');

    /* Catalog ------------------------------------------------------------ */
    $router->get('products', 'CatalogController@index')->name('products.index');
    $router->get('deals', 'CatalogController@deals')->name('deals');
    $router->get('categories', 'CategoryController@index')->name('categories.index');
    $router->get('categories/{slug:[a-z0-9-]+}', 'CatalogController@category')->name('categories.show');
    $router->get('brands', 'BrandController@index')->name('brands.index');
    $router->get('brands/{slug:[a-z0-9-]+}', 'CatalogController@brand')->name('brands.show');

    // Specific paths are matched before this catch-all, so keep it last in the
    // catalogue block.
    $router->get('products/{slug:[a-z0-9-]+}', 'ProductController@show')->name('products.show');

    /* Content ------------------------------------------------------------ */
    $router->get('faqs', 'PageController@faqs')->name('faqs');
    $router->get('how-it-works', 'PageController@howItWorks')->name('how-it-works');
    $router->get('contact', 'ContactController@index')->name('contact');
    $router->post('contact', 'ContactController@store')
        ->middleware(['auth', 'verified', 'throttle:5,300'])
        ->name('contact.store');
    $router->get('page/{slug:[a-z0-9-]+}', 'PageController@show')->name('pages.show');

    /* Locale + newsletter ------------------------------------------------ */
    $router->get('locale/{code:[a-z]{2}}', 'LocaleController@set')->name('locale.set');
    $router->post('newsletter', 'NewsletterController@store')
        ->middleware('throttle:5,120')
        ->name('newsletter.store');
    $router->get('newsletter/confirm/{token:[a-f0-9]+}', 'NewsletterController@confirm')
        ->name('newsletter.confirm');

    /* Cart --------------------------------------------------------------- */
    $router->get('cart', 'CartController@index')->name('cart.index');
    $router->post('cart/add', 'CartController@add')
        ->middleware('throttle:40,60')
        ->name('cart.add');
    $router->post('cart/update', 'CartController@update')->name('cart.update');
    $router->post('cart/remove', 'CartController@remove')->name('cart.remove');
    $router->post('cart/clear', 'CartController@clear')->name('cart.clear');
    $router->post('cart/coupon', 'CartController@applyCoupon')
        ->middleware('throttle:10,60')
        ->name('cart.coupon');
    $router->post('cart/coupon/remove', 'CartController@removeCoupon')->name('cart.coupon.remove');
});
