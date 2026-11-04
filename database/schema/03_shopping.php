<?php

/**
 * Shopping schema: guest and member carts plus wishlists. Cart lines snapshot
 * name, plan, SKU and unit price so catalog edits cannot rewrite history.
 */

declare(strict_types=1);

return [
    [
        'version' => '20261105001700',
        'class' => 'CreateCartsTable',
        'table' => 'carts',
        'comment' => 'One open cart per customer; guest carts are keyed by a random token.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['user_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['guest_token', 'char', ['limit' => 40, 'null' => true]],
            ['coupon_code', 'string', ['limit' => 60, 'null' => true]],
            ['merged_at', 'datetime', ['null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['carts_user_open_index', ['user_id', 'merged_at']],
            ['carts_guest_open_index', ['guest_token', 'merged_at']],
            ['carts_updated_index', ['updated_at']],
        ],
        'keys' => [
            ['name' => 'fk_carts_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105001800',
        'class' => 'CreateCartItemsTable',
        'table' => 'cart_items',
        'comment' => 'Cart lines with server-side priced snapshots.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['cart_id', 'biginteger', ['signed' => false]],
            ['product_id', 'biginteger', ['signed' => false]],
            ['variation_id', 'biginteger', ['signed' => false]],
            ['quantity', 'integer', ['signed' => false, 'default' => 1]],
            ['unit_price', 'decimal', ['precision' => 12, 'scale' => 2]],
            ['product_name', 'string', ['limit' => 190]],
            ['variation_label', 'string', ['limit' => 120, 'null' => true]],
            ['sku', 'string', ['limit' => 80]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['cart_items_cart_variation_unique', ['cart_id', 'variation_id'], ['unique' => true]],
            ['cart_items_product_index', ['product_id']],
            ['cart_items_variation_index', ['variation_id']],
        ],
        'keys' => [
            ['name' => 'fk_cart_items_cart', 'column' => 'cart_id', 'table' => 'carts', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_cart_items_product', 'column' => 'product_id', 'table' => 'products', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_cart_items_variation', 'column' => 'variation_id', 'table' => 'product_variations', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105001900',
        'class' => 'CreateWishlistsTable',
        'table' => 'wishlists',
        'comment' => 'Saved-for-later lists (one default list per customer).',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['user_id', 'biginteger', ['signed' => false]],
            ['name', 'string', ['limit' => 80, 'default' => 'default']],
            ['is_public', 'boolean', ['default' => false]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['wishlists_user_name_unique', ['user_id', 'name'], ['unique' => true]],
        ],
        'keys' => [
            ['name' => 'fk_wishlists_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105002000',
        'class' => 'CreateWishlistItemsTable',
        'table' => 'wishlist_items',
        'comment' => 'Products saved by a customer.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['wishlist_id', 'biginteger', ['signed' => false]],
            ['product_id', 'biginteger', ['signed' => false]],
            ['note', 'string', ['limit' => 255, 'null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['wishlist_items_pair_unique', ['wishlist_id', 'product_id'], ['unique' => true]],
            ['wishlist_items_product_index', ['product_id']],
        ],
        'keys' => [
            ['name' => 'fk_wishlist_items_list', 'column' => 'wishlist_id', 'table' => 'wishlists', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_wishlist_items_product', 'column' => 'product_id', 'table' => 'products', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
];
