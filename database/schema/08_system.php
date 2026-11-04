<?php

/**
 * Security and system schema: audit trail, login throttle, idempotency keys
 * and the queue tables behind background jobs and cron maintenance.
 */

declare(strict_types=1);

return [
    [
        'version' => '20261105004500',
        'class' => 'CreateAuditLogsTable',
        'table' => 'audit_logs',
        'comment' => 'Append-only record of privileged actions.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['user_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['action', 'string', ['limit' => 100]],
            ['description', 'string', ['limit' => 500, 'null' => true]],
            ['subject_type', 'string', ['limit' => 80, 'null' => true]],
            ['subject_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['old_values', 'text', ['null' => true]],
            ['new_values', 'text', ['null' => true]],
            ['context', 'text', ['null' => true]],
            ['ip_address', 'string', ['limit' => 45, 'null' => true]],
            ['user_agent', 'string', ['limit' => 512, 'null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['audit_logs_action_index', ['action', 'created_at']],
            ['audit_logs_user_index', ['user_id', 'created_at']],
            ['audit_logs_subject_index', ['subject_type', 'subject_id']],
            ['audit_logs_created_index', ['created_at']],
        ],
        'keys' => [
            ['name' => 'fk_audit_logs_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105004600',
        'class' => 'CreateLoginAttemptsTable',
        'table' => 'login_attempts',
        'comment' => 'Hashed-identifier login throttle log; raw emails are never stored.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['user_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['identifier', 'char', ['limit' => 64]],
            ['ip', 'string', ['limit' => 45]],
            ['user_agent', 'string', ['limit' => 512, 'null' => true]],
            ['successful', 'boolean', ['default' => false]],
            ['attempted_at', 'datetime'],
        ],
        'indexes' => [
            ['login_attempts_identifier_index', ['identifier', 'successful', 'attempted_at']],
            ['login_attempts_ip_index', ['ip', 'successful', 'attempted_at']],
            ['login_attempts_user_index', ['user_id']],
            ['login_attempts_time_index', ['attempted_at']],
        ],
        'keys' => [
            ['name' => 'fk_login_attempts_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105004700',
        'class' => 'CreateIdempotencyKeysTable',
        'table' => 'idempotency_keys',
        'comment' => 'Replay protection for checkout, webhooks and job dispatch.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['key', 'string', ['limit' => 190]],
            ['scope', 'string', ['limit' => 80]],
            ['user_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['request_hash', 'char', ['limit' => 64, 'null' => true]],
            ['status', 'enum', ['values' => ['in_progress', 'completed', 'failed'], 'default' => 'in_progress']],
            ['response_code', 'smallinteger', ['signed' => false, 'null' => true]],
            ['response_body', 'text', ['null' => true]],
            ['resource_type', 'string', ['limit' => 60, 'null' => true]],
            ['resource_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['expires_at', 'datetime', ['null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['idempotency_key_scope_unique', ['scope', 'key'], ['unique' => true]],
            ['idempotency_expiry_index', ['status', 'expires_at']],
            ['idempotency_user_index', ['user_id']],
        ],
        'keys' => [
            ['name' => 'fk_idempotency_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105004800',
        'class' => 'CreateBackgroundJobsTable',
        'table' => 'background_jobs',
        'comment' => 'Database-backed job queue drained by scripts/cron/worker.php.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['queue', 'string', ['limit' => 40, 'default' => 'default']],
            ['job', 'string', ['limit' => 120]],
            ['payload', 'text', ['null' => true]],
            ['attempts', 'tinyinteger', ['signed' => false, 'default' => 0]],
            ['available_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['reserved_at', 'datetime', ['null' => true]],
            ['executed_at', 'datetime', ['null' => true]],
            ['status', 'enum', ['values' => ['pending', 'processing', 'completed', 'failed', 'cancelled'], 'default' => 'pending']],
            ['last_error', 'text', ['null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['background_jobs_claim_index', ['status', 'available_at']],
            ['background_jobs_queue_index', ['queue', 'status']],
            ['background_jobs_job_index', ['job']],
        ],
    ],
    [
        'version' => '20261105004900',
        'class' => 'CreateFailedJobsTable',
        'table' => 'failed_jobs',
        'comment' => 'Jobs that exhausted their retries, kept for inspection and replay.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['job_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['queue', 'string', ['limit' => 40, 'default' => 'default']],
            ['job', 'string', ['limit' => 120]],
            ['payload', 'text', ['null' => true]],
            ['attempts', 'tinyinteger', ['signed' => false, 'default' => 0]],
            ['exception', 'text', ['null' => true]],
            ['failed_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['failed_jobs_failed_index', ['failed_at']],
            ['failed_jobs_job_index', ['job']],
        ],
    ],
    [
        'version' => '20261105005000',
        'class' => 'CreateSystemLogsTable',
        'table' => 'system_logs',
        'comment' => 'Database mirror of the log stream for the admin log viewer.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['level', 'string', ['limit' => 16]],
            ['channel', 'string', ['limit' => 40, 'default' => 'app']],
            ['message', 'text'],
            ['context', 'text', ['null' => true]],
            ['user_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['ip_address', 'string', ['limit' => 45, 'null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['system_logs_level_index', ['level', 'created_at']],
            ['system_logs_channel_index', ['channel', 'created_at']],
        ],
        'keys' => [
            ['name' => 'fk_system_logs_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
        ],
    ],
];
