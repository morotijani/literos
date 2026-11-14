-- =============================================================================
-- LITEROS — Lite Restaurant Operating System
-- Complete MySQL Schema & Rich Seed Data for "Shakes and Dessert" (Kumasi, Ghana)
-- Owner: Hajia Rabi Adam | Currency: GHS (¢)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `literos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `literos`;

SET FOREIGN_KEY_CHECKS = 0;
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
  `tax_rate` DECIMAL(5,2) DEFAULT 6.00,
  `service_charge_rate` DECIMAL(5,2) DEFAULT 2.50,
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

-- =============================================================================
-- STATIC CORE SEED DATA (COMPANY, 3 KUMASI BRANCHES, 31 PERSONNEL, MENU, TABLES)
-- =============================================================================

INSERT INTO `company_profile` (`id`, `name`, `owner_name`, `tagline`, `logo_text`, `currency_symbol`, `currency_code`, `tax_rate`, `service_charge_rate`, `email`, `phone`, `address`, `receipt_footer`) VALUES
(1, 'Shakes and Dessert', 'Hajia Rabi Adam', 'Signature Milkshakes, Artisanal Desserts & Savoury Grill • Kumasi, Ghana', 'S&D', 'GHS ', 'GHS', 6.00, 2.50, 'hajia.rabi@shakesanddessertgh.com', '+233 24 489 1020', 'KNUST Commercial Road, Ayiduase, Kumasi, Ashanti Region, Ghana', 'Medaase! Thank you for patronizing Shakes and Dessert • Owned by Hajia Rabi Adam');

INSERT INTO `branches` (`id`, `code`, `name`, `city`, `address`, `phone`, `manager_name`, `opening_hours`, `tax_override`, `is_active`) VALUES
(1, 'KSI-AYI', 'Ayiduase Flagship Branch', 'Kumasi (Ayiduase)', 'KNUST New Site Road, Near Ayiduase Gate, Kumasi', '+233 24 489 1021', 'Alhaji Sulemana Issah', '08:00 AM - 11:30 PM', NULL, 1),
(2, 'KSI-SAW', 'Sawaba Junction Lounge', 'Kumasi (Sawaba)', 'Sawaba Main Road, Opposite Central Mosque, Kumasi', '+233 24 489 1022', 'Mariam Abdul-Rahman', '08:30 AM - 11:00 PM', NULL, 1),
(3, 'KSI-DEC', 'Dechemso Plaza Terrace', 'Kumasi (Dechemso)', 'Airport Roundabout - Dechemso Link, Kumasi', '+233 24 489 1023', 'Kwame Osei Bonsu', '09:00 AM - 11:30 PM', NULL, 1);

-- 31 Personnel:
-- 1 Owner/Executive Admin (Hajia Rabi Adam)
-- Branch 1 (Ayiduase): 1 Manager, 1 Cashier, 4 Waitresses, 4 Chefs (IDs 2..11)
-- Branch 2 (Sawaba):   1 Manager, 1 Cashier, 4 Waitresses, 4 Chefs (IDs 12..21)
-- Branch 3 (Dechemso): 1 Manager, 1 Cashier, 4 Waitresses, 4 Chefs (IDs 22..31)
INSERT INTO `staff` (`id`, `branch_id`, `employee_code`, `full_name`, `role`, `pin_code`, `email`, `phone`, `avatar_color`, `hourly_rate`, `commission_rate`, `is_active`) VALUES
(1, 1, 'EMP-001', 'Hajia Rabi Adam', 'Admin', '1111', 'hajia.rabi@shakesanddessertgh.com', '+233 24 489 1001', '#f59e0b', 120.00, 3.00, 1),

-- Branch 1: Ayiduase (1 Manager, 1 Cashier, 4 Waitresses, 4 Chefs)
(2, 1, 'AYI-MGR', 'Alhaji Sulemana Issah', 'Manager', '1001', 'sulemana@shakesanddessertgh.com', '+233 24 401 1001', '#8b5cf6', 65.00, 2.50, 1),
(3, 1, 'AYI-CSH', 'Nana Ama Mensah', 'Cashier', '2222', 'ama.mensah@shakesanddessertgh.com', '+233 24 401 1002', '#10b981', 42.00, 1.50, 1),
(4, 1, 'AYI-W01', 'Abena Serwaa Kyei', 'Waiter', '3333', 'abena.kyei@shakesanddessertgh.com', '+233 24 401 1003', '#6366f1', 35.00, 2.00, 1),
(5, 1, 'AYI-W02', 'Fatima Mohammed', 'Waiter', '3002', 'fatima.m@shakesanddessertgh.com', '+233 24 401 1004', '#ec4899', 35.00, 2.00, 1),
(6, 1, 'AYI-W03', 'Akosua Pokuaa', 'Waiter', '3003', 'akosua.p@shakesanddessertgh.com', '+233 24 401 1005', '#06b6d4', 35.00, 2.00, 1),
(7, 1, 'AYI-W04', 'Zainab Alhassan', 'Waiter', '3004', 'zainab.a@shakesanddessertgh.com', '+233 24 401 1006', '#14b8a6', 35.00, 2.00, 1),
(8, 1, 'AYI-C01', 'Chef Ibrahim Yakubu', 'Chef', '4444', 'ibrahim.y@shakesanddessertgh.com', '+233 24 401 1007', '#ef4444', 55.00, 1.00, 1),
(9, 1, 'AYI-C02', 'Chef Kofi Boateng', 'Chef', '4002', 'kofi.b@shakesanddessertgh.com', '+233 24 401 1008', '#f97316', 52.00, 1.00, 1),
(10, 1, 'AYI-C03', 'Chef Halima Sadiq', 'Chef', '4003', 'halima.s@shakesanddessertgh.com', '+233 24 401 1009', '#eab308', 52.00, 1.00, 1),
(11, 1, 'AYI-C04', 'Chef Yaw Frimpong', 'Chef', '4004', 'yaw.f@shakesanddessertgh.com', '+233 24 401 1010', '#f43f5e', 50.00, 1.00, 1),

-- Branch 2: Sawaba (1 Manager, 1 Cashier, 4 Waitresses, 4 Chefs)
(12, 2, 'SAW-MGR', 'Mariam Abdul-Rahman', 'Manager', '5555', 'mariam@shakesanddessertgh.com', '+233 24 402 2001', '#8b5cf6', 65.00, 2.50, 1),
(13, 2, 'SAW-CSH', 'Rahinatu Fuseini', 'Cashier', '2002', 'rahinatu@shakesanddessertgh.com', '+233 24 402 2002', '#10b981', 42.00, 1.50, 1),
(14, 2, 'SAW-W01', 'Maimuna Seidu', 'Waiter', '6666', 'maimuna@shakesanddessertgh.com', '+233 24 402 2003', '#6366f1', 35.00, 2.00, 1),
(15, 2, 'SAW-W02', 'Adwoa Bempong', 'Waiter', '6002', 'adwoa.b@shakesanddessertgh.com', '+233 24 402 2004', '#ec4899', 35.00, 2.00, 1),
(16, 2, 'SAW-W03', 'Safia Haruna', 'Waiter', '6003', 'safia.h@shakesanddessertgh.com', '+233 24 402 2005', '#06b6d4', 35.00, 2.00, 1),
(17, 2, 'SAW-W04', 'Afia Nyarko', 'Waiter', '6004', 'afia.n@shakesanddessertgh.com', '+233 24 402 2006', '#14b8a6', 35.00, 2.00, 1),
(18, 2, 'SAW-C01', 'Chef Musah Dauda', 'Chef', '4011', 'musah.d@shakesanddessertgh.com', '+233 24 402 2007', '#ef4444', 55.00, 1.00, 1),
(19, 2, 'SAW-C02', 'Chef Latifa Zakari', 'Chef', '4012', 'latifa.z@shakesanddessertgh.com', '+233 24 402 2008', '#f97316', 52.00, 1.00, 1),
(20, 2, 'SAW-C03', 'Chef Kwaku Asante', 'Chef', '4013', 'kwaku.a@shakesanddessertgh.com', '+233 24 402 2009', '#eab308', 52.00, 1.00, 1),
(21, 2, 'SAW-C04', 'Chef Rukaya Bello', 'Chef', '4014', 'rukaya.b@shakesanddessertgh.com', '+233 24 402 2010', '#f43f5e', 50.00, 1.00, 1),

-- Branch 3: Dechemso (1 Manager, 1 Cashier, 4 Waitresses, 4 Chefs)
(22, 3, 'DEC-MGR', 'Kwame Osei Bonsu', 'Manager', '7777', 'kwame.bonsu@shakesanddessertgh.com', '+233 24 403 3001', '#8b5cf6', 65.00, 2.50, 1),
(23, 3, 'DEC-CSH', 'Gifty Acheampong', 'Cashier', '2003', 'gifty.a@shakesanddessertgh.com', '+233 24 403 3002', '#10b981', 42.00, 1.50, 1),
(24, 3, 'DEC-W01', 'Esi Mansa Owusu', 'Waiter', '8888', 'esi.owusu@shakesanddessertgh.com', '+233 24 403 3003', '#6366f1', 35.00, 2.00, 1),
(25, 3, 'DEC-W02', 'Samira Iddrisu', 'Waiter', '8002', 'samira.i@shakesanddessertgh.com', '+233 24 403 3004', '#ec4899', 35.00, 2.00, 1),
(26, 3, 'DEC-W03', 'Maame Yaa Sarpong', 'Waiter', '8003', 'maame.y@shakesanddessertgh.com', '+233 24 403 3005', '#06b6d4', 35.00, 2.00, 1),
(27, 3, 'DEC-W04', 'Khadija Osman', 'Waiter', '8004', 'khadija.o@shakesanddessertgh.com', '+233 24 403 3006', '#14b8a6', 35.00, 2.00, 1),
(28, 3, 'DEC-C01', 'Chef Kojo Antwi-Boasiako', 'Chef', '4021', 'kojo.a@shakesanddessertgh.com', '+233 24 403 3007', '#ef4444', 55.00, 1.00, 1),
(29, 3, 'DEC-C02', 'Chef Amina Gariba', 'Chef', '4022', 'amina.g@shakesanddessertgh.com', '+233 24 403 3008', '#f97316', 52.00, 1.00, 1),
(30, 3, 'DEC-C03', 'Chef Nana Yaw Opoku', 'Chef', '4023', 'nanayaw@shakesanddessertgh.com', '+233 24 403 3009', '#eab308', 52.00, 1.00, 1),
(31, 3, 'DEC-C04', 'Chef Habiba Salifu', 'Chef', '4024', 'habiba.s@shakesanddessertgh.com', '+233 24 403 3010', '#f43f5e', 50.00, 1.00, 1);

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `color`, `sort_order`) VALUES
(1, 'Signature Freakshakes & Milkshakes', 'signature-milkshakes', 'fa-blender', '#ec4899', 1),
(2, 'Belgian Waffles, Crepes & Sundaes', 'waffles-sundaes', 'fa-ice-cream', '#f59e0b', 2),
(3, 'Artisanal Cakes & CelebrationPlatters', 'cakes-platters', 'fa-cake-candles', '#8b5cf6', 3),
(4, 'Kumasi Grill, Burgers & Shawarma', 'kumasi-grill-shawarma', 'fa-burger', '#ef4444', 4),
(5, 'Boba Tea, Smoothies & Sobolo', 'boba-smoothies', 'fa-mug-hot', '#06b6d4', 5),
(6, 'VIP Party & Corporate Dessert Boxes', 'vip-party-boxes', 'fa-box-open', '#10b981', 6);

INSERT INTO `menu_items` (`id`, `category_id`, `sku`, `name`, `description`, `price`, `cost`, `course_type`, `prep_time_mins`, `calories`, `stock_qty`, `low_stock_threshold`, `modifiers_json`, `image_emoji`, `is_popular`, `is_available`) VALUES
(1, 1, 'SHK-101', 'Hajia''s Royal Lotus Biscoff Freakshake', 'Creamy Madagascar vanilla gelato blended with Lotus Biscoff spread, whipped cream & crushed speculoos', 95.00, 32.00, 'Dessert', 5, 680, 180, 20, '["Extra Biscoff Drizzle (+GHS 15)", "Oat Milk", "Double Scoop (+GHS 25)"]', '🥤', 1, 1),
(2, 1, 'SHK-102', 'Nutella Ferrero Rocher Monster Shake', 'Belgian hazelnut chocolate shake topped with Ferrero Rocher, waffle cone & fudge sauce', 110.00, 38.00, 'Dessert', 6, 740, 165, 20, '["Extra Ferrero (+GHS 20)", "Less Ice", "Whipped Cream on Side"]', '🍫', 1, 1),
(3, 1, 'SHK-103', 'Oreo Cookies & Cream Supreme Shake', 'Double-stuffed Oreo cookies spun with sweet cream ice cream and dark chocolate shards', 85.00, 27.00, 'Dessert', 5, 620, 210, 25, '["Add Brownie Bite (+GHS 20)", "Extra Oreo Crumble (+GHS 10)"]', '🍪', 1, 1),
(4, 1, 'SHK-104', 'Salted Caramel & Roasted Pistachio Shake', 'Rich Sicilian pistachio paste, sea-salt caramel ribbon & toasted almond praline', 105.00, 36.00, 'Dessert', 6, 650, 140, 20, '["No Nuts", "Extra Caramel"]', '🥜', 0, 1),
(5, 2, 'WAF-201', 'Belgian Liege Waffle & Gelato Trio', 'Warm pearl-sugar Belgian waffle, 3 scoops artisanal gelato, warm Nutella & fresh strawberries', 135.00, 44.00, 'Dessert', 10, 820, 150, 20, '["Extra Gelato Scoop (+GHS 25)", "Add Banana Slices (+GHS 10)"]', '🧇', 1, 1),
(6, 2, 'WAF-202', 'Sizzling Brownie Skillet Sundae', 'Hot Valrhona fudge brownie on cast iron, vanilla bean ice cream & molten chocolate pour', 125.00, 40.00, 'Dessert', 9, 790, 130, 15, '["Extra Hot Fudge (+GHS 15)", "Add Roasted Pecans (+GHS 15)"]', '🍨', 1, 1),
(7, 2, 'WAF-203', 'Kunafa Pistachio Chocolate Crepe (Dubai Style)', 'Crispy kataifi pistachio filling wrapped in French crepe with milk chocolate ganache', 145.00, 48.00, 'Dessert', 11, 840, 120, 15, '["Extra Pistachio Cream (+GHS 25)", "Add Vanilla Ice Cream (+GHS 20)"]', '🥞', 1, 1),
(8, 3, 'CAK-301', 'San Sebastián Burnt Basque Cheesecake', 'Creamy caramelized cheesecake slice served with warm Belgian milk chocolate dip', 115.00, 36.00, 'Dessert', 4, 590, 95, 15, '["White Chocolate Dip", "Dark Chocolate Dip", "Berry Compote (+GHS 15)"]', '🍰', 1, 1),
(9, 3, 'CAK-302', 'Red Velvet & Cream CheeseTres Leches', 'Soaked sponge cake layered with mascarpone chantilly and ruby velvet crumbs', 95.00, 30.00, 'Dessert', 4, 560, 110, 15, '["Birthday Candle", "Extra Tres Leches Milk"]', '🧁', 0, 1),
(10, 4, 'GRL-401', 'Ayiduase Loaded Double Beef Smash Burger & Fries', 'Double spiced Ghanaian beef patties, cheddar, caramelized onions, suya mayo & peri-peri fries', 140.00, 52.00, 'Main', 12, 920, 175, 20, '["Extra Patty (+GHS 35)", "Spicy Shito Dip (+GHS 10)", "Cheese Fries (+GHS 20)"]', '🍔', 1, 1),
(11, 4, 'GRL-402', 'Kumasi Spicy Chicken & Cheese Shawarma Platter', 'Char-grilled marinated chicken shawarma, garlic toum, mozzarella, fries & coleslaw', 115.00, 42.00, 'Main', 10, 780, 220, 25, '["Double Meat (+GHS 30)", "ExtraSpicy", "Extra Garlic Sauce"]', '🌯', 1, 1),
(12, 4, 'GRL-403', 'Honey-Suya Glazed Crispy Chicken Wings (10pcs)', 'Crispy jumbo wings tossed in Northern suya spice & wildflower honey glaze', 130.00, 46.00, 'Starter', 12, 740, 160, 20, '["Extra Suya Spice", "Ranch Dip", "Loaded Fries Side (+GHS 35)"]', '🍗', 1, 1),
(13, 5, 'BOB-501', 'Brown Sugar Tiger Milk Boba Tea', 'Caramelized Okinawa brown sugar pearls, fresh jersey milk & cheese foam crown', 75.00, 22.00, 'Drink', 4, 410, 250, 30, '["Extra Boba Pearls (+GHS 12)", "50% Sweetness", "Less Ice"]', '🧋', 1, 1),
(14, 5, 'BOB-502', 'Tropical Mango, Passion & Hibiscus Sobolo Cooler', 'Chilled spiced hibiscus sobolo layered with fresh mango & passionfruit puree', 65.00, 18.00, 'Drink', 4, 240, 260, 30, '["Add Chia Seeds (+GHS 10)", "Less Ginger"]', '🍹', 0, 1),
(15, 6, 'BOX-601', 'Hajia''s Grand Family & Fiesta Dessert Platter', '4 Signature Shakes, 2 Belgian Waffles, 12 Mini Pancakes, Brownie Bites, Shawarma & Dipping Pots', 580.00, 195.00, 'Dessert', 16, 3200, 85, 10, '["Assorted Shake Flavors", "Birthday Sparkler", "Extra Nutella Pot (+GHS 30)"]', '🎁', 1, 1);

INSERT INTO `restaurant_tables` (`id`, `branch_id`, `table_number`, `zone`, `capacity`, `status`, `current_guests`, `assigned_staff_id`) VALUES
(1, 1, 'AYI-T1', 'Main Hall', 4, 'Occupied', 3, 4),
(2, 1, 'AYI-T2', 'Main Hall', 4, 'Available', 0, NULL),
(3, 1, 'AYI-T3', 'Main Hall', 6, 'Occupied', 5, 5),
(4, 1, 'AYI-T4', 'Patio Terrace', 4, 'Available', 0, NULL),
(5, 1, 'AYI-T5', 'Patio Terrace', 6, 'Reserved', 4, 6),
(6, 1, 'AYI-VIP1', 'VIP Lounge', 8, 'Occupied', 6, 4),
(7, 1, 'AYI-VIP2', 'VIP Lounge', 10, 'Available', 0, NULL),
(8, 1, 'AYI-BAR1', 'Bar Counter', 2, 'Available', 0, NULL),
(9, 2, 'SAW-T1', 'Main Hall', 4, 'Occupied', 4, 14),
(10, 2, 'SAW-T2', 'Main Hall', 4, 'Available', 0, NULL),
(11, 2, 'SAW-T3', 'Patio Terrace', 6, 'Occupied', 5, 15),
(12, 2, 'SAW-VIP1', 'VIP Lounge', 8, 'Available', 0, NULL),
(13, 3, 'DEC-T1', 'Main Hall', 4, 'Occupied', 3, 24),
(14, 3, 'DEC-T2', 'Patio Terrace', 6, 'Available', 0, NULL),
(15, 3, 'DEC-VIP1', 'VIP Lounge', 8, 'Occupied', 6, 25);

INSERT INTO `shifts` (`id`, `branch_id`, `staff_id`, `opening_cash`, `closing_cash`, `status`, `notes`) VALUES
(1, 1, 3, 4500.00, NULL, 'Open', 'Ayiduase Flagship - Active Cashier Drawer (Nana Ama)'),
(2, 2, 13, 3500.00, NULL, 'Open', 'Sawaba Branch - Active Cashier Drawer (Rahinatu)'),
(3, 3, 23, 3500.00, NULL, 'Open', 'Dechemso Branch - Active Cashier Drawer (Gifty)');
