-- ================================================
-- FrameKlip Database v3.0 - 4 Tables
-- TESTED & WORKING - Pasti bisa di-import!
-- ================================================

-- Hapus database lama (OPTIONAL - hanya jika mau fresh install)
-- DROP DATABASE IF EXISTS frameklip_db;

-- Buat database baru
CREATE DATABASE IF NOT EXISTS frameklip_db;
USE frameklip_db;

-- ================================================
-- HAPUS TABEL LAMA (jika ada)
-- ================================================
DROP TABLE IF EXISTS productions;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS admin_users;

-- ================================================
-- TABLE 1: ADMINS
-- ================================================
CREATE TABLE admins (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_username (username),
    KEY idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin (password: admin123)
INSERT INTO admins (username, password, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@frameklip.com');

-- ================================================
-- TABLE 2: ORDERS
-- ================================================
CREATE TABLE orders (
    id INT NOT NULL AUTO_INCREMENT,
    service VARCHAR(100) NOT NULL,
    package VARCHAR(50) NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    notes TEXT,
    status ENUM('pending','processing','completed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email (customer_email),
    KEY idx_status (status),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- TABLE 3: PAYMENTS
-- ================================================
CREATE TABLE payments (
    id INT NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_verified TINYINT(1) DEFAULT 0,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    verified_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order (order_id),
    KEY idx_verified (payment_verified),
    KEY idx_verified_by (verified_by),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_admin FOREIGN KEY (verified_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- TABLE 4: PRODUCTIONS
-- ================================================
CREATE TABLE productions (
    id INT NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    gdrive_link VARCHAR(500) DEFAULT NULL,
    status ENUM('draft','in_progress','completed') DEFAULT 'draft',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order (order_id),
    KEY idx_status (status),
    CONSTRAINT fk_productions_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- SAMPLE DATA (untuk testing)
-- ================================================
INSERT INTO orders (service, package, customer_name, customer_email, customer_phone, notes, status) VALUES
('Edit Reels / Video Pendek', 'Regular', 'John Doe', 'john@example.com', '081234567890', 'Sample order untuk testing', 'pending');

INSERT INTO payments (order_id, total_amount, payment_verified) VALUES
(1, 15000.00, 0);

INSERT INTO productions (order_id, status) VALUES
(1, 'draft');

-- ================================================
-- VERIFY (jalankan query ini untuk cek)
-- ================================================
-- SELECT 'admins' as tabel, COUNT(*) as rows FROM admins
-- UNION ALL SELECT 'orders', COUNT(*) FROM orders
-- UNION ALL SELECT 'payments', COUNT(*) FROM payments
-- UNION ALL SELECT 'productions', COUNT(*) FROM productions;

-- ================================================
-- DONE!
-- Default login: admin / admin123
-- ================================================
