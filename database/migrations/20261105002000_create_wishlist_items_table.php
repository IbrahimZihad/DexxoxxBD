<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Products saved by a customer.
 */
final class CreateWishlistItemsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('wishlist_items', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Products saved by a customer.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('wishlist_id', 'biginteger', ['signed' => false])
            ->addColumn('product_id', 'biginteger', ['signed' => false])
            ->addColumn('note', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['wishlist_id', 'product_id'], ['unique' => true, 'name' => 'wishlist_items_pair_unique']);
        $table->addIndex(['product_id'], ['name' => 'wishlist_items_product_index']);

        $table->addForeignKeyWithName('fk_wishlist_items_list', 'wishlist_id', 'wishlists', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_wishlist_items_product', 'product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('wishlist_items')->drop()->save();
    }
}
