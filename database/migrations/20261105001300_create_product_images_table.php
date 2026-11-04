<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Gallery images per product.
 */
final class CreateProductImagesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('product_images', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Gallery images per product.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('product_id', 'biginteger', ['signed' => false])
            ->addColumn('path', 'string', ['limit' => 255])
            ->addColumn('alt', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('is_primary', 'boolean', ['default' => false])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['product_id', 'sort_order'], ['name' => 'product_images_product_index']);

        $table->addForeignKeyWithName('fk_product_images_product', 'product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('product_images')->drop()->save();
    }
}
