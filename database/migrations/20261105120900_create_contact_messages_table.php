<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateContactMessagesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('contact_messages', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci']);

        $table
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('email', 'string', ['limit' => 180, 'null' => true])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('subject', 'string', ['limit' => 160, 'null' => true])
            ->addColumn('message', 'text')
            ->addColumn('channel', 'enum', ['values' => ['form', 'whatsapp', 'chat'], 'default' => 'form'])
            ->addColumn('status', 'enum', ['values' => ['new', 'answered', 'archived'], 'default' => 'new'])
            ->addColumn('reply_note', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('answered_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('answered_at', 'datetime', ['null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['status'], ['name' => 'contact_status_index'])
            ->addIndex(['created_at'], ['name' => 'contact_created_index'])
            ->create();
    }
}
