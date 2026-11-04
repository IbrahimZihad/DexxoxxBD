<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Site-wide frequently asked questions.
 */
final class CreateFaqsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('faqs', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Site-wide frequently asked questions.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('question', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('answer', 'text', ['null' => false])
            ->addColumn('category', 'string', ['limit' => 80, 'null' => true])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('show_on_home', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 1, 'null' => false])
            ->addColumn('locale', 'string', ['limit' => 5, 'default' => 'en', 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['status', 'sort_order'], ['name' => 'faqs_status_sort_index']);
        $table->addIndex(['show_on_home', 'status'], ['name' => 'faqs_home_index']);
        $table->addIndex(['locale'], ['name' => 'faqs_locale_index']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('faqs')->drop()->save();
    }
}
