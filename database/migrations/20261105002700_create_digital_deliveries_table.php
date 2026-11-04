<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Delivery of one inventory item against one order line.
 */
final class CreateDigitalDeliveriesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('digital_deliveries', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Delivery of one inventory item against one order line.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('order_item_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('digital_inventory_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('method', 'string', ['limit' => 30, 'default' => 'auto_key', 'null' => false])
            ->addColumn('status', 'enum', ['values' => ['pending', 'delivered', 'failed', 'revoked'], 'default' => 'pending', 'null' => false])
            ->addColumn('delivered_at', 'datetime', ['null' => true])
            ->addColumn('emailed_at', 'datetime', ['null' => true])
            ->addColumn('viewed_at', 'datetime', ['null' => true])
            ->addColumn('view_count', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('attempts', 'smallinteger', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('note', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['order_id'], ['name' => 'digital_deliveries_order_index']);
        $table->addIndex(['order_item_id', 'digital_inventory_id'], ['unique' => true, 'name' => 'delivery_line_inventory_unique']);
        $table->addIndex(['digital_inventory_id'], ['name' => 'deliveries_inventory_index']);
        $table->addIndex(['user_id'], ['name' => 'deliveries_user_index']);

        $table->addForeignKeyWithName('fk_deliveries_order', 'order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_deliveries_order_item', 'order_item_id', 'order_items', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_deliveries_inventory', 'digital_inventory_id', 'digital_inventory', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_deliveries_user', 'user_id', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('digital_deliveries')->drop()->save();
    }
}
