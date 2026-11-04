<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Customer orders with server-side totals and status timeline.
 */
final class CreateOrdersTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('orders', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Customer orders with server-side totals and status timeline.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('order_number', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('status', 'enum', [
            'values' => [
                        'pending',
                        'awaiting_payment',
                        'processing',
                        'paid',
                        'delivered',
                        'completed',
                        'cancelled',
                        'refunded',
                        'failed',
                    ],
            'default' => 'pending',
            'null' => false,
        ])
            ->addColumn('subtotal', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00', 'null' => false])
            ->addColumn('discount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00', 'null' => false])
            ->addColumn('coupon_code', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('total', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00', 'null' => false])
            ->addColumn('grand_total', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00', 'null' => false])
            ->addColumn('refunded_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00', 'null' => false])
            ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'BDT', 'null' => false])
            ->addColumn('payment_method', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('payment_status', 'enum', [
            'values' => ['unpaid', 'pending', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'],
            'default' => 'unpaid',
            'null' => false,
        ])
            ->addColumn('payment_reference', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('customer_name', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('customer_email', 'string', ['limit' => 190, 'null' => false])
            ->addColumn('customer_phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('billing_address', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('billing_city', 'string', ['limit' => 80, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('internal_notes', 'text', ['null' => true])
            ->addColumn('expires_at', 'datetime', ['null' => true])
            ->addColumn('paid_at', 'datetime', ['null' => true])
            ->addColumn('delivered_at', 'datetime', ['null' => true])
            ->addColumn('cancelled_at', 'datetime', ['null' => true])
            ->addColumn('cancel_reason', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('refunded_at', 'datetime', ['null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'datetime', ['null' => true]);

        $table->addIndex(['order_number'], ['unique' => true, 'name' => 'orders_number_unique']);
        $table->addIndex(['user_id'], ['name' => 'orders_user_index']);
        $table->addIndex(['customer_email'], ['name' => 'orders_email_index']);
        $table->addIndex(['status', 'created_at'], ['name' => 'orders_status_created_index']);
        $table->addIndex(['payment_status', 'created_at'], ['name' => 'orders_payment_status_index']);
        $table->addIndex(['payment_method'], ['name' => 'orders_method_index']);
        $table->addIndex(['grand_total'], ['name' => 'orders_grand_total_index']);
        $table->addIndex(['expires_at'], ['name' => 'orders_expiry_index']);

        $table->addForeignKeyWithName('fk_orders_user', 'user_id', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('orders')->drop()->save();
    }
}
