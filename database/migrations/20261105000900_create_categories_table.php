<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Two-level category tree for the storefront.
 */
final class CreateCategoriesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('categories', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Two-level category tree for the storefront.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('parent_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('slug', 'string', ['limit' => 140])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('image', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('icon', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('color', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('is_featured', 'boolean', ['default' => false])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 1])
            ->addColumn('meta_title', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('meta_description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'datetime', ['null' => true]);

        $table->addIndex(['slug'], ['unique' => true, 'name' => 'categories_slug_unique']);
        $table->addIndex(['parent_id'], ['name' => 'categories_parent_index']);
        $table->addIndex(['status', 'sort_order'], ['name' => 'categories_status_sort_index']);

        $table->addForeignKeyWithName('fk_categories_parent', 'parent_id', 'categories', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('categories')->drop()->save();
    }
}
