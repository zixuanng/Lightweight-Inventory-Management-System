-- ============================================================
-- IMS Enterprise Edition - Database Schema & Seed Data
-- Database: ims_enterprise_db
-- ============================================================

CREATE DATABASE IF NOT EXISTS `ims_enterprise_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `ims_enterprise_db`;

-- Drop in reverse FK order
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `stock_logs`;
DROP TABLE IF EXISTS `stock_transfer_items`;
DROP TABLE IF EXISTS `stock_transfers`;
DROP TABLE IF EXISTS `po_items`;
DROP TABLE IF EXISTS `purchase_orders`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `product_batches`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `locations`;
DROP TABLE IF EXISTS `warehouses`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- 1. Roles
CREATE TABLE `roles` (
  `role_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(50) UNIQUE NOT NULL
) ENGINE=InnoDB;

-- 2. Users
CREATE TABLE `users` (
  `user_id`       INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(50) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id`       INT NOT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`role_id`)
) ENGINE=InnoDB;

-- 3. Warehouses
CREATE TABLE `warehouses` (
  `warehouse_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(100) NOT NULL,
  `code`         VARCHAR(20) UNIQUE NOT NULL,
  `address`      TEXT
) ENGINE=InnoDB;

-- 4. Locations (Zone + Shelf/Bin within a Warehouse)
CREATE TABLE `locations` (
  `location_id`  INT AUTO_INCREMENT PRIMARY KEY,
  `warehouse_id` INT NOT NULL,
  `zone`         VARCHAR(20) NOT NULL,
  `shelf_bin`    VARCHAR(20) NOT NULL,
  FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`warehouse_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Suppliers
CREATE TABLE `suppliers` (
  `supplier_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_name` VARCHAR(100) NOT NULL,
  `contact_email` VARCHAR(100),
  `phone`         VARCHAR(20)
) ENGINE=InnoDB;

-- 6. Categories
CREATE TABLE `categories` (
  `category_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- 7. Products
CREATE TABLE `products` (
  `product_id`          INT AUTO_INCREMENT PRIMARY KEY,
  `sku`                 VARCHAR(50) UNIQUE NOT NULL,
  `barcode`             VARCHAR(100) UNIQUE,
  `name`                VARCHAR(150) NOT NULL,
  `category_id`         INT,
  `default_supplier_id` INT,
  `reorder_level`       INT NOT NULL DEFAULT 10,
  `unit_cost`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `unit_price`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `abc_class`           ENUM('A','B','C') DEFAULT 'C',
  `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`)         REFERENCES `categories`(`category_id`) ON DELETE SET NULL,
  FOREIGN KEY (`default_supplier_id`) REFERENCES `suppliers`(`supplier_id`)  ON DELETE SET NULL,
  INDEX `idx_sku`     (`sku`),
  INDEX `idx_barcode` (`barcode`)
) ENGINE=InnoDB;

