<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Active and expired entitlements shown in the customer dashboard.
 */
final class CreateCustomerSubscriptionsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('customer_subscriptions', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Active and expired entitlements shown in the customer dashboard.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('order_item_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('product_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('variation_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('status', 'enum', [
            'values' => ['pending', 'active', 'expired', 'cancelled', 'suspended'],
            'default' => 'pending',
            'null' => false,
        ])
            ->addColumn('starts_at', 'datetime', ['null' => true])
            ->addColumn('expires_at', 'datetime', ['null' => true])
            ->addColumn('activated_at', 'datetime', ['null' => true])
            ->addColumn('reminder_sent_at', 'datetime', ['null' => true])
            ->addColumn('auto_renew', 'boolean', ['default' => false, 'comment' => 'Recurring billing is opt-in, never assumed', 'null' => false])
            ->addColumn('device_limit', 'smallinteger', ['signed' => false, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['user_id', 'status'], ['name' => 'subscriptions_user_status_index']);
        $table->addIndex(['expires_at'], ['name' => 'subscriptions_expiry_index']);
        $table->addIndex(['product_id', 'status'], ['name' => 'subscriptions_product_index']);
        $table->addIndex(['order_id'], ['name' => 'subscriptions_order_index']);
        $table->addIndex(['order_item_id'], ['unique' => true, 'name' => 'subscriptions_order_item_unique']);

        $table->addForeignKeyWithName('fk_subscriptions_user', 'user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_subscriptions_order', 'order_id', 'orders', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_subscriptions_order_item', 'order_item_id', 'order_items', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_subscriptions_product', 'product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_subscriptions_variation', 'variation_id', 'product_variations', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('customer_subscriptions')->drop()->save();
    }
}
