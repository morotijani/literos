-- =============================================================================
-- LITEROS — Lite Restaurant Operating System
-- Complete MySQL Schema & Rich Seed Data for "Shakes and Dessert" (Kumasi, Ghana)
-- Owner: Hajia Rabi Adam | Currency: GHS (¢)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `literos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `literos`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `coffers_ledger`;
DROP TABLE IF EXISTS `expenditures`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `shifts`;
DROP TABLE IF EXISTS `restaurant_tables`;
DROP TABLE IF EXISTS `menu_items`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `staff`;
DROP TABLE IF EXISTS `branches`;
DROP TABLE IF EXISTS `company_profile`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Company Profile
CREATE TABLE `company_profile` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `owner_name` VARCHAR(120) DEFAULT 'Hajia Rabi Adam',
  `tagline` VARCHAR(200) DEFAULT 'Artisanal Milkshakes, Gelato, Pastries & Grill • Kumasi, Ghana',
  `logo_text` VARCHAR(20) DEFAULT 'S&D',
  `currency_symbol` VARCHAR(10) DEFAULT 'GHS ',
  `currency_code` VARCHAR(10) DEFAULT 'GHS',
  `tax_rate` DECIMAL(5,2) DEFAULT 0.00,
  `service_charge_rate` DECIMAL(5,2) DEFAULT 0.00,
  `email` VARCHAR(120) DEFAULT 'hq@shakesanddessertgh.com',
  `phone` VARCHAR(40) DEFAULT '+233 24 489 1020',
  `address` VARCHAR(255) DEFAULT 'KNUST Commercial Road, Ayiduase, Kumasi, Ashanti Region, Ghana',
  `receipt_footer` VARCHAR(255) DEFAULT 'Medaase! Thank you for dining at Shakes and Dessert (By Hajia Rabi Adam).',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Branches
CREATE TABLE `branches` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(120) NOT NULL,
  `city` VARCHAR(80) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(40) NOT NULL,
  `manager_name` VARCHAR(100) NOT NULL,
  `opening_hours` VARCHAR(100) DEFAULT '08:00 AM - 11:30 PM',
  `tax_override` DECIMAL(5,2) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Staff / Sales Personnel
CREATE TABLE `staff` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `employee_code` VARCHAR(24) NOT NULL UNIQUE,
  `full_name` VARCHAR(120) NOT NULL,
  `role` ENUM('Admin', 'Manager', 'Cashier', 'Waiter', 'Chef') NOT NULL DEFAULT 'Waiter',
  `pin_code` VARCHAR(10) NOT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `phone` VARCHAR(40) DEFAULT NULL,
  `avatar_color` VARCHAR(20) DEFAULT '#f59e0b',
  `hourly_rate` DECIMAL(10,2) DEFAULT 45.00,
  `commission_rate` DECIMAL(5,2) DEFAULT 1.50,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_staff_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Categories
CREATE TABLE `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `icon` VARCHAR(50) DEFAULT 'fa-utensils',
  `color` VARCHAR(20) DEFAULT '#f59e0b',
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- 5. Menu Items
CREATE TABLE `menu_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NOT NULL,
  `sku` VARCHAR(30) NOT NULL UNIQUE,
  `name` VARCHAR(140) NOT NULL,
  `description` VARCHAR(255) DEFAULT '',
  `price` DECIMAL(10,2) NOT NULL,
  `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `course_type` ENUM('Starter', 'Main', 'Drink', 'Dessert', 'Side') DEFAULT 'Dessert',
  `prep_time_mins` INT DEFAULT 8,
  `calories` INT DEFAULT 450,
  `stock_qty` INT DEFAULT 200,
  `low_stock_threshold` INT DEFAULT 20,
  `modifiers_json` TEXT DEFAULT NULL,
  `image_emoji` VARCHAR(20) DEFAULT '🥤',
  `is_popular` TINYINT(1) DEFAULT 0,
  `is_available` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Restaurant Tables
CREATE TABLE `restaurant_tables` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `table_number` VARCHAR(20) NOT NULL,
  `zone` ENUM('Main Hall', 'Patio Terrace', 'VIP Lounge', 'Bar Counter') DEFAULT 'Main Hall',
  `capacity` INT DEFAULT 4,
  `status` ENUM('Available', 'Occupied', 'Reserved', 'Needs Cleaning') DEFAULT 'Available',
  `current_guests` INT DEFAULT 0,
  `assigned_staff_id` INT UNSIGNED DEFAULT NULL,
  CONSTRAINT `fk_table_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Shifts / Cash Drawer
CREATE TABLE `shifts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `staff_id` INT UNSIGNED NOT NULL,
  `opening_cash` DECIMAL(12,2) NOT NULL DEFAULT 2500.00,
  `closing_cash` DECIMAL(12,2) DEFAULT NULL,
  `status` ENUM('Open', 'Closed') DEFAULT 'Open',
  `notes` VARCHAR(255) DEFAULT NULL,
  `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `closed_at` TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT `fk_shift_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shift_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. Orders / Sales
