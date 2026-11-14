-- =============================================================================
-- LITEROS — Lite Restaurant Operating System
-- Complete MySQL Schema & Rich Demo Seed Data
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `literos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `literos`;

SET FOREIGN_KEY_CHECKS = 0;
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
  `tagline` VARCHAR(200) DEFAULT 'Culinary Craft & Hospitality OS',
  `logo_text` VARCHAR(20) DEFAULT 'LR',
  `currency_symbol` VARCHAR(10) DEFAULT '$',
  `currency_code` VARCHAR(10) DEFAULT 'USD',
  `tax_rate` DECIMAL(5,2) DEFAULT 8.50,
  `service_charge_rate` DECIMAL(5,2) DEFAULT 5.00,
  `email` VARCHAR(120) DEFAULT 'hq@lumina-hospitality.com',
  `phone` VARCHAR(40) DEFAULT '+1 (555) 234-8900',
  `address` VARCHAR(255) DEFAULT '742 Evergreen Culinary Plaza, Suite 400, New York, NY',
  `receipt_footer` VARCHAR(255) DEFAULT 'Thank you for dining with us! Powered by Literos POS.',
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
  `hourly_rate` DECIMAL(8,2) DEFAULT 18.50,
  `commission_rate` DECIMAL(5,2) DEFAULT 2.00,
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
  `course_type` ENUM('Starter', 'Main', 'Drink', 'Dessert', 'Side') DEFAULT 'Main',
  `prep_time_mins` INT DEFAULT 12,
  `calories` INT DEFAULT 450,
  `stock_qty` INT DEFAULT 100,
  `low_stock_threshold` INT DEFAULT 15,
  `modifiers_json` TEXT DEFAULT NULL,
  `image_emoji` VARCHAR(20) DEFAULT '🍽️',
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
  `opening_cash` DECIMAL(10,2) NOT NULL DEFAULT 250.00,
  `closing_cash` DECIMAL(10,2) DEFAULT NULL,
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
  `status` ENUM('Pending', 'Preparing', 'Ready', 'Served', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Preparing',
  `payment_status` ENUM('Unpaid', 'Paid', 'Refunded') NOT NULL DEFAULT 'Paid',
  `payment_method` ENUM('Cash', 'Card', 'QR Pay', 'Split') DEFAULT 'Card',
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `service_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tip_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `amount_tendered` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT `fk_order_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. Order Items
CREATE TABLE `order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `menu_item_id` INT UNSIGNED NOT NULL,
  `item_name` VARCHAR(140) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `unit_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 1,
  `modifiers` VARCHAR(255) DEFAULT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  `kitchen_status` ENUM('Queued', 'Cooking', 'Plated') DEFAULT 'Cooking',
  CONSTRAINT `fk_orderitem_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================================================
-- SEED DATA
-- =============================================================================

INSERT INTO `company_profile` (`id`, `name`, `tagline`, `logo_text`, `currency_symbol`, `currency_code`, `tax_rate`, `service_charge_rate`, `email`, `phone`, `address`, `receipt_footer`) VALUES
(1, 'Saffron & Slate Hospitality Group', 'Artisanal Wood-Fired Kitchen & Lounge', 'S&S', '$', 'USD', 8.25, 5.00, 'concierge@saffronandslate.com', '+1 (212) 555-0194', '480 Mercer Street, SoHo, New York, NY 10013', 'Thank you for dining at Saffron & Slate! Follow us @saffronandslate');

INSERT INTO `branches` (`id`, `code`, `name`, `city`, `address`, `phone`, `manager_name`, `opening_hours`, `tax_override`, `is_active`) VALUES
(1, 'NYC-SOHO', 'SoHo Flagship Bistro', 'New York', '480 Mercer St, SoHo, NY 10013', '+1 (212) 555-0194', 'Elena Rostova', '08:00 AM - 12:00 AM', NULL, 1),
(2, 'BRK-WBG', 'Williamsburg Waterfront Grill', 'Brooklyn', '114 Wythe Ave, Brooklyn, NY 11249', '+1 (718) 555-0281', 'Marcus Vance', '10:00 AM - 01:00 AM', 8.88, 1),
(3, 'MIA-WYN', 'Wynwood Garden Terrace', 'Miami', '2550 NW 2nd Ave, Miami, FL 33127', '+1 (305) 555-0842', 'Mateo Silva', '11:00 AM - 11:30 PM', 7.00, 1);

