<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Percentage or fixed-amount discount codes.
 */
final class CreateCouponsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('coupons', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Percentage or fixed-amount discount codes.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('code', 'string', ['limit' => 60])
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('type', 'enum', ['values' => ['percent', 'fixed'], 'default' => 'percent'])
            ->addColumn('value', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('min_order_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('max_discount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
            ->addColumn('restriction_mode', 'enum', ['values' => ['all', 'products', 'categories'], 'default' => 'all'])
            ->addColumn('usage_limit', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('usage_limit_per_user', 'smallinteger', ['signed' => false, 'null' => true])
            ->addColumn('usage_count', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('first_order_only', 'boolean', ['default' => false])
            ->addColumn('auto_apply', 'boolean', ['default' => false])
            ->addColumn('stackable', 'boolean', ['default' => false])
            ->addColumn('starts_at', 'datetime', ['null' => true])
            ->addColumn('expires_at', 'datetime', ['null' => true])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 1])
            ->addColumn('created_by', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'datetime', ['null' => true]);

        $table->addIndex(['code'], ['unique' => true, 'name' => 'coupons_code_unique']);
        $table->addIndex(['status', 'starts_at', 'expires_at'], ['name' => 'coupons_status_window_index']);
        $table->addIndex(['auto_apply', 'status'], ['name' => 'coupons_auto_apply_index']);

        $table->addForeignKeyWithName('fk_coupons_creator', 'created_by', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('coupons')->drop()->save();
    }
}
