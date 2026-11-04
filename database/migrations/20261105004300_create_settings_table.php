<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Key/value website settings grouped for the admin CMS screen.
 */
final class CreateSettingsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('settings', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Key/value website settings grouped for the admin CMS screen.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('key', 'string', ['limit' => 140, 'null' => false])
            ->addColumn('value', 'text', ['null' => true])
            ->addColumn('type', 'enum', [
            'values' => ['string', 'text', 'number', 'boolean', 'json', 'image', 'color'],
            'default' => 'string',
            'null' => false,
        ])
            ->addColumn('group_name', 'string', ['limit' => 60, 'default' => 'general', 'null' => false])
            ->addColumn('label', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('is_public', 'boolean', ['default' => true, 'comment' => 'Read by the storefront, not only the admin', 'null' => false])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['key'], ['unique' => true, 'name' => 'settings_key_unique']);
        $table->addIndex(['group_name', 'sort_order'], ['name' => 'settings_group_index']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('settings')->drop()->save();
    }
}
