<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Replay protection for checkout, webhooks and job dispatch.
 */
final class CreateIdempotencyKeysTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('idempotency_keys', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Replay protection for checkout, webhooks and job dispatch.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('key', 'string', ['limit' => 190, 'null' => false])
            ->addColumn('scope', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('request_hash', 'char', ['limit' => 64, 'null' => true])
            ->addColumn('status', 'enum', ['values' => ['in_progress', 'completed', 'failed'], 'default' => 'in_progress', 'null' => false])
            ->addColumn('response_code', 'smallinteger', ['signed' => false, 'null' => true])
            ->addColumn('response_body', 'text', ['null' => true])
            ->addColumn('resource_type', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('resource_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('expires_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['scope', 'key'], ['unique' => true, 'name' => 'idempotency_key_scope_unique']);
        $table->addIndex(['status', 'expires_at'], ['name' => 'idempotency_expiry_index']);
        $table->addIndex(['user_id'], ['name' => 'idempotency_user_index']);

        $table->addForeignKeyWithName('fk_idempotency_user', 'user_id', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('idempotency_keys')->drop()->save();
    }
}
