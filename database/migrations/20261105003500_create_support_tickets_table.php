<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Customer support conversations.
 */
final class CreateSupportTicketsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('support_tickets', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Customer support conversations.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('ticket_number', 'string', ['limit' => 24, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('department', 'enum', [
            'values' => ['general', 'order', 'payment', 'delivery', 'technical'],
            'default' => 'general',
            'null' => false,
        ])
            ->addColumn('subject', 'string', ['limit' => 190, 'null' => false])
            ->addColumn('body', 'text', ['null' => false])
            ->addColumn('priority', 'enum', ['values' => ['low', 'normal', 'high', 'urgent'], 'default' => 'normal', 'null' => false])
            ->addColumn('status', 'enum', [
            'values' => ['open', 'answered', 'customer_reply', 'in_progress', 'resolved', 'closed'],
            'default' => 'open',
            'null' => false,
        ])
            ->addColumn('assigned_to', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('last_reply_at', 'datetime', ['null' => true])
            ->addColumn('closed_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'datetime', ['null' => true]);

        $table->addIndex(['ticket_number'], ['unique' => true, 'name' => 'support_tickets_number_unique']);
        $table->addIndex(['user_id', 'status'], ['name' => 'support_tickets_user_status_index']);
        $table->addIndex(['status', 'priority'], ['name' => 'support_tickets_status_priority_index']);
        $table->addIndex(['assigned_to'], ['name' => 'support_tickets_assignee_index']);
        $table->addIndex(['order_id'], ['name' => 'support_tickets_order_index']);

        $table->addForeignKeyWithName('fk_tickets_user', 'user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_tickets_order', 'order_id', 'orders', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_tickets_assignee', 'assigned_to', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('support_tickets')->drop()->save();
    }
}
