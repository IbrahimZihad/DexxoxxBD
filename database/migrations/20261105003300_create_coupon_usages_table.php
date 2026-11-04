<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * One row per redeemed coupon, the basis for usage limits.
 */
final class CreateCouponUsagesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('coupon_usages', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'One row per redeemed coupon, the basis for usage limits.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('coupon_id', 'biginteger', ['signed' => false])
            ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('cart_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('discount_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('used_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['coupon_id', 'user_id'], ['name' => 'coupon_usages_coupon_user_index']);
        $table->addIndex(['order_id'], ['name' => 'coupon_usages_order_index']);
        $table->addIndex(['cart_id'], ['name' => 'coupon_usages_cart_index']);

        $table->addForeignKeyWithName('fk_coupon_usages_coupon', 'coupon_id', 'coupons', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_coupon_usages_order', 'order_id', 'orders', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_coupon_usages_user', 'user_id', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_coupon_usages_cart', 'cart_id', 'carts', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('coupon_usages')->drop()->save();
    }
}
