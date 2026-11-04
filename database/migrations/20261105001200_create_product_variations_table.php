<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * One plan per row: SKU, price, duration, stock and delivery.
 */
final class CreateProductVariationsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('product_variations', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'One plan per row: SKU, price, duration, stock and delivery.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('product_id', 'biginteger', ['signed' => false])
            ->addColumn('label', 'string', ['limit' => 120])
            ->addColumn('sku', 'string', ['limit' => 80])
            ->addColumn('price', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('compare_price', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
            ->addColumn('cost_price', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
            ->addColumn('duration_days', 'integer', ['signed' => false, 'default' => 30])
            ->addColumn('duration_label', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('device_limit', 'smallinteger', ['signed' => false, 'null' => true])
            ->addColumn('account_type', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('delivery_method', 'enum', ['values' => ['auto_key', 'auto_code', 'manual', 'account_transfer'], 'default' => 'manual'])
            ->addColumn('stock', 'integer', ['default' => -1, 'comment' => '-1 means not stock limited'])
            ->addColumn('min_quantity', 'smallinteger', ['signed' => false, 'default' => 1])
            ->addColumn('max_quantity', 'smallinteger', ['signed' => false, 'default' => 10])
            ->addColumn('warranty_days', 'smallinteger', ['signed' => false, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('sales_count', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 1])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'datetime', ['null' => true]);

        $table->addIndex(['sku'], ['unique' => true, 'name' => 'variations_sku_unique']);
        $table->addIndex(['product_id', 'status', 'deleted_at'], ['name' => 'variations_product_status_index']);
        $table->addIndex(['price'], ['name' => 'variations_price_index']);
        $table->addIndex(['duration_days'], ['name' => 'variations_duration_index']);
        $table->addIndex(['stock'], ['name' => 'variations_stock_index']);
        $table->addIndex(['delivery_method'], ['name' => 'variations_delivery_index']);

        $table->addForeignKeyWithName('fk_variations_product', 'product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('product_variations')->drop()->save();
    }
}
