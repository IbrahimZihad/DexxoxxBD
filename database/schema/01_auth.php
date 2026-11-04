<?php

/**
 * Authentication and authorisation schema: accounts, RBAC, verification
 * tokens and the recorded session ledger.
 */

declare(strict_types=1);

return [
    [
        'version' => '20261105000100',
        'class' => 'CreateUsersTable',
        'table' => 'users',
        'comment' => 'Customer and staff accounts.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['name', 'string', ['limit' => 120]],
            ['username', 'string', ['limit' => 60, 'null' => true]],
            ['email', 'string', ['limit' => 190]],
            ['phone', 'string', ['limit' => 20, 'null' => true]],
            ['password', 'string', ['limit' => 255]],
            ['status', 'tinyinteger', ['signed' => false, 'default' => 2, 'comment' => '0 suspended, 1 active, 2 awaiting verification']],
            ['email_verified_at', 'datetime', ['null' => true]],
            ['locale', 'string', ['limit' => 5, 'default' => 'en']],
            ['avatar', 'string', ['limit' => 255, 'null' => true]],
            ['must_change_password', 'boolean', ['default' => false]],
            ['last_login_at', 'datetime', ['null' => true]],
            ['last_login_ip', 'string', ['limit' => 45, 'null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
            ['deleted_at', 'datetime', ['null' => true]],
        ],
        'indexes' => [
            ['users_email_unique', ['email'], ['unique' => true]],
            ['users_username_unique', ['username'], ['unique' => true]],
            ['users_phone_index', ['phone']],
            ['users_status_deleted_index', ['status', 'deleted_at']],
            ['users_created_at_index', ['created_at']],
        ],
    ],
    [
        'version' => '20261105000200',
        'class' => 'CreateRolesTable',
        'table' => 'roles',
        'comment' => 'RBAC roles; level 1 is the most privileged.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['name', 'string', ['limit' => 80]],
            ['slug', 'string', ['limit' => 80]],
            ['description', 'string', ['limit' => 255, 'null' => true]],
            ['level', 'smallinteger', ['signed' => false, 'default' => 100]],
            ['staff', 'boolean', ['default' => false, 'comment' => 'Grants access to the admin panel']],
            ['is_system', 'boolean', ['default' => false, 'comment' => 'Cannot be deleted from the admin panel']],
            ['status', 'tinyinteger', ['signed' => false, 'default' => 1]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['roles_slug_unique', ['slug'], ['unique' => true]],
            ['roles_name_unique', ['name'], ['unique' => true]],
            ['roles_level_index', ['level']],
            ['roles_staff_status_index', ['staff', 'status']],
        ],
    ],
    [
        'version' => '20261105000300',
        'class' => 'CreatePermissionsTable',
        'table' => 'permissions',
        'comment' => 'Individual abilities checked server-side, grouped by module.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['name', 'string', ['limit' => 120]],
            ['slug', 'string', ['limit' => 120]],
            ['module', 'string', ['limit' => 60]],
            ['description', 'string', ['limit' => 255, 'null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['permissions_slug_unique', ['slug'], ['unique' => true]],
            ['permissions_module_index', ['module']],
        ],
    ],
    [
        'version' => '20261105000400',
        'class' => 'CreateUserRolesTable',
        'table' => 'user_roles',
        'comment' => 'Role assignments (a user may hold several roles).',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['user_id', 'biginteger', ['signed' => false]],
            ['role_id', 'biginteger', ['signed' => false]],
            ['assigned_by', 'biginteger', ['signed' => false, 'null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['user_roles_user_role_unique', ['user_id', 'role_id'], ['unique' => true]],
            ['user_roles_role_index', ['role_id']],
        ],
        'keys' => [
            ['name' => 'fk_user_roles_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_user_roles_role', 'column' => 'role_id', 'table' => 'roles', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_user_roles_assigner', 'column' => 'assigned_by', 'table' => 'users', 'delete' => 'SET NULL', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105000500',
        'class' => 'CreateRolePermissionsTable',
        'table' => 'role_permissions',
        'comment' => 'Permissions granted to each role.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['role_id', 'biginteger', ['signed' => false]],
            ['permission_id', 'biginteger', ['signed' => false]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['role_permissions_role_permission_unique', ['role_id', 'permission_id'], ['unique' => true]],
            ['role_permissions_permission_index', ['permission_id']],
        ],
        'keys' => [
            ['name' => 'fk_role_permissions_role', 'column' => 'role_id', 'table' => 'roles', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
            ['name' => 'fk_role_permissions_permission', 'column' => 'permission_id', 'table' => 'permissions', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105000600',
        'class' => 'CreateEmailVerificationTokensTable',
        'table' => 'email_verification_tokens',
        'comment' => 'Hashed email-verification tokens; the raw token never lands in MySQL.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['user_id', 'biginteger', ['signed' => false]],
            ['token_hash', 'char', ['limit' => 64]],
            ['expires_at', 'datetime'],
            ['consumed_at', 'datetime', ['null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['email_tokens_hash_unique', ['token_hash'], ['unique' => true]],
            ['email_tokens_user_expiry_index', ['user_id', 'expires_at']],
        ],
        'keys' => [
            ['name' => 'fk_email_tokens_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105000700',
        'class' => 'CreatePasswordResetTokensTable',
        'table' => 'password_reset_tokens',
        'comment' => 'Single-use hashed password-reset tokens.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['user_id', 'biginteger', ['signed' => false, 'null' => true]],
            ['email', 'string', ['limit' => 190]],
            ['token_hash', 'char', ['limit' => 64]],
            ['expires_at', 'datetime'],
            ['consumed_at', 'datetime', ['null' => true]],
            ['ip_address', 'string', ['limit' => 45, 'null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['password_tokens_hash_unique', ['token_hash'], ['unique' => true]],
            ['password_tokens_email_index', ['email']],
            ['password_tokens_user_index', ['user_id']],
        ],
        'keys' => [
            ['name' => 'fk_password_tokens_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
    [
        'version' => '20261105000800',
        'class' => 'CreateUserSessionsTable',
        'table' => 'user_sessions',
        'comment' => 'Ledger of live sessions so a customer can revoke devices.',
        'columns' => [
            ['id', 'biginteger', ['identity' => true, 'signed' => false]],
            ['user_id', 'biginteger', ['signed' => false]],
            ['guard', 'string', ['limit' => 20, 'default' => 'web']],
            ['session_hash', 'char', ['limit' => 64]],
            ['ip_address', 'string', ['limit' => 45, 'null' => true]],
            ['user_agent', 'string', ['limit' => 512, 'null' => true]],
            ['last_activity', 'datetime', ['null' => true]],
            ['expires_at', 'datetime', ['null' => true]],
            ['revoked_at', 'datetime', ['null' => true]],
            ['created_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP']],
            ['updated_at', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP']],
        ],
        'indexes' => [
            ['user_sessions_hash_index', ['session_hash']],
            ['user_sessions_user_guard_index', ['user_id', 'guard', 'revoked_at']],
            ['user_sessions_expiry_index', ['expires_at']],
        ],
        'keys' => [
            ['name' => 'fk_user_sessions_user', 'column' => 'user_id', 'table' => 'users', 'delete' => 'CASCADE', 'update' => 'CASCADE'],
        ],
    ],
];
