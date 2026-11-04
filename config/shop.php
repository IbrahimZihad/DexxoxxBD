<?php

/**
 * Storefront commerce rules. Cart, coupon and stock code paths read these keys
 * with `shop.*` so a deployment can tune limits without touching classes.
 */

declare(strict_types=1);

return [
    'currency' => env('DEFAULT_CURRENCY', 'BDT'),

    // Units of one plan a single cart may hold.
    'max_per_cart' => (int) env('SHOP_MAX_PER_CART', 5),

    // Distinct plan lines allowed before the cart refuses more.
    'max_cart_lines' => (int) env('SHOP_MAX_CART_LINES', 20),

    'minimum_order_amount' => (float) env('SHOP_MIN_ORDER_AMOUNT', 0),

    'order_number_prefix' => (string) env('SHOP_ORDER_PREFIX', 'DXB'),

    'guest_checkout' => filter_var(env('SHOP_GUEST_CHECKOUT', true), FILTER_VALIDATE_BOOLEAN),

    'allow_negative_stock' => filter_var(env('SHOP_ALLOW_NEGATIVE_STOCK', false), FILTER_VALIDATE_BOOLEAN),

    'low_stock_threshold' => (int) env('SHOP_LOW_STOCK_THRESHOLD', 5),

    // Carts untouched for this many days are reported to the abandoned-cart cron.
    'abandoned_cart_days' => (int) env('SHOP_ABANDONED_CART_DAYS', 7),

    // Digital goods are delivered by key, so no shipping fee applies by default.
    'delivery_fee' => (float) env('SHOP_DELIVERY_FEE', 0),

    'free_delivery_threshold' => (float) env('SHOP_FREE_DELIVERY_THRESHOLD', 0),

    // How long an order stays open while a payment is in flight.
    'payment_hold_minutes' => (int) env('SHOP_PAYMENT_HOLD_MINUTES', 30),
];
