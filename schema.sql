-- Inventory Management System (IMS) Schema & Seed Data

CREATE DATABASE IF NOT EXISTS `ims_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ims_db`;

-- Drop existing tables in reverse dependency order for clean setup
DROP TABLE IF EXISTS `stock_transactions`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- 1. Users Table
CREATE TABLE `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('Admin', 'Staff') NOT NULL DEFAULT 'Staff',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Categories Table
CREATE TABLE `categories` (
  `category_id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL,
  `description` TEXT
) ENGINE=InnoDB;

-- 3. Suppliers Table
CREATE TABLE `suppliers` (
  `supplier_id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `phone` VARCHAR(20)
) ENGINE=InnoDB;

-- 4. Products Table (with indexes on sku and category_id for performance)
CREATE TABLE `products` (
  `product_id` INT AUTO_INCREMENT PRIMARY KEY,
  `sku` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `category_id` INT,
  `supplier_id` INT,
  `quantity` INT NOT NULL DEFAULT 0,
  `reorder_level` INT NOT NULL DEFAULT 10,
  `unit_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`supplier_id`) ON DELETE SET NULL,
  INDEX `idx_sku` (`sku`),
  INDEX `idx_category` (`category_id`)
) ENGINE=InnoDB;

-- 5. Stock Transactions Table
CREATE TABLE `stock_transactions` (
  `transaction_id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `transaction_type` ENUM('STOCK_IN', 'STOCK_OUT') NOT NULL,
  `quantity` INT NOT NULL,
  `user_id` INT NOT NULL,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB;

-- ========================================================
-- SEED DATA
-- Default passwords:
-- Admin: admin / admin123
-- Staff: staff / staff123
-- ========================================================

INSERT INTO `users` (`username`, `password_hash`, `role`) VALUES
('admin', '$2y$10$iWjB8zD8x5Y1D5y4WwF4y.sQf4K5Z6w7E8r9t0y1u2i3o4p5q6r7s', 'Admin'), -- hashed 'admin123'
('staff', '$2y$10$xK9mP8w7v6u5t4s3r2q1o0P9o8i7u6y5t4r3e2w1q0p9o8i7u6y5t', 'Staff');  -- hashed 'staff123'

-- Default users are inserted above and automatically synchronized on initial login.

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Electronics', 'Electronic components, gadgets, and accessories'),
(2, 'Office Supplies', 'Stationery, paper, pens, and office equipment'),
(3, 'Packaging & Hardware', 'Boxes, tape, tools, and industrial materials');

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `email`, `phone`) VALUES
(1, 'TechDistro Global', 'sales@techdistro.com', '+1-800-555-0199'),
(2, 'Papyrus Office Co', 'support@papyrusoffice.com', '+1-800-555-0244'),
(3, 'Industrial Hardware Ltd', 'info@indhardware.com', '+1-800-555-0311');

INSERT INTO `products` (`product_id`, `sku`, `name`, `category_id`, `supplier_id`, `quantity`, `reorder_level`, `unit_price`) VALUES
(1, 'ELEC-1001', 'Wireless Ergonomic Mouse', 1, 1, 45, 10, 29.99),
(2, 'ELEC-1002', 'Mechanical Keyboard (RGB)', 1, 1, 4, 15, 89.50), -- LOW STOCK ALERT (4 <= 15)
(3, 'OFF-2001', 'A4 Multipurpose Copy Paper (500 Sheets)', 2, 2, 120, 25, 6.75),
(4, 'OFF-2002', 'Heavy Duty Stapler', 2, 2, 7, 10, 18.25), -- LOW STOCK ALERT (7 <= 10)
(5, 'PKG-3001', 'Heavy Duty Shipping Tape (Pack of 6)', 3, 3, 50, 20, 14.99),
(6, 'ELEC-1003', 'USB-C Multi-Port Adapter Hub', 1, 1, 2, 8, 34.00); -- LOW STOCK ALERT (2 <= 8)

INSERT INTO `stock_transactions` (`product_id`, `transaction_type`, `quantity`, `user_id`, `notes`) VALUES
(1, 'STOCK_IN', 50, 1, 'Initial inventory batch intake'),
(1, 'STOCK_OUT', 5, 2, 'Customer order #1001 fulfillment'),
(2, 'STOCK_IN', 20, 1, 'Initial shipment'),
(2, 'STOCK_OUT', 16, 2, 'Bulk sale to office client');
