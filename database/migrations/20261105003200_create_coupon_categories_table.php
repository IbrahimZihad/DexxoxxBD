<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Categories a coupon is restricted to.
 */
final class CreateCouponCategoriesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('coupon_categories', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Categories a coupon is restricted to.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('coupon_id', 'biginteger', ['signed' => false])
            ->addColumn('category_id', 'biginteger', ['signed' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['coupon_id', 'category_id'], ['unique' => true, 'name' => 'coupon_categories_pair_unique']);
        $table->addIndex(['category_id'], ['name' => 'coupon_categories_category_index']);

        $table->addForeignKeyWithName('fk_coupon_categories_coupon', 'coupon_id', 'coupons', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_coupon_categories_category', 'category_id', 'categories', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('coupon_categories')->drop()->save();
    }
}
