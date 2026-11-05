<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCouponsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('coupons', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('code', 'string', ['limit' => 32])
            ->addColumn('type', 'enum', ['values' => ['percent', 'fixed'], 'default' => 'percent'])
            ->addColumn('value', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('min_order', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('max_uses', 'integer', ['default' => 0])
            ->addColumn('used_count', 'integer', ['default' => 0])
            ->addColumn('per_customer_limit', 'integer', ['default' => 1])
            ->addColumn('expires_at', 'datetime', ['null' => true])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['code'], ['unique' => true, 'name' => 'coupons_code_unique'])
            ->addIndex(['is_active', 'expires_at'], ['name' => 'coupons_active_expiry_index'])
            ->create();
    }
}