INSERT INTO `staff` (`id`, `branch_id`, `employee_code`, `full_name`, `role`, `pin_code`, `email`, `phone`, `avatar_color`, `hourly_rate`, `commission_rate`, `is_active`) VALUES
(1, 1, 'EMP-001', 'Elena Rostova', 'Admin', '1111', 'elena@saffronandslate.com', '+1 (212) 555-1001', '#f59e0b', 38.00, 3.00, 1),
(2, 1, 'EMP-002', 'Julian Sterling', 'Cashier', '2222', 'julian@saffronandslate.com', '+1 (212) 555-1002', '#10b981', 22.00, 2.50, 1),
(3, 1, 'EMP-003', 'Amara Okafor', 'Waiter', '3333', 'amara@saffronandslate.com', '+1 (212) 555-1003', '#6366f1', 19.50, 4.00, 1),
(4, 1, 'EMP-004', 'Kenji Takahashi', 'Chef', '4444', 'kenji@saffronandslate.com', '+1 (212) 555-1004', '#ef4444', 34.00, 1.50, 1),
(5, 2, 'EMP-005', 'Marcus Vance', 'Manager', '5555', 'marcus@saffronandslate.com', '+1 (718) 555-2001', '#8b5cf6', 35.00, 3.00, 1),
(6, 2, 'EMP-006', 'Sofia Rossi', 'Waiter', '6666', 'sofia@saffronandslate.com', '+1 (718) 555-2002', '#ec4899', 20.00, 4.00, 1),
(7, 3, 'EMP-007', 'Mateo Silva', 'Manager', '7777', 'mateo@saffronandslate.com', '+1 (305) 555-3001', '#14b8a6', 34.00, 3.00, 1);

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `color`, `sort_order`) VALUES
(1, 'Small Plates & Tapas', 'small-plates', 'fa-cheese', '#f59e0b', 1),
(2, 'Wood-Fired Mains', 'wood-fired-mains', 'fa-fire-burner', '#ef4444', 2),
(3, 'Artisan Pasta & Risotto', 'artisan-pasta', 'fa-bowl-food', '#eab308', 3),
(4, 'Handcrafted Burgers & Grill', 'burgers-grill', 'fa-burger', '#f97316', 4),
(5, 'Signature Cocktails & Drinks', 'drinks-cocktails', 'fa-martini-glass-citrus', '#06b6d4', 5),
(6, 'Patisserie & Desserts', 'desserts', 'fa-ice-cream', '#ec4899', 6);

