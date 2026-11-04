<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Hero, promotional and announcement banners by placement.
 */
final class CreateBannersTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('banners', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Hero, promotional and announcement banners by placement.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('placement', 'enum', ['values' => ['announcement', 'home_hero', 'home_promo', 'category', 'sidebar'], 'default' => 'home_hero'])
            ->addColumn('title', 'string', ['limit' => 190])
            ->addColumn('subtitle', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('badge', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('image', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('image_alt', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('link', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('button_text', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('button_style', 'string', ['limit' => 30, 'default' => 'primary'])
            ->addColumn('starts_at', 'datetime', ['null' => true])
            ->addColumn('ends_at', 'datetime', ['null' => true])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 1])
            ->addColumn('locale', 'string', ['limit' => 5, 'default' => 'en'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['placement', 'status', 'sort_order'], ['name' => 'banners_placement_index']);
        $table->addIndex(['starts_at', 'ends_at'], ['name' => 'banners_window_index']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('banners')->drop()->save();
    }
}
