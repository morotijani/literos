<?php
/**
 * LITEROS — Unified Backend JSON API Router
 * Handles POS Checkout, KDS Tickets, Floor Tables, Menu, Staff, Branches, Company Profile & Analytics
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $pdo = Database::getConnection();
    $driver = Database::getDriver();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';
$rawInput = file_get_contents('php://input');
$payload = $rawInput ? (json_decode($rawInput, true) ?? []) : $_POST;

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $status < 400], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($action) {
        // =====================================================================
        // 1. BOOTSTRAP: Load entire Restaurant OS state in a single fast call
        // =====================================================================
        case 'bootstrap':
            $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

            $company = $pdo->query("SELECT * FROM company_profile LIMIT 1")->fetch() ?: [];
            $branches = $pdo->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();

            if ($branchId <= 0 && !empty($branches)) {
                $branchId = (int)$branches[0]['id'];
            }

            $categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, id ASC")->fetchAll();
            $menuItems = $pdo->query("
                SELECT m.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
                FROM menu_items m
                LEFT JOIN categories c ON c.id = m.category_id
                ORDER BY m.is_popular DESC, m.name ASC
            ")->fetchAll();

            foreach ($menuItems as &$item) {
                $item['modifiers'] = !empty($item['modifiers_json'])
                    ? (json_decode((string)$item['modifiers_json'], true) ?: [])
                    : [];
            }
            unset($item);

            $staff = $pdo->query("
                SELECT s.*, b.name AS branch_name, b.code AS branch_code,
                    (SELECT COUNT(*) FROM orders o WHERE o.staff_id = s.id AND o.status != 'Cancelled') AS total_orders,
                    (SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o WHERE o.staff_id = s.id AND o.status != 'Cancelled') AS total_sales,
                    (SELECT COALESCE(SUM(o.tip_amount), 0) FROM orders o WHERE o.staff_id = s.id AND o.status != 'Cancelled') AS total_tips
                FROM staff s
                LEFT JOIN branches b ON b.id = s.branch_id
                ORDER BY s.branch_id ASC, s.id ASC
            ")->fetchAll();

            $tablesStmt = $pdo->prepare("
                SELECT t.*, s.full_name AS staff_name, s.avatar_color AS staff_color
                FROM restaurant_tables t
                LEFT JOIN staff s ON s.id = t.assigned_staff_id
                WHERE t.branch_id = ?
                ORDER BY t.id ASC
            ");
            $tablesStmt->execute([$branchId]);
            $tables = $tablesStmt->fetchAll();

            // Attach active unpaid/in-progress order to each table if any
            foreach ($tables as &$tbl) {
                $activeOrdStmt = $pdo->prepare("
                    SELECT id, order_number, total_amount, status, payment_status, created_at, customer_name
                    FROM orders
                    WHERE table_id = ? AND status NOT IN ('Completed', 'Cancelled')
                    ORDER BY id DESC LIMIT 1
                ");
                $activeOrdStmt->execute([$tbl['id']]);
                $tbl['active_order'] = $activeOrdStmt->fetch() ?: null;
            }
            unset($tbl);

            // Recent orders (for active branch or all if requested)
            $ordersStmt = $pdo->prepare("
                SELECT o.*, b.name AS branch_name, b.code AS branch_code,
                       s.full_name AS staff_name, s.role AS staff_role, s.avatar_color AS staff_color,
                       t.table_number, t.zone AS table_zone
                FROM orders o
                LEFT JOIN branches b ON b.id = o.branch_id
                LEFT JOIN staff s ON s.id = o.staff_id
                LEFT JOIN restaurant_tables t ON t.id = o.table_id
                WHERE (? = 0 OR o.branch_id = ?)
                ORDER BY o.id DESC
                LIMIT 60
            ");
            $ordersStmt->execute([$branchId, $branchId]);
            $orders = $ordersStmt->fetchAll();

            // Fetch order items for all loaded orders
            if (!empty($orders)) {
                $orderIds = array_column($orders, 'id');
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ({$placeholders}) ORDER BY id ASC");
                $itemsStmt->execute($orderIds);
                $allItems = $itemsStmt->fetchAll();

                $groupedItems = [];
                foreach ($allItems as $oi) {
                    $groupedItems[$oi['order_id']][] = $oi;
                }
                foreach ($orders as &$ord) {
                    $ord['items'] = $groupedItems[$ord['id']] ?? [];
                }
                unset($ord);
            }

            // Shifts
            $shiftsStmt = $pdo->prepare("
                SELECT sh.*, s.full_name AS staff_name, b.name AS branch_name
                FROM shifts sh
                LEFT JOIN staff s ON s.id = sh.staff_id
                LEFT JOIN branches b ON b.id = sh.branch_id
                WHERE (? = 0 OR sh.branch_id = ?)
                ORDER BY sh.id DESC LIMIT 15
            ");
            $shiftsStmt->execute([$branchId, $branchId]);
            $shifts = $shiftsStmt->fetchAll();

            // Branch-level summary metrics
            $branchMetrics = $pdo->query("
                SELECT b.id, b.name, b.code,
                    (SELECT COUNT(*) FROM orders o WHERE o.branch_id = b.id AND o.status != 'Cancelled') AS order_count,
                    (SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o WHERE o.branch_id = b.id AND o.status != 'Cancelled') AS revenue,
                    (SELECT COUNT(*) FROM staff s WHERE s.branch_id = b.id AND s.is_active = 1) AS active_staff,
                    (SELECT COUNT(*) FROM restaurant_tables rt WHERE rt.branch_id = b.id) AS total_tables,
                    (SELECT COUNT(*) FROM restaurant_tables rt WHERE rt.branch_id = b.id AND rt.status = 'Occupied') AS occupied_tables
                FROM branches b
                ORDER BY b.id ASC
            ")->fetchAll();

            respond([
                'driver' => $driver,
                'active_branch_id' => $branchId,
                'company' => $company,
                'branches' => $branches,
                'branch_metrics' => $branchMetrics,
                'categories' => $categories,
                'menu_items' => $menuItems,
                'staff' => $staff,
                'tables' => $tables,
                'orders' => $orders,
                'shifts' => $shifts,
            ]);
            break;

        // =====================================================================
        // 2. POS: CREATE ORDER / CHECKOUT
        // =====================================================================
        case 'create_order':
            $branchId = (int)($payload['branch_id'] ?? 1);
            $tableId = !empty($payload['table_id']) ? (int)$payload['table_id'] : null;
            $staffId = (int)($payload['staff_id'] ?? 1);
            $customerName = trim((string)($payload['customer_name'] ?? 'Walk-in Guest')) ?: 'Walk-in Guest';
            $guestsCount = max(1, (int)($payload['guests_count'] ?? 1));
            $orderType = in_array($payload['order_type'] ?? '', ['Dine-In', 'Takeaway', 'Delivery'], true)
                ? $payload['order_type']
                : 'Dine-In';
            $paymentStatus = in_array($payload['payment_status'] ?? '', ['Paid', 'Unpaid'], true)
                ? $payload['payment_status']
                : 'Paid';
            $paymentMethod = in_array($payload['payment_method'] ?? '', ['Cash', 'Card', 'QR Pay', 'Split'], true)
                ? $payload['payment_method']
                : 'Card';
            $discountAmount = max(0, (float)($payload['discount_amount'] ?? 0));
            $tipAmount = max(0, (float)($payload['tip_amount'] ?? 0));
            $notes = trim((string)($payload['notes'] ?? ''));
            $items = $payload['items'] ?? [];

            if (empty($items) || !is_array($items)) {
                respond(['error' => 'Cannot create an empty order. Please add menu items first.'], 422);
            }

            // Get company & branch tax/service rates
            $company = $pdo->query("SELECT * FROM company_profile LIMIT 1")->fetch();
            $branchStmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
            $branchStmt->execute([$branchId]);
            $branch = $branchStmt->fetch();

            $taxRate = ($branch && $branch['tax_override'] !== null && $branch['tax_override'] !== '')
                ? (float)$branch['tax_override']
                : (float)($company['tax_rate'] ?? 8.25);
            $serviceRate = ($orderType === 'Dine-In')
                ? (float)($company['service_charge_rate'] ?? 5.00)
                : 0.00;

            $subtotal = 0.0;
            $validatedItems = [];

            foreach ($items as $cartItem) {
                $menuId = (int)($cartItem['menu_item_id'] ?? 0);
                $qty = max(1, (int)($cartItem['quantity'] ?? 1));
                $mods = trim((string)($cartItem['modifiers'] ?? ''));
                $extraPrice = (float)($cartItem['extra_price'] ?? 0.0);

                $mStmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
                $mStmt->execute([$menuId]);
                $mRow = $mStmt->fetch();
                if (!$mRow) {
                    continue;
                }

                $unitPrice = round((float)$mRow['price'] + $extraPrice, 2);
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $validatedItems[] = [
                    'menu_item_id' => $menuId,
                    'item_name' => $mRow['name'],
                    'unit_price' => $unitPrice,
                    'unit_cost' => (float)$mRow['cost'],
                    'quantity' => $qty,
                    'modifiers' => $mods,
                    'line_total' => $lineTotal,
                ];
            }

            if (empty($validatedItems)) {
                respond(['error' => 'No valid menu items found in cart.'], 422);
            }

            $taxableAmount = max(0, $subtotal - $discountAmount);
            $taxAmount = round($taxableAmount * ($taxRate / 100), 2);
            $serviceCharge = round($taxableAmount * ($serviceRate / 100), 2);
            $totalAmount = round($taxableAmount + $taxAmount + $serviceCharge + $tipAmount, 2);
            $amountTendered = max($totalAmount, (float)($payload['amount_tendered'] ?? $totalAmount));

            $pdo->beginTransaction();

            // Generate next order number
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id), 1000) FROM orders")->fetchColumn();
            $orderNumber = 'ORD-' . (1001 + $maxId);

            $insOrder = $pdo->prepare("
                INSERT INTO orders (
                    order_number, branch_id, table_id, staff_id, customer_name, guests_count,
                    order_type, status, payment_status, payment_method,
                    subtotal, tax_amount, service_charge, discount_amount, tip_amount, total_amount,
                    amount_tendered, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Preparing', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insOrder->execute([
                $orderNumber, $branchId, $tableId, $staffId, $customerName, $guestsCount,
                $orderType, $paymentStatus, $paymentMethod,
                $subtotal, $taxAmount, $serviceCharge, $discountAmount, $tipAmount, $totalAmount,
                $amountTendered, $notes
            ]);

            $orderId = (int)$pdo->lastInsertId();

            $insItem = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, menu_item_id, item_name, unit_price, unit_cost, quantity, modifiers, line_total, kitchen_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Cooking')
            ");
            $decStock = $pdo->prepare("
                UPDATE menu_items SET stock_qty = CASE WHEN stock_qty >= ? THEN stock_qty - ? ELSE 0 END WHERE id = ?
            ");

            foreach ($validatedItems as $vi) {
                $insItem->execute([
                    $orderId,
                    $vi['menu_item_id'],
                    $vi['item_name'],
                    $vi['unit_price'],
                    $vi['unit_cost'],
                    $vi['quantity'],
                    $vi['modifiers'],
                    $vi['line_total']
                ]);
                $decStock->execute([$vi['quantity'], $vi['quantity'], $vi['menu_item_id']]);
            }

            // If Dine-In table selected, mark table Occupied
            if ($tableId) {
                $updTable = $pdo->prepare("
                    UPDATE restaurant_tables
                    SET status = 'Occupied', current_guests = ?, assigned_staff_id = ?
                    WHERE id = ?
                ");
                $updTable->execute([$guestsCount, $staffId, $tableId]);
            }

            $pdo->commit();

            respond([
                'message' => "Order {$orderNumber} fired to Kitchen Display System!",
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount
            ]);
            break;

        // =====================================================================
        // 3. KITCHEN DISPLAY (KDS) & ORDER STATUS UPDATES
        // =====================================================================
        case 'update_order_status':
            $orderId = (int)($payload['order_id'] ?? 0);
            $newStatus = (string)($payload['status'] ?? '');
            $validStatuses = ['Pending', 'Preparing', 'Ready', 'Served', 'Completed', 'Cancelled'];

            if ($orderId <= 0 || !in_array($newStatus, $validStatuses, true)) {
                respond(['error' => 'Invalid order ID or status'], 422);
            }

            $completedAt = ($newStatus === 'Completed') ? date('Y-m-d H:i:s') : null;
            $paymentStatusSql = ($newStatus === 'Completed') ? ", payment_status = 'Paid'" : "";

            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = ?, completed_at = COALESCE(?, completed_at) {$paymentStatusSql}
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $completedAt, $orderId]);

            if (in_array($newStatus, ['Ready', 'Served', 'Completed'], true)) {
                $pdo->prepare("UPDATE order_items SET kitchen_status = 'Plated' WHERE order_id = ?")
                    ->execute([$orderId]);
            }

            // If order is Completed or Cancelled and linked to a table, free or mark table for cleaning
            if (in_array($newStatus, ['Completed', 'Cancelled'], true)) {
                $ordRow = $pdo->prepare("SELECT table_id FROM orders WHERE id = ?");
                $ordRow->execute([$orderId]);
                $tId = $ordRow->fetchColumn();
                if ($tId) {
                    $pdo->prepare("UPDATE restaurant_tables SET status = 'Available', current_guests = 0 WHERE id = ?")
                        ->execute([$tId]);
                }
            }

            respond(['message' => "Order status updated to {$newStatus}"]);
            break;

        case 'settle_order_payment':
            $orderId = (int)($payload['order_id'] ?? 0);
            $paymentMethod = (string)($payload['payment_method'] ?? 'Card');
            $tipAmount = max(0, (float)($payload['tip_amount'] ?? 0));

            $ordStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $ordStmt->execute([$orderId]);
            $ord = $ordStmt->fetch();
            if (!$ord) {
                respond(['error' => 'Order not found'], 404);
            }

            $baseWithoutTip = (float)$ord['subtotal'] - (float)$ord['discount_amount'] + (float)$ord['tax_amount'] + (float)$ord['service_charge'];
            $newTotal = round($baseWithoutTip + $tipAmount, 2);

            $upd = $pdo->prepare("
                UPDATE orders
                SET payment_status = 'Paid', payment_method = ?, tip_amount = ?, total_amount = ?, amount_tendered = ?
                WHERE id = ?
            ");
            $upd->execute([$paymentMethod, $tipAmount, $newTotal, $newTotal, $orderId]);

            respond(['message' => "Order {$ord['order_number']} payment settled ({$paymentMethod})"]);
            break;

        // =====================================================================
        // 4. FLOOR PLAN & TABLE MANAGEMENT
        // =====================================================================
        case 'update_table_status':
            $tableId = (int)($payload['table_id'] ?? 0);
            $status = (string)($payload['status'] ?? 'Available');
            $guests = max(0, (int)($payload['current_guests'] ?? 0));
            $staffId = !empty($payload['assigned_staff_id']) ? (int)$payload['assigned_staff_id'] : null;

            if ($status === 'Available' || $status === 'Needs Cleaning') {
                $guests = 0;
            }

            $stmt = $pdo->prepare("
                UPDATE restaurant_tables
                SET status = ?, current_guests = ?, assigned_staff_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $guests, $staffId, $tableId]);

            respond(['message' => 'Table status updated']);
            break;

        case 'save_table':
            $id = (int)($payload['id'] ?? 0);
            $branchId = (int)($payload['branch_id'] ?? 1);
            $tableNumber = strtoupper(trim((string)($payload['table_number'] ?? '')));
            $zone = (string)($payload['zone'] ?? 'Main Hall');
            $capacity = max(1, (int)($payload['capacity'] ?? 4));

            if ($tableNumber === '') {
                respond(['error' => 'Table number is required'], 422);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE restaurant_tables SET branch_id = ?, table_number = ?, zone = ?, capacity = ? WHERE id = ?");
                $stmt->execute([$branchId, $tableNumber, $zone, $capacity, $id]);
                respond(['message' => "Table {$tableNumber} updated"]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO restaurant_tables (branch_id, table_number, zone, capacity, status) VALUES (?, ?, ?, ?, 'Available')");
                $stmt->execute([$branchId, $tableNumber, $zone, $capacity]);
                respond(['message' => "Table {$tableNumber} added to floor plan"]);
            }
            break;

        // =====================================================================
        // 5. MENU & CATEGORY STUDIO
        // =====================================================================
        case 'save_menu_item':
            $id = (int)($payload['id'] ?? 0);
            $categoryId = (int)($payload['category_id'] ?? 1);
            $sku = strtoupper(trim((string)($payload['sku'] ?? '')));
            $name = trim((string)($payload['name'] ?? ''));
            $description = trim((string)($payload['description'] ?? ''));
            $price = max(0, (float)($payload['price'] ?? 0));
            $cost = max(0, (float)($payload['cost'] ?? 0));
            $courseType = (string)($payload['course_type'] ?? 'Main');
            $prepTime = max(1, (int)($payload['prep_time_mins'] ?? 10));
            $stockQty = max(0, (int)($payload['stock_qty'] ?? 50));
            $emoji = trim((string)($payload['image_emoji'] ?? '🍽️')) ?: '🍽️';
            $isPopular = !empty($payload['is_popular']) ? 1 : 0;
            $isAvailable = isset($payload['is_available']) ? ((int)$payload['is_available'] ? 1 : 0) : 1;

            $modsRaw = $payload['modifiers'] ?? '';
            if (is_array($modsRaw)) {
                $modsJson = json_encode(array_values(array_filter(array_map('trim', $modsRaw))));
            } else {
                $parts = array_values(array_filter(array_map('trim', explode(',', (string)$modsRaw))));
                $modsJson = json_encode($parts);
            }

            if ($name === '' || $price <= 0) {
                respond(['error' => 'Dish name and a positive price are required.'], 422);
            }
            if ($sku === '') {
                $sku = 'MNU-' . rand(100, 999);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE menu_items
                    SET category_id = ?, sku = ?, name = ?, description = ?, price = ?, cost = ?,
                        course_type = ?, prep_time_mins = ?, stock_qty = ?, modifiers_json = ?,
                        image_emoji = ?, is_popular = ?, is_available = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $categoryId, $sku, $name, $description, $price, $cost,
                    $courseType, $prepTime, $stockQty, $modsJson,
                    $emoji, $isPopular, $isAvailable, $id
                ]);
                respond(['message' => "Menu item '{$name}' updated"]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO menu_items (
                        category_id, sku, name, description, price, cost,
                        course_type, prep_time_mins, stock_qty, modifiers_json,
                        image_emoji, is_popular, is_available
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $categoryId, $sku, $name, $description, $price, $cost,
                    $courseType, $prepTime, $stockQty, $modsJson,
                    $emoji, $isPopular, $isAvailable
                ]);
                respond(['message' => "New dish '{$name}' added to menu"]);
            }
            break;

        case 'toggle_menu_availability':
            $id = (int)($payload['id'] ?? 0);
            $pdo->prepare("UPDATE menu_items SET is_available = CASE WHEN is_available = 1 THEN 0 ELSE 1 END WHERE id = ?")
                ->execute([$id]);
            respond(['message' => 'Item availability toggled']);
            break;

        case 'restock_menu_item':
            $id = (int)($payload['id'] ?? 0);
            $addQty = max(1, (int)($payload['add_qty'] ?? 25));
            $pdo->prepare("UPDATE menu_items SET stock_qty = stock_qty + ?, is_available = 1 WHERE id = ?")
                ->execute([$addQty, $id]);
            respond(['message' => "Added +{$addQty} portions to inventory"]);
            break;

        case 'delete_menu_item':
            $id = (int)($payload['id'] ?? 0);
            $pdo->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$id]);
            respond(['message' => 'Menu item deleted']);
            break;

        case 'save_category':
            $name = trim((string)($payload['name'] ?? ''));
            $icon = trim((string)($payload['icon'] ?? 'fa-utensils'));
            $color = trim((string)($payload['color'] ?? '#f59e0b'));
            if ($name === '') {
                respond(['error' => 'Category name is required'], 422);
            }
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, color, sort_order) VALUES (?, ?, ?, ?, 10)");
            $stmt->execute([$name, $slug, $icon, $color]);
            respond(['message' => "Category '{$name}' created"]);
            break;

        // =====================================================================
        // 6. SALES PERSONNEL / STAFF MANAGEMENT & PIN VERIFICATION
        // =====================================================================
        case 'save_staff':
            $id = (int)($payload['id'] ?? 0);
            $branchId = (int)($payload['branch_id'] ?? 1);
            $empCode = strtoupper(trim((string)($payload['employee_code'] ?? '')));
            $fullName = trim((string)($payload['full_name'] ?? ''));
            $role = in_array($payload['role'] ?? '', ['Admin', 'Manager', 'Cashier', 'Waiter', 'Chef'], true)
                ? $payload['role']
                : 'Waiter';
            $pinCode = trim((string)($payload['pin_code'] ?? '1234'));
            $email = trim((string)($payload['email'] ?? ''));
            $phone = trim((string)($payload['phone'] ?? ''));
            $avatarColor = trim((string)($payload['avatar_color'] ?? '#f59e0b'));
            $hourlyRate = max(0, (float)($payload['hourly_rate'] ?? 18.00));
            $commissionRate = max(0, (float)($payload['commission_rate'] ?? 2.50));
            $isActive = isset($payload['is_active']) ? ((int)$payload['is_active'] ? 1 : 0) : 1;

            if ($fullName === '' || $pinCode === '') {
                respond(['error' => 'Staff full name and POS PIN code are required'], 422);
            }
            if ($empCode === '') {
                $empCode = 'EMP-' . rand(100, 999);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE staff
                    SET branch_id = ?, employee_code = ?, full_name = ?, role = ?, pin_code = ?,
                        email = ?, phone = ?, avatar_color = ?, hourly_rate = ?, commission_rate = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $branchId, $empCode, $fullName, $role, $pinCode,
                    $email, $phone, $avatarColor, $hourlyRate, $commissionRate, $isActive, $id
                ]);
                respond(['message' => "Personnel profile '{$fullName}' updated"]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO staff (
                        branch_id, employee_code, full_name, role, pin_code,
                        email, phone, avatar_color, hourly_rate, commission_rate, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $branchId, $empCode, $fullName, $role, $pinCode,
                    $email, $phone, $avatarColor, $hourlyRate, $commissionRate, $isActive
                ]);
                respond(['message' => "Sales personnel '{$fullName}' onboarded"]);
            }
            break;

        case 'verify_pin':
            $pin = trim((string)($payload['pin_code'] ?? ''));
            $stmt = $pdo->prepare("
                SELECT s.*, b.name AS branch_name
                FROM staff s
                LEFT JOIN branches b ON b.id = s.branch_id
                WHERE s.pin_code = ? AND s.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$pin]);
            $staffMember = $stmt->fetch();

            if (!$staffMember) {
                respond(['error' => 'Invalid POS PIN code. Try 1111 (Elena), 2222 (Julian), or 3333 (Amara).'], 401);
            }
            respond([
                'message' => "Welcome back, {$staffMember['full_name']} ({$staffMember['role']})",
                'staff' => $staffMember
            ]);
            break;

        // =====================================================================
        // 7. BRANCHES & COMPANY PROFILE
        // =====================================================================
        case 'save_branch':
            $id = (int)($payload['id'] ?? 0);
            $code = strtoupper(trim((string)($payload['code'] ?? '')));
            $name = trim((string)($payload['name'] ?? ''));
            $city = trim((string)($payload['city'] ?? ''));
            $address = trim((string)($payload['address'] ?? ''));
            $phone = trim((string)($payload['phone'] ?? ''));
            $managerName = trim((string)($payload['manager_name'] ?? ''));
            $openingHours = trim((string)($payload['opening_hours'] ?? '09:00 AM - 11:00 PM'));
            $taxOverride = ($payload['tax_override'] !== '' && $payload['tax_override'] !== null)
                ? (float)$payload['tax_override']
                : null;
            $isActive = isset($payload['is_active']) ? ((int)$payload['is_active'] ? 1 : 0) : 1;

            if ($name === '' || $city === '') {
                respond(['error' => 'Branch name and city are required'], 422);
            }
            if ($code === '') {
                $code = strtoupper(substr($city, 0, 3)) . '-' . rand(10, 99);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE branches
                    SET code = ?, name = ?, city = ?, address = ?, phone = ?,
                        manager_name = ?, opening_hours = ?, tax_override = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $name, $city, $address, $phone, $managerName, $openingHours, $taxOverride, $isActive, $id]);
                respond(['message' => "Branch '{$name}' updated"]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO branches (code, name, city, address, phone, manager_name, opening_hours, tax_override, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$code, $name, $city, $address, $phone, $managerName, $openingHours, $taxOverride, $isActive]);
                $newBranchId = (int)$pdo->lastInsertId();

                // Seed 4 starter tables for the new branch automatically
                $tIns = $pdo->prepare("INSERT INTO restaurant_tables (branch_id, table_number, zone, capacity, status) VALUES (?, ?, ?, ?, 'Available')");
                $tIns->execute([$newBranchId, 'T-01', 'Main Hall', 2]);
                $tIns->execute([$newBranchId, 'T-02', 'Main Hall', 4]);
                $tIns->execute([$newBranchId, 'T-03', 'Patio Terrace', 4]);
                $tIns->execute([$newBranchId, 'VIP-1', 'VIP Lounge', 8]);

                respond(['message' => "Branch '{$name}' launched with 4 starter floor tables!", 'branch_id' => $newBranchId]);
            }
            break;

        case 'save_company_profile':
            $name = trim((string)($payload['name'] ?? ''));
            $tagline = trim((string)($payload['tagline'] ?? ''));
            $logoText = trim((string)($payload['logo_text'] ?? 'LR'));
            $currencySymbol = trim((string)($payload['currency_symbol'] ?? '$'));
            $currencyCode = trim((string)($payload['currency_code'] ?? 'USD'));
            $taxRate = max(0, (float)($payload['tax_rate'] ?? 8.25));
            $serviceRate = max(0, (float)($payload['service_charge_rate'] ?? 5.00));
            $email = trim((string)($payload['email'] ?? ''));
            $phone = trim((string)($payload['phone'] ?? ''));
            $address = trim((string)($payload['address'] ?? ''));
            $receiptFooter = trim((string)($payload['receipt_footer'] ?? ''));

            if ($name === '') {
                respond(['error' => 'Company name cannot be empty'], 422);
            }

            $stmt = $pdo->prepare("
                UPDATE company_profile
                SET name = ?, tagline = ?, logo_text = ?, currency_symbol = ?, currency_code = ?,
                    tax_rate = ?, service_charge_rate = ?, email = ?, phone = ?, address = ?, receipt_footer = ?
                WHERE id = 1
            ");
            $stmt->execute([
                $name, $tagline, $logoText, $currencySymbol, $currencyCode,
                $taxRate, $serviceRate, $email, $phone, $address, $receiptFooter
            ]);

            respond(['message' => 'Company HQ profile & tax settings saved']);
            break;

        case 'reset_demo_data':
            Database::resetAndSeed();
            respond(['message' => 'Demo database reset to fresh factory state']);
            break;

        default:
            respond(['error' => "Unknown API action: {$action}"], 400);
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['error' => $e->getMessage()], 500);
}
