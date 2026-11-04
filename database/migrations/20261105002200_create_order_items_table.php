<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Immutable purchase snapshot: name, plan, SKU and price at purchase
 * time.
 */
final class CreateOrderItemsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('order_items', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Immutable purchase snapshot: name, plan, SKU and price at purchase time.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('order_id', 'biginteger', ['signed' => false])
            ->addColumn('product_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('variation_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('product_name', 'string', ['limit' => 190])
            ->addColumn('variation_label', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('sku', 'string', ['limit' => 80])
            ->addColumn('unit_price', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('quantity', 'integer', ['signed' => false, 'default' => 1])
            ->addColumn('discount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('line_total', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('duration_days', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('delivery_method', 'string', ['limit' => 30, 'default' => 'manual'])
            ->addColumn('warranty_info', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('details', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['order_id'], ['name' => 'order_items_order_index']);
        $table->addIndex(['product_id'], ['name' => 'order_items_product_index']);
        $table->addIndex(['variation_id'], ['name' => 'order_items_variation_index']);

        $table->addForeignKeyWithName('fk_order_items_order', 'order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_order_items_product', 'product_id', 'products', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_order_items_variation', 'variation_id', 'product_variations', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('order_items')->drop()->save();
    }
}
