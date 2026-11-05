<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOrdersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('orders', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $table
            ->addColumn('order_no', 'string', ['limit' => 40])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('status', 'enum', [
                'values' => ['pending', 'processing', 'completed', 'cancelled', 'failed'],
                'default' => 'pending',
            ])
            // Nothing outside the admin verification step may write "paid".
            ->addColumn('payment_status', 'enum', [
                'values' => ['unpaid', 'pending_verification', 'paid', 'refunded'],
                'default' => 'unpaid',
            ])
            ->addColumn('fulfillment_status', 'enum', [
                'values' => ['unassigned', 'assigned', 'delivered'],
                'default' => 'unassigned',
            ])
            ->addColumn('subtotal', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('discount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('total', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('coupon_code', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('customer_name', 'string', ['limit' => 120])
            ->addColumn('email', 'string', ['limit' => 180])
            ->addColumn('phone', 'string', ['limit' => 20])
            ->addColumn('note', 'string', ['limit' => 500, 'null' => true])
            // What the customer wants delivered (their own email, device count).
            // Encrypted at rest with APP_KEY.
            ->addColumn('delivery_request', 'text', ['null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('verified_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('paid_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['order_no'], ['unique' => true, 'name' => 'orders_no_unique'])
            ->addIndex(['status'], ['name' => 'orders_status_index'])
            ->addIndex(['payment_status'], ['name' => 'orders_payment_status_index'])
            ->addIndex(['email'], ['name' => 'orders_email_index'])
            ->addIndex(['created_at'], ['name' => 'orders_created_index'])
            ->create();

        $table
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'SET NULL',
                'update' => 'CASCADE',
                'constraint' => 'orders_user_fk',
            ])
            ->update();
    }
}
