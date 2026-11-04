<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Permissions granted to each role.
 */
final class CreateRolePermissionsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('role_permissions', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Permissions granted to each role.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('role_id', 'biginteger', ['signed' => false])
            ->addColumn('permission_id', 'biginteger', ['signed' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['role_id', 'permission_id'], ['unique' => true, 'name' => 'role_permissions_role_permission_unique']);
        $table->addIndex(['permission_id'], ['name' => 'role_permissions_permission_index']);

        $table->addForeignKeyWithName('fk_role_permissions_role', 'role_id', 'roles', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_role_permissions_permission', 'permission_id', 'permissions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('role_permissions')->drop()->save();
    }
}
