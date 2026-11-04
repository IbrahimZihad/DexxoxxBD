-- 003 — Payments, manual transfer proof and issued passes.

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    tran_id VARCHAR(80) NOT NULL,
    val_id VARCHAR(120) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BDT',
    store_id VARCHAR(60) DEFAULT NULL,
    bank_tran_id VARCHAR(120) DEFAULT NULL,
    card_type VARCHAR(60) DEFAULT NULL,
    status ENUM('initiated','valid','failed','cancelled') NOT NULL DEFAULT 'initiated',
    failure_reason VARCHAR(190) DEFAULT NULL,
    gateway_payload JSON DEFAULT NULL,
    validated_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY payments_tran_unique (tran_id),
    KEY payments_order_idx (order_id),
    KEY payments_status_idx (status),
    CONSTRAINT payments_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_item_id INT NOT NULL,
    item_type ENUM('product','plan') NOT NULL,
    item_name VARCHAR(160) NOT NULL,
    pass_code VARCHAR(40) NOT NULL,
    starts_at DATE NOT NULL,
    ends_at DATE NOT NULL,
    status ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
    revoked_by INT DEFAULT NULL,
    revoked_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY passes_code_unique (pass_code),
    KEY passes_user_status_idx (user_id, status),
    KEY passes_expiry_idx (status, ends_at),
    KEY passes_order_item_idx (order_item_id),
    CONSTRAINT passes_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT passes_item_fk FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    CONSTRAINT passes_revoked_by_fk FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT passes_window_check CHECK (ends_at >= starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS manual_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method ENUM('bkash','nagad','rocket','bank') NOT NULL,
    sender_number VARCHAR(30) DEFAULT NULL,
    transaction_id VARCHAR(120) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    note TEXT,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note TEXT,
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY manual_txn_per_method_unique (payment_method, transaction_id),
    KEY manual_status_idx (status, created_at),
    KEY manual_order_idx (order_id),
    CONSTRAINT manual_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT manual_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
