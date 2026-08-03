-- Run this migration if you already have an existing database
-- (skip if running schema.sql fresh — manual_payments is already included)

ALTER TABLE orders
    MODIFY COLUMN payment_method VARCHAR(40) NOT NULL DEFAULT 'manual';

CREATE TABLE IF NOT EXISTS manual_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method ENUM('bkash','nagad','rocket','bank') NOT NULL DEFAULT 'bkash',
    sender_number VARCHAR(30) DEFAULT NULL,
    transaction_id VARCHAR(120) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    note TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;
