<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Double opt-in newsletter list.
 */
final class CreateNewsletterSubscribersTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('newsletter_subscribers', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Double opt-in newsletter list.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('name', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('locale', 'string', ['limit' => 5, 'default' => 'en'])
            ->addColumn('status', 'enum', ['values' => ['pending', 'subscribed', 'unsubscribed'], 'default' => 'pending'])
            ->addColumn('token_hash', 'char', ['limit' => 64, 'null' => true])
            ->addColumn('subscribed_at', 'datetime', ['null' => true])
            ->addColumn('unsubscribed_at', 'datetime', ['null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['email'], ['unique' => true, 'name' => 'newsletter_email_unique']);
        $table->addIndex(['status'], ['name' => 'newsletter_status_index']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('newsletter_subscribers')->drop()->save();
    }
}
