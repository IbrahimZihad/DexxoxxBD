<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOrderItemsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('order_items', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $table
            ->addColumn('order_id', 'integer', ['signed' => false])
            ->addColumn('plan_id', 'integer', ['signed' => false, 'null' => true])
            // Names and prices are copied, not joined, so an old order still
            // reads correctly after a plan is renamed or repriced.
            ->addColumn('service_name', 'string', ['limit' => 80])
            ->addColumn('plan_title', 'string', ['limit' => 120])
            ->addColumn('unit_price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('quantity', 'integer', ['default' => 1])
            ->addColumn('line_total', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('status', 'enum', ['values' => ['pending', 'assigned', 'delivered', 'failed'], 'default' => 'pending'])
            // The account details handed over; stored AES-256-GCM encrypted.
            ->addColumn('delivery_note', 'text', ['null' => true])
            ->addColumn('delivered_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['order_id'], ['name' => 'order_items_order_index'])
            ->addIndex(['status'], ['name' => 'order_items_status_index'])
            ->create();

        $table
            ->addForeignKey('order_id', 'orders', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'order_items_order_fk',
            ])
            ->addForeignKey('plan_id', 'plans', 'id', [
                'delete' => 'SET NULL',
                'update' => 'CASCADE',
                'constraint' => 'order_items_plan_fk',
            ])
            ->update();
    }
}
