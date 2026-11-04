<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Append-only gateway event log used to block duplicate callbacks.
 */
final class CreatePaymentTransactionsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('payment_transactions', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Append-only gateway event log used to block duplicate callbacks.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('payment_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('event', 'string', ['limit' => 60, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => true])
            ->addColumn('reference', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('idempotency_key', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('signature_valid', 'tinyinteger', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('source_ip', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('raw_payload', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['payment_id'], ['name' => 'payment_tx_payment_index']);
        $table->addIndex(['order_id', 'created_at'], ['name' => 'payment_tx_order_index']);
        $table->addIndex(['reference'], ['name' => 'payment_tx_reference_index']);
        $table->addIndex(['idempotency_key'], ['unique' => true, 'name' => 'payment_tx_idempotency_unique']);

        $table->addForeignKeyWithName('fk_payment_tx_payment', 'payment_id', 'payments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_payment_tx_order', 'order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('payment_transactions')->drop()->save();
    }
}