INSERT INTO `menu_items` (`id`, `category_id`, `sku`, `name`, `description`, `price`, `cost`, `course_type`, `prep_time_mins`, `calories`, `stock_qty`, `low_stock_threshold`, `modifiers_json`, `image_emoji`, `is_popular`, `is_available`) VALUES
(1, 1, 'TAP-101', 'Truffle Burrata & Charred Fig', 'Creamy Pugliese burrata, black winter truffle honey, sourdough points', 18.50, 5.80, 'Starter', 7, 420, 42, 10, '["Gluten-Free Bread", "Extra Truffle Honey (+$3)", "No Nuts"]', '🧀', 1, 1),
(2, 1, 'TAP-102', 'Crispy Calamari & Yuzu Aioli', 'Tempura baby squid, blistered shishito peppers, citrus yuzu dip', 16.00, 4.50, 'Starter', 8, 510, 55, 12, '["Extra Spicy", "Aioli on Side", "No Shishito"]', '🦑', 1, 1),
(3, 1, 'TAP-103', 'Wagyu Beef Carpaccio', 'A5 Miyazaki sear, caper berries, 36-month Parmigiano, smoked sea salt', 24.00, 8.20, 'Starter', 6, 340, 28, 8, '["No Capers", "Extra Parmesan"]', '🥩', 0, 1),
(4, 2, 'WFM-201', 'Dry-Aged Prime Ribeye (14oz)', '45-day dry-aged prime ribeye, bone marrow herb butter, roasted garlic', 54.00, 19.50, 'Main', 18, 980, 24, 8, '["Rare", "Medium Rare", "Medium", "Well Done", "Peppercorn Sauce (+$4)"]', '🥩', 1, 1),
(5, 2, 'WFM-202', 'Miso-Glazed Black Cod', 'Wild Alaskan sablefish, sweet Saikyo miso, charred baby bok choy', 38.00, 13.20, 'Main', 15, 620, 19, 8, '["Extra Glaze", "Steamed Jasmine Rice (+$3)"]', '🐟', 1, 1),
(6, 2, 'WFM-203', 'Herb-Crusted Rack of Lamb', 'Pasture-raised lamb, pistachio mint crust, pomegranate jus, parsnip puree', 46.00, 16.00, 'Main', 20, 840, 12, 15, '["Medium Rare", "Medium", "No Pistachio Crust"]', '🍖', 0, 1),
(7, 3, 'PAS-301', 'Black Truffle Tagliolini', 'Hand-rolled 40-yolk pasta, cultured butter emulsion, shaved Périgord truffle', 32.00, 8.90, 'Main', 12, 740, 36, 10, '["Gluten-Free Pasta (+$2)", "Add Burrata (+$6)"]', '🍝', 1, 1),
(8, 3, 'PAS-302', 'Saffron & Maine Lobster Risotto', 'Acquerello carnaroli rice, butter-poached lobster tail, Iranian saffron', 42.00, 14.80, 'Main', 16, 790, 22, 8, '["Split in Two Bowls", "Extra Chili Oil"]', '🦞', 1, 1),
(9, 4, 'BGR-401', 'The Saffron Double Smash Burger', 'Double Wagyu smash patties, aged gruyère, caramelized onion jam, brioche', 23.00, 6.40, 'Main', 10, 890, 64, 15, '["Medium", "Well Done", "Add Smoked Bacon (+$3)", "Truffle Fries Upgrade (+$4)"]', '🍔', 1, 1),
(10, 4, 'BGR-402', 'Wood-Fired Margherita DOP Pizza', 'San Marzano tomatoes, fior di latte, fresh sweet basil, extra virgin olive oil', 21.00, 4.20, 'Main', 9, 760, 48, 12, '["Add Prosciutto (+$5)", "Hot Honey Drizzle (+$2)", "Well Done Crust"]', '🍕', 0, 1),
(11, 5, 'DRK-501', 'Smoked Saffron Old Fashioned', 'Small-batch bourbon, saffron-infused demerara, angostura, hickory smoke', 17.00, 3.20, 'Drink', 4, 180, 90, 20, '["Single Sphere Ice", "Neat", "Extra Orange Peel"]', '🥃', 1, 1),
(12, 5, 'DRK-502', 'Passionfruit & Yuzu Spritz (Zero Proof)', 'House yuzu cordial, fresh passionfruit, artisanal botanical tonic, rosemary', 12.00, 2.10, 'Drink', 3, 110, 85, 20, '["Less Ice", "No Rosemary"]', '🍹', 0, 1),
(13, 5, 'DRK-503', 'Single-Origin Espresso Martini', 'Cold-brew Ethiopian espresso, vodka, Borghetti liqueur, vanilla bean foam', 16.50, 3.10, 'Drink', 4, 210, 75, 15, '["Decaf Espresso", "Oat Milk Foam"]', '🍸', 1, 1),
(14, 6, 'DES-601', 'Basque Burnt Cheesecake', 'Caramelized San Sebastián style cheesecake, macerated wild berries', 14.50, 3.40, 'Dessert', 5, 540, 11, 15, '["Birthday Candle", "Extra Berry Compote"]', '🍰', 1, 1),
(15, 6, 'DES-602', 'Valrhona 70% Molten Lava Cake', 'Warm dark chocolate fondant, Madagascar vanilla bean gelato, hazelnut praline', 15.50, 3.80, 'Dessert', 11, 610, 29, 10, '["No Hazelnut", "Extra Scoop Gelato (+$3)"]', '🍫', 0, 1);

INSERT INTO `restaurant_tables` (`id`, `branch_id`, `table_number`, `zone`, `capacity`, `status`, `current_guests`, `assigned_staff_id`) VALUES
(1, 1, 'T-01', 'Main Hall', 2, 'Occupied', 2, 3),
(2, 1, 'T-02', 'Main Hall', 4, 'Available', 0, NULL),
(3, 1, 'T-03', 'Main Hall', 4, 'Occupied', 4, 3),
(4, 1, 'T-04', 'Main Hall', 6, 'Reserved', 5, 2),
(5, 1, 'T-05', 'Patio Terrace', 2, 'Available', 0, NULL),
(6, 1, 'T-06', 'Patio Terrace', 4, 'Occupied', 3, 3),
(7, 1, 'T-07', 'Patio Terrace', 6, 'Needs Cleaning', 0, NULL),
(8, 1, 'VIP-1', 'VIP Lounge', 8, 'Occupied', 6, 1),
(9, 1, 'VIP-2', 'VIP Lounge', 10, 'Available', 0, NULL),
(10, 1, 'BAR-1', 'Bar Counter', 2, 'Available', 0, NULL),
(11, 2, 'W-01', 'Main Hall', 4, 'Occupied', 4, 6),
(12, 2, 'W-02', 'Patio Terrace', 6, 'Available', 0, NULL),
(13, 3, 'M-01', 'Patio Terrace', 4, 'Available', 0, NULL);

