<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Append-only record of privileged actions.
 */
final class CreateAuditLogsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('audit_logs', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Append-only record of privileged actions.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('action', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('subject_type', 'string', ['limit' => 80, 'null' => true])
            ->addColumn('subject_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('old_values', 'text', ['null' => true])
            ->addColumn('new_values', 'text', ['null' => true])
            ->addColumn('context', 'text', ['null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['action', 'created_at'], ['name' => 'audit_logs_action_index']);
        $table->addIndex(['user_id', 'created_at'], ['name' => 'audit_logs_user_index']);
        $table->addIndex(['subject_type', 'subject_id'], ['name' => 'audit_logs_subject_index']);
        $table->addIndex(['created_at'], ['name' => 'audit_logs_created_index']);

        $table->addForeignKeyWithName('fk_audit_logs_user', 'user_id', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('audit_logs')->drop()->save();
    }
}
