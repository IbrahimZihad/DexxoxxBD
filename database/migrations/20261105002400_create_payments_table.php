<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Payment attempts per order; card data is never stored.
 */
final class CreatePaymentsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('payments', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Payment attempts per order; card data is never stored.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('method', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('gateway', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('status', 'enum', [
            'values' => ['pending', 'completed', 'failed', 'cancelled', 'refunded'],
            'default' => 'pending',
            'null' => false,
        ])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'BDT', 'null' => false])
            ->addColumn('transaction_reference', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('gateway_payment_id', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('gateway_track_id', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('customer_account_hint', 'string', ['limit' => 60, 'null' => true, 'comment' => 'Masked account, never a full number'])
            ->addColumn('request_payload', 'json', ['null' => true])
            ->addColumn('response_payload', 'json', ['null' => true])
            ->addColumn('failure_reason', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('verified_at', 'datetime', ['null' => true])
            ->addColumn('verified_by', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('completed_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['order_id', 'created_at'], ['name' => 'payments_order_index']);
        $table->addIndex(['transaction_reference'], ['name' => 'payments_reference_index']);
        $table->addIndex(['gateway', 'gateway_payment_id'], ['name' => 'payments_gateway_payment_index']);
        $table->addIndex(['status'], ['name' => 'payments_status_index']);
        $table->addIndex(['verified_by'], ['name' => 'payments_verifier_index']);

        $table->addForeignKeyWithName('fk_payments_order', 'order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_payments_verifier', 'verified_by', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('payments')->drop()->save();
    }
}