INSERT INTO `shifts` (`id`, `branch_id`, `staff_id`, `opening_cash`, `closing_cash`, `status`, `notes`) VALUES
(1, 1, 2, 350.00, NULL, 'Open', 'Evening dinner service drawer - SoHo Flagship'),
(2, 2, 5, 300.00, NULL, 'Open', 'Williamsburg evening shift');

INSERT INTO `orders` (`id`, `order_number`, `branch_id`, `table_id`, `staff_id`, `customer_name`, `guests_count`, `order_type`, `status`, `payment_status`, `payment_method`, `subtotal`, `tax_amount`, `service_charge`, `discount_amount`, `tip_amount`, `total_amount`, `amount_tendered`, `notes`, `created_at`, `completed_at`) VALUES
(1, 'ORD-1001', 1, 1, 3, 'Table T-01 (Clara & Liam)', 2, 'Dine-In', 'Preparing', 'Unpaid', 'Card', 90.50, 7.47, 4.53, 0.00, 0.00, 102.50, 0.00, 'Anniversary couple - bring complimentary prosecco splash', DATE_SUB(NOW(), INTERVAL 18 MINUTE), NULL),
(2, 'ORD-1002', 1, 3, 3, 'Table T-03 (Henderson Party)', 4, 'Dine-In', 'Ready', 'Paid', 'Card', 143.00, 11.80, 7.15, 0.00, 25.00, 186.95, 186.95, 'Allergies: 1 guest no nuts', DATE_SUB(NOW(), INTERVAL 29 MINUTE), NULL),
(3, 'ORD-1003', 1, 8, 1, 'VIP-1 (Apex Ventures Dinner)', 6, 'Dine-In', 'Preparing', 'Paid', 'Card', 246.50, 20.34, 12.33, 15.00, 45.00, 309.17, 309.17, 'VIP Corporate Account - Course 1 & 2 together', DATE_SUB(NOW(), INTERVAL 11 MINUTE), NULL),
(4, 'ORD-1004', 1, NULL, 2, 'Maya Lin (Takeaway)', 1, 'Takeaway', 'Completed', 'Paid', 'QR Pay', 55.00, 4.54, 0.00, 0.00, 5.00, 64.54, 64.54, 'Extra napkins and chili oil', DATE_SUB(NOW(), INTERVAL 95 MINUTE), DATE_SUB(NOW(), INTERVAL 75 MINUTE)),
(5, 'ORD-1005', 2, 11, 6, 'Table W-01 (Rivera Group)', 4, 'Dine-In', 'Completed', 'Paid', 'Cash', 112.50, 9.99, 5.63, 0.00, 18.00, 146.12, 160.00, '', DATE_SUB(NOW(), INTERVAL 140 MINUTE), DATE_SUB(NOW(), INTERVAL 100 MINUTE)),
(6, 'ORD-1006', 1, 6, 3, 'Table T-06 (Patio Guests)', 3, 'Dine-In', 'Completed', 'Paid', 'Card', 88.50, 7.30, 4.43, 0.00, 15.00, 115.23, 115.23, '', DATE_SUB(NOW(), INTERVAL 210 MINUTE), DATE_SUB(NOW(), INTERVAL 170 MINUTE)),
(7, 'ORD-1007', 1, 2, 3, 'Sterling Architecture Lunch', 4, 'Dine-In', 'Completed', 'Paid', 'Card', 164.00, 13.53, 8.20, 0.00, 28.00, 213.73, 213.73, '', DATE_SUB(NOW(), INTERVAL 1420 MINUTE), DATE_SUB(NOW(), INTERVAL 1380 MINUTE)),
(8, 'ORD-1008', 2, 12, 5, 'Brooklyn Creative Studio', 5, 'Dine-In', 'Completed', 'Paid', 'Split', 210.00, 18.65, 10.50, 10.00, 35.00, 264.15, 264.15, 'Split 5 ways', DATE_SUB(NOW(), INTERVAL 1560 MINUTE), DATE_SUB(NOW(), INTERVAL 1510 MINUTE)),
(9, 'ORD-1009', 3, 13, 7, 'Wynwood Art Collectors', 4, 'Dine-In', 'Completed', 'Paid', 'Card', 184.00, 12.88, 9.20, 0.00, 32.00, 238.08, 238.08, '', DATE_SUB(NOW(), INTERVAL 2880 MINUTE), DATE_SUB(NOW(), INTERVAL 2830 MINUTE)),
(10, 'ORD-1010', 1, NULL, 2, 'DoorDash / Courier #819', 2, 'Delivery', 'Completed', 'Paid', 'QR Pay', 77.00, 6.35, 0.00, 0.00, 8.00, 91.35, 91.35, 'Leave at concierge desk', DATE_SUB(NOW(), INTERVAL 2990 MINUTE), DATE_SUB(NOW(), INTERVAL 2960 MINUTE)),
(11, 'ORD-1011', 1, 4, 1, 'Mercer Street Wine Society', 6, 'Dine-In', 'Completed', 'Paid', 'Card', 312.00, 25.74, 15.60, 20.00, 60.00, 393.34, 393.34, 'VIP Sommelier pairing', DATE_SUB(NOW(), INTERVAL 4320 MINUTE), DATE_SUB(NOW(), INTERVAL 4260 MINUTE)),
(12, 'ORD-1012', 2, 11, 6, 'Williamsburg Loft Dinner', 3, 'Dine-In', 'Completed', 'Paid', 'Cash', 129.50, 11.50, 6.48, 0.00, 20.00, 167.48, 180.00, '', DATE_SUB(NOW(), INTERVAL 5760 MINUTE), DATE_SUB(NOW(), INTERVAL 5710 MINUTE)),
(13, 'ORD-1013', 3, 13, 7, 'Miami Design District Takeout', 2, 'Takeaway', 'Completed', 'Paid', 'QR Pay', 69.00, 4.83, 0.00, 0.00, 10.00, 83.83, 83.83, '', DATE_SUB(NOW(), INTERVAL 7200 MINUTE), DATE_SUB(NOW(), INTERVAL 7160 MINUTE)),
(14, 'ORD-1014', 1, 8, 3, 'SoHo Fashion Week Gala', 8, 'Dine-In', 'Completed', 'Paid', 'Split', 358.00, 29.54, 17.90, 25.00, 70.00, 450.44, 450.44, '', DATE_SUB(NOW(), INTERVAL 8640 MINUTE), DATE_SUB(NOW(), INTERVAL 8580 MINUTE)),
(15, 'ORD-1015', 2, NULL, 5, 'Noah & Chloe (Delivery)', 2, 'Delivery', 'Completed', 'Paid', 'Card', 84.00, 7.46, 0.00, 0.00, 12.00, 103.46, 103.46, '', DATE_SUB(NOW(), INTERVAL 11520 MINUTE), DATE_SUB(NOW(), INTERVAL 11480 MINUTE)),
(16, 'ORD-1016', 1, 3, 2, 'Cancelled Walk-in Test', 2, 'Takeaway', 'Cancelled', 'Refunded', 'Card', 37.00, 3.05, 0.00, 0.00, 0.00, 40.05, 40.05, 'Guest changed mind before prep', DATE_SUB(NOW(), INTERVAL 4200 MINUTE), NULL);

