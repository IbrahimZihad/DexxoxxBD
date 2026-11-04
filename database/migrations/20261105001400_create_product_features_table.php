<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Bullet-point feature list shown on the product page.
 */
final class CreateProductFeaturesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('product_features', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Bullet-point feature list shown on the product page.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('product_id', 'biginteger', ['signed' => false])
            ->addColumn('label', 'string', ['limit' => 190])
            ->addColumn('icon', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['product_id', 'sort_order'], ['name' => 'product_features_product_index']);

        $table->addForeignKeyWithName('fk_product_features_product', 'product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('product_features')->drop()->save();
    }
}
