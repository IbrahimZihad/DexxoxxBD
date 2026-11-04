<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Database-backed job queue drained by scripts/cron/worker.php.
 */
final class CreateBackgroundJobsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('background_jobs', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Database-backed job queue drained by scripts/cron/worker.php.',
        ]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('queue', 'string', ['limit' => 40, 'default' => 'default', 'null' => false])
            ->addColumn('job', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('payload', 'text', ['null' => true])
            ->addColumn('attempts', 'tinyinteger', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('available_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('reserved_at', 'datetime', ['null' => true])
            ->addColumn('executed_at', 'datetime', ['null' => true])
            ->addColumn('status', 'enum', [
            'values' => ['pending', 'processing', 'completed', 'failed', 'cancelled'],
            'default' => 'pending',
            'null' => false,
        ])
            ->addColumn('last_error', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['status', 'available_at'], ['name' => 'background_jobs_claim_index']);
        $table->addIndex(['queue', 'status'], ['name' => 'background_jobs_queue_index']);
        $table->addIndex(['job'], ['name' => 'background_jobs_job_index']);

        $table->create();
    }

    public function down(): void
    {
        $this->table('background_jobs')->drop()->save();
    }
}
