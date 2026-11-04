<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Cart lines with server-side priced snapshots.
 */
final class CreateCartItemsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('cart_items', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Cart lines with server-side priced snapshots.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('cart_id', 'biginteger', ['signed' => false])
            ->addColumn('product_id', 'biginteger', ['signed' => false])
            ->addColumn('variation_id', 'biginteger', ['signed' => false])
            ->addColumn('quantity', 'integer', ['signed' => false, 'default' => 1])
            ->addColumn('unit_price', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('product_name', 'string', ['limit' => 190])
            ->addColumn('variation_label', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('sku', 'string', ['limit' => 80])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['cart_id', 'variation_id'], ['unique' => true, 'name' => 'cart_items_cart_variation_unique']);
        $table->addIndex(['product_id'], ['name' => 'cart_items_product_index']);
        $table->addIndex(['variation_id'], ['name' => 'cart_items_variation_index']);

        $table->addForeignKeyWithName('fk_cart_items_cart', 'cart_id', 'carts', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_cart_items_product', 'product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_cart_items_variation', 'variation_id', 'product_variations', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('cart_items')->drop()->save();
    }
}
