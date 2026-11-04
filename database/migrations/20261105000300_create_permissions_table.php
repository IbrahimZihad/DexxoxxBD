<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Individual abilities checked server-side, grouped by module.
 */
final class CreatePermissionsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('permissions', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Individual abilities checked server-side, grouped by module.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('slug', 'string', ['limit' => 120])
            ->addColumn('module', 'string', ['limit' => 60])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['slug'], ['unique' => true, 'name' => 'permissions_slug_unique']);
        $table->addIndex(['module'], ['name' => 'permissions_module_index']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('permissions')->drop()->save();
    }
}
