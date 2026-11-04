<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Encrypted license keys and credentials (AES-256-GCM ciphertext
 * only).
 */
final class CreateDigitalInventoryTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('digital_inventory', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Encrypted license keys and credentials (AES-256-GCM ciphertext only).',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('product_id', 'biginteger', ['signed' => false])
            ->addColumn('variation_id', 'biginteger', ['signed' => false])
            ->addColumn('type', 'enum', ['values' => ['license_key', 'code', 'credential', 'account'], 'default' => 'license_key'])
            ->addColumn('encrypted_payload', 'text', ['null' => false])
            ->addColumn('encryption_iv', 'string', ['limit' => 44])
            ->addColumn('auth_tag', 'string', ['limit' => 44])
            ->addColumn('key_version', 'smallinteger', ['signed' => false, 'default' => 1])
            ->addColumn('dedupe_hash', 'char', ['limit' => 64])
            ->addColumn('code_hint', 'string', ['limit' => 12, 'null' => true, 'comment' => 'Last characters, for staff lists without decrypting'])
            ->addColumn('status', 'enum', [
            'values' => ['available', 'reserved', 'assigned', 'delivered', 'expired', 'revoked', 'invalid'],
            'default' => 'available',
        ])
            ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('order_item_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('assigned_to', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('assigned_at', 'datetime', ['null' => true])
            ->addColumn('delivered_at', 'datetime', ['null' => true])
            ->addColumn('expires_at', 'datetime', ['null' => true])
            ->addColumn('import_batch', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['dedupe_hash'], ['unique' => true, 'name' => 'digital_inventory_dedupe_unique']);
        $table->addIndex(['variation_id', 'status'], ['name' => 'digital_inventory_variation_status_index']);
        $table->addIndex(['product_id', 'status'], ['name' => 'digital_inventory_product_index']);
        $table->addIndex(['order_id'], ['name' => 'digital_inventory_order_index']);
        $table->addIndex(['assigned_to'], ['name' => 'digital_inventory_assignee_index']);
        $table->addIndex(['expires_at'], ['name' => 'digital_inventory_expiry_index']);
        $table->addIndex(['import_batch'], ['name' => 'digital_inventory_batch_index']);

        $table->addForeignKeyWithName('fk_inventory_product', 'product_id', 'products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_inventory_variation', 'variation_id', 'product_variations', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_inventory_order', 'order_id', 'orders', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_inventory_order_item', 'order_item_id', 'order_items', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_inventory_assignee', 'assigned_to', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('digital_inventory')->drop()->save();
    }
}