-- 8. Product Batches (Lot tracking)
CREATE TABLE `product_batches` (
  `batch_id`           INT AUTO_INCREMENT PRIMARY KEY,
  `product_id`         INT NOT NULL,
  `batch_number`       VARCHAR(50) NOT NULL,
  `manufacturing_date` DATE,
  `expiry_date`        DATE NOT NULL,
  `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
  INDEX `idx_expiry` (`expiry_date`)
) ENGINE=InnoDB;

-- 9. Inventory (quantity per product+batch+location)
CREATE TABLE `inventory` (
  `inventory_id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id`   INT NOT NULL,
  `batch_id`     INT NOT NULL,
  `location_id`  INT NOT NULL,
  `quantity`     INT NOT NULL DEFAULT 0,
  UNIQUE KEY `uq_inv` (`product_id`, `batch_id`, `location_id`),
  FOREIGN KEY (`product_id`)  REFERENCES `products`(`product_id`)       ON DELETE CASCADE,
  FOREIGN KEY (`batch_id`)    REFERENCES `product_batches`(`batch_id`)  ON DELETE CASCADE,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`location_id`)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Purchase Orders
CREATE TABLE `purchase_orders` (
  `po_id`        INT AUTO_INCREMENT PRIMARY KEY,
  `po_number`    VARCHAR(50) UNIQUE NOT NULL,
  `supplier_id`  INT NOT NULL,
  `status`       ENUM('Draft','Sent','Partial','Completed','Cancelled') DEFAULT 'Draft',
  `notes`        TEXT,
  `created_by`   INT NOT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`supplier_id`),
  FOREIGN KEY (`created_by`)  REFERENCES `users`(`user_id`)
) ENGINE=InnoDB;

-- 11. PO Line Items
CREATE TABLE `po_items` (
  `po_item_id`        INT AUTO_INCREMENT PRIMARY KEY,
  `po_id`             INT NOT NULL,
  `product_id`        INT NOT NULL,
  `ordered_quantity`  INT NOT NULL,
  `received_quantity` INT DEFAULT 0,
  `unit_cost`         DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`po_id`)       REFERENCES `purchase_orders`(`po_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`)  REFERENCES `products`(`product_id`)
) ENGINE=InnoDB;

-- 12. Stock Transfers (inter-warehouse)
CREATE TABLE `stock_transfers` (
  `transfer_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `transfer_code`    VARCHAR(50) UNIQUE NOT NULL,
  `from_location_id` INT NOT NULL,
  `to_location_id`   INT NOT NULL,
  `status`           ENUM('Pending','In-Transit','Received','Cancelled') DEFAULT 'Pending',
  `notes`            TEXT,
  `initiated_by`     INT NOT NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`from_location_id`) REFERENCES `locations`(`location_id`),
  FOREIGN KEY (`to_location_id`)   REFERENCES `locations`(`location_id`),
  FOREIGN KEY (`initiated_by`)     REFERENCES `users`(`user_id`)
) ENGINE=InnoDB;

-- 13. Stock Transfer Line Items
CREATE TABLE `stock_transfer_items` (
  `transfer_item_id` INT AUTO_INCREMENT PRIMARY KEY,
  `transfer_id`      INT NOT NULL,
  `product_id`       INT NOT NULL,
  `batch_id`         INT NOT NULL,
  `quantity`         INT NOT NULL,
  FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers`(`transfer_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`)  REFERENCES `products`(`product_id`),
  FOREIGN KEY (`batch_id`)    REFERENCES `product_batches`(`batch_id`)
) ENGINE=InnoDB;

-- 14. Stock Logs (full transaction log)
CREATE TABLE `stock_logs` (
  `log_id`           INT AUTO_INCREMENT PRIMARY KEY,
  `product_id`       INT NOT NULL,
  `batch_id`         INT NOT NULL,
  `location_id`      INT NOT NULL,
  `user_id`          INT NOT NULL,
  `transaction_type` ENUM('STOCK_IN','STOCK_OUT','TRANSFER','ADJUSTMENT') NOT NULL,
  `quantity_change`  INT NOT NULL,
  `reference_id`     VARCHAR(50),
  `notes`            TEXT,
  `timestamp`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`)  REFERENCES `products`(`product_id`),
  FOREIGN KEY (`batch_id`)    REFERENCES `product_batches`(`batch_id`),
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`location_id`),
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`user_id`)
) ENGINE=InnoDB;

-- 15. Audit Logs (JSON delta, immutable)
CREATE TABLE `audit_logs` (
  `audit_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `table_name` VARCHAR(50)  NOT NULL,
  `record_id`  INT NOT NULL,
  `delta_json` JSON,
  `ip_address` VARCHAR(45),
  `timestamp`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Roles
INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'Super Admin'),
(2, 'Warehouse Manager'),
(3, 'Inventory Clerk'),
(4, 'Auditor');

-- Users (passwords set via auto-heal on first login)
-- superadmin / admin123 | manager / manager123 | clerk / clerk123 | auditor / auditor123
INSERT INTO `users` (`username`, `password_hash`, `role_id`) VALUES
('superadmin', 'NEEDS_REHASH:admin123',    1),
('manager',    'NEEDS_REHASH:manager123',  2),
('clerk',      'NEEDS_REHASH:clerk123',    3),
('auditor',    'NEEDS_REHASH:auditor123',  4);

-- Warehouses
INSERT INTO `warehouses` (`warehouse_id`, `name`, `code`, `address`) VALUES
(1, 'Main Warehouse (HQ)',  'WH-HQ',  '100 Commerce Blvd, Industrial Park'),
(2, 'North Distribution',   'WH-NTH', '45 Logistics Ave, North Zone'),
(3, 'South Storage Depot',  'WH-STH', '22 Depot Road, Southern District');

-- Locations
INSERT INTO `locations` (`location_id`, `warehouse_id`, `zone`, `shelf_bin`) VALUES
(1, 1, 'A', 'A-01'), (2, 1, 'A', 'A-02'), (3, 1, 'B', 'B-01'),
(4, 2, 'A', 'A-01'), (5, 2, 'B', 'B-01'),
(6, 3, 'A', 'A-01');

-- Suppliers
INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_email`, `phone`) VALUES
(1, 'TechDistro Global',       'sales@techdistro.com',       '+1-800-555-0199'),
(2, 'Papyrus Office Co',       'support@papyrusoffice.com',  '+1-800-555-0244'),
(3, 'Industrial Hardware Ltd', 'info@indhardware.com',       '+1-800-555-0311');

