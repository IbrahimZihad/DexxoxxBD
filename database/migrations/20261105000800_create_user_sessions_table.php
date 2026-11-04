<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Ledger of live sessions so a customer can revoke devices.
 */
final class CreateUserSessionsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('user_sessions', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Ledger of live sessions so a customer can revoke devices.',
        ]);

        $table
        ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('guard', 'string', ['limit' => 20, 'default' => 'web'])
            ->addColumn('session_hash', 'char', ['limit' => 64])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('last_activity', 'datetime', ['null' => true])
            ->addColumn('expires_at', 'datetime', ['null' => true])
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);
        ;

        $table->addIndex(['session_hash'], ['name' => 'user_sessions_hash_index']);
        $table->addIndex(['user_id', 'guard', 'revoked_at'], ['name' => 'user_sessions_user_guard_index']);
        $table->addIndex(['expires_at'], ['name' => 'user_sessions_expiry_index']);

        $table->addForeignKeyWithName('fk_user_sessions_user', 'user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('user_sessions')->drop()->save();
    }
}
