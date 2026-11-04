<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Replies inside a ticket, from customer or staff.
 */
final class CreateSupportMessagesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('support_messages', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Replies inside a ticket, from customer or staff.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('ticket_id', 'biginteger', ['signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('is_staff', 'boolean', ['default' => false])
            ->addColumn('body', 'text', [])
            ->addColumn('attachment', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['ticket_id', 'created_at'], ['name' => 'support_messages_ticket_index']);
        $table->addIndex(['user_id'], ['name' => 'support_messages_user_index']);

        $table->addForeignKeyWithName('fk_messages_ticket', 'ticket_id', 'support_tickets', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_messages_user', 'user_id', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('support_messages')->drop()->save();
    }
}
