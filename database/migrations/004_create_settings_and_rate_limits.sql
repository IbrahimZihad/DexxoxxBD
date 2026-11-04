-- 004 — Site settings and the rate-limit window table.

CREATE TABLE IF NOT EXISTS settings (
    key_name VARCHAR(80) NOT NULL PRIMARY KEY,
    value TEXT,
    type ENUM('string','bool','number','json') NOT NULL DEFAULT 'string',
    group_name VARCHAR(40) NOT NULL DEFAULT 'general',
    label VARCHAR(120) NOT NULL DEFAULT '',
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    rl_key VARCHAR(190) NOT NULL PRIMARY KEY,
    hits INT NOT NULL DEFAULT 1,
    expires_at DATETIME NOT NULL,
    KEY rate_limits_expiry_idx (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
