<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Saved-for-later lists (one default list per customer).
 */
final class CreateWishlistsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('wishlists', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Saved-for-later lists (one default list per customer).',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 80, 'default' => 'default', 'null' => false])
            ->addColumn('is_public', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['user_id', 'name'], ['unique' => true, 'name' => 'wishlists_user_name_unique']);

        $table->addForeignKeyWithName('fk_wishlists_user', 'user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('wishlists')->drop()->save();
    }
}
