<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Header, mobile and footer navigation items.
 */
final class CreateMenusTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('menus', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Header, mobile and footer navigation items.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('location', 'enum', ['values' => ['header', 'mobile', 'footer'], 'default' => 'header', 'null' => false])
            ->addColumn('parent_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('label', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('target_type', 'enum', ['values' => ['url', 'page', 'category', 'product'], 'default' => 'url', 'null' => false])
            ->addColumn('target_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('url', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('icon', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('open_in_new_tab', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 1, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['location', 'status', 'sort_order'], ['name' => 'menus_location_index']);
        $table->addIndex(['parent_id'], ['name' => 'menus_parent_index']);

        $table->addForeignKeyWithName('fk_menus_parent', 'parent_id', 'menus', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('menus')->drop()->save();
    }
}
