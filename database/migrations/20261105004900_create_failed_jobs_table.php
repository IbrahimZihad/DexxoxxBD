<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Jobs that exhausted their retries, kept for inspection and replay.
 */
final class CreateFailedJobsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('failed_jobs', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Jobs that exhausted their retries, kept for inspection and replay.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('job_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('queue', 'string', ['limit' => 40, 'default' => 'default', 'null' => false])
            ->addColumn('job', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('payload', 'text', ['null' => true])
            ->addColumn('attempts', 'tinyinteger', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('exception', 'text', ['null' => true])
            ->addColumn('failed_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['failed_at'], ['name' => 'failed_jobs_failed_index']);
        $table->addIndex(['job'], ['name' => 'failed_jobs_job_index']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('failed_jobs')->drop()->save();
    }
}