-- Categories
INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'Electronics'),
(2, 'Office Supplies'),
(3, 'Packaging & Hardware');

-- Products
INSERT INTO `products` (`product_id`, `sku`, `barcode`, `name`, `category_id`, `default_supplier_id`, `reorder_level`, `unit_cost`, `unit_price`, `abc_class`) VALUES
(1, 'ELEC-1001', '8901234560011', 'Wireless Ergonomic Mouse',        1, 1, 10, 18.00, 29.99, 'B'),
(2, 'ELEC-1002', '8901234560022', 'Mechanical Keyboard (RGB)',       1, 1, 15, 55.00, 89.50, 'A'),
(3, 'OFF-2001',  '8901234560033', 'A4 Copy Paper (500 Sheets)',      2, 2, 25,  3.50,  6.75, 'C'),
(4, 'OFF-2002',  '8901234560044', 'Heavy Duty Stapler',              2, 2, 10, 10.00, 18.25, 'C'),
(5, 'PKG-3001',  '8901234560055', 'Heavy Duty Shipping Tape (x6)',   3, 3, 20,  8.00, 14.99, 'B'),
(6, 'ELEC-1003', '8901234560066', 'USB-C Multi-Port Adapter Hub',    1, 1,  8, 20.00, 34.00, 'A');

-- Product Batches
INSERT INTO `product_batches` (`batch_id`, `product_id`, `batch_number`, `manufacturing_date`, `expiry_date`) VALUES
(1, 1, 'BATCH-MOU-001', '2025-01-10', '2028-01-10'),
(2, 2, 'BATCH-KBD-001', '2025-03-05', '2027-10-15'),
(3, 3, 'BATCH-PPR-001', '2026-01-01', '2026-10-31'),  -- expiring soon
(4, 4, 'BATCH-STP-001', '2025-06-01', '2028-06-01'),
(5, 5, 'BATCH-TPE-001', '2025-09-01', '2027-09-01'),
(6, 6, 'BATCH-USB-001', '2025-02-20', '2027-12-31');

-- Inventory (product + batch + location = quantity)
INSERT INTO `inventory` (`product_id`, `batch_id`, `location_id`, `quantity`) VALUES
(1, 1, 1, 30), (1, 1, 4, 15),
(2, 2, 1, 4),
(3, 3, 1, 80), (3, 3, 2, 40),
(4, 4, 2, 7),
(5, 5, 3, 50),
(6, 6, 1, 2);

-- Sample Stock Logs
INSERT INTO `stock_logs` (`product_id`, `batch_id`, `location_id`, `user_id`, `transaction_type`, `quantity_change`, `reference_id`, `notes`) VALUES
(1, 1, 1, 1, 'STOCK_IN',  50, 'PO-20250101', 'Initial batch intake'),
(1, 1, 1, 2, 'STOCK_OUT', -5, 'SO-10001',    'Customer order fulfillment'),
(2, 2, 1, 1, 'STOCK_IN',  20, 'PO-20250102', 'Initial shipment'),
(2, 2, 1, 3, 'STOCK_OUT',-16, 'SO-10002',    'Bulk sale to office client');
