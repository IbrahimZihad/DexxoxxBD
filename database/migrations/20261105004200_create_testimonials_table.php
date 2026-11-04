<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Curated customer testimonials for the homepage.
 */
final class CreateTestimonialsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('testimonials', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Curated customer testimonials for the homepage.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('customer_name', 'string', ['limit' => 120])
            ->addColumn('customer_title', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('customer_location', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('quote', 'text', [])
            ->addColumn('rating', 'tinyinteger', ['signed' => false, 'default' => 5])
            ->addColumn('avatar', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('product_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 0])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('locale', 'string', ['limit' => 5, 'default' => 'en'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['status', 'sort_order'], ['name' => 'testimonials_status_sort_index']);
        $table->addIndex(['product_id'], ['name' => 'testimonials_product_index']);

        $table->addForeignKeyWithName('fk_testimonials_product', 'product_id', 'products', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_testimonials_user', 'user_id', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('testimonials')->drop()->save();
    }
}
