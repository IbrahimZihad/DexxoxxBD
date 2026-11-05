<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAuthSupportTables extends AbstractMigration
{
    public function change(): void
    {
        $resets = $this->table('password_resets', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $resets
            ->addColumn('email', 'string', ['limit' => 180])
            // Only the hash of the token is stored: a leaked table cannot be
            // replayed into a working reset link.
            ->addColumn('token_hash', 'string', ['limit' => 64])
            ->addColumn('expires_at', 'datetime')
            ->addColumn('used_at', 'datetime', ['null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addIndex(['email'], ['name' => 'resets_email_index'])
            ->addIndex(['token_hash'], ['name' => 'resets_token_index'])
            ->create();

        $logs = $this->table('admin_audit_logs', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $logs
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('user_name', 'string', ['limit' => 80])
            ->addColumn('action', 'string', ['limit' => 60])
            ->addColumn('entity', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('entity_id', 'integer', ['default' => 0])
            ->addColumn('description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addIndex(['created_at'], ['name' => 'audit_created_index'])
            ->addIndex(['user_id'], ['name' => 'audit_user_index'])
            ->addIndex(['action'], ['name' => 'audit_action_index'])
            ->create();
    }
}
