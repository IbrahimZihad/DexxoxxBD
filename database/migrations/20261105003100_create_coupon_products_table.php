<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Products a coupon is restricted to.
 */
final class CreateCouponProductsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('coupon_products', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Products a coupon is restricted to.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('coupon_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('product_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['coupon_id', 'product_id'], ['unique' => true, 'name' => 'coupon_products_pair_unique']);
        $table->addIndex(['product_id'], ['name' => 'coupon_products_product_index']);

        $table->addForeignKeyWithName('fk_coupon_products_coupon', 'coupon_id', 'coupons', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_coupon_products_product', 'product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('coupon_products')->drop()->save();
    }
}
