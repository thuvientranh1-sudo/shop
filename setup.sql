-- ============================================================
--  Shopee Product Detail - Schema + Sample Data
--  Chạy file này trước khi dùng product.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS shopee_product
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE shopee_product;

-- ──────────────────────────────────────────────────────────
--  Bảng sản phẩm
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(500) NOT NULL,
    description TEXT         NOT NULL,
    rating      DECIMAL(2,1) NOT NULL DEFAULT 5.0,
    review_count INT UNSIGNED NOT NULL DEFAULT 0,
    sold         INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────
--  Bảng ảnh sản phẩm
--  Mỗi ảnh có thể gắn với một màu cụ thể (nullable)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_images (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    image       VARCHAR(1000) NOT NULL,
    color       VARCHAR(100)  DEFAULT NULL,   -- NULL = ảnh chung
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────
--  Bảng biến thể sản phẩm (color + size)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_variants (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id     INT UNSIGNED NOT NULL,
    name           VARCHAR(200) NOT NULL,      -- ví dụ: "Trắng - S"
    color          VARCHAR(100) NOT NULL,
    size           VARCHAR(50)  NOT NULL,
    price          DECIMAL(12,0) NOT NULL,
    original_price DECIMAL(12,0) NOT NULL,
    stock          INT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────
--  Bảng đơn hàng
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_name     VARCHAR(200)  NOT NULL,
    customer_phone    VARCHAR(20)   NOT NULL,
    customer_address  VARCHAR(500)  NOT NULL,
    customer_note     TEXT          DEFAULT NULL,
    payment_method    VARCHAR(20)   NOT NULL DEFAULT 'cod',
    total_amount      DECIMAL(14,0) NOT NULL DEFAULT 0,
    status            ENUM('pending','processing','shipped','delivered','cancelled')
                                    NOT NULL DEFAULT 'pending',
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────
--  Bảng chi tiết đơn hàng
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED  NOT NULL,
    variant_id   INT UNSIGNED  DEFAULT NULL,
    variant_name VARCHAR(200)  NOT NULL,
    price        DECIMAL(12,0) NOT NULL,
    quantity     INT UNSIGNED  NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────
--  Dữ liệu mẫu
-- ──────────────────────────────────────────────────────────

INSERT INTO products (name, description, rating, review_count, sold) VALUES
(
    'Áo Thun Nam Cao Cấp Premium Cotton - Nhiều Size và Màu Sắc - Chất Liệu Co Giãn 4 Chiều Thời Trang Hàn Quốc',
    'Áo thun nam cao cấp được làm từ vải cotton 100% nguyên chất, mềm mại và thoáng mát. Chất liệu co giãn 4 chiều giúp thoải mái trong mọi hoạt động. Thiết kế hiện đại, phù hợp nhiều dịp mặc từ đi làm đến dạo phố.',
    4.8,
    2341,
    15892
);

SET @pid = LAST_INSERT_ID();

-- Ảnh sản phẩm (gắn theo màu)
INSERT INTO product_images (product_id, image, color, sort_order) VALUES
(@pid, 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=600&q=80', 'Trắng',     0),
(@pid, 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=600&q=80', 'Đen',       1),
(@pid, 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=600&q=80', 'Xanh Navy', 2),
(@pid, 'https://images.unsplash.com/photo-1581655353564-df123a1eb820?w=600&q=80', NULL,        3),
(@pid, 'https://images.unsplash.com/photo-1503341504253-dff4815485f1?w=600&q=80', NULL,        4);

-- Biến thể
INSERT INTO product_variants (product_id, name, color, size, price, original_price, stock) VALUES
(@pid, 'Trắng - S',        'Trắng',     'S',   185000, 259000, 42),
(@pid, 'Trắng - M',        'Trắng',     'M',   185000, 259000, 67),
(@pid, 'Trắng - L',        'Trắng',     'L',   195000, 269000, 28),
(@pid, 'Trắng - XL',       'Trắng',     'XL',  205000, 279000, 15),
(@pid, 'Trắng - XXL',      'Trắng',     'XXL', 215000, 289000,  0),
(@pid, 'Đen - S',          'Đen',       'S',   185000, 259000, 38),
(@pid, 'Đen - M',          'Đen',       'M',   185000, 259000, 54),
(@pid, 'Đen - L',          'Đen',       'L',   195000, 269000, 19),
(@pid, 'Đen - XL',         'Đen',       'XL',  205000, 279000,  7),
(@pid, 'Đen - XXL',        'Đen',       'XXL', 215000, 289000, 12),
(@pid, 'Xanh Navy - S',    'Xanh Navy', 'S',   195000, 279000, 22),
(@pid, 'Xanh Navy - M',    'Xanh Navy', 'M',   195000, 279000, 31),
(@pid, 'Xanh Navy - L',    'Xanh Navy', 'L',   205000, 289000,  0),
(@pid, 'Xanh Navy - XL',   'Xanh Navy', 'XL',  215000, 299000, 11),
(@pid, 'Xanh Navy - XXL',  'Xanh Navy', 'XXL', 225000, 309000,  5);
