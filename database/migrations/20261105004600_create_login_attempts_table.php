<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Hashed-identifier login throttle log; raw emails are never stored.
 */
final class CreateLoginAttemptsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('login_attempts', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Hashed-identifier login throttle log; raw emails are never stored.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('identifier', 'char', ['limit' => 64, 'null' => false])
            ->addColumn('ip', 'string', ['limit' => 45, 'null' => false])
            ->addColumn('user_agent', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('successful', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('attempted_at', 'datetime', ['null' => false]);

        $table->addIndex(['identifier', 'successful', 'attempted_at'], ['name' => 'login_attempts_identifier_index']);
        $table->addIndex(['ip', 'successful', 'attempted_at'], ['name' => 'login_attempts_ip_index']);
        $table->addIndex(['user_id'], ['name' => 'login_attempts_user_index']);
        $table->addIndex(['attempted_at'], ['name' => 'login_attempts_time_index']);

        $table->addForeignKeyWithName('fk_login_attempts_user', 'user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('login_attempts')->drop()->save();
    }
}