INSERT INTO `order_items` (`order_id`, `menu_item_id`, `item_name`, `unit_price`, `unit_cost`, `quantity`, `modifiers`, `line_total`, `kitchen_status`) VALUES
(1, 1, 'Truffle Burrata & Charred Fig', 18.50, 5.80, 1, 'Extra Truffle Honey (+$3)', 18.50, 'Plated'),
(1, 5, 'Miso-Glazed Black Cod', 38.00, 13.20, 1, 'Extra Glaze', 38.00, 'Cooking'),
(1, 11, 'Smoked Saffron Old Fashioned', 17.00, 3.20, 2, 'Single Sphere Ice', 34.00, 'Plated'),
(2, 4, 'Dry-Aged Prime Ribeye (14oz)', 54.00, 19.50, 1, 'Medium Rare', 54.00, 'Plated'),
(2, 7, 'Black Truffle Tagliolini', 32.00, 8.90, 1, '', 32.00, 'Plated'),
(2, 9, 'The Saffron Double Smash Burger', 23.00, 6.40, 1, 'Medium, Add Smoked Bacon (+$3)', 23.00, 'Plated'),
(2, 11, 'Smoked Saffron Old Fashioned', 17.00, 3.20, 2, '', 34.00, 'Plated'),
(3, 3, 'Wagyu Beef Carpaccio', 24.00, 8.20, 2, '', 48.00, 'Plated'),
(3, 4, 'Dry-Aged Prime Ribeye (14oz)', 54.00, 19.50, 2, 'Medium Rare', 108.00, 'Cooking'),
(3, 8, 'Saffron & Maine Lobster Risotto', 42.00, 14.80, 1, '', 42.00, 'Cooking'),
(3, 13, 'Single-Origin Espresso Martini', 16.50, 3.10, 3, '', 49.50, 'Queued'),
(4, 7, 'Black Truffle Tagliolini', 32.00, 8.90, 1, '', 32.00, 'Plated'),
(4, 9, 'The Saffron Double Smash Burger', 23.00, 6.40, 1, 'Medium', 23.00, 'Plated'),
(5, 2, 'Crispy Calamari & Yuzu Aioli', 16.00, 4.50, 2, '', 32.00, 'Plated'),
(5, 6, 'Herb-Crusted Rack of Lamb', 46.00, 16.00, 1, 'Medium Rare', 46.00, 'Plated'),
(5, 14, 'Basque Burnt Cheesecake', 14.50, 3.40, 1, '', 14.50, 'Plated'),
(5, 12, 'Passionfruit & Yuzu Spritz (Zero Proof)', 10.00, 2.10, 2, '', 20.00, 'Plated'),
(6, 1, 'Truffle Burrata & Charred Fig', 18.50, 5.80, 1, '', 18.50, 'Plated'),
(6, 5, 'Miso-Glazed Black Cod', 38.00, 13.20, 1, '', 38.00, 'Plated'),
(6, 7, 'Black Truffle Tagliolini', 32.00, 8.90, 1, '', 32.00, 'Plated'),
(7, 4, 'Dry-Aged Prime Ribeye (14oz)', 54.00, 19.50, 2, 'Medium Rare', 108.00, 'Plated'),
(7, 1, 'Truffle Burrata & Charred Fig', 18.50, 5.80, 2, '', 37.00, 'Plated'),
(7, 11, 'Smoked Saffron Old Fashioned', 19.00, 3.20, 1, '', 19.00, 'Plated'),
(8, 8, 'Saffron & Maine Lobster Risotto', 42.00, 14.80, 3, '', 126.00, 'Plated'),
(8, 10, 'Wood-Fired Margherita DOP Pizza', 21.00, 4.20, 2, '', 42.00, 'Plated'),
(8, 13, 'Single-Origin Espresso Martini', 14.00, 3.10, 3, '', 42.00, 'Plated'),
(9, 4, 'Dry-Aged Prime Ribeye (14oz)', 54.00, 19.50, 2, 'Medium', 108.00, 'Plated'),
(9, 5, 'Miso-Glazed Black Cod', 38.00, 13.20, 2, '', 76.00, 'Plated'),
(10, 9, 'The Saffron Double Smash Burger', 23.00, 6.40, 2, '', 46.00, 'Plated'),
(10, 15, 'Valrhona 70% Molten Lava Cake', 15.50, 3.80, 2, '', 31.00, 'Plated'),
(11, 4, 'Dry-Aged Prime Ribeye (14oz)', 54.00, 19.50, 4, 'Medium Rare', 216.00, 'Plated'),
(11, 3, 'Wagyu Beef Carpaccio', 24.00, 8.20, 4, '', 96.00, 'Plated'),
(12, 6, 'Herb-Crusted Rack of Lamb', 46.00, 16.00, 2, '', 92.00, 'Plated'),
(12, 1, 'Truffle Burrata & Charred Fig', 18.75, 5.80, 2, '', 37.50, 'Plated'),
(13, 9, 'The Saffron Double Smash Burger', 23.00, 6.40, 3, '', 69.00, 'Plated'),
(14, 8, 'Saffron & Maine Lobster Risotto', 42.00, 14.80, 5, '', 210.00, 'Plated'),
(14, 4, 'Dry-Aged Prime Ribeye (14oz)', 54.00, 19.50, 2, '', 108.00, 'Plated'),
(14, 14, 'Basque Burnt Cheesecake', 20.00, 3.40, 2, '', 40.00, 'Plated'),
(15, 8, 'Saffron & Maine Lobster Risotto', 42.00, 14.80, 2, '', 84.00, 'Plated'),
(16, 1, 'Truffle Burrata & Charred Fig', 18.50, 5.80, 2, '', 37.00, 'Plated');
