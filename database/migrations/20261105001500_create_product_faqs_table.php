<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Per-product questions and answers.
 */
final class CreateProductFaqsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('product_faqs', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Per-product questions and answers.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('product_id', 'biginteger', ['signed' => false])
            ->addColumn('question', 'string', ['limit' => 255])
            ->addColumn('answer', 'text', [])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 1])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['product_id', 'sort_order'], ['name' => 'product_faqs_product_index']);

        $table->addForeignKeyWithName('fk_product_faqs_product', 'product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('product_faqs')->drop()->save();
    }
}
