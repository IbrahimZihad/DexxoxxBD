<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * One open cart per customer; guest carts are keyed by a random token.
 */
final class CreateCartsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('carts', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'One open cart per customer; guest carts are keyed by a random token.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('guest_token', 'char', ['limit' => 40, 'null' => true])
            ->addColumn('coupon_code', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('merged_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['user_id', 'merged_at'], ['name' => 'carts_user_open_index']);
        $table->addIndex(['guest_token', 'merged_at'], ['name' => 'carts_guest_open_index']);
        $table->addIndex(['updated_at'], ['name' => 'carts_updated_index']);

        $table->addForeignKeyWithName('fk_carts_user', 'user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('carts')->drop()->save();
    }
}
