<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * In-app notification inbox.
 */
final class CreateNotificationsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('notifications', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'In-app notification inbox.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('type', 'string', ['limit' => 60, 'null' => false])
            ->addColumn('title', 'string', ['limit' => 190, 'null' => false])
            ->addColumn('message', 'text', ['null' => true])
            ->addColumn('link', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('icon', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('is_read', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('read_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['user_id', 'is_read', 'created_at'], ['name' => 'notifications_user_read_index']);
        $table->addIndex(['type'], ['name' => 'notifications_type_index']);

        $table->addForeignKeyWithName('fk_notifications_user', 'user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('notifications')->drop()->save();
    }
}
