<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Database mirror of the log stream for the admin log viewer.
 */
final class CreateSystemLogsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('system_logs', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Database mirror of the log stream for the admin log viewer.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('level', 'string', ['limit' => 16])
            ->addColumn('channel', 'string', ['limit' => 40, 'default' => 'app'])
            ->addColumn('message', 'text', [])
            ->addColumn('context', 'text', ['null' => true])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['level', 'created_at'], ['name' => 'system_logs_level_index']);
        $table->addIndex(['channel', 'created_at'], ['name' => 'system_logs_channel_index']);

        $table->addForeignKeyWithName('fk_system_logs_user', 'user_id', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('system_logs')->drop()->save();
    }
}