CREATE TABLE `orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(32) NOT NULL UNIQUE,
  `branch_id` INT UNSIGNED NOT NULL,
  `table_id` INT UNSIGNED DEFAULT NULL,
  `staff_id` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(100) DEFAULT 'Walk-in Guest',
  `guests_count` INT DEFAULT 1,
  `order_type` ENUM('Dine-In', 'Takeaway', 'Delivery') NOT NULL DEFAULT 'Dine-In',
  `status` ENUM('Pending', 'Preparing', 'Ready', 'Served', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Completed',
  `payment_status` ENUM('Unpaid', 'Paid', 'Refunded') NOT NULL DEFAULT 'Paid',
  `payment_method` ENUM('Cash', 'MoMo', 'QR Pay', 'Card', 'Split') DEFAULT 'Cash',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tip_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_tendered` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL DEFAULT NULL,
  INDEX `idx_orders_created_at` (`created_at`),
  INDEX `idx_orders_branch_date` (`branch_id`, `created_at`),
  INDEX `idx_orders_payment_method` (`payment_method`),
  CONSTRAINT `fk_order_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. Order Items
CREATE TABLE `order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `menu_item_id` INT UNSIGNED NOT NULL,
  `item_name` VARCHAR(140) NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 1,
  `modifiers` VARCHAR(255) DEFAULT NULL,
  `line_total` DECIMAL(12,2) NOT NULL,
  `kitchen_status` ENUM('Queued', 'Cooking', 'Plated') DEFAULT 'Plated',
  INDEX `idx_orderitems_order` (`order_id`),
  CONSTRAINT `fk_orderitem_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Expenditures / Operating Expenses
CREATE TABLE `expenditures` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference_no` VARCHAR(32) NOT NULL UNIQUE,
  `branch_id` INT UNSIGNED NOT NULL,
  `staff_id` INT UNSIGNED NOT NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT 'Ingredients & Dairy',
  `title` VARCHAR(160) NOT NULL,
  `vendor_name` VARCHAR(120) DEFAULT NULL,
  `payment_method` ENUM('Cash', 'MoMo', 'QR Pay', 'Bank Transfer', 'Card') NOT NULL DEFAULT 'MoMo',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `expense_date` DATE NOT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_exp_date` (`expense_date`),
  INDEX `idx_exp_branch_date` (`branch_id`, `expense_date`),
  CONSTRAINT `fk_exp_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exp_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 11. Finance & Coffers Treasury Ledger (Deposits, Withdrawals for Goods/Market & Normal Withdrawals)
