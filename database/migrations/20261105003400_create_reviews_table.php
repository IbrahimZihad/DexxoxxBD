<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Moderated product reviews; ratings are rolled up onto products.
 */
final class CreateReviewsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('reviews', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Moderated product reviews; ratings are rolled up onto products.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('product_id', 'biginteger', ['signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('author_name', 'string', ['limit' => 120])
            ->addColumn('rating', 'tinyinteger', ['signed' => false, 'default' => 5])
            ->addColumn('title', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('body', 'text', [])
            ->addColumn('status', 'enum', ['values' => ['pending', 'approved', 'rejected'], 'default' => 'pending'])
            ->addColumn('is_verified_purchase', 'boolean', ['default' => false])
            ->addColumn('helpful_count', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('owner_response', 'text', ['null' => true])
            ->addColumn('responded_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('deleted_at', 'datetime', ['null' => true]);

        $table->addIndex(['product_id', 'status', 'created_at'], ['name' => 'reviews_product_status_index']);
        $table->addIndex(['user_id'], ['name' => 'reviews_user_index']);
        $table->addIndex(['order_id'], ['name' => 'reviews_order_index']);
        $table->addIndex(['rating'], ['name' => 'reviews_rating_index']);

        $table->addForeignKeyWithName('fk_reviews_product', 'product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_reviews_user', 'user_id', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_reviews_order', 'order_id', 'orders', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('reviews')->drop()->save();
    }
}
