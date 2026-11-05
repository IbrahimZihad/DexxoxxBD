<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $table
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('email', 'string', ['limit' => 180])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('role', 'enum', ['values' => ['customer', 'staff', 'admin'], 'default' => 'customer'])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('marketing_opt_in', 'boolean', ['default' => false])
            ->addColumn('last_login_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['email'], ['unique' => true, 'name' => 'users_email_unique'])
            ->addIndex(['role'], ['name' => 'users_role_index'])
            ->create();
    }
}
