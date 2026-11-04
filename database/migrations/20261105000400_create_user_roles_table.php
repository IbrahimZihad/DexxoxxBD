<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Role assignments (a user may hold several roles).
 */
final class CreateUserRolesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('user_roles', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Role assignments (a user may hold several roles).',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('assigned_by', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['user_id', 'role_id'], ['unique' => true, 'name' => 'user_roles_user_role_unique']);
        $table->addIndex(['role_id'], ['name' => 'user_roles_role_index']);

        $table->addForeignKeyWithName('fk_user_roles_user', 'user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_user_roles_role', 'role_id', 'roles', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_user_roles_assigner', 'assigned_by', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('user_roles')->drop()->save();
    }
}
