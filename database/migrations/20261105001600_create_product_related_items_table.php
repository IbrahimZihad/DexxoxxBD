<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Curated cross-sells; falls back to category/brand matches.
 */
final class CreateProductRelatedItemsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('product_related_items', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Curated cross-sells; falls back to category/brand matches.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('product_id', 'biginteger', ['signed' => false])
            ->addColumn('related_product_id', 'biginteger', ['signed' => false])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['product_id', 'related_product_id'], ['unique' => true, 'name' => 'related_pair_unique']);
        $table->addIndex(['related_product_id'], ['name' => 'related_reverse_index']);

        $table->addForeignKeyWithName('fk_related_product', 'product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_related_target_product', 'related_product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('product_related_items')->drop()->save();
    }
}
