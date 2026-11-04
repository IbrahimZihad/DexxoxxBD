<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Every status transition, including staff notes.
 */
final class CreateOrderStatusHistoryTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('order_status_history', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Every status transition, including staff notes.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('from_status', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('to_status', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('note', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('is_customer_visible', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['order_id', 'created_at'], ['name' => 'order_status_history_order_index']);
        $table->addIndex(['user_id'], ['name' => 'order_status_history_user_index']);

        $table->addForeignKeyWithName('fk_status_history_order', 'order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_status_history_user', 'user_id', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('order_status_history')->drop()->save();
    }
}
