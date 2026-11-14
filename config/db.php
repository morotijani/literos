<?php
/**
 * LITEROS — Database & Auto-Bootstrap Configuration
 * Supports XAMPP MySQL (127.0.0.1:3306) with automatic database & schema provisioning.
 * Also includes a seamless SQLite PDO fallback if MySQL service happens to be stopped.
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
            // 1. Connect to MySQL Server
            $dsnServer = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsnServer, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 2,
            ]);

            // 2. Ensure `literos` database exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            // 3. Check if tables exist; if not, run schema.sql automatically
            $stmt = $pdo->query("SHOW TABLES LIKE 'company_profile'");
            if ($stmt->rowCount() === 0) {
                self::seedMysqlSchema($pdo);
            }

            self::$driver = 'mysql';
            self::$pdo = $pdo;
            return self::$pdo;
        } catch (Throwable $e) {
            // Fallback to local SQLite file if MySQL daemon is not running or blocked
            return self::getSqliteFallback();
        }
    }

    public static function getDriver(): string {
        if (self::$pdo === null) {
            self::getConnection();
        }
        return self::$driver;
    }

    private static function seedMysqlSchema(PDO $pdo): void {
        $schemaPath = __DIR__ . '/../database/schema.sql';
        if (!file_exists($schemaPath)) {
            return;
        }
        $sql = file_get_contents($schemaPath);
        if ($sql !== false) {
            $pdo->exec($sql);
        }
    }

    private static function getSqliteFallback(): PDO {
        $sqlitePath = __DIR__ . '/../database/literos_local.sqlite';
        $isNew = !file_exists($sqlitePath);

        $pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");

        self::$driver = 'sqlite';
        self::$pdo = $pdo;

        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='company_profile'")->fetch();
        if (!$check) {
            self::seedSqliteSchema($pdo);
        }

        return self::$pdo;
    }

    public static function resetAndSeed(): void {
        $pdo = self::getConnection();
        if (self::$driver === 'mysql') {
            self::seedMysqlSchema($pdo);
        } else {
            self::seedSqliteSchema($pdo);
        }
    }

    private static function seedSqliteSchema(PDO $pdo): void {
        $pdo->exec("
            DROP TABLE IF EXISTS order_items;
            DROP TABLE IF EXISTS orders;
            DROP TABLE IF EXISTS shifts;
            DROP TABLE IF EXISTS restaurant_tables;
            DROP TABLE IF EXISTS menu_items;
            DROP TABLE IF EXISTS categories;
            DROP TABLE IF EXISTS staff;
            DROP TABLE IF EXISTS branches;
            DROP TABLE IF EXISTS company_profile;

            CREATE TABLE company_profile (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                tagline TEXT DEFAULT 'Culinary Craft & Hospitality OS',
                logo_text TEXT DEFAULT 'LR',
                currency_symbol TEXT DEFAULT '$',
                currency_code TEXT DEFAULT 'USD',
                tax_rate REAL DEFAULT 8.50,
                service_charge_rate REAL DEFAULT 5.00,
                email TEXT DEFAULT 'hq@lumina-hospitality.com',
                phone TEXT DEFAULT '+1 (555) 234-8900',
                address TEXT DEFAULT '742 Evergreen Culinary Plaza, Suite 400, New York, NY',
                receipt_footer TEXT DEFAULT 'Thank you for dining with us! Powered by Literos POS.',
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
                hourly_rate REAL DEFAULT 18.50,
                commission_rate REAL DEFAULT 2.00,
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
                course_type TEXT DEFAULT 'Main',
                prep_time_mins INTEGER DEFAULT 12,
                calories INTEGER DEFAULT 450,
                stock_qty INTEGER DEFAULT 100,
                low_stock_threshold INTEGER DEFAULT 15,
                modifiers_json TEXT DEFAULT NULL,
                image_emoji TEXT DEFAULT '🍽️',
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
                opening_cash REAL NOT NULL DEFAULT 250.00,
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
                status TEXT NOT NULL DEFAULT 'Preparing',
                payment_status TEXT NOT NULL DEFAULT 'Paid',
                payment_method TEXT DEFAULT 'Card',
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
                kitchen_status TEXT DEFAULT 'Cooking'
            );
        ");

        // Extract INSERT statements from schema.sql and adapt DATE_SUB for SQLite
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
                        // Replace MySQL DATE_SUB(NOW(), INTERVAL X MINUTE) with SQLite datetime('now', '-X minutes')
                        $stmtSql = preg_replace(
                            '/DATE_SUB\(NOW\(\),\s*INTERVAL\s+(\d+)\s+MINUTE\)/i',
                            "datetime('now', '-$1 minutes')",
                            $buffer
                        );
                        $pdo->exec($stmtSql);
                        $buffer = '';
                        $capturing = false;
                    }
                }
            }
        }
    }
}
