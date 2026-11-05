<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class CreateContentTables extends AbstractMigration
{
    public function change(): void
    {
        $testimonials = $this->table('testimonials', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $testimonials
            ->addColumn('name', 'string', ['limit' => 80])
            ->addColumn('location', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('service_label', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('rating', 'integer', ['limit' => MysqlAdapter::INT_TINY, 'default' => 5])
            ->addColumn('quote', 'string', ['limit' => 400])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['is_active', 'sort_order'], ['name' => 'testimonials_active_index'])
            ->create();

        $faqs = $this->table('faqs', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $faqs
            ->addColumn('question', 'string', ['limit' => 200])
            ->addColumn('answer', 'string', ['limit' => 1000])
            ->addColumn('category', 'string', ['limit' => 40, 'default' => 'general'])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['is_active', 'category'], ['name' => 'faqs_active_category_index'])
            ->create();
    }
}
