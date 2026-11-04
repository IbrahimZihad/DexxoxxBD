<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Hashed email-verification tokens; the raw token never lands in
 * MySQL.
 */
final class CreateEmailVerificationTokensTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('email_verification_tokens', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Hashed email-verification tokens; the raw token never lands in MySQL.',
        ]);

        $table
        ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('token_hash', 'char', ['limit' => 64])
            ->addColumn('expires_at', 'datetime', [])
            ->addColumn('consumed_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);
        ;

        $table->addIndex(['token_hash'], ['unique' => true, 'name' => 'email_tokens_hash_unique']);
        $table->addIndex(['user_id', 'expires_at'], ['name' => 'email_tokens_user_expiry_index']);

        $table->addForeignKeyWithName('fk_email_tokens_user', 'user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('email_verification_tokens')->drop()->save();
    }
}
