<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePaymentsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('payments', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $table
            ->addColumn('order_id', 'integer', ['signed' => false])
            // A varchar, not an enum: the rails live in config/payments.php and a
            // new one must not require a schema change.
            ->addColumn('method', 'string', ['limit' => 24])
            ->addColumn('txn_id', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('status', 'enum', ['values' => ['pending', 'verified', 'rejected'], 'default' => 'pending'])
            ->addColumn('sender_number', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('admin_note', 'string', ['limit' => 300, 'null' => true])
            ->addColumn('verified_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('verified_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            // One declared payment per rail per order. Duplicate transaction IDs
            // are refused by PaymentService/OrderService before the insert, which
            // keeps a rejected attempt reusable for a correction.
            ->addIndex(['order_id', 'method'], ['unique' => true, 'name' => 'payments_order_method_unique'])
            ->addIndex(['txn_id'], ['name' => 'payments_txn_index'])
            ->addIndex(['status'], ['name' => 'payments_status_index'])
            ->create();

        $table
            ->addForeignKey('order_id', 'orders', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'payments_order_fk',
            ])
            ->update();
    }
}
