<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class CreateServicesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('services', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $table
            ->addColumn('slug', 'string', ['limit' => 80])
            ->addColumn('name', 'string', ['limit' => 80])
            ->addColumn('category', 'string', ['limit' => 60])
            ->addColumn('brand_color', 'string', ['limit' => 20, 'default' => '#6366f1'])
            ->addColumn('logo_mark', 'string', ['limit' => 6, 'default' => 'Z'])
            ->addColumn('tagline', 'string', ['limit' => 160, 'null' => true])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('show_in_home', 'boolean', ['default' => true])
            ->addColumn('popular', 'integer', ['limit' => MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['slug'], ['unique' => true, 'name' => 'services_slug_unique'])
            ->addIndex(['category'], ['name' => 'services_category_index'])
            ->addIndex(['is_active', 'sort_order'], ['name' => 'services_active_sort_index'])
            ->create();
    }
}
