<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class CreatePlansTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('plans', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $table
            ->addColumn('service_id', 'integer', ['signed' => false])
            ->addColumn('title', 'string', ['limit' => 120])
            ->addColumn('duration_months', 'integer', ['limit' => MysqlAdapter::INT_SMALL, 'signed' => false, 'default' => 1])
            ->addColumn('seats', 'integer', ['limit' => MysqlAdapter::INT_SMALL, 'signed' => false, 'default' => 1])
            ->addColumn('quality', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('device', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('compare_at_price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            // Negative stock means "not limited", which is how most plans are sold.
            ->addColumn('stock', 'integer', ['default' => -1])
            ->addColumn('badge', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('is_featured', 'boolean', ['default' => false])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addForeignKey(
                'service_id',
                'services',
                'id',
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'plans_service_fk']
            )
            ->addIndex(['service_id', 'is_active'], ['name' => 'plans_service_active_index'])
            ->addIndex(['price'], ['name' => 'plans_price_index'])
            ->addIndex(['duration_months'], ['name' => 'plans_duration_index'])
            ->create();
    }
}
