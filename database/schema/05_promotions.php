<?php

/**
 * Promotion schema: coupons with usage limits, per-product/category scope and
 * an auditable usage ledger.
 */

declare(strict_types=1);

return [
    [
        'version' => '20261105003000',
        'class' => 'CreateCouponsTable',
        'table' => 'coupons',
        'comment' => 'Percentage or fixed-amount discount codes.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['code', 'string', ['limit' => 60]],
            ['name', 'string', ['limit' => 120]],
            ['description', 'string', ['limit' => 500, 'null' => true]],
            ['type', 'enum', ['values' => ['percent', 'fixed'], 'default' => 'percent']],
            ['value', 'decimal', ['precision' => 12, 'scale' => 2]],
            ['min_order_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00']],
            ['max_discount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true]],
            ['restriction_mode', 'enum', ['values' => ['all', 'products', 'categories'], 'default' => 'all']],
            ['usage_limit', 'integer', ['signed' => false, 'null' => true]],
            ['usage_limit_per_user', 'smallinteger', ['signed' => false, 'null' => true]],
            ['usage_count', 'integer', ['signed' => false, 'default' => 0]],
            ['first_order_only', 'boolean', ['default' => false]],
            ['auto_apply', 'boolean', ['default' => false]],
            ['stackable', 'boolean', ['default' => false]],
            ['starts_at', 'datetime', ['null' => true]],
            ['expires_at', 'datetime', ['null' => true]],
            ['status', 'tinyinteger', ['signed' => false, 'default' => 1]],
            ['created_by', 'biginteger', ['signed' => false, 'null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
            ['deleted_at', 'datetime', ['null' => true]],
        ],
        'indexes' => [
            ['coupons_code_unique', ['code'], ['unique' => true]],
            ['coupons_status_window_index', ['status', 'starts_at', 'expires_at']],
            ['coupons_auto_apply_index', ['auto_apply', 'status']],
        ],
        'keys' => [
            ['name' => 'fk_coupons_creator', 'column' => 'created_by', 'table' => 'users', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105003100',
        'class' => 'CreateCouponProductsTable',
        'table' => 'coupon_products',
        'comment' => 'Products a coupon is restricted to.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['coupon_id', 'biginteger', ['signed' => false]],
            ['product_id', 'biginteger', ['signed' => false]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['coupon_products_pair_unique', ['coupon_id', 'product_id'], ['unique' => true]],
            ['coupon_products_product_index', ['product_id']],
        ],
        'keys' => [
            ['name' => 'fk_coupon_products_coupon', 'column' => 'coupon_id', 'table' => 'coupons', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_coupon_products_product', 'column' => 'product_id', 'table' => 'products', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105003200',
        'class' => 'CreateCouponCategoriesTable',
        'table' => 'coupon_categories',
        'comment' => 'Categories a coupon is restricted to.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['coupon_id', 'biginteger', ['signed' => false]],
            ['category_id', 'biginteger', ['signed' => false]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['coupon_categories_pair_unique', ['coupon_id', 'category_id'], ['unique' => true]],
            ['coupon_categories_category_index', ['category_id']],
        ],
        'keys' => [
            ['name' => 'fk_coupon_categories_coupon', 'column' => 'coupon_id', 'table' => 'coupons', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_coupon_categories_category', 'column' => 'category_id', 'table' => 'categories', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105003300',
        'class' => 'CreateCouponUsagesTable',
        'table' => 'coupon_usages',
        'comment' => 'One row per redeemed coupon, the basis for usage limits.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['coupon_id', 'biginteger', ['signed' => false]],
            ['order_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['user_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['cart_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['discount_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00']],
            ['used_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['coupon_usages_coupon_user_index', ['coupon_id', 'user_id']],
            ['coupon_usages_order_index', ['order_id']],
            ['coupon_usages_cart_index', ['cart_id']],
        ],
        'keys' => [
            ['name' => 'fk_coupon_usages_coupon', 'column' => 'coupon_id', 'table' => 'coupons', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_coupon_usages_order', 'column' => 'order_id', 'table' => 'orders', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
            ['name' => 'fk_coupon_usages_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
            ['name' => 'fk_coupon_usages_cart', 'column' => 'cart_id', 'table' => 'carts', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
        ],
    ],
];