CREATE TABLE `coffers_ledger` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference_no` VARCHAR(32) NOT NULL UNIQUE,
  `branch_id` INT UNSIGNED NOT NULL,
  `staff_id` INT UNSIGNED NOT NULL,
  `entry_type` ENUM('Deposit', 'Withdrawal') NOT NULL DEFAULT 'Withdrawal',
  `purpose_type` VARCHAR(80) NOT NULL DEFAULT 'Withdrawal for Goods / Market',
  `payment_channel` ENUM('Cash', 'MoMo', 'Bank Transfer', 'QR Pay') NOT NULL DEFAULT 'Cash',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `recipient_or_source` VARCHAR(140) DEFAULT NULL,
  `description` VARCHAR(255) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_coffers_date` (`transaction_date`),
  INDEX `idx_coffers_branch_date` (`branch_id`, `transaction_date`),
  INDEX `idx_coffers_type` (`entry_type`, `purpose_type`),
  CONSTRAINT `fk_coffers_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_coffers_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================================================
-- STATIC CORE SEED DATA (COMPANY, 3 KUMASI BRANCHES, 31 PERSONNEL, MENU, TABLES)
-- =============================================================================

INSERT INTO `company_profile` (`id`, `name`, `owner_name`, `tagline`, `logo_text`, `currency_symbol`, `currency_code`, `tax_rate`, `service_charge_rate`, `email`, `phone`, `address`, `receipt_footer`) VALUES
(1, 'Shakes and Dessert', 'Hajia Rabi Adam', 'Start With Passion, Cooked With Love ♥ • Shakes, Ice Cream & Desserts', 'S&D', 'GHS ', 'GHS', 0.00, 0.00, 'hajia.rabi@shakesanddessertgh.com', '054 661 3197 / 050 064 9477', 'Ayeduasi Market, Sawaba Junction & Dichemso, Kumasi, Ghana', 'Thank you for choosing Shakes & Dessert ♥ • @shakes_and_dessert_gh');

INSERT INTO `branches` (`id`, `code`, `name`, `city`, `address`, `phone`, `manager_name`, `opening_hours`, `tax_override`, `is_active`) VALUES
(1, 'KSI-AYI', 'Ayeduasi Market Branch', 'Kumasi (Ayiduase)', 'Ayeduasi Market Road, Near KNUST Gate, Kumasi', '054 661 3197 / 059 680 1426', 'Zainab', '08:00 AM - 11:30 PM', NULL, 1),
(2, 'KSI-SAW', 'Sawaba Junction Branch', 'Kumasi (Sawaba)', 'Sawaba Junction Main Road, Kumasi', '050 064 9477 / 054 643 5129', 'Baki', '08:30 AM - 11:00 PM', NULL, 1),
(3, 'KSI-DEC', 'Dichemso Branch', 'Kumasi (Dichemso)', 'Dichemso Plaza Road, Kumasi', '053 569 8046 / 050 064 9477', 'Kudus', '09:00 AM - 11:30 PM', NULL, 1);

-- 19 Shakes and Dessert Personnel:
-- 1 Owner/Executive Admin: Hajia Rabi Adam
-- Branch 1 (Ayiduase): Zainab (Branch Manager), Yasmine (Waitress), Aisha (Waitress), Latif (Waiter), Samuel (Waiter), Fati (Head Chef), Dorithy (Assistant Chef)
-- Branch 2 (Sawaba):   Baki (Manager/Supervisor), Aisha (Waitress), Maabena (Head Chef), Fati (Assistant Chef)
-- Branch 3 (Dechemso): Kudus (Branch Manager), Matilda (Cashier), Daniel (Waiter), Mary (Waitress), Nat (Waiter), Theresah (Head Chef), Belinda (Assistant Chef)
INSERT INTO `staff` (`id`, `branch_id`, `employee_code`, `full_name`, `role`, `pin_code`, `email`, `phone`, `avatar_color`, `hourly_rate`, `commission_rate`, `is_active`) VALUES
(1, 1, 'EMP-001', 'Hajia Rabi Adam', 'Admin', '1111', 'hajia.rabi@shakesanddessertgh.com', '054 661 3197', '#f59e0b', 120.00, 0.00, 1),

-- Branch 1: Ayiduase Branch
(2, 1, 'AYI-MGR', 'Zainab (Branch Manager)', 'Manager', '1001', 'zainab@shakesanddessertgh.com', '054 661 3197', '#8b5cf6', 65.00, 0.00, 1),
(3, 1, 'AYI-W01', 'Yasmine (Waitress)', 'Waiter', '3001', 'yasmine@shakesanddessertgh.com', '059 680 1426', '#ec4899', 35.00, 0.00, 1),
(4, 1, 'AYI-W02', 'Aisha - Ayiduase (Waitress)', 'Waiter', '3002', 'aisha.ayi@shakesanddessertgh.com', '054 661 3198', '#6366f1', 35.00, 0.00, 1),
(5, 1, 'AYI-W03', 'Latif (Waiter)', 'Waiter', '3003', 'latif@shakesanddessertgh.com', '054 661 3199', '#06b6d4', 35.00, 0.00, 1),
(6, 1, 'AYI-W04', 'Samuel (Waiter)', 'Waiter', '3004', 'samuel@shakesanddessertgh.com', '059 680 1427', '#14b8a6', 35.00, 0.00, 1),
(7, 1, 'AYI-C01', 'Fati - Ayiduase (Head Chef)', 'Chef', '4001', 'fati.ayi@shakesanddessertgh.com', '054 661 3200', '#ef4444', 55.00, 0.00, 1),
(8, 1, 'AYI-C02', 'Dorithy (Assistant Chef)', 'Chef', '4002', 'dorithy@shakesanddessertgh.com', '059 680 1428', '#f97316', 48.00, 0.00, 1),

-- Branch 2: Sawaba Branch
(9, 2, 'SAW-MGR', 'Baki (Manager / Supervisor)', 'Manager', '5001', 'baki@shakesanddessertgh.com', '050 064 9477', '#8b5cf6', 65.00, 0.00, 1),
(10, 2, 'SAW-W01', 'Aisha - Sawaba (Waitress)', 'Waiter', '6001', 'aisha.saw@shakesanddessertgh.com', '054 643 5129', '#ec4899', 35.00, 0.00, 1),
(11, 2, 'SAW-C01', 'Maabena (Head Chef)', 'Chef', '4011', 'maabena@shakesanddessertgh.com', '050 064 9478', '#ef4444', 55.00, 0.00, 1),
(12, 2, 'SAW-C02', 'Fati - Sawaba (Assistant Chef)', 'Chef', '4012', 'fati.saw@shakesanddessertgh.com', '054 643 5130', '#f97316', 48.00, 0.00, 1),

-- Branch 3: Dechemso Branch
(13, 3, 'DEC-MGR', 'Kudus (Branch Manager)', 'Manager', '7001', 'kudus@shakesanddessertgh.com', '053 569 8046', '#8b5cf6', 65.00, 0.00, 1),
(14, 3, 'DEC-CSH', 'Matilda (Cashier)', 'Cashier', '2001', 'matilda@shakesanddessertgh.com', '050 064 9477', '#10b981', 42.00, 0.00, 1),
(15, 3, 'DEC-W01', 'Daniel (Waiter)', 'Waiter', '8001', 'daniel@shakesanddessertgh.com', '053 569 8047', '#6366f1', 35.00, 0.00, 1),
(16, 3, 'DEC-W02', 'Mary (Waitress)', 'Waiter', '8002', 'mary@shakesanddessertgh.com', '053 569 8048', '#ec4899', 35.00, 0.00, 1),
(17, 3, 'DEC-W03', 'Nat (Waiter)', 'Waiter', '8003', 'nat@shakesanddessertgh.com', '053 569 8049', '#06b6d4', 35.00, 0.00, 1),
(18, 3, 'DEC-C01', 'Theresah (Head Chef)', 'Chef', '4021', 'theresah@shakesanddessertgh.com', '053 569 8050', '#ef4444', 55.00, 0.00, 1),
(19, 3, 'DEC-C02', 'Belinda (Assistant Chef)', 'Chef', '4022', 'belinda@shakesanddessertgh.com', '053 569 8051', '#f97316', 48.00, 0.00, 1);

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `color`, `sort_order`, `is_active`) VALUES
(1, 'Mini Pancakes & Waffles', 'mini-pancakes-waffles', 'fa-cookie-bite', '#ec4899', 10, 1),
(2, 'Crepes & Pancakes (Pick 2 Sauce)', 'crepes-and-pancakes', 'fa-utensils', '#f59e0b', 20, 1),
(3, 'Milkshakes', 'milkshakes', 'fa-blender', '#a855f7', 30, 1),
(4, 'Crazy Shakes', 'crazy-shakes', 'fa-wand-magic-sparkles', '#f43f5e', 40, 1),
(5, 'SD Special', 'sd-special', 'fa-star', '#e11d48', 50, 1),
(6, 'Cakes & Pastries', 'cakes-and-pastries', 'fa-cake-candles', '#d97706', 60, 1),
(7, 'Signature Dessert', 'signature-dessert', 'fa-bowl-food', '#be185d', 70, 1),
(8, 'Scoop Ice Cream', 'scoop-ice-cream', 'fa-ice-cream', '#06b6d4', 80, 1),
(9, 'Slushies', 'slushies', 'fa-glass-water', '#3b82f6', 90, 1),
(10, 'Mocktails', 'mocktails', 'fa-martini-glass-citrus', '#10b981', 100, 1),
(11, 'Finger Food', 'finger-food', 'fa-drumstick-bite', '#f97316', 110, 1);

INSERT INTO `menu_items` (`id`, `category_id`, `sku`, `name`, `description`, `price`, `cost`, `course_type`, `prep_time_mins`, `calories`, `stock_qty`, `low_stock_threshold`, `modifiers_json`, `image_emoji`, `is_popular`, `is_available`) VALUES
-- 1. MINI PANCAKES & WAFFLES (Mini Pancakes + Waffles)
(1, 1, 'MPW-101', 'Biscoff Lover (Mini Pancakes)', 'Fluffy bite-sized Dutch mini pancakes drizzled with warm Lotus Biscoff spread & speculoos crumbs', 50.00, 16.00, 'Dessert', 6, 480, 220, 20, '["Extra Biscoff Sauce (+GHS 10)", "Add Vanilla Scoop (+GHS 15)"]', '🥞', 1, 1),
(2, 1, 'MPW-102', 'Milky Way (Mini Pancakes)', 'Warm mini pancakes glazed with creamy white & milk chocolate Milky Way drizzle', 50.00, 16.00, 'Dessert', 6, 470, 200, 20, '["White Chocolate Drizzle", "Add Whipped Cream (+GHS 10)"]', '🥞', 0, 1),
(3, 1, 'MPW-103', 'Simple Bee (Mini Pancakes)', 'Classic golden mini pancakes with pure wildflower honey, butter & powdered sugar', 50.00, 15.00, 'Dessert', 5, 420, 210, 20, '["Extra Honey", "Add Strawberry Slices (+GHS 10)"]', '🍯', 0, 1),
(4, 1, 'MPW-104', 'Sweet Tooth (Mini Pancakes)', 'Loaded mini pancakes with Nutella, strawberry glaze, Oreo crumbs & sweet cream drizzle', 60.00, 20.00, 'Dessert', 6, 540, 200, 20, '["Add Ice Cream Scoop (+GHS 15)", "Extra Nutella (+GHS 10)"]', '🍓', 1, 1),
(5, 1, 'MPW-105', 'Waffer Land', 'Crispy golden Belgian waffle served warm with signature chocolate & syrup drizzle', 50.00, 16.00, 'Dessert', 7, 510, 190, 20, '["Add Ice Cream Scoop (+GHS 15)", "Whipped Cream (+GHS 10)"]', '🧇', 0, 1),
(6, 1, 'MPW-106', 'Sweet Tooth (Waffles)', 'Belgian waffle topped with indulgent mixed sauces, crushed cookies & fresh berries', 60.00, 20.00, 'Dessert', 7, 560, 190, 20, '["Extra Biscoff (+GHS 10)", "Add Vanilla Scoop (+GHS 15)"]', '🧇', 1, 1),
(7, 1, 'MPW-107', 'Biscoff Lover (Waffles)', 'Warm Belgian waffle smothered in melted Lotus Biscoff butter and caramelized biscuit crumble', 50.00, 17.00, 'Dessert', 7, 530, 210, 20, '["Add Vanilla Scoop (+GHS 15)", "Extra Biscoff Crumb (+GHS 10)"]', '🧇', 1, 1),
(8, 1, 'MPW-108', 'Oreo Waffles', 'Crisp waffle loaded with crushed Oreo cookies, cookies-and-cream drizzle & dark chocolate sauce', 60.00, 20.00, 'Dessert', 7, 550, 195, 20, '["Add Oreo Scoop (+GHS 15)", "Extra Oreo (+GHS 10)"]', '🍪', 1, 1),
(9, 1, 'MPW-109', 'Bubble Waffles', 'Hong Kong-style egg bubble waffle cone filled with ice cream, whipped cream, fruits & sauces', 100.00, 32.00, 'Dessert', 9, 690, 160, 15, '["Nutella Sauce", "Biscoff Sauce", "Extra Scoop (+GHS 15)"]', '🧇', 1, 1),
(10, 1, 'MPW-110', 'Croffles', 'Caramelized flaky croissant-waffle hybrid pressed golden and topped with cream & sauce', 70.00, 22.00, 'Dessert', 7, 520, 175, 15, '["Biscoff Topping", "Nutella Topping", "White Chocolate"]', '🥐', 1, 1),

-- 2. CREPES & PANCAKES (PICK 2 SAUCE: NUTELLA • BISCOFF • OREO • WHITE CHOCOLATE • CHOCOLATE SAUCE)
(11, 2, 'CRP-201', '3 Stack Pancakes', 'Fluffy 3-tier American pancake stack — Pick 2 Sauces: Nutella, Biscoff, Oreo, White Chocolate or Chocolate Sauce', 80.00, 25.00, 'Dessert', 8, 640, 180, 20, '["Sauce: Nutella", "Sauce: Biscoff", "Sauce: Oreo", "Sauce: White Chocolate", "Sauce: Chocolate Sauce"]', '🥞', 1, 1),
(12, 2, 'CRP-202', 'Oreo Crepe', 'Delicate French crepe folded with crushed Oreos & cream — Pick 2 Sauces', 70.00, 22.00, 'Dessert', 7, 580, 190, 20, '["Sauce: Nutella", "Sauce: Biscoff", "Sauce: Oreo", "Sauce: White Chocolate", "Sauce: Chocolate Sauce"]', '🥞', 1, 1),
(13, 2, 'CRP-203', 'Banana Crepe', 'Warm crepe filled with fresh sliced sweet bananas — Pick 2 Sauces', 70.00, 21.00, 'Dessert', 7, 560, 185, 20, '["Sauce: Nutella", "Sauce: Biscoff", "Sauce: Oreo", "Sauce: White Chocolate", "Sauce: Chocolate Sauce"]', '🍌', 0, 1),
(14, 2, 'CRP-204', 'Biscoff Crepe', 'Signature French crepe filled and drizzled with Lotus Biscoff — Pick 2 Sauces', 70.00, 23.00, 'Dessert', 7, 590, 200, 20, '["Sauce: Biscoff", "Sauce: White Chocolate", "Sauce: Nutella", "Sauce: Oreo", "Sauce: Chocolate Sauce"]', '🥞', 1, 1),
(15, 2, 'CRP-205', 'Chocolate Crepe', 'Rich chocolate-filled French crepe topped with chocolate curls — Pick 2 Sauces', 70.00, 22.00, 'Dessert', 7, 590, 190, 20, '["Sauce: Chocolate Sauce", "Sauce: Nutella", "Sauce: White Chocolate", "Sauce: Biscoff", "Sauce: Oreo"]', '🍫', 0, 1),

-- 3. MILKSHAKES (60GHc Regular / 80GHc Large)
(16, 3, 'SHK-301', 'Chocolate Milkshake (Regular)', 'Thick artisanal chocolate ice cream milkshake topped with whipped cream & chocolate drizzle (60GHc / Large 80GHc)', 60.00, 19.00, 'Drink', 4, 520, 240, 25, '["Upgrade to Large 80GHc (+GHS 20)", "Extra Whipped Cream (+GHS 10)"]', '🥤', 1, 1),
(17, 3, 'SHK-302', 'Chocolate Milkshake (Large)', 'Large cup thick artisanal chocolate ice cream milkshake with whipped cream crown', 80.00, 25.00, 'Drink', 4, 680, 220, 25, '["Extra Chocolate Sauce", "Extra Whipped Cream (+GHS 10)"]', '🍫', 1, 1),
(18, 3, 'SHK-303', 'Vanilla Milkshake (Regular)', 'Classic creamy Madagascar vanilla bean milkshake with whipped cream (60GHc / Large 80GHc)', 60.00, 18.00, 'Drink', 4, 490, 240, 25, '["Upgrade to Large 80GHc (+GHS 20)", "Extra Whipped Cream (+GHS 10)"]', '🥤', 0, 1),
(19, 3, 'SHK-304', 'Vanilla Milkshake (Large)', 'Large cup creamy Madagascar vanilla bean milkshake with whipped cream', 80.00, 24.00, 'Drink', 4, 640, 210, 25, '["Add Caramel Drizzle (+GHS 10)", "Extra Whipped Cream (+GHS 10)"]', '🍨', 0, 1),
(20, 3, 'SHK-305', 'Strawberry Milkshake (Regular)', 'Sweet strawberry ice cream milkshake spun with real berry puree & whipped cream (60GHc / Large 80GHc)', 60.00, 19.00, 'Drink', 4, 500, 240, 25, '["Upgrade to Large 80GHc (+GHS 20)", "Extra Strawberry Drizzle"]', '🍓', 1, 1),
(21, 3, 'SHK-306', 'Strawberry Milkshake (Large)', 'Large cup strawberry ice cream milkshake spun with berry puree & whipped cream', 80.00, 25.00, 'Drink', 4, 660, 220, 25, '["Extra Whipped Cream (+GHS 10)", "Add Waffle Wafer"]', '🍓', 1, 1),

-- 4. CRAZY SHAKES (MADE TO CRAZE! — 150GHc)
(22, 4, 'CRZ-401', 'Crazy Oreo', 'Made to Craze! Towering Oreo freakshake rimmed with chocolate frosting, whole Oreos, wafer sticks & whipped cream', 150.00, 48.00, 'Dessert', 7, 890, 170, 20, '["Extra Oreo Stack (+GHS 15)", "Extra Wafer Rolls"]', '🍪', 1, 1),
(23, 4, 'CRZ-402', 'Red Velvet Madness', 'Made to Craze! Decadent red velvet freakshake crowned with a slice of red velvet cake & cream cheese frosting', 150.00, 49.00, 'Dessert', 7, 920, 165, 20, '["Extra Cake Crumbs", "Extra Whipped Cream"]', '🍰', 1, 1),
(24, 4, 'CRZ-403', 'Chocoloco', 'Made to Craze! Triple-chocolate monster shake loaded with chocolate brownie, fudge sauce, choco rolls & sprinkles', 150.00, 49.00, 'Dessert', 7, 940, 175, 20, '["Extra Fudge Drizzle", "Add Ice Cream Scoop (+GHS 15)"]', '🍫', 1, 1),
(25, 4, 'CRZ-404', 'Creamy Vanilla', 'Made to Craze! Gourmet French vanilla freakshake topped with white chocolate shards, wafers & clouds of cream', 150.00, 46.00, 'Dessert', 7, 850, 150, 15, '["Add Rainbow Sprinkles", "Add Biscoff Drizzle (+GHS 10)"]', '🍨', 0, 1),
(26, 4, 'CRZ-405', 'Crazy Biscoff', 'Made to Craze! Lotus Biscoff freakshake with speculoos cookie rim, Biscoff biscuits, caramel & whipped cream', 150.00, 49.00, 'Dessert', 7, 910, 180, 20, '["Extra Biscoff Biscuit", "Extra Lotus Drizzle (+GHS 15)"]', '🥤', 1, 1),
(27, 4, 'CRZ-406', 'Dreamy Girl', 'Made to Craze! Pink strawberry & vanilla fantasy shake topped with berries, marshmallows, wafers & whipped cream', 150.00, 48.00, 'Dessert', 7, 880, 160, 15, '["Extra Strawberries", "Extra Whipped Cream"]', '🌸', 1, 1),

-- 5. SD SPECIAL (150GHc)
(28, 5, 'SDS-501', 'SD Special', 'A delightful blend of cream, strawberry, crunch & sweetness made just for you! Served in our signature goblet', 150.00, 46.00, 'Dessert', 6, 780, 200, 20, '["Extra Fresh Strawberries (+GHS 15)", "Extra Choco Crunch (+GHS 10)"]', '🍓', 1, 1),

-- 6. CAKES & PASTRIES
(29, 6, 'CAK-601', 'Red Velvet Cake', 'Moist ruby red velvet layered cake slice with smooth cream cheese frosting', 50.00, 16.00, 'Dessert', 3, 460, 160, 15, '["Add Ice Cream Scoop (+GHS 15)", "Warm Slice"]', '🍰', 1, 1),
(30, 6, 'CAK-602', 'Chocolate Cake', 'Rich double-fudge chocolate layer cake slice with dark chocolate ganache', 50.00, 16.00, 'Dessert', 3, 480, 165, 15, '["Add Ice Cream Scoop (+GHS 15)", "Extra Chocolate Drizzle"]', '🍫', 1, 1),
(31, 6, 'CAK-603', 'Banana Cake', 'Home-baked moist ripe banana loaf cake with warm spice notes', 50.00, 15.00, 'Dessert', 3, 410, 140, 15, '["Warm with Butter", "Add Caramel Drizzle (+GHS 10)"]', '🍌', 0, 1),
(32, 6, 'CAK-604', 'Cupcake', 'Frosted gourmet cupcake topped with buttercream swirl & chocolate garnish', 25.00, 8.00, 'Dessert', 2, 310, 220, 25, '["Chocolate Flavour", "Red Velvet Flavour", "Vanilla Flavour"]', '🧁', 1, 1),
(33, 6, 'CAK-605', 'Cookies', 'Freshly baked chewy chocolate chip & double chocolate bakery cookies', 40.00, 12.00, 'Dessert', 3, 380, 200, 20, '["Serve Warm", "Add Ice Cream Scoop (+GHS 15)"]', '🍪', 0, 1),
(34, 6, 'CAK-606', 'Croissant', 'Flaky, golden all-butter French pastry croissant baked fresh daily', 35.00, 11.00, 'Dessert', 3, 340, 180, 20, '["Warm Croissant", "Add Nutella Drizzle (+GHS 10)"]', '🥐', 0, 1),
(35, 6, 'CAK-607', 'Croissant Icecream', 'Warm flaky butter croissant split and filled with artisanal ice cream & chocolate drizzle', 70.00, 22.00, 'Dessert', 5, 560, 160, 15, '["Vanilla Ice Cream", "Chocolate Ice Cream", "Strawberry Ice Cream", "Biscoff Drizzle (+GHS 10)"]', '🥐', 1, 1),

-- 7. SIGNATURE DESSERT
(36, 7, 'SIG-701', 'Matilda Cake', 'Iconic ultra-fudgy molten chocolate Matilda cake drenched in warm Belgian chocolate sauce', 70.00, 23.00, 'Dessert', 5, 640, 175, 15, '["Add Vanilla Scoop (+GHS 15)", "Extra Hot Chocolate Pour (+GHS 10)"]', '🍫', 1, 1),
(37, 7, 'SIG-702', 'Cake Lava', 'Warm molten chocolate lava cake with a gooey liquid chocolate center', 70.00, 23.00, 'Dessert', 8, 590, 160, 15, '["Add Vanilla Scoop (+GHS 15)", "Strawberry Garnish"]', '🌋', 1, 1),
(38, 7, 'SIG-703', 'Brownies with Icecream', 'Warm fudgy chocolate walnut brownie crowned with a scoop of vanilla ice cream & fudge drizzle', 80.00, 26.00, 'Dessert', 6, 670, 180, 15, '["Extra Scoop (+GHS 15)", "Nutella Drizzle (+GHS 10)"]', '🍨', 1, 1),
(39, 7, 'SIG-704', 'Fettuccine Crepe', 'Ribbons of fresh crepe cut pasta-style, tossed in Nutella & white chocolate with ice cream', 90.00, 28.00, 'Dessert', 8, 660, 165, 15, '["Extra Nutella (+GHS 10)", "Extra Biscoff (+GHS 10)"]', '🍝', 1, 1),
(40, 7, 'SIG-705', 'Red Velvet Burrito', 'Soft red velvet crepe-burrito rolled with cream cheese filling, cake cubes, berries & chocolate drizzle', 90.00, 29.00, 'Dessert', 8, 680, 150, 15, '["Add Ice Cream Scoop (+GHS 15)", "Extra White Chocolate"]', '🌯', 1, 1),
(41, 7, 'SIG-706', 'Mug Cake', 'Piping hot freshly baked sponge cake in a mug topped with melted chocolate & ice cream', 90.00, 28.00, 'Dessert', 9, 650, 145, 15, '["Chocolate Mug", "Oreo Mug", "Biscoff Mug"]', '☕', 0, 1),
(42, 7, 'SIG-707', 'Cooky Sandwich', 'Two giant warm chocolate chip cookies sandwiching creamy ice cream and chocolate drizzle', 80.00, 25.00, 'Dessert', 6, 640, 155, 15, '["Vanilla Filling", "Chocolate Filling", "Oreo Crumbs"]', '🍪', 0, 1),
(43, 7, 'SIG-708', 'Sweet Pizza', 'Dessert pizza base smothered in Nutella, Biscoff, white chocolate, Oreo & M&M toppings', 80.00, 26.00, 'Dessert', 9, 720, 160, 15, '["Add Banana Slices", "Add Marshmallows (+GHS 10)"]', '🍕', 1, 1),

-- 8. SCOOP ICE CREAM (15GHc PER SCOOP — Vanilla, Mango, Strawberry, Chocolate)
(44, 8, 'SCP-801', 'Vanilla Scoop Ice Cream', 'Single scoop (15GHc) of smooth Madagascar vanilla ice cream in a cup or cone', 15.00, 4.50, 'Dessert', 2, 180, 300, 30, '["2 Scoops (+GHS 15)", "3 Scoops (+GHS 30)", "Chocolate Sauce (+GHS 5)"]', '🍨', 1, 1),
(45, 8, 'SCP-802', 'Mango Scoop Ice Cream', 'Single scoop (15GHc) of refreshing tropical ripe mango ice cream', 15.00, 4.50, 'Dessert', 2, 170, 280, 30, '["2 Scoops (+GHS 15)", "3 Scoops (+GHS 30)"]', '🥭', 1, 1),
(46, 8, 'SCP-803', 'Strawberry Scoop Ice Cream', 'Single scoop (15GHc) of creamy sweet strawberry ice cream', 15.00, 4.50, 'Dessert', 2, 175, 290, 30, '["2 Scoops (+GHS 15)", "3 Scoops (+GHS 30)"]', '🍓', 1, 1),
(47, 8, 'SCP-804', 'Chocolate Scoop Ice Cream', 'Single scoop (15GHc) of rich Belgian dark & milk chocolate ice cream', 15.00, 4.50, 'Dessert', 2, 190, 300, 30, '["2 Scoops (+GHS 15)", "3 Scoops (+GHS 30)"]', '🍫', 1, 1),

-- 9. SLUSHIES
(48, 9, 'SLS-901', 'Lemonade Slushy', 'Icy tangy-sweet zesty lemonade frozen slushy', 60.00, 16.00, 'Drink', 3, 220, 220, 25, '["Extra Lemon Zing", "Add Popping Boba (+GHS 10)"]', '🍋', 0, 1),
(49, 9, 'SLS-902', 'Pineapple Slushy', 'Tropical golden pineapple crushed ice slushy', 60.00, 16.00, 'Drink', 3, 230, 220, 25, '["Add Mint Leaf", "Add Ice Cream Float (+GHS 15)"]', '🍍', 0, 1),
(50, 9, 'SLS-903', 'Ice Tea Slushy', 'Chilled peach & lemon iced tea blended into a refreshing frozen slush', 60.00, 16.00, 'Drink', 3, 200, 200, 20, '["Extra Lemon"]', '🧊', 0, 1),
(51, 9, 'SLS-904', 'Strawberry Slushy', 'Bright ruby strawberry frozen slushy bursting with berry flavour', 60.00, 17.00, 'Drink', 3, 240, 240, 25, '["Add Vanilla Scoop (+GHS 15)"]', '🍓', 1, 1),
(52, 9, 'SLS-905', 'Bubble Gum Slushy', 'Fun electric-blue & pink carnival bubble gum flavoured frozen slushy', 60.00, 17.00, 'Drink', 3, 250, 230, 25, '["Add Marshmallows (+GHS 10)"]', '🍬', 1, 1),
(53, 9, 'SLS-906', 'Fanta Slushy', 'Fizzy orange Fanta spun into an icy citrus slushy', 60.00, 16.00, 'Drink', 3, 230, 210, 20, '["Add Vanilla Scoop (+GHS 15)"]', '🍊', 0, 1),
(54, 9, 'SLS-907', 'Coke Slushy', 'Classic Coca-Cola frozen slushy served ice cold', 60.00, 16.00, 'Drink', 3, 230, 210, 20, '["Add Vanilla Scoop (+GHS 15)"]', '🥤', 0, 1),
(55, 9, 'SLS-908', 'Crazy Slushy', 'Jumbo layered multi-flavour rainbow slushy topped with ice cream, sweets & syrups', 120.00, 36.00, 'Drink', 5, 460, 160, 15, '["Strawberry + Bubble Gum", "Pineapple + Lemonade"]', '🌈', 1, 1),
(56, 9, 'SLS-909', 'Slushy & Icecream', 'Refreshing fruit slushy crowned with a creamy scoop of ice cream float', 70.00, 22.00, 'Drink', 4, 350, 190, 20, '["Vanilla Scoop", "Strawberry Scoop", "Mango Scoop"]', '🍨', 1, 1),

-- 10. MOCKTAILS (60GHc EACH)
(57, 10, 'MCK-1001', 'I Choose Me', 'Mango Juice, Passion Fruit Syrup, Mint Syrup and Sprite', 60.00, 18.00, 'Drink', 4, 210, 220, 20, '["Less Ice", "Extra Mint"]', '🥭', 1, 1),
(58, 10, 'MCK-1002', 'Taste The Music', 'Mango Juice, Strawberry Syrup, Ginger Beer, Mint Leave, Lime, Sprite, Orange Juice', 60.00, 19.00, 'Drink', 4, 230, 220, 20, '["Less Ice", "Extra Ginger Zing"]', '🍹', 1, 1),
(59, 10, 'MCK-1003', 'Tropical Dream', 'Banana, Strawberry Syrup, Pineapple Juice, Coconut Syrup and Whipped Cream', 60.00, 19.00, 'Drink', 4, 290, 210, 20, '["Extra Whipped Cream", "No Whipped Cream"]', '🥥', 1, 1),
(60, 10, 'MCK-1004', 'Strawberry Colada', 'Pineapple Juice, Strawberry Syrup, Coconut Syrup, Coconut Milk', 60.00, 19.00, 'Drink', 4, 280, 220, 20, '["Extra Strawberry Syrup", "Less Ice"]', '🍓', 1, 1),
(61, 10, 'MCK-1005', 'Special Zone', 'Ginger Beer, Passion Fruit, Pineapple Juice, Vanilla Syrup', 60.00, 18.00, 'Drink', 4, 220, 200, 20, '["Extra Passion Fruit", "Less Ice"]', '🍍', 0, 1),
(62, 10, 'MCK-1006', 'Kiss Me', 'Raspberry Syrup, Ginger Beer, Lemon Juice, Pineapple, Grenadine Syrup', 60.00, 19.00, 'Drink', 4, 225, 215, 20, '["Extra Grenadine", "Less Ice"]', '🍒', 1, 1),
(63, 10, 'MCK-1007', 'Ginger Me', 'Ginger Syrup, Lime, Simple Syrup and Sprite', 60.00, 16.00, 'Drink', 4, 180, 210, 20, '["Extra Spicy Ginger", "Extra Lime"]', '🫚', 0, 1),
(64, 10, 'MCK-1008', 'Classic Mocktail', 'Mint Leave, Simple Syrup, Sprite, Lime (Virgin Mojito)', 60.00, 16.00, 'Drink', 4, 175, 230, 20, '["Extra Mint Leaves", "Less Ice"]', '🌿', 1, 1),

-- 11. FINGER FOOD (Top Sellers: Loaded Fries 150 GHc & Loaded Fries 180 GHc)
(65, 11, 'FNG-1101', 'Loaded Fries (150 GHc)', 'Top Seller! Golden crispy fries loaded with seasoned chicken/beef chunks, melted cheese, sausages & signature sauces (150 GHc)', 150.00, 50.00, 'Main', 12, 820, 250, 20, '["Extra Cheese (+GHS 20)", "Spicy Pepper Sauce", "Upgrade to Jumbo 180 GHc (+GHS 30)"]', '🍟', 1, 1),
(66, 11, 'FNG-1102', 'Loaded Fries (180 GHc)', 'Top Seller! Jumbo platter of loaded fries with double chicken/beef, extra melted mozzarella, sausages & sauces (180 GHc)', 180.00, 60.00, 'Main', 12, 960, 250, 20, '["Extra Cheese (+GHS 20)", "Spicy Pepper Sauce", "Extra Chicken (+GHS 30)"]', '🍟', 1, 1),
(67, 11, 'FNG-1103', 'Chicken Wings & Fries', 'Crispy spiced glazed chicken wings served with a generous portion of golden French fries & dip', 150.00, 52.00, 'Main', 12, 820, 185, 20, '["Spicy Glaze", "BBQ Glaze", "Extra Ketchup & Mayo"]', '🍗', 1, 1),
(68, 11, 'FNG-1104', 'Fries Only', 'Hot, crispy golden seasoned French fries served with ketchup & signature dip', 50.00, 15.00, 'Starter', 7, 410, 250, 25, '["Add Cheese Drizzle (+GHS 15)", "Peri-Peri Seasoning"]', '🍟', 0, 1),
(69, 11, 'FNG-1105', 'Cheesy Crepe', 'Savoury warm French crepe stuffed with melted mozzarella, cheddar & seasoned fillings', 90.00, 30.00, 'Main', 9, 580, 170, 15, '["Add Chicken (+GHS 25)", "Extra Mozzarella (+GHS 15)"]', '🧀', 1, 1);

INSERT INTO `restaurant_tables` (`id`, `branch_id`, `table_number`, `zone`, `capacity`, `status`, `current_guests`, `assigned_staff_id`) VALUES
(1, 1, 'AYI-T1', 'Main Hall', 4, 'Occupied', 3, 3),
(2, 1, 'AYI-T2', 'Main Hall', 4, 'Available', 0, NULL),
(3, 1, 'AYI-T3', 'Main Hall', 6, 'Occupied', 5, 4),
(4, 1, 'AYI-T4', 'Patio Terrace', 4, 'Available', 0, NULL),
(5, 1, 'AYI-T5', 'Patio Terrace', 6, 'Reserved', 4, 5),
(6, 1, 'AYI-VIP1', 'VIP Lounge', 8, 'Occupied', 6, 6),
(7, 1, 'AYI-VIP2', 'VIP Lounge', 10, 'Available', 0, NULL),
(8, 1, 'AYI-BAR1', 'Bar Counter', 2, 'Available', 0, NULL),
(9, 2, 'SAW-T1', 'Main Hall', 4, 'Occupied', 4, 10),
(10, 2, 'SAW-T2', 'Main Hall', 4, 'Available', 0, NULL),
(11, 2, 'SAW-T3', 'Patio Terrace', 6, 'Occupied', 5, 10),
(12, 2, 'SAW-VIP1', 'VIP Lounge', 8, 'Available', 0, NULL),
(13, 3, 'DEC-T1', 'Main Hall', 4, 'Occupied', 3, 15),
(14, 3, 'DEC-T2', 'Patio Terrace', 6, 'Available', 0, NULL),
(15, 3, 'DEC-VIP1', 'VIP Lounge', 8, 'Occupied', 6, 16);

INSERT INTO `shifts` (`id`, `branch_id`, `staff_id`, `opening_cash`, `closing_cash`, `status`, `notes`) VALUES
(1, 1, 2, 4500.00, NULL, 'Open', 'Ayiduase Branch - Active Shift (Zainab)'),
(2, 2, 9, 3500.00, NULL, 'Open', 'Sawaba Branch - Active Shift (Baki)'),
(3, 3, 14, 3500.00, NULL, 'Open', 'Dechemso Branch - Active Cashier Drawer (Matilda)');
