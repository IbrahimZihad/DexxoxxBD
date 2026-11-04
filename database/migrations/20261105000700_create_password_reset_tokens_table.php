<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Single-use hashed password-reset tokens.
 */
final class CreatePasswordResetTokensTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('password_reset_tokens', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Single-use hashed password-reset tokens.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 190, 'null' => false])
            ->addColumn('token_hash', 'char', ['limit' => 64, 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('consumed_at', 'datetime', ['null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['token_hash'], ['unique' => true, 'name' => 'password_tokens_hash_unique']);
        $table->addIndex(['email'], ['name' => 'password_tokens_email_index']);
        $table->addIndex(['user_id'], ['name' => 'password_tokens_user_index']);

        $table->addForeignKeyWithName('fk_password_tokens_user', 'user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('password_reset_tokens')->drop()->save();
    }
}
