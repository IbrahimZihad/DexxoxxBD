-- 005 — Upgrade path for a database created by the pre-refactor schema.
-- On a fresh install every statement here is already satisfied by 001-004, and
-- the runner treats "already exists" as a skip.

ALTER TABLE users
    ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER status;

ALTER TABLE categories
    ADD COLUMN icon VARCHAR(40) NOT NULL DEFAULT '' AFTER slug,
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER icon,
    ADD INDEX categories_sort_idx (sort_order);

ALTER TABLE products
    ADD COLUMN sold_count INT NOT NULL DEFAULT 0 AFTER stock,
    ADD INDEX products_status_category_idx (status, category_id),
    ADD INDEX products_featured_idx (is_featured);

ALTER TABLE plans
    ADD INDEX plans_status_sort_idx (status, sort_order);

ALTER TABLE cart_items
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD INDEX cart_user_idx (user_id);

ALTER TABLE orders
    ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal,
    ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER customer_phone,
    ADD COLUMN expires_at DATETIME DEFAULT NULL AFTER ip_address,
    ADD COLUMN paid_at DATETIME DEFAULT NULL AFTER expires_at,
    MODIFY COLUMN status ENUM('pending','paid','failed','cancelled','expired') NOT NULL DEFAULT 'pending',
    ADD INDEX orders_user_idx (user_id),
    ADD INDEX orders_status_expires_idx (status, expires_at),
    ADD INDEX orders_created_idx (created_at);

ALTER TABLE order_items
    ADD COLUMN stock_released TINYINT(1) NOT NULL DEFAULT 0 AFTER line_total,
    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER line_total,
    ADD INDEX items_order_idx (order_id),
    ADD INDEX items_product_idx (product_id),
    ADD INDEX items_plan_idx (plan_id);

ALTER TABLE payments
    ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'BDT' AFTER amount,
    ADD COLUMN store_id VARCHAR(60) DEFAULT NULL AFTER currency,
    ADD COLUMN failure_reason VARCHAR(190) DEFAULT NULL AFTER status,
    ADD COLUMN gateway_payload JSON DEFAULT NULL AFTER failure_reason,
    ADD COLUMN validated_at DATETIME DEFAULT NULL AFTER gateway_payload,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD INDEX payments_order_idx (order_id),
    ADD INDEX payments_status_idx (status);

ALTER TABLE user_subscriptions
    ADD COLUMN revoked_by INT DEFAULT NULL AFTER status,
    ADD COLUMN revoked_at DATETIME DEFAULT NULL AFTER revoked_by,
    ADD INDEX passes_user_status_idx (user_id, status),
    ADD INDEX passes_expiry_idx (status, ends_at),
    ADD INDEX passes_order_item_idx (order_item_id);

ALTER TABLE manual_payments
    ADD COLUMN reviewed_by INT DEFAULT NULL AFTER reviewed_at,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER reviewed_at,
    ADD INDEX manual_status_idx (status, created_at),
    ADD INDEX manual_order_idx (order_id);

-- The two reviewer columns point at users; the legacy schema had no such links.
ALTER TABLE user_subscriptions
    ADD CONSTRAINT passes_revoked_by_fk FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE manual_payments
    ADD CONSTRAINT manual_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL;

-- A transaction ID is only unique per channel; the legacy table allowed reuse,
-- which let the same screenshot claim two different orders.
ALTER TABLE manual_payments
    ADD CONSTRAINT manual_txn_per_method_unique UNIQUE (payment_method, transaction_id);
