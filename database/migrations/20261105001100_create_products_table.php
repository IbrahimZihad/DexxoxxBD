<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Sellable subscriptions; pricing lives on product_variations.
 */
final class CreateProductsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('products', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Sellable subscriptions; pricing lives on product_variations.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('category_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('brand_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 190, 'null' => false])
            ->addColumn('slug', 'string', ['limit' => 200, 'null' => false])
            ->addColumn('short_description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('image', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('badge', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('account_type', 'string', ['limit' => 60, 'null' => true, 'comment' => 'Individual, family, shared, seat...'])
            ->addColumn('delivery_method', 'string', ['limit' => 30, 'default' => 'manual', 'null' => false])
            ->addColumn('warranty_info', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('support_info', 'text', ['null' => true])
            ->addColumn('activation_instructions', 'text', ['null' => true])
            ->addColumn('is_featured', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('is_bestseller', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('is_new_arrival', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('status', 'enum', ['values' => ['draft', 'published', 'archived'], 'default' => 'draft', 'null' => false])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('view_count', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('sales_count', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('rating_avg', 'decimal', ['precision' => 3, 'scale' => 2, 'default' => '0.00', 'null' => false])
            ->addColumn('rating_count', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('meta_title', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('meta_description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'datetime', ['null' => true]);

        $table->addIndex(['slug'], ['unique' => true, 'name' => 'products_slug_unique']);
        $table->addIndex(['category_id'], ['name' => 'products_category_index']);
        $table->addIndex(['brand_id'], ['name' => 'products_brand_index']);
        $table->addIndex(['status', 'deleted_at'], ['name' => 'products_status_deleted_index']);
        $table->addIndex(['is_featured'], ['name' => 'products_featured_index']);
        $table->addIndex(['is_bestseller'], ['name' => 'products_bestseller_index']);
        $table->addIndex(['is_new_arrival'], ['name' => 'products_new_arrival_index']);
        $table->addIndex(['sales_count'], ['name' => 'products_sales_index']);
        $table->addIndex(['rating_avg', 'rating_count'], ['name' => 'products_rating_index']);
        $table->addIndex(['created_at'], ['name' => 'products_created_index']);
        $table->addIndex(['name', 'short_description'], ['type' => 'fulltext', 'name' => 'products_search_fulltext']);

        $table->addForeignKeyWithName('fk_products_category', 'category_id', 'categories', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_products_brand', 'brand_id', 'brands', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('products')->drop()->save();
    }
}
