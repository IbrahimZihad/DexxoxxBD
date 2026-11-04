<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Customer and staff accounts.
 */
final class CreateUsersTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('users', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Customer and staff accounts.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('username', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('password', 'string', ['limit' => 255])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 2, 'comment' => '0 suspended, 1 active, 2 awaiting verification'])
            ->addColumn('email_verified_at', 'datetime', ['null' => true])
            ->addColumn('locale', 'string', ['limit' => 5, 'default' => 'en'])
            ->addColumn('avatar', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('must_change_password', 'boolean', ['default' => false])
            ->addColumn('last_login_at', 'datetime', ['null' => true])
            ->addColumn('last_login_ip', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'datetime', ['null' => true]);

        $table->addIndex(['email'], ['unique' => true, 'name' => 'users_email_unique']);
        $table->addIndex(['username'], ['unique' => true, 'name' => 'users_username_unique']);
        $table->addIndex(['phone'], ['name' => 'users_phone_index']);
        $table->addIndex(['status', 'deleted_at'], ['name' => 'users_status_deleted_index']);
        $table->addIndex(['created_at'], ['name' => 'users_created_at_index']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('users')->drop()->save();
    }
}
