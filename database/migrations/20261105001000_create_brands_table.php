<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Service brands (AI, streaming, VPN vendors and so on).
 */
final class CreateBrandsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('brands', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Service brands (AI, streaming, VPN vendors and so on).',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('slug', 'string', ['limit' => 140])
            ->addColumn('logo', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('website', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('country', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 1])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'datetime', ['null' => true]);

        $table->addIndex(['slug'], ['unique' => true, 'name' => 'brands_slug_unique']);
        $table->addIndex(['name'], ['name' => 'brands_name_index']);
        $table->addIndex(['status'], ['name' => 'brands_status_index']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('brands')->drop()->save();
    }
}
