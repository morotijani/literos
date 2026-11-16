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
                $stmt = $pdo->query("SHOW TABLES LIKE 'coffers_ledger'");
                if ($stmt->rowCount() === 0) {
                    $needsSeed = true;
                } else {
                    $compName = (string)$pdo->query("SELECT name FROM company_profile LIMIT 1")->fetchColumn();
                    $orderCount = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                    $catCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
                    $coffersCount = (int)$pdo->query("SELECT COUNT(*) FROM coffers_ledger")->fetchColumn();
                    $taxTipSum = (float)$pdo->query("SELECT COALESCE(SUM(tax_amount + service_charge + tip_amount), 0) FROM orders")->fetchColumn();
                    $oldSeed = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE employee_code = 'AYI-MGR' AND full_name LIKE '%Alhaji Sulemana%'")->fetchColumn();
                    $decBreakMax = (float)$pdo->query("SELECT COALESCE(MAX(total_amount), 0) FROM orders WHERE branch_id = 3 AND created_at >= '2026-05-02 00:00:00' AND created_at <= '2026-07-26 23:59:59'")->fetchColumn();
                    $sawClosureCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE branch_id = 2 AND created_at >= '2024-11-01 00:00:00' AND created_at <= '2025-11-30 23:59:59'")->fetchColumn();
                    $recentSlumpAvg = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) / 75.0 FROM orders WHERE created_at >= '2026-09-01 00:00:00' AND created_at <= '2026-11-14 23:59:59'")->fetchColumn();
                    $otherExpCount = (int)$pdo->query("SELECT COUNT(*) FROM expenditures WHERE category = 'Other'")->fetchColumn();
                    if ($compName !== 'Shakes and Dessert' || $orderCount < 500 || $catCount < 11 || $coffersCount < 20 || $taxTipSum > 0 || $oldSeed > 0 || $decBreakMax > 3500 || $sawClosureCount > 0 || $recentSlumpAvg > 42000 || $otherExpCount === 0) {
                        $needsSeed = true;
                    }
                }
            } catch (Throwable $ignore) {
                $needsSeed = true;
            }

            if ($needsSeed) {
                self::seedMysqlSchema($pdo);
            }
            self::ensureSchemaUpgrades($pdo, 'mysql');

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
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='coffers_ledger'")->fetch();
            if (!$check) {
                $needsSeed = true;
            } else {
                $compName = (string)$pdo->query("SELECT name FROM company_profile LIMIT 1")->fetchColumn();
                $orderCount = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                $catCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
                $coffersCount = (int)$pdo->query("SELECT COUNT(*) FROM coffers_ledger")->fetchColumn();
                $taxTipSum = (float)$pdo->query("SELECT COALESCE(SUM(tax_amount + service_charge + tip_amount), 0) FROM orders")->fetchColumn();
                $oldSeed = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE employee_code = 'AYI-MGR' AND full_name LIKE '%Alhaji Sulemana%'")->fetchColumn();
                $decBreakMax = (float)$pdo->query("SELECT COALESCE(MAX(total_amount), 0) FROM orders WHERE branch_id = 3 AND created_at >= '2026-05-02 00:00:00' AND created_at <= '2026-07-26 23:59:59'")->fetchColumn();
                $sawClosureCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE branch_id = 2 AND created_at >= '2024-11-01 00:00:00' AND created_at <= '2025-11-30 23:59:59'")->fetchColumn();
                $recentSlumpAvg = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) / 75.0 FROM orders WHERE created_at >= '2026-09-01 00:00:00' AND created_at <= '2026-11-14 23:59:59'")->fetchColumn();
                $otherExpCount = (int)$pdo->query("SELECT COUNT(*) FROM expenditures WHERE category = 'Other'")->fetchColumn();
                if ($compName !== 'Shakes and Dessert' || $orderCount < 500 || $catCount < 11 || $coffersCount < 20 || $taxTipSum > 0 || $oldSeed > 0 || $decBreakMax > 3500 || $sawClosureCount > 0 || $recentSlumpAvg > 42000 || $otherExpCount === 0) {
                    $needsSeed = true;
                }
            }
        } catch (Throwable $ignore) {
            $needsSeed = true;
        }

        if ($needsSeed) {
            self::seedSqliteSchema($pdo);
        }
        self::ensureSchemaUpgrades($pdo, 'sqlite');

        return self::$pdo;
    }

    private static function ensureSchemaUpgrades(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $cols = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'image_url'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE menu_items ADD COLUMN image_url VARCHAR(255) DEFAULT '' AFTER image_emoji");
                }
            } else {
                $cols = $pdo->query("PRAGMA table_info(menu_items)")->fetchAll(PDO::FETCH_COLUMN, 1);
                if (!in_array('image_url', $cols, true)) {
                    $pdo->exec("ALTER TABLE menu_items ADD COLUMN image_url TEXT DEFAULT ''");
                }
            }
        } catch (Throwable $ignore) {
            // Non-fatal if table does not exist yet
        }
    }

    private static function seedSqliteSchema(PDO $pdo): void {
        $pdo->exec("
            PRAGMA foreign_keys = OFF;
            DROP TABLE IF EXISTS coffers_ledger;
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
                tax_rate REAL DEFAULT 0.00,
                service_charge_rate REAL DEFAULT 0.00,
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
                image_url TEXT DEFAULT '',
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

            CREATE TABLE coffers_ledger (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference_no TEXT NOT NULL UNIQUE,
                branch_id INTEGER NOT NULL,
                staff_id INTEGER NOT NULL,
                entry_type TEXT NOT NULL DEFAULT 'Withdrawal',
                purpose_type TEXT NOT NULL DEFAULT 'Withdrawal for Goods / Market',
                payment_channel TEXT NOT NULL DEFAULT 'Cash',
                amount REAL NOT NULL DEFAULT 0.00,
                recipient_or_source TEXT DEFAULT NULL,
                description TEXT NOT NULL,
                transaction_date TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE INDEX idx_coffers_date ON coffers_ledger(transaction_date);
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

        $rows = $pdo->query("SELECT id, name, price, cost FROM menu_items ORDER BY id ASC")->fetchAll();
        $menuCatalog = [];
        foreach ($rows as $r) {
            $menuCatalog[(int)$r['id']] = [
                'name'  => (string)$r['name'],
                'price' => (float)$r['price'],
                'cost'  => (float)$r['cost'],
            ];
        }
        $menuIds = array_keys($menuCatalog);
        $menuCount = count($menuIds);

        // Branch staff & table pools (exact Shakes & Dessert personnel per branch)
        $branchConfig = [
            1 => [
                'weight' => 0.42, // Ayiduase (KNUST Flagship)
                'waiters' => [3, 4, 5, 6], // Yasmine, Aisha (Ayiduase), Latif, Samuel
                'cashier' => 2,            // Zainab (Branch Manager)
                'manager' => 2,            // Zainab
                'tables' => [1, 2, 3, 4, 5, 6, 7, 8],
            ],
            2 => [
                'weight' => 0.30, // Sawaba
                'waiters' => [10],         // Aisha (Sawaba)
                'cashier' => 9,            // Baki (Manager/Supervisor)
                'manager' => 9,            // Baki
                'tables' => [9, 10, 11, 12],
            ],
            3 => [
                'weight' => 0.28, // Dechemso
                'waiters' => [15, 16, 17], // Daniel, Mary, Nat
                'cashier' => 14,           // Matilda (Cashier)
                'manager' => 13,           // Kudus (Branch Manager)
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

        $insCoffer = $pdo->prepare("
            INSERT INTO coffers_ledger (
                reference_no, branch_id, staff_id, entry_type, purpose_type, payment_channel, amount, recipient_or_source, description, transaction_date, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate   = new DateTimeImmutable('2026-11-14');
        $currDate  = $startDate;

        $orderSeq = 10000;
        $orderIdCounter = 1;
        $expSeq = 5000;
        $cofferSeq = 1000;

        $marketWithdrawalTemplates = [
            [
                'recipient' => 'Kejetia Central Market & ColdStore Run (Hajia Rabi & Chef Fati)',
                'desc' => 'Monthly bulk market purchase: flour, eggs, Lotus Biscoff spread, Nutella tubs, fresh whipping cream, milk & berries',
                'channel' => 'Cash'
            ],
            [
                'recipient' => 'Asafo & Ayeduasi Wholesale Market Supply Run (Maabena & Theresah)',
                'desc' => 'Mid-month market restock: chicken wings, Irish potatoes for Loaded Fries, mozzarella cheese, cooking oil, syrups & waffle mix',
                'channel' => 'MoMo'
            ],
            [
                'recipient' => 'Kumasi Dairy, Fruit & Packaging Market Procurement',
                'desc' => 'Market purchase of fresh strawberries, mangoes, pineapples, ice cream base, Crazy Shake cups & takeaway boxes',
                'channel' => 'Cash'
            ],
        ];

        $normalWithdrawalTemplates = [
            [
                'recipient' => 'Hajia Rabi Adam (Executive Owner Drawing)',
                'desc' => 'Normal coffers withdrawal — Monthly owner personal drawing & family reserve',
                'channel' => 'Bank Transfer',
                'min' => 3500,
                'max' => 6500
            ],
            [
                'recipient' => 'Branch Petty Cash & Staff Welfare Allowance',
                'desc' => 'Normal withdrawal — Staff transport allowance, weekend rush bonus & branch petty cash float',
                'channel' => 'Cash',
                'min' => 1800,
                'max' => 3400
            ],
            [
                'recipient' => 'Utility, ECG Power & Store Rent Reserve',
                'desc' => 'Normal withdrawal — Monthly ECG prepaid top-up, water & shop maintenance reserve',
                'channel' => 'MoMo',
                'min' => 2200,
                'max' => 4200
            ],
        ];

        // Initial Opening Coffer Float Deposit by Hajia Rabi Adam on Jan 1, 2024
        $cofferSeq++;
        $insCoffer->execute([
            'COF-' . $cofferSeq, 1, 1, 'Deposit', 'Manual Coffer Deposit', 'Bank Transfer',
            45000.00, 'Hajia Rabi Adam (Owner Capital Float)',
            'Opening Shakes & Dessert Treasury Coffers Float across Ayeduasi, Sawaba & Dichemso branches',
            '2024-01-01', '2024-01-01 08:15:00'
        ]);

        $expenseTemplates = [
            ['cat' => 'Ingredients & Dairy', 'title' => 'Bulk Fresh Cream, Jersey Milk & Belgian Chocolate Supply', 'vendor' => 'Kumasi ColdStore & Dairy Ltd', 'method' => 'MoMo', 'min' => 6500, 'max' => 12500],
            ['cat' => 'Fruits, Nuts & Toppings', 'title' => 'Biscoff Spread, Nutella Tubs, Pistachios & Fresh Berries', 'vendor' => 'Kejetia Central Wholesale Market', 'method' => 'Cash', 'min' => 4200, 'max' => 8800],
            ['cat' => 'Meat & Shawarma Supplies', 'title' => 'Prime Beef Patties, Chicken Breast, Suya Spice & Fries', 'vendor' => 'Ashanti Halal Meats & Poultry', 'method' => 'Cash', 'min' => 4800, 'max' => 9200],
            ['cat' => 'Packaging & Cups', 'title' => 'Branded Dome Shake Cups, Boba Straws & Takeout Boxes', 'vendor' => 'Accra-Kumasi EcoPack Ltd', 'method' => 'MoMo', 'min' => 2800, 'max' => 5400],
            ['cat' => 'Utilities & Generator Fuel', 'title' => 'ECG Prepaid Power & Standby Generator Diesel Top-up', 'vendor' => 'ECG / GOIL Kumasi', 'method' => 'MoMo', 'min' => 3200, 'max' => 6400],
            ['cat' => 'Equipment & Maintenance', 'title' => 'Commercial Blender, Gelato Freezer & Waffle Iron Servicing', 'vendor' => 'Suame Tech Cold-Chain Services', 'method' => 'Cash', 'min' => 1500, 'max' => 4200],
        ];

        $otherExpenseTemplates = [
            ['title' => 'Miscellaneous Petty Cash & Dispatch Rider Stipends', 'method' => 'Cash', 'min' => 650, 'max' => 1450],
            ['title' => 'KMA Market Levy, Refuse & Sanitation Clearance Fee', 'method' => 'MoMo', 'min' => 450, 'max' => 950],
            ['title' => 'Emergency Errand Transport, Courier & Fuel Payout', 'method' => 'Cash', 'min' => 350, 'max' => 850],
            ['title' => 'Branch Promotional Flyers & Local Community Sponsorship', 'method' => 'MoMo', 'min' => 800, 'max' => 1900],
            ['title' => 'Staff Emergency Support & Shift Refreshment Allowance', 'method' => 'Cash', 'min' => 500, 'max' => 1200],
        ];

        while ($currDate <= $endDate) {
            $year = (int)$currDate->format('Y');
            $month = (int)$currDate->format('n');
            $dayOfWeek = (int)$currDate->format('N'); // 1 (Mon) .. 7 (Sun)
            $dateStr = $currDate->format('Y-m-d');
            $isWeekend = ($dayOfWeek >= 5); // Friday, Saturday, Sunday

            $isRecentSlump = ($dateStr >= '2026-09-01');

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
            } elseif ($isRecentSlump) {
                // Past 3 months (Sep - Nov 2026): bad sales across all three branches (~GHS 30,000 - 40,000/day combined)
                $companyDailyTarget = $isWeekend
                    ? (float)mt_rand(35500, 40500)
                    : (float)mt_rand(30000, 35500);
            } else {
                // Early-to-mid 2026: Business is down compared to 2024/2025 boom, but still healthy/good
                $companyDailyTarget = $isWeekend
                    ? (float)mt_rand(48000, 69000)
                    : (float)mt_rand(31000, 43000);
            }

            // Festive boost in December & Eid/Graduation months (skip during the Sep-Nov 2026 slump)
            if (!$isRecentSlump && ($month === 12 || $month === 4 || $month === 11)) {
                $companyDailyTarget *= 1.08;
            }

            // Pre-compute Sawaba target when active in late 2025/2026 so company-wide 3-month slump stays GHS 30k-40k/day
            $sawabaOverrideTarget = null;
            if ($dateStr >= '2025-12-01' && $dateStr <= '2026-08-31') {
                $sawabaOverrideTarget = $isWeekend
                    ? (float)mt_rand(23000, 27000)
                    : (float)mt_rand(19500, 23000);
            } elseif ($dateStr >= '2026-09-01' && $dateStr <= '2026-09-30') {
                $sawabaOverrideTarget = $isWeekend
                    ? (float)mt_rand(20500, 22000)
                    : (float)mt_rand(19500, 21000);
            } elseif ($dateStr >= '2026-10-01') {
                $sawabaOverrideTarget = $isWeekend
                    ? (float)mt_rand(10200, 12200)
                    : (float)mt_rand(8200, 10200);
            }

            // Dechemso branch (ID 3) incident closure & deep-cleaning break: 2026-05-02 to 2026-07-26 (reopens 2026-07-27)
            $isDechemsoBreakPeriod = ($dateStr >= '2026-05-02' && $dateStr <= '2026-07-26');

            // Sawaba branch (ID 2) incident closure: 2024-11-01 to 2025-11-30 (0 sales; reopens 2025-12-01)
            $isSawabaClosurePeriod = ($dateStr >= '2024-11-01' && $dateStr <= '2025-11-30');

            // Create consolidated shift/group orders per day across the 3 branches
            // so total daily revenue matches $companyDailyTarget and 35% MoMo / 60% Cash / 5% QR Pay
            foreach ([1, 2, 3] as $bId) {
                $bCfg = $branchConfig[$bId];

                // Sawaba completely closed Nov 2024 - Nov 2025 (0 sales)
                if ($bId === 2 && $isSawabaClosurePeriod) {
                    continue;
                }

                // During Dechemso's closure break (May 2 - late July 2026):
                // Not every day had sales (~52% days have 0 orders); active days only have small Loaded Fries sales totaling GHS 1,500 - 3,000/day
                $inDechemsoBreak = ($bId === 3 && $isDechemsoBreakPeriod);
                if ($inDechemsoBreak && mt_rand(1, 100) <= 52) {
                    continue;
                }

                if ($isRecentSlump && $sawabaOverrideTarget !== null) {
                    // Distribute recent 3-month downturn across all 3 branches totaling ~GHS 30,000 - 40,000/day
                    $remainingTarget = max(12000.0, $companyDailyTarget - $sawabaOverrideTarget);
                    if ($bId === 2) {
                        $branchDailyTarget = $sawabaOverrideTarget;
                    } elseif ($bId === 1) {
                        $branchDailyTarget = $remainingTarget * 0.60;
                    } else {
                        $branchDailyTarget = $remainingTarget * 0.40;
                    }
                } else {
                    $branchDailyTarget = ($bId === 2 && $sawabaOverrideTarget !== null)
                        ? $sawabaOverrideTarget
                        : ($companyDailyTarget * $bCfg['weight']);
                }

                // 2 consolidated orders per branch per day (Afternoon & Evening Rush), 1 small batch during Dechemso break
                $numOrders = $inDechemsoBreak ? 1 : (($isWeekend && $bId === 1) ? 2 : 1);
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

                    if ($inDechemsoBreak) {
                        // Small break-period takeaway/delivery sales of Loaded Fries ONLY (GHS 1,500.00 - GHS 3,000.00/day)
                        $ordType = (mt_rand(1, 100) <= 70) ? 'Takeaway' : 'Delivery';
                        $custName = 'Dechemso Break-Period Loaded Fries Takeaway';
                        $guests = mt_rand(2, 6);
                        $orderNote = 'Incident closure break (May 2 - late July 2026): small Loaded Fries sales only';

                        $m1 = $menuCatalog[65]; // Loaded Fries (150 GHc)
                        $m2 = $menuCatalog[66]; // Loaded Fries (180 GHc)

                        // Pick exact combinations of 150 GHc & 180 GHc Loaded Fries strictly within GHS 1,500 - 3,000
                        $qty1 = mt_rand(4, 10); // 600 .. 1500
                        $maxQty2 = max(1, (int)floor((3000 - ($qty1 * 150)) / 180));
                        $minQty2 = max(1, (int)ceil((1500 - ($qty1 * 150)) / 180));
                        $qty2 = ($minQty2 <= $maxQty2) ? mt_rand($minQty2, $maxQty2) : $minQty2;

                        $line1 = round($qty1 * $m1['price'], 2);
                        $line2 = round($qty2 * $m2['price'], 2);
                        $itemId1 = 65;
                        $itemId2 = 66;
                    } else {
                        $ordType = $orderTypes[array_rand($orderTypes)];
                        $custName = $customerNames[array_rand($customerNames)];
                        $guests = mt_rand(8, 32);
                        $orderNote = 'Daily consolidated shift batch';

                        // Pick 2 menu items heavily weighted toward Top Sellers:
                        // Crazy Shakes category (IDs 22..27) and Loaded Fries 150 & 180 (IDs 65, 66)
                        $crazyShakeIds = [22, 23, 24, 25, 26, 27];
                        $loadedFriesIds = [65, 66];
                        if (mt_rand(1, 100) <= 68) {
                            $itemId1 = $crazyShakeIds[array_rand($crazyShakeIds)];
                        } else {
                            $itemId1 = $menuIds[mt_rand(0, max(0, (int)floor($menuCount / 2) - 1))];
                        }

                        if (mt_rand(1, 100) <= 65) {
                            $itemId2 = $loadedFriesIds[array_rand($loadedFriesIds)];
                        } else {
                            $itemId2 = $menuIds[mt_rand((int)floor($menuCount / 2), max(0, $menuCount - 1))];
                        }
                        $m1 = $menuCatalog[$itemId1];
                        $m2 = $menuCatalog[$itemId2];

                        $targetSubtotal = $targetPerOrder;
                        $halfSub = $targetSubtotal * 0.52;
                        $qty1 = max(1, (int)round($halfSub / $m1['price']));
                        $line1 = round($qty1 * $m1['price'], 2);

                        $remSub = max($m2['price'], $targetSubtotal - $line1);
                        $qty2 = max(1, (int)round($remSub / $m2['price']));
                        $line2 = round($qty2 * $m2['price'], 2);
                    }

                    // Shakes and Dessert: 0.00 Taxes, 0.00 Service Charge, 0.00 Tips
                    $subtotal = round($line1 + $line2, 2);
                    $discount = 0.00;
                    $taxAmt = 0.00;
                    $svcAmt = 0.00;
                    $tipAmt = 0.00;
                    $totalAmt = $subtotal;

                    $hour = $inDechemsoBreak ? mt_rand(13, 18) : (($k === 0) ? mt_rand(11, 15) : mt_rand(17, 21));
                    $min = mt_rand(10, 58);
                    $createdAt = sprintf('%s %02d:%02d:00', $dateStr, $hour, $min);
                    $completedAt = sprintf('%s %02d:%02d:00', $dateStr, min(23, $hour + 1), $min);

                    $insOrder->execute([
                        $orderNumber, $bId, ($ordType === 'Dine-In' ? $tableId : null), $waiterId,
                        $custName, $guests, $ordType, 'Completed', 'Paid', $payMethod,
                        $subtotal, $taxAmt, $svcAmt, $discount, $tipAmt, $totalAmt,
                        $totalAmt, $orderNote, $createdAt, $completedAt
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

            // Seed Operating Expenditures every 3 days across branches (pause regular stock expenses during Dechemso/Sawaba closures)
            $dayOfYear = (int)$currDate->format('z');
            if ($dateStr === '2024-11-05' || $dateStr === '2025-11-25') {
                // Deep cleaning, sanitization & reopening preparation vouchers for Sawaba incident closure
                $expSeq++;
                $sawCleanTitle = ($dateStr === '2024-11-05')
                    ? 'Sawaba Branch Incident Closure — Deep Cleaning, Sanitizing & Equipment Audit'
                    : 'Sawaba Branch Reopening Prep — Refurbishment, Deep Cleaning & Cold-Chain Servicing';
                $insExp->execute([
                    'EXP-' . $expSeq, 2, $branchConfig[2]['manager'], 'Equipment & Maintenance',
                    $sawCleanTitle, 'Kumasi CleanPro & Cold-Chain Services',
                    'MoMo', ($dateStr === '2024-11-05' ? 4800.00 : 6500.00), $dateStr,
                    'Approved by Baki (Supervisor) & Hajia Rabi Adam HQ',
                    $dateStr . ' 10:00:00'
                ]);
            }
            if ($dateStr === '2026-05-05' || $dateStr === '2026-07-20') {
                // Deep cleaning, sanitization & reopening maintenance voucher for Dechemso incident break
                $expSeq++;
                $cleanTitle = ($dateStr === '2026-05-05')
                    ? 'Dechemso Branch Incident Break — Deep Cleaning, Equipment Sanitizing & Audit'
                    : 'Dechemso Branch Pre-Reopening Deep Cleaning, Fryer Servicing & Restock Prep';
                $insExp->execute([
                    'EXP-' . $expSeq, 3, $branchConfig[3]['manager'], 'Equipment & Maintenance',
                    $cleanTitle, 'Kumasi CleanPro & Cold-Chain Services',
                    'MoMo', ($dateStr === '2026-05-05' ? 4200.00 : 5800.00), $dateStr,
                    'Approved by Kudus (Branch Manager) & Hajia Rabi Adam HQ',
                    $dateStr . ' 10:00:00'
                ]);
            }
            if ($dayOfYear % 3 === 0) {
                $expBranchId = ($dayOfYear % 3) + 1;
                foreach ([1, 2, 3] as $bId) {
                    if ($bId === 2 && $isSawabaClosurePeriod) {
                        continue; // Skip regular bulk branch expenses while Sawaba is closed
                    }
                    if ($bId === 3 && $isDechemsoBreakPeriod) {
                        continue; // Skip regular bulk branch expenses while Dechemso is on closure break
                    }
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

            // Seed periodic "Other" miscellaneous expenses (no supplier/approval fields)
            $dayOfMonth = (int)$currDate->format('j');
            if ($dayOfMonth === 6 || $dayOfMonth === 19 || $dayOfMonth === 28) {
                $otherBranchId = (($month + $dayOfMonth) % 3) + 1;
                if (($otherBranchId === 2 && $isSawabaClosurePeriod) || ($otherBranchId === 3 && $isDechemsoBreakPeriod)) {
                    $otherBranchId = 1;
                }
                $oTpl = $otherExpenseTemplates[($month + $dayOfMonth + $year) % count($otherExpenseTemplates)];
                $expSeq++;
                $scale = ($year === 2026) ? 0.85 : 1.0;
                $oAmt = round(mt_rand($oTpl['min'], $oTpl['max']) * $scale, 2);
                $insExp->execute([
                    'EXP-' . $expSeq, $otherBranchId, $branchConfig[$otherBranchId]['manager'], 'Other',
                    $oTpl['title'], '', $oTpl['method'], $oAmt, $dateStr, '', $dateStr . ' 13:15:00'
                ]);
            }

            // Seed Monthly Finance & Coffers Entries:
            // 1) Market / Goods Withdrawals (~GHS 8,500+ to GHS 10,600 per month split across 2 market trips on day 3 and day 17)
            // 2) Normal Withdrawals (on day 10 and day 25)
            // 3) Periodic Manual Coffer Deposits (on day 14 of quarterly/event months)
            if ($dayOfMonth === 3) {
                // First monthly market & ingredients purchase withdrawal (~GHS 4,600 - 5,600)
                $cofferSeq++;
                $mTpl = $marketWithdrawalTemplates[($month + $year) % count($marketWithdrawalTemplates)];
                $mktAmt1 = (float)mt_rand(4600, 5600);
                $insCoffer->execute([
                    'COF-' . $cofferSeq, 1, 1, 'Withdrawal', 'Withdrawal for Goods / Market', $mTpl['channel'],
                    $mktAmt1, $mTpl['recipient'], $mTpl['desc'] . ' (First Monthly Market Trip)',
                    $dateStr, $dateStr . ' 07:45:00'
                ]);
            } elseif ($dayOfMonth === 17) {
                // Second monthly market & cooking supplies withdrawal (~GHS 4,100 - 5,200) -> Total monthly Market Withdrawal = GHS 8,700 - 10,800+
                $cofferSeq++;
                $mTpl = $marketWithdrawalTemplates[($month + $year + 1) % count($marketWithdrawalTemplates)];
                $mktAmt2 = (float)mt_rand(4100, 5200);
                $branchForMkt = (($month % 3) + 1);
                if (($branchForMkt === 3 && $isDechemsoBreakPeriod) || ($branchForMkt === 2 && $isSawabaClosurePeriod)) {
                    $branchForMkt = 1; // Reroute bulk market run to Ayiduase while branch is closed/on break
                }
                $staffForMkt = $branchConfig[$branchForMkt]['manager'];
                $insCoffer->execute([
                    'COF-' . $cofferSeq, $branchForMkt, $staffForMkt, 'Withdrawal', 'Withdrawal for Goods / Market', $mTpl['channel'],
                    $mktAmt2, $mTpl['recipient'], $mTpl['desc'] . ' (Mid-Month Market & Cooking Supplies Replenishment)',
                    $dateStr, $dateStr . ' 08:20:00'
                ]);
            } elseif ($dayOfMonth === 10) {
                // Normal withdrawal #1 (Staff Welfare / Petty Cash / Utility Reserve)
                $cofferSeq++;
                $nTpl = $normalWithdrawalTemplates[($month) % count($normalWithdrawalTemplates)];
                $normAmt = (float)mt_rand($nTpl['min'], $nTpl['max']);
                $branchForNorm = (($month + 1) % 3) + 1;
                if ($branchForNorm === 3 && $isDechemsoBreakPeriod) {
                    $branchForNorm = 2;
                } elseif ($branchForNorm === 2 && $isSawabaClosurePeriod) {
                    $branchForNorm = 1;
                }
                $insCoffer->execute([
                    'COF-' . $cofferSeq, $branchForNorm, 1, 'Withdrawal', 'Normal Withdrawal', $nTpl['channel'],
                    $normAmt, $nTpl['recipient'], $nTpl['desc'],
                    $dateStr, $dateStr . ' 14:10:00'
                ]);
            } elseif ($dayOfMonth === 26) {
                // Normal withdrawal #2 (Owner Drawing / Bank Reserve Sweep)
                $cofferSeq++;
                $nTpl = $normalWithdrawalTemplates[0];
                $normAmt2 = (float)mt_rand(3800, 6200);
                $insCoffer->execute([
                    'COF-' . $cofferSeq, 1, 1, 'Withdrawal', 'Normal Withdrawal', 'Bank Transfer',
                    $normAmt2, $nTpl['recipient'], $nTpl['desc'],
                    $dateStr, $dateStr . ' 16:40:00'
                ]);
            } elseif ($dayOfMonth === 14 && ($month % 2 === 0)) {
                // Periodic Manual Coffer Deposit (Event catering advance / owner float injection)
                $cofferSeq++;
                $depAmt = (float)mt_rand(6000, 15000);
                $insCoffer->execute([
                    'COF-' . $cofferSeq, 1, 1, 'Deposit', 'Manual Coffer Deposit', 'MoMo',
                    $depAmt, 'Hajia Rabi Adam / VIP Event Catering Advance',
                    'Manual coffer deposit from wedding/graduation dessert table catering & vault top-up',
                    $dateStr, $dateStr . ' 11:15:00'
                ]);
            }

            $currDate = $currDate->modify('+1 day');
        }

        // Also insert 4 live active KDS orders across Ayiduase, Sawaba & Dechemso so Kitchen Display & Floor Tables have live tickets
        $liveOrders = [
            [
                'num' => 'SD-LIVE01', 'branch' => 1, 'table' => 1, 'staff' => 3, // Yasmine (Ayiduase)
                'cust' => 'Table AYI-T1 (KNUST Architecture Postgrads)', 'guests' => 3,
                'type' => 'Dine-In', 'status' => 'Preparing', 'pay_status' => 'Unpaid', 'method' => 'MoMo',
                'notes' => 'Top Sellers: Crazy Biscoff + Loaded Fries 150 & 180',
                'items' => [
                    [26, 2, 'Extra Biscoff Biscuit', 'Cooking'],   // Crazy Biscoff (150 GHc)
                    [65, 2, 'Spicy Pepper Sauce', 'Cooking'],      // Loaded Fries (150 GHc)
                    [66, 1, 'Extra Cheese (+GHS 20)', 'Plated']    // Loaded Fries (180 GHc)
                ]
            ],
            [
                'num' => 'SD-LIVE02', 'branch' => 1, 'table' => 6, 'staff' => 5, // Latif (Ayiduase)
                'cust' => "VIP-1 (Hajia's Special Guests - Birthday)", 'guests' => 6,
                'type' => 'Dine-In', 'status' => 'Ready', 'pay_status' => 'Paid', 'method' => 'Cash',
                'notes' => 'VIP Birthday Table — SD Special, Crazy Oreo & Matilda Cake',
                'items' => [
                    [28, 2, 'Extra Fresh Strawberries (+GHS 15)', 'Plated'], // SD Special (150 GHc)
                    [22, 3, '', 'Plated'],                                   // Crazy Oreo (150 GHc)
                    [36, 3, 'Add Vanilla Scoop (+GHS 15)', 'Plated']         // Matilda Cake (70 GHc)
                ]
            ],
            [
                'num' => 'SD-LIVE03', 'branch' => 2, 'table' => 9, 'staff' => 10, // Aisha (Sawaba)
                'cust' => 'Table SAW-T1 (Sawaba Evening Family)', 'guests' => 4,
                'type' => 'Dine-In', 'status' => 'Preparing', 'pay_status' => 'Paid', 'method' => 'MoMo',
                'notes' => 'Less ice in Mocktails, Chocoloco Crazy Shake & Loaded Fries 150',
                'items' => [
                    [24, 2, 'Extra Fudge Drizzle', 'Cooking'],     // Chocoloco (150 GHc)
                    [65, 2, 'Spicy Pepper Sauce', 'Cooking'],      // Loaded Fries (150 GHc)
                    [58, 3, 'Less Ice', 'Plated']                  // Taste The Music Mocktail (60 GHc)
                ]
            ],
            [
                'num' => 'SD-LIVE04', 'branch' => 3, 'table' => 13, 'staff' => 15, // Daniel (Dechemso)
                'cust' => 'Table DEC-T1 (Dichemso Evening Lounge)', 'guests' => 4,
                'type' => 'Dine-In', 'status' => 'Preparing', 'pay_status' => 'Paid', 'method' => 'Cash',
                'notes' => 'Served by Daniel / Cashier Matilda — Red Velvet Madness & Loaded Fries 180',
                'items' => [
                    [23, 2, 'Extra Whipped Cream', 'Cooking'],     // Red Velvet Madness (150 GHc)
                    [66, 2, 'Extra Chicken (+GHS 30)', 'Cooking'], // Loaded Fries (180 GHc)
                    [69, 2, 'Extra Mozzarella (+GHS 15)', 'Plated'] // Cheesy Crepe (90 GHc)
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
            $tax = 0.00;
            $svc = 0.00;
            $tot = round($sub, 2);

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
