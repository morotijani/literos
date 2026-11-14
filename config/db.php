<?php
/**
 * LITEROS — Database & Auto-Bootstrap Configuration
 * Supports XAMPP MySQL (127.0.0.1:3306) with automatic database & schema provisioning,
 * plus a seamless SQLite PDO fallback if MySQL service is stopped.
 *
 * Includes a high-speed historical seeder for "Shakes and Dessert" (Hajia Rabi Adam, Kumasi, Ghana)
 * generating backdated daily sales & expenditures from January 2024 to November 2026:
 *   - 2024 & 2025: Booming sales (~GHS 52,000 - 68,000/day on weekdays, GHS 75,000 - 112,000/day on weekends)
 *   - 2026: Moderate/good sales (~GHS 32,000 - 44,000/day on weekdays, GHS 50,000 - 72,000/day on weekends)
 *   - Payment Tender Mix: ~60% Cash, ~35% MoMo (Mobile Money), ~5% QR Pay
 */

declare(strict_types=1);

class Database {
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function getConnection(): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbName = getenv('DB_NAME') ?: 'literos';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        try {
            $dsnServer = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsnServer, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 2,
            ]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            self::$driver = 'mysql';
            self::$pdo = $pdo;

            // Check if company_profile is already seeded with "Shakes and Dessert" and expenditures table exists
            $needsSeed = false;
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE 'expenditures'");
                if ($stmt->rowCount() === 0) {
                    $needsSeed = true;
                } else {
                    $compName = (string)$pdo->query("SELECT name FROM company_profile LIMIT 1")->fetchColumn();
                    $orderCount = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                    if ($compName !== 'Shakes and Dessert' || $orderCount < 500) {
                        $needsSeed = true;
                    }
                }
            } catch (Throwable $ignore) {
                $needsSeed = true;
            }

            if ($needsSeed) {
                self::seedMysqlSchema($pdo);
            }

            return self::$pdo;
        } catch (Throwable $e) {
            return self::getSqliteFallback();
        }
    }

    public static function getDriver(): string {
        if (self::$pdo === null) {
            self::getConnection();
        }
        return self::$driver;
    }

    public static function resetAndSeed(): void {
        $pdo = self::getConnection();
        if (self::$driver === 'mysql') {
            self::seedMysqlSchema($pdo);
        } else {
            self::seedSqliteSchema($pdo);
        }
    }

    private static function seedMysqlSchema(PDO $pdo): void {
        $schemaPath = __DIR__ . '/../database/schema.sql';
        if (file_exists($schemaPath)) {
            $sql = file_get_contents($schemaPath);
            if ($sql !== false) {
                $pdo->exec($sql);
            }
        }
        self::seedHistoricalData($pdo);
    }

    private static function getSqliteFallback(): PDO {
        $sqlitePath = __DIR__ . '/../database/literos_local.sqlite';

        $pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");

        self::$driver = 'sqlite';
        self::$pdo = $pdo;

        $needsSeed = false;
        try {
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='expenditures'")->fetch();
            if (!$check) {
                $needsSeed = true;
            } else {
                $compName = (string)$pdo->query("SELECT name FROM company_profile LIMIT 1")->fetchColumn();
                $orderCount = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                if ($compName !== 'Shakes and Dessert' || $orderCount < 500) {
                    $needsSeed = true;
                }
            }
        } catch (Throwable $ignore) {
            $needsSeed = true;
        }

        if ($needsSeed) {
            self::seedSqliteSchema($pdo);
        }

        return self::$pdo;
    }

    private static function seedSqliteSchema(PDO $pdo): void {
        $pdo->exec("
            PRAGMA foreign_keys = OFF;
            DROP TABLE IF EXISTS expenditures;
            DROP TABLE IF EXISTS order_items;
            DROP TABLE IF EXISTS orders;
            DROP TABLE IF EXISTS shifts;
            DROP TABLE IF EXISTS restaurant_tables;
            DROP TABLE IF EXISTS menu_items;
            DROP TABLE IF EXISTS categories;
            DROP TABLE IF EXISTS staff;
            DROP TABLE IF EXISTS branches;
            DROP TABLE IF EXISTS company_profile;
            PRAGMA foreign_keys = ON;

            CREATE TABLE company_profile (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                owner_name TEXT DEFAULT 'Hajia Rabi Adam',
                tagline TEXT DEFAULT 'Signature Milkshakes, Artisanal Desserts & Savoury Grill • Kumasi, Ghana',
                logo_text TEXT DEFAULT 'S&D',
                currency_symbol TEXT DEFAULT 'GHS ',
                currency_code TEXT DEFAULT 'GHS',
                tax_rate REAL DEFAULT 6.00,
                service_charge_rate REAL DEFAULT 2.50,
                email TEXT DEFAULT 'hajia.rabi@shakesanddessertgh.com',
                phone TEXT DEFAULT '+233 24 489 1020',
                address TEXT DEFAULT 'KNUST Commercial Road, Ayiduase, Kumasi, Ashanti Region, Ghana',
                receipt_footer TEXT DEFAULT 'Medaase! Thank you for patronizing Shakes and Dessert • Owned by Hajia Rabi Adam',
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE branches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                city TEXT NOT NULL,
                address TEXT NOT NULL,
                phone TEXT NOT NULL,
                manager_name TEXT NOT NULL,
                opening_hours TEXT DEFAULT '08:00 AM - 11:30 PM',
                tax_override REAL DEFAULT NULL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE staff (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                branch_id INTEGER NOT NULL,
                employee_code TEXT NOT NULL UNIQUE,
                full_name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'Waiter',
                pin_code TEXT NOT NULL,
                email TEXT DEFAULT NULL,
                phone TEXT DEFAULT NULL,
                avatar_color TEXT DEFAULT '#f59e0b',
                hourly_rate REAL DEFAULT 45.00,
                commission_rate REAL DEFAULT 1.50,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL,
                icon TEXT DEFAULT 'fa-utensils',
                color TEXT DEFAULT '#f59e0b',
                sort_order INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1
            );

            CREATE TABLE menu_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                sku TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                description TEXT DEFAULT '',
                price REAL NOT NULL,
                cost REAL NOT NULL DEFAULT 0.00,
                course_type TEXT DEFAULT 'Dessert',
                prep_time_mins INTEGER DEFAULT 8,
                calories INTEGER DEFAULT 450,
                stock_qty INTEGER DEFAULT 200,
                low_stock_threshold INTEGER DEFAULT 20,
                modifiers_json TEXT DEFAULT NULL,
                image_emoji TEXT DEFAULT '🥤',
                is_popular INTEGER DEFAULT 0,
                is_available INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE restaurant_tables (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                branch_id INTEGER NOT NULL,
                table_number TEXT NOT NULL,
                zone TEXT DEFAULT 'Main Hall',
                capacity INTEGER DEFAULT 4,
                status TEXT DEFAULT 'Available',
                current_guests INTEGER DEFAULT 0,
                assigned_staff_id INTEGER DEFAULT NULL
            );

            CREATE TABLE shifts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                branch_id INTEGER NOT NULL,
                staff_id INTEGER NOT NULL,
                opening_cash REAL NOT NULL DEFAULT 3500.00,
                closing_cash REAL DEFAULT NULL,
                status TEXT DEFAULT 'Open',
                notes TEXT DEFAULT NULL,
                opened_at TEXT DEFAULT CURRENT_TIMESTAMP,
                closed_at TEXT DEFAULT NULL
            );

            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_number TEXT NOT NULL UNIQUE,
                branch_id INTEGER NOT NULL,
                table_id INTEGER DEFAULT NULL,
                staff_id INTEGER NOT NULL,
                customer_name TEXT DEFAULT 'Walk-in Guest',
                guests_count INTEGER DEFAULT 1,
                order_type TEXT NOT NULL DEFAULT 'Dine-In',
                status TEXT NOT NULL DEFAULT 'Completed',
                payment_status TEXT NOT NULL DEFAULT 'Paid',
                payment_method TEXT DEFAULT 'Cash',
                subtotal REAL NOT NULL DEFAULT 0.00,
                tax_amount REAL NOT NULL DEFAULT 0.00,
                service_charge REAL NOT NULL DEFAULT 0.00,
                discount_amount REAL NOT NULL DEFAULT 0.00,
                tip_amount REAL NOT NULL DEFAULT 0.00,
                total_amount REAL NOT NULL DEFAULT 0.00,
                amount_tendered REAL NOT NULL DEFAULT 0.00,
                notes TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                completed_at TEXT DEFAULT NULL
            );

            CREATE INDEX idx_orders_created_at ON orders(created_at);
            CREATE INDEX idx_orders_branch_date ON orders(branch_id, created_at);

            CREATE TABLE order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                menu_item_id INTEGER NOT NULL,
                item_name TEXT NOT NULL,
                unit_price REAL NOT NULL,
                unit_cost REAL NOT NULL DEFAULT 0.00,
                quantity INTEGER NOT NULL DEFAULT 1,
                modifiers TEXT DEFAULT NULL,
                line_total REAL NOT NULL,
                kitchen_status TEXT DEFAULT 'Plated'
            );

            CREATE INDEX idx_orderitems_order ON order_items(order_id);

            CREATE TABLE expenditures (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference_no TEXT NOT NULL UNIQUE,
                branch_id INTEGER NOT NULL,
                staff_id INTEGER NOT NULL,
                category TEXT NOT NULL DEFAULT 'Ingredients & Dairy',
                title TEXT NOT NULL,
                vendor_name TEXT DEFAULT NULL,
                payment_method TEXT NOT NULL DEFAULT 'MoMo',
                amount REAL NOT NULL DEFAULT 0.00,
                expense_date TEXT NOT NULL,
                notes TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE INDEX idx_exp_date ON expenditures(expense_date);
        ");

        $schemaPath = __DIR__ . '/../database/schema.sql';
        if (file_exists($schemaPath)) {
            $lines = file($schemaPath);
            $buffer = '';
            $capturing = false;
            foreach ($lines as $line) {
                if (stripos(trim($line), 'INSERT INTO') === 0) {
                    $capturing = true;
                }
                if ($capturing) {
                    $buffer .= $line;
                    if (substr(rtrim($line), -1) === ';') {
                        $pdo->exec($buffer);
                        $buffer = '';
                        $capturing = false;
                    }
                }
            }
        }

        self::seedHistoricalData($pdo);
    }

    /**
     * Generates realistic historical orders, order_items, and expenditures from 2024-01-01 to 2026-11-14
     * for Ayiduase, Sawaba, and Dechemso branches in Kumasi, Ghana.
     *
     * Calibration:
     * - 2024 & 2025 (Booming): Weekday company sales ~GHS 50,000 - 68,000/day; Weekend (Fri/Sat/Sun) ~GHS 78,000 - 110,000/day
     * - 2026 (Down from peak, still solid): Weekday ~GHS 34,000 - 44,000/day; Weekend ~GHS 52,000 - 72,000/day
     * - Payment Tender Split: 60% Cash, 35% MoMo, 5% QR Pay
     */
    private static function seedHistoricalData(PDO $pdo): void {
        mt_srand(20241114); // Deterministic seed for consistent analytics

        $menuCatalog = [
            1  => ['name' => "Hajia's Royal Lotus Biscoff Freakshake", 'price' => 95.0,  'cost' => 32.0],
            2  => ['name' => "Nutella Ferrero Rocher Monster Shake",   'price' => 110.0, 'cost' => 38.0],
            3  => ['name' => "Oreo Cookies & Cream Supreme Shake",     'price' => 85.0,  'cost' => 27.0],
            4  => ['name' => "Salted Caramel & Roasted Pistachio Shake", 'price' => 105.0, 'cost' => 36.0],
            5  => ['name' => "Belgian Liege Waffle & Gelato Trio",     'price' => 135.0, 'cost' => 44.0],
            6  => ['name' => "Sizzling Brownie Skillet Sundae",        'price' => 125.0, 'cost' => 40.0],
            7  => ['name' => "Kunafa Pistachio Chocolate Crepe (Dubai Style)", 'price' => 145.0, 'cost' => 48.0],
            8  => ['name' => "San Sebastián Burnt Basque Cheesecake",  'price' => 115.0, 'cost' => 36.0],
            9  => ['name' => "Red Velvet & Cream Cheese Tres Leches",  'price' => 95.0,  'cost' => 30.0],
            10 => ['name' => "Ayiduase Loaded Double Beef Smash Burger & Fries", 'price' => 140.0, 'cost' => 52.0],
            11 => ['name' => "Kumasi Spicy Chicken & Cheese Shawarma Platter", 'price' => 115.0, 'cost' => 42.0],
            12 => ['name' => "Honey-Suya Glazed Crispy Chicken Wings (10pcs)", 'price' => 130.0, 'cost' => 46.0],
            13 => ['name' => "Brown Sugar Tiger Milk Boba Tea",        'price' => 75.0,  'cost' => 22.0],
            14 => ['name' => "Tropical Mango, Passion & Hibiscus Sobolo Cooler", 'price' => 65.0, 'cost' => 18.0],
            15 => ['name' => "Hajia's Grand Family & Fiesta Dessert Platter", 'price' => 580.0, 'cost' => 195.0],
        ];

        // Branch staff & table pools
        $branchConfig = [
            1 => [
                'weight' => 0.42, // Ayiduase (KNUST Flagship)
                'waiters' => [4, 5, 6, 7],
                'cashier' => 3,
                'manager' => 2,
                'tables' => [1, 2, 3, 4, 5, 6, 7, 8],
            ],
            2 => [
                'weight' => 0.31, // Sawaba
                'waiters' => [14, 15, 16, 17],
                'cashier' => 13,
                'manager' => 12,
                'tables' => [9, 10, 11, 12],
            ],
            3 => [
                'weight' => 0.27, // Dechemso
                'waiters' => [24, 25, 26, 27],
                'cashier' => 23,
                'manager' => 22,
                'tables' => [13, 14, 15],
            ],
        ];

        $customerNames = [
            'KNUST Graduating Class Group', 'Nana Kwame & Friends', 'Hajia Zainab Family Table',
            'Ayiduase Hostel Fiesta', 'Dr. Boateng Family', 'Kumasi Mall Shoppers',
            'Sawaba Youth Fellowship', 'Dechemso Executive Lounge', 'Ashanti Gold Corporate Team',
            'Osei Tutu Hall Alumni', "Queen Mother's Entourage", 'Kofi & Ama Anniversary',
            'Tech Junction Takeout Batch', 'Bantama Social Club', 'Adum Business Group',
            'KNUST SRC Catering Order', 'Suame Magazine Engineers', 'Airport Residential Guests'
        ];

        $orderTypes = ['Dine-In', 'Dine-In', 'Dine-In', 'Takeaway', 'Delivery'];

        $pdo->beginTransaction();

        $insOrder = $pdo->prepare("
            INSERT INTO orders (
                order_number, branch_id, table_id, staff_id, customer_name, guests_count,
                order_type, status, payment_status, payment_method,
                subtotal, tax_amount, service_charge, discount_amount, tip_amount, total_amount,
                amount_tendered, notes, created_at, completed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insItem = $pdo->prepare("
            INSERT INTO order_items (
                order_id, menu_item_id, item_name, unit_price, unit_cost, quantity, modifiers, line_total, kitchen_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insExp = $pdo->prepare("
            INSERT INTO expenditures (
                reference_no, branch_id, staff_id, category, title, vendor_name, payment_method, amount, expense_date, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate   = new DateTimeImmutable('2026-11-14');
        $currDate  = $startDate;

        $orderSeq = 10000;
        $orderIdCounter = 1;
        $expSeq = 5000;

        $expenseTemplates = [
            ['cat' => 'Ingredients & Dairy', 'title' => 'Bulk Fresh Cream, Jersey Milk & Belgian Chocolate Supply', 'vendor' => 'Kumasi ColdStore & Dairy Ltd', 'method' => 'MoMo', 'min' => 6500, 'max' => 12500],
            ['cat' => 'Fruits, Nuts & Toppings', 'title' => 'Biscoff Spread, Nutella Tubs, Pistachios & Fresh Berries', 'vendor' => 'Kejetia Central Wholesale Market', 'method' => 'Cash', 'min' => 4200, 'max' => 8800],
            ['cat' => 'Meat & Shawarma Supplies', 'title' => 'Prime Beef Patties, Chicken Breast, Suya Spice & Fries', 'vendor' => 'Ashanti Halal Meats & Poultry', 'method' => 'Cash', 'min' => 4800, 'max' => 9200],
            ['cat' => 'Packaging & Cups', 'title' => 'Branded Dome Shake Cups, Boba Straws & Takeout Boxes', 'vendor' => 'Accra-Kumasi EcoPack Ltd', 'method' => 'MoMo', 'min' => 2800, 'max' => 5400],
            ['cat' => 'Utilities & Generator Fuel', 'title' => 'ECG Prepaid Power & Standby Generator Diesel Top-up', 'vendor' => 'ECG / GOIL Kumasi', 'method' => 'MoMo', 'min' => 3200, 'max' => 6400],
            ['cat' => 'Equipment & Maintenance', 'title' => 'Commercial Blender, Gelato Freezer & Waffle Iron Servicing', 'vendor' => 'Suame Tech Cold-Chain Services', 'method' => 'Cash', 'min' => 1500, 'max' => 4200],
        ];

        while ($currDate <= $endDate) {
            $year = (int)$currDate->format('Y');
            $month = (int)$currDate->format('n');
            $dayOfWeek = (int)$currDate->format('N'); // 1 (Mon) .. 7 (Sun)
            $dateStr = $currDate->format('Y-m-d');
            $isWeekend = ($dayOfWeek >= 5); // Friday, Saturday, Sunday

            // Determine daily company-wide target revenue in GHS
            if ($year === 2024) {
                // Booming year 1
                $companyDailyTarget = $isWeekend
                    ? (float)mt_rand(74000, 104000)
                    : (float)mt_rand(47000, 61000);
            } elseif ($year === 2025) {
                // Peak booming year 2
                $companyDailyTarget = $isWeekend
                    ? (float)mt_rand(78000, 112000)
                    : (float)mt_rand(51000, 66000);
            } else {
                // 2026: Business is down compared to 2024/2025 boom, but still healthy/good
                $companyDailyTarget = $isWeekend
                    ? (float)mt_rand(48000, 69000)
                    : (float)mt_rand(31000, 43000);
            }

            // Festive boost in December & Eid/Graduation months
            if ($month === 12 || $month === 4 || $month === 11) {
                $companyDailyTarget *= 1.08;
            }

            // Create 4 consolidated shift/group orders per day across the 3 branches
            // so total daily revenue matches $companyDailyTarget and 35% MoMo / 60% Cash / 5% QR Pay
            foreach ([1, 2, 3] as $bId) {
                $bCfg = $branchConfig[$bId];
                $branchDailyTarget = $companyDailyTarget * $bCfg['weight'];

                // 2 consolidated orders per branch per day (Afternoon & Evening Rush)
                $numOrders = ($isWeekend && $bId === 1) ? 2 : 1;
                $targetPerOrder = $branchDailyTarget / $numOrders;

                for ($k = 0; $k < $numOrders; $k++) {
                    $orderSeq++;
                    $orderNumber = 'SD-' . $orderSeq;

                    // Exact 60% Cash, 35% MoMo, 5% QR Pay distribution
                    $roll = mt_rand(1, 100);
                    if ($roll <= 60) {
                        $payMethod = 'Cash';
                    } elseif ($roll <= 95) {
                        $payMethod = 'MoMo';
                    } else {
                        $payMethod = 'QR Pay';
                    }

                    $waiterId = $bCfg['waiters'][array_rand($bCfg['waiters'])];
                    $tableId = $bCfg['tables'][array_rand($bCfg['tables'])];
                    $ordType = $orderTypes[array_rand($orderTypes)];
                    $custName = $customerNames[array_rand($customerNames)];
                    $guests = mt_rand(8, 32);

                    // Pick 2 menu items and scale quantities to hit $targetPerOrder
                    $itemId1 = mt_rand(1, 7);   // Signature Shake or Waffle/Crepe
                    $itemId2 = mt_rand(8, 15);  // Grill/Shawarma/Cake/Platter
                    $m1 = $menuCatalog[$itemId1];
                    $m2 = $menuCatalog[$itemId2];

                    // Tax (6%) + Service Charge (2.5% for Dine-In) = 8.5%
                    $svcRate = ($ordType === 'Dine-In') ? 0.025 : 0.0;
                    $taxRate = 0.06;
                    $targetSubtotal = $targetPerOrder / (1.0 + $taxRate + $svcRate);

                    $halfSub = $targetSubtotal * 0.52;
                    $qty1 = max(1, (int)round($halfSub / $m1['price']));
                    $line1 = round($qty1 * $m1['price'], 2);

                    $remSub = max($m2['price'], $targetSubtotal - $line1);
                    $qty2 = max(1, (int)round($remSub / $m2['price']));
                    $line2 = round($qty2 * $m2['price'], 2);

                    $subtotal = round($line1 + $line2, 2);
                    $discount = 0.00;
                    $taxAmt = round($subtotal * $taxRate, 2);
                    $svcAmt = round($subtotal * $svcRate, 2);
                    $tipAmt = ($isWeekend && mt_rand(1, 100) <= 45) ? (float)(mt_rand(5, 25) * 10) : 0.00;
                    $totalAmt = round($subtotal + $taxAmt + $svcAmt + $tipAmt, 2);

                    $hour = ($k === 0) ? mt_rand(11, 15) : mt_rand(17, 21);
                    $min = mt_rand(10, 58);
                    $createdAt = sprintf('%s %02d:%02d:00', $dateStr, $hour, $min);
                    $completedAt = sprintf('%s %02d:%02d:00', $dateStr, min(23, $hour + 1), $min);

                    $insOrder->execute([
                        $orderNumber, $bId, ($ordType === 'Dine-In' ? $tableId : null), $waiterId,
                        $custName, $guests, $ordType, 'Completed', 'Paid', $payMethod,
                        $subtotal, $taxAmt, $svcAmt, $discount, $tipAmt, $totalAmt,
                        $totalAmt, 'Daily consolidated shift batch', $createdAt, $completedAt
                    ]);

                    $currentOrderId = (int)$pdo->lastInsertId();

                    $insItem->execute([
                        $currentOrderId, $itemId1, $m1['name'], $m1['price'], $m1['cost'],
                        $qty1, '', $line1, 'Plated'
                    ]);
                    $insItem->execute([
                        $currentOrderId, $itemId2, $m2['name'], $m2['price'], $m2['cost'],
                        $qty2, '', $line2, 'Plated'
                    ]);
                }
            }

            // Seed Operating Expenditures every 3 days across branches
            $dayOfYear = (int)$currDate->format('z');
            if ($dayOfYear % 3 === 0) {
                $expBranchId = ($dayOfYear % 3) + 1;
                foreach ([1, 2, 3] as $bId) {
                    $tpl = $expenseTemplates[($dayOfYear + $bId) % count($expenseTemplates)];
                    $expSeq++;
                    $refNo = 'EXP-' . $expSeq;
                    $scale = ($year === 2026) ? 0.72 : 1.0;
                    $amt = round(mt_rand($tpl['min'], $tpl['max']) * $scale, 2);
                    $mgrId = $branchConfig[$bId]['manager'];

                    $insExp->execute([
                        $refNo, $bId, $mgrId, $tpl['cat'], $tpl['title'], $tpl['vendor'],
                        $tpl['method'], $amt, $dateStr,
                        'Approved by Branch Manager & Hajia Rabi Adam HQ',
                        $dateStr . ' 09:30:00'
                    ]);
                }
            }

            $currDate = $currDate->modify('+1 day');
        }

        // Also insert 3 live active KDS orders for today (2026-11-14) so Kitchen Display & Floor Tables have live tickets
        $liveOrders = [
            [
                'num' => 'SD-LIVE01', 'branch' => 1, 'table' => 1, 'staff' => 4,
                'cust' => 'Table AYI-T1 (KNUST Architecture Postgrads)', 'guests' => 3,
                'type' => 'Dine-In', 'status' => 'Preparing', 'pay_status' => 'Unpaid', 'method' => 'MoMo',
                'notes' => 'Extra Biscoff drizzle on 2 shakes, serve waffle warm',
                'items' => [
                    [1, 3, 'Extra Biscoff Drizzle (+GHS 15)', 'Cooking'],
                    [5, 2, '', 'Cooking'],
                    [11, 2, 'Extra Spicy', 'Plated']
                ]
            ],
            [
                'num' => 'SD-LIVE02', 'branch' => 1, 'table' => 6, 'staff' => 5,
                'cust' => "VIP-1 (Hajia's Special Guests - Birthday)", 'guests' => 6,
                'type' => 'Dine-In', 'status' => 'Ready', 'pay_status' => 'Paid', 'method' => 'Cash',
                'notes' => 'Bring birthday sparkler with Grand Fiesta Platter',
                'items' => [
                    [15, 1, 'Birthday Sparkler', 'Plated'],
                    [2, 4, '', 'Plated'],
                    [10, 3, '', 'Plated']
                ]
            ],
            [
                'num' => 'SD-LIVE03', 'branch' => 2, 'table' => 9, 'staff' => 14,
                'cust' => 'Table SAW-T1 (Sawaba Evening Family)', 'guests' => 4,
                'type' => 'Dine-In', 'status' => 'Preparing', 'pay_status' => 'Paid', 'method' => 'MoMo',
                'notes' => 'Less ice in Boba teas',
                'items' => [
                    [7, 2, '', 'Cooking'],
                    [13, 4, 'Less Ice', 'Plated']
                ]
            ],
        ];

        $nowStr = date('Y-m-d H:i:s');
        foreach ($liveOrders as $lo) {
            $sub = 0.0;
            foreach ($lo['items'] as $li) {
                $m = $menuCatalog[$li[0]];
                $sub += $m['price'] * $li[1];
            }
            $tax = round($sub * 0.06, 2);
            $svc = round($sub * 0.025, 2);
            $tot = round($sub + $tax + $svc, 2);

            $insOrder->execute([
                $lo['num'], $lo['branch'], $lo['table'], $lo['staff'],
                $lo['cust'], $lo['guests'], $lo['type'], $lo['status'], $lo['pay_status'], $lo['method'],
                $sub, $tax, $svc, 0.0, 0.0, $tot,
                ($lo['pay_status'] === 'Paid' ? $tot : 0.0), $lo['notes'], $nowStr, null
            ]);
            $liveOrderId = (int)$pdo->lastInsertId();
            foreach ($lo['items'] as $li) {
                $m = $menuCatalog[$li[0]];
                $insItem->execute([
                    $liveOrderId, $li[0], $m['name'], $m['price'], $m['cost'],
                    $li[1], $li[2], round($m['price'] * $li[1], 2), $li[3]
                ]);
            }
        }

        $pdo->commit();
    }
}
