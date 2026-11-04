<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * RBAC roles; level 1 is the most privileged.
 */
final class CreateRolesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('roles', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'RBAC roles; level 1 is the most privileged.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('slug', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('level', 'smallinteger', ['signed' => false, 'default' => 100, 'null' => false])
            ->addColumn('staff', 'boolean', ['default' => false, 'comment' => 'Grants access to the admin panel', 'null' => false])
            ->addColumn('is_system', 'boolean', ['default' => false, 'comment' => 'Cannot be deleted from the admin panel', 'null' => false])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 1, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['slug'], ['unique' => true, 'name' => 'roles_slug_unique']);
        $table->addIndex(['name'], ['unique' => true, 'name' => 'roles_name_unique']);
        $table->addIndex(['level'], ['name' => 'roles_level_index']);
        $table->addIndex(['staff', 'status'], ['name' => 'roles_staff_status_index']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('roles')->drop()->save();
    }
}
