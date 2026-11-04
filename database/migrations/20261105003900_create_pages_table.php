<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Static content pages (about, terms, privacy, delivery, refund).
 */
final class CreatePagesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('pages', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Static content pages (about, terms, privacy, delivery, refund).',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('title', 'string', ['limit' => 190, 'null' => false])
            ->addColumn('slug', 'string', ['limit' => 200, 'null' => false])
            ->addColumn('excerpt', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('content', 'text', ['null' => true])
            ->addColumn('template', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('meta_title', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('meta_description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('status', 'enum', ['values' => ['draft', 'published'], 'default' => 'draft', 'null' => false])
            ->addColumn('is_footer', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('locale', 'string', ['limit' => 5, 'default' => 'en', 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'datetime', ['null' => true]);

        $table->addIndex(['slug', 'locale'], ['unique' => true, 'name' => 'pages_slug_locale_unique']);
        $table->addIndex(['status', 'is_footer'], ['name' => 'pages_status_footer_index']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('pages')->drop()->save();
    }
}
