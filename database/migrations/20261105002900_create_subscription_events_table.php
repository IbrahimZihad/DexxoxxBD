<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Activation, renewal and expiry trail per subscription.
 */
final class CreateSubscriptionEventsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('subscription_events', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Activation, renewal and expiry trail per subscription.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('subscription_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('event', 'string', ['limit' => 60, 'null' => false])
            ->addColumn('from_status', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('to_status', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('note', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['subscription_id', 'created_at'], ['name' => 'subscription_events_sub_index']);
        $table->addIndex(['user_id'], ['name' => 'subscription_events_user_index']);

        $table->addForeignKeyWithName('fk_subscription_events_sub', 'subscription_id', 'customer_subscriptions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKeyWithName('fk_subscription_events_user', 'user_id', 'users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('subscription_events')->drop()->save();
    }
}
