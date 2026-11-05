<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOrderStatusHistoryTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('order_status_history', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $table
            ->addColumn('order_id', 'integer', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 32])
            ->addColumn('note', 'string', ['limit' => 300, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addIndex(['order_id'], ['name' => 'order_history_order_index'])
            ->create();

        $table
            ->addForeignKey('order_id', 'orders', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'order_history_order_fk',
            ])
            ->update();
    }
}
