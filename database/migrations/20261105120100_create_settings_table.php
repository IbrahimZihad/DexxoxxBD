<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSettingsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('settings', ['engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('key', 'string', ['limit' => 80])
            // "value" is not reserved in MySQL but is quoted anyway because the
            // query builder wraps every identifier.
            ->addColumn('value', 'text', ['null' => true])
            ->addColumn('group_name', 'string', ['limit' => 40, 'default' => 'general'])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['key'], ['unique' => true, 'name' => 'settings_key_unique'])
            ->create();
    }
}
