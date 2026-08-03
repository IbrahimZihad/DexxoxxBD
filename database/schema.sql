-- =========================================================
-- SubPass — Digital Subscriptions Marketplace
-- MySQL Schema
-- =========================================================

-- ---------------------------------------------------------
-- Users
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Categories (used by both one-time products and plans)
-- ---------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Products — individual digital subscription products
-- e.g. "Netflix Premium 1 Month", "Spotify Family 3 Months"
-- ---------------------------------------------------------
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT DEFAULT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT,
    image_url VARCHAR(500) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    compare_at_price DECIMAL(10,2) DEFAULT NULL,
    duration_days INT NOT NULL DEFAULT 30,
    stock INT NOT NULL DEFAULT 999,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Plans — site-wide membership tiers (Monthly / Yearly etc.)
-- ---------------------------------------------------------
CREATE TABLE plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_days INT NOT NULL,
    features JSON DEFAULT NULL,
    badge VARCHAR(40) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Cart
-- ---------------------------------------------------------
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_type ENUM('product','plan') NOT NULL,
    product_id INT DEFAULT NULL,
    plan_id INT DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Orders
-- ---------------------------------------------------------
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(40) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(40) DEFAULT 'sslcommerz',
    customer_name VARCHAR(120) DEFAULT NULL,
    customer_email VARCHAR(190) DEFAULT NULL,
    customer_phone VARCHAR(30) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_type ENUM('product','plan') NOT NULL,
    product_id INT DEFAULT NULL,
    plan_id INT DEFAULT NULL,
    item_name VARCHAR(160) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    duration_days INT NOT NULL DEFAULT 30,
    line_total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Payments (SSLCommerz transaction log)
-- ---------------------------------------------------------
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    tran_id VARCHAR(80) NOT NULL UNIQUE,
    val_id VARCHAR(120) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    card_type VARCHAR(60) DEFAULT NULL,
    bank_tran_id VARCHAR(120) DEFAULT NULL,
    status ENUM('initiated','valid','failed','cancelled') NOT NULL DEFAULT 'initiated',
    raw_response JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- User subscriptions — the actual "access passes" granted
-- after a successful payment
-- ---------------------------------------------------------
CREATE TABLE user_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_item_id INT NOT NULL,
    item_type ENUM('product','plan') NOT NULL,
    item_name VARCHAR(160) NOT NULL,
    pass_code VARCHAR(40) NOT NULL UNIQUE,
    starts_at DATE NOT NULL,
    ends_at DATE NOT NULL,
    status ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------
INSERT INTO categories (name, slug) VALUES
('Streaming', 'streaming'),
('Music', 'music'),
('Software & Tools', 'software'),
('Gaming', 'gaming'),
('Education', 'education');

INSERT INTO products (category_id, name, slug, description, image_url, price, compare_at_price, duration_days, stock, is_featured) VALUES
(1, 'Netflix Premium — 1 Month', 'netflix-premium-1m', '4K Ultra HD, 4 screens at once, full catalog access.', 'https://images.unsplash.com/photo-1574375927938-d5a98e8ffe85?w=600', 450.00, 550.00, 30, 999, 1),
(1, 'Amazon Prime Video — 1 Month', 'prime-video-1m', 'Full Prime Video catalog including originals.', 'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?w=600', 300.00, NULL, 30, 999, 0),
(2, 'Spotify Premium — 1 Month', 'spotify-premium-1m', 'Ad-free music, offline downloads, unlimited skips.', 'https://images.unsplash.com/photo-1611339555312-e607c8352fd7?w=600', 250.00, 300.00, 30, 999, 1),
(2, 'YouTube Premium — 1 Month', 'youtube-premium-1m', 'Ad-free YouTube plus YouTube Music Premium.', 'https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=600', 350.00, NULL, 30, 999, 0),
(3, 'Canva Pro — 1 Month', 'canva-pro-1m', 'Premium templates, background remover, brand kit.', 'https://images.unsplash.com/photo-1626785774573-4b799315345d?w=600', 400.00, NULL, 30, 999, 1),
(3, 'ChatGPT Plus — 1 Month', 'chatgpt-plus-1m', 'Priority access, faster responses, latest models.', 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=600', 2200.00, 2500.00, 30, 999, 1),
(4, 'PlayStation Plus — 1 Month', 'ps-plus-1m', 'Online multiplayer, monthly games, exclusive discounts.', 'https://images.unsplash.com/photo-1606144042614-b2417e99c4e3?w=600', 700.00, NULL, 30, 999, 0),
(5, 'LinkedIn Learning — 1 Month', 'linkedin-learning-1m', 'Unlimited access to professional courses.', 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600', 500.00, NULL, 30, 999, 0);

INSERT INTO plans (name, slug, description, price, duration_days, features, badge, sort_order) VALUES
('Starter Pass', 'starter-pass', 'For casual browsing and light use of the marketplace.', 199.00, 30, JSON_ARRAY('1 active subscription redemption', 'Email support', 'Standard checkout speed'), NULL, 1),
('Plus Pass', 'plus-pass', 'Our most popular pass for regular subscribers.', 499.00, 30, JSON_ARRAY('Up to 5 active redemptions', 'Priority email + chat support', 'Early access to new products', '5% cashback credit'), 'Most Popular', 2),
('Pro Pass — Annual', 'pro-pass-annual', 'Best value for power users who subscribe to everything.', 4999.00, 365, JSON_ARRAY('Unlimited redemptions', 'Dedicated support line', 'Earliest access + beta products', '10% cashback credit', '2 months free vs monthly'), 'Best Value', 3);

-- ---------------------------------------------------------
-- Default admin user
-- Email: admin@subpass.test   Password: Admin@123
-- (hash generated with PHP password_hash — change after first login)
-- ---------------------------------------------------------
INSERT INTO users (name, email, password_hash, role) VALUES
('Site Admin', 'admin@subpass.test', '$2b$10$0plTRhzCTLn9MbyhaVW1PuKzla/Ka8iQ1BWXs6PtiOSGUky2mVcZ6', 'admin');

-- ---------------------------------------------------------
-- Manual Payments — customer submits payment proof; admin approves
-- ---------------------------------------------------------
CREATE TABLE manual_payments (
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
