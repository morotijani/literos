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

/**
 * Resolve the currently acting operator from payload or query string and enforce allowed roles.
 */
function requireRoles(PDO $pdo, array $payload, array $allowedRoles): array {
    $actorId = (int)($payload['actor_staff_id'] ?? $_GET['actor_staff_id'] ?? 0);
    if ($actorId <= 0) {
        respond(['error' => 'Authentication required: please sign in to perform this action.'], 401);
    }
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$actorId]);
    $actor = $stmt->fetch();
    if (!$actor) {
        respond(['error' => 'Active operator session not found. Please sign in again.'], 401);
    }
    if (!in_array($actor['role'], $allowedRoles, true)) {
        $rolesStr = implode(', ', $allowedRoles);
        respond([
            'error' => "Access Denied: Your role ({$actor['role']}) does not have permission for this action. Required role: {$rolesStr}."
        ], 403);
    }
    return $actor;
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

            $categories = $pdo->query("
                SELECT c.*,
                    (SELECT COUNT(*) FROM menu_items m WHERE m.category_id = c.id) AS items_count
                FROM categories c
                ORDER BY c.sort_order ASC, c.id ASC
            ")->fetchAll();
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
            if (isProductionMode()) {
                foreach ($staff as &$stRow) {
                    $stRow['pin_code'] = '••••';
                }
                unset($stRow);
            }

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

            // Recent expenditures (for active branch or all if requested)
            $expStmt = $pdo->prepare("
                SELECT e.*, b.name AS branch_name, b.code AS branch_code,
                       s.full_name AS staff_name, s.role AS staff_role
                FROM expenditures e
                LEFT JOIN branches b ON b.id = e.branch_id
                LEFT JOIN staff s ON s.id = e.staff_id
                WHERE (? = 0 OR e.branch_id = ?)
                ORDER BY e.expense_date DESC, e.id DESC
                LIMIT 150
            ");
            $expStmt->execute([$branchId, $branchId]);
            $expenditures = $expStmt->fetchAll();

            // Expenditure summary for current branch / company
            $expSummaryStmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_count,
                    COALESCE(SUM(amount), 0) AS total_amount,
                    COALESCE(SUM(CASE WHEN payment_method = 'MoMo' THEN amount ELSE 0 END), 0) AS momo_amount,
                    COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END), 0) AS cash_amount,
                    COALESCE(SUM(CASE WHEN expense_date >= '2026-01-01' THEN amount ELSE 0 END), 0) AS ytd_2026_amount
                FROM expenditures
                WHERE (? = 0 OR branch_id = ?)
            ");
            $expSummaryStmt->execute([$branchId, $branchId]);
            $expenditureSummary = $expSummaryStmt->fetch() ?: [
                'total_count' => 0,
                'total_amount' => 0,
                'momo_amount' => 0,
                'cash_amount' => 0,
                'ytd_2026_amount' => 0,
            ];

            // Finance & Coffers Ledger (all entries across company + branch metadata)
            $coffers = $pdo->query("
                SELECT c.*, b.name AS branch_name, b.code AS branch_code,
                       s.full_name AS staff_name, s.role AS staff_role
                FROM coffers_ledger c
                LEFT JOIN branches b ON b.id = c.branch_id
                LEFT JOIN staff s ON s.id = c.staff_id
                ORDER BY c.transaction_date DESC, c.id DESC
            ")->fetchAll();

            // Company-wide & Active-Branch Revenue + Coffers Summary
            $revRow = $pdo->query("
                SELECT
                    COUNT(*) AS paid_orders_count,
                    COALESCE(SUM(total_amount), 0) AS total_paid_revenue,
                    COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN total_amount ELSE 0 END), 0) AS cash_revenue,
                    COALESCE(SUM(CASE WHEN payment_method = 'MoMo' THEN total_amount ELSE 0 END), 0) AS momo_revenue,
                    COALESCE(SUM(CASE WHEN payment_method = 'QR Pay' THEN total_amount ELSE 0 END), 0) AS qr_revenue,
                    COALESCE(SUM(CASE WHEN created_at >= '2026-01-01' THEN total_amount ELSE 0 END), 0) AS revenue_2026
                FROM orders
                WHERE status != 'Cancelled' AND payment_status = 'Paid'
            ")->fetch() ?: [];

            $cofAgg = $pdo->query("
                SELECT
                    COALESCE(SUM(CASE WHEN entry_type = 'Deposit' THEN amount ELSE 0 END), 0) AS manual_deposits,
                    COUNT(CASE WHEN entry_type = 'Deposit' THEN 1 END) AS deposits_count,
                    COALESCE(SUM(CASE WHEN entry_type = 'Withdrawal' THEN amount ELSE 0 END), 0) AS total_withdrawals,
                    COUNT(CASE WHEN entry_type = 'Withdrawal' THEN 1 END) AS withdrawals_count,
                    COALESCE(SUM(CASE WHEN entry_type = 'Withdrawal' AND purpose_type = 'Withdrawal for Goods / Market' THEN amount ELSE 0 END), 0) AS goods_market_withdrawals,
                    COUNT(CASE WHEN entry_type = 'Withdrawal' AND purpose_type = 'Withdrawal for Goods / Market' THEN 1 END) AS goods_market_count,
                    COALESCE(SUM(CASE WHEN entry_type = 'Withdrawal' AND purpose_type != 'Withdrawal for Goods / Market' THEN amount ELSE 0 END), 0) AS normal_withdrawals,
                    COUNT(CASE WHEN entry_type = 'Withdrawal' AND purpose_type != 'Withdrawal for Goods / Market' THEN 1 END) AS normal_withdrawals_count
                FROM coffers_ledger
            ")->fetch() ?: [];

            $totalAllExpenditures = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenditures")->fetchColumn();
            $totalPaidRev = (float)($revRow['total_paid_revenue'] ?? 0);
            $manualDeps = (float)($cofAgg['manual_deposits'] ?? 0);
            $totWithdrawals = (float)($cofAgg['total_withdrawals'] ?? 0);
            $goodsWithdrawals = (float)($cofAgg['goods_market_withdrawals'] ?? 0);
            $normalWithdrawals = (float)($cofAgg['normal_withdrawals'] ?? 0);

            $coffersSummary = [
                'paid_orders_count'        => (int)($revRow['paid_orders_count'] ?? 0),
                'total_paid_revenue'       => round($totalPaidRev, 2),
                'cash_revenue'             => round((float)($revRow['cash_revenue'] ?? 0), 2),
                'momo_revenue'             => round((float)($revRow['momo_revenue'] ?? 0), 2),
                'qr_revenue'               => round((float)($revRow['qr_revenue'] ?? 0), 2),
                'revenue_2026'             => round((float)($revRow['revenue_2026'] ?? 0), 2),
                'manual_deposits'          => round($manualDeps, 2),
                'deposits_count'           => (int)($cofAgg['deposits_count'] ?? 0),
                'total_withdrawals'        => round($totWithdrawals, 2),
                'withdrawals_count'        => (int)($cofAgg['withdrawals_count'] ?? 0),
                'goods_market_withdrawals' => round($goodsWithdrawals, 2),
                'goods_market_count'       => (int)($cofAgg['goods_market_count'] ?? 0),
                'avg_monthly_goods_withdrawal' => round($goodsWithdrawals / 35.0, 2),
                'normal_withdrawals'       => round($normalWithdrawals, 2),
                'normal_withdrawals_count' => (int)($cofAgg['normal_withdrawals_count'] ?? 0),
                'total_expenditures'       => round($totalAllExpenditures, 2),
                'gross_coffers_inflow'     => round($totalPaidRev + $manualDeps, 2),
                'net_coffers_balance'      => round(($totalPaidRev + $manualDeps) - $totWithdrawals - $totalAllExpenditures, 2),
            ];

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
                    (SELECT COALESCE(SUM(e.amount), 0) FROM expenditures e WHERE e.branch_id = b.id) AS total_expenditures,
                    (SELECT COUNT(*) FROM staff s WHERE s.branch_id = b.id AND s.is_active = 1) AS active_staff,
                    (SELECT COUNT(*) FROM restaurant_tables rt WHERE rt.branch_id = b.id) AS total_tables,
                    (SELECT COUNT(*) FROM restaurant_tables rt WHERE rt.branch_id = b.id AND rt.status = 'Occupied') AS occupied_tables
                FROM branches b
                ORDER BY b.id ASC
            ")->fetchAll();

            respond([
                'app_env' => getAppEnv(),
                'is_production' => isProductionMode(),
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
                'expenditures' => $expenditures,
                'expenditure_summary' => $expenditureSummary,
                'coffers' => $coffers,
                'coffers_summary' => $coffersSummary,
                'shifts' => $shifts,
            ]);
            break;

        // =====================================================================
        // 2. POS: CREATE ORDER / CHECKOUT
        // =====================================================================
        case 'create_order':
            $actor = requireRoles($pdo, $payload, ['Admin', 'Manager', 'Cashier', 'Waiter']);
            $branchId = (int)($payload['branch_id'] ?? $actor['branch_id'] ?? 1);
            $tableId = !empty($payload['table_id']) ? (int)$payload['table_id'] : null;
            $staffId = (int)$actor['id'];
            $customerName = trim((string)($payload['customer_name'] ?? 'Walk-in Guest')) ?: 'Walk-in Guest';
            $guestsCount = max(1, (int)($payload['guests_count'] ?? 1));
            $orderType = in_array($payload['order_type'] ?? '', ['Dine-In', 'Takeaway', 'Delivery'], true)
                ? $payload['order_type']
                : 'Dine-In';
            $paymentStatus = in_array($payload['payment_status'] ?? '', ['Paid', 'Unpaid'], true)
                ? $payload['payment_status']
                : 'Paid';

            // Waiter role can only fire unpaid orders to Kitchen; Cashier/Manager/Admin settle payments
            if ($actor['role'] === 'Waiter' && $paymentStatus === 'Paid') {
                respond(['error' => 'Role Restriction: Waiters can send tickets to the Kitchen, while Cashiers or Managers settle payments.'], 403);
            }
            $paymentMethod = in_array($payload['payment_method'] ?? '', ['Cash', 'MoMo', 'QR Pay', 'Card', 'Split'], true)
                ? $payload['payment_method']
                : 'Cash';
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
                : (float)($company['tax_rate'] ?? 0.00);
            $serviceRate = ($orderType === 'Dine-In')
                ? (float)($company['service_charge_rate'] ?? 0.00)
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
            $orderNumber = 'SD-' . (15000 + $maxId);

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
            requireRoles($pdo, $payload, ['Admin', 'Manager', 'Chef', 'Cashier', 'Waiter']);
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
            requireRoles($pdo, $payload, ['Admin', 'Manager', 'Cashier']);
            $orderId = (int)($payload['order_id'] ?? 0);
            $paymentMethod = in_array($payload['payment_method'] ?? '', ['Cash', 'MoMo', 'QR Pay', 'Card', 'Split'], true)
                ? (string)$payload['payment_method']
                : 'MoMo';
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
            requireRoles($pdo, $payload, ['Admin', 'Manager', 'Cashier', 'Waiter']);
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
            requireRoles($pdo, $payload, ['Admin', 'Manager']);
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
        case 'save_coffer_entry':
            $actor = requireRoles($pdo, $payload, ['Admin', 'Manager', 'Cashier']);
            $id = (int)($payload['id'] ?? 0);
            $branchId = (int)($payload['branch_id'] ?? $actor['branch_id'] ?? 1);
            $staffId = (int)$actor['id'];
            $entryType = in_array($payload['entry_type'] ?? '', ['Deposit', 'Withdrawal'], true)
                ? $payload['entry_type']
                : 'Withdrawal';
            $purposeType = trim((string)($payload['purpose_type'] ?? ''));
            if ($entryType === 'Deposit') {
                $purposeType = $purposeType !== '' ? $purposeType : 'Manual Coffer Deposit';
            } else {
                if (!in_array($purposeType, ['Withdrawal for Goods / Market', 'Normal Withdrawal'], true)) {
                    $purposeType = 'Withdrawal for Goods / Market';
                }
            }
            $channel = in_array($payload['payment_channel'] ?? '', ['Cash', 'MoMo', 'Bank Transfer', 'QR Pay'], true)
                ? $payload['payment_channel']
                : 'Cash';
            $amount = round(max(0, (float)($payload['amount'] ?? 0)), 2);
            $recipientOrSource = trim((string)($payload['recipient_or_source'] ?? ''));
            $description = trim((string)($payload['description'] ?? ''));
            $txDate = trim((string)($payload['transaction_date'] ?? date('Y-m-d'))) ?: date('Y-m-d');

            if ($amount <= 0 || $description === '') {
                respond(['error' => 'Please enter a valid positive amount and description.'], 422);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE coffers_ledger
                    SET branch_id = ?, entry_type = ?, purpose_type = ?, payment_channel = ?,
                        amount = ?, recipient_or_source = ?, description = ?, transaction_date = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $branchId, $entryType, $purposeType, $channel,
                    $amount, $recipientOrSource, $description, $txDate, $id
                ]);
                respond(['message' => "Coffers entry updated ({$entryType}: GHS {$amount})"]);
            } else {
                $refNo = 'COF-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 5));
                $stmt = $pdo->prepare("
                    INSERT INTO coffers_ledger (
                        reference_no, branch_id, staff_id, entry_type, purpose_type,
                        payment_channel, amount, recipient_or_source, description, transaction_date, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $refNo, $branchId, $staffId, $entryType, $purposeType,
                    $channel, $amount, $recipientOrSource, $description, $txDate, date('Y-m-d H:i:s')
                ]);
                $label = $entryType === 'Deposit'
                    ? "Added GHS {$amount} to Coffers ({$refNo})"
                    : "Withdrew GHS {$amount} from Coffers — {$purposeType} ({$refNo})";
                respond(['message' => $label]);
            }
            break;

        case 'delete_coffer_entry':
            requireRoles($pdo, $payload, ['Admin', 'Manager']);
            $id = (int)($payload['id'] ?? 0);
            if ($id <= 0) {
                respond(['error' => 'Invalid coffers entry ID.'], 422);
            }
            $pdo->prepare("DELETE FROM coffers_ledger WHERE id = ?")->execute([$id]);
            respond(['message' => 'Coffers ledger entry deleted']);
            break;

        case 'upload_menu_image':
            requireRoles($pdo, $payload, ['Admin', 'Manager']);
            if (empty($_FILES['image']) || !isset($_FILES['image']['tmp_name'])) {
                respond(['error' => 'No image file uploaded.'], 422);
            }
            $file = $_FILES['image'];
            if ((int)$file['error'] !== UPLOAD_ERR_OK) {
                respond(['error' => 'Image upload failed (code ' . (int)$file['error'] . ').'], 422);
            }
            if ((int)$file['size'] > 5 * 1024 * 1024) {
                respond(['error' => 'Image exceeds 5 MB size limit.'], 422);
            }
            $imgInfo = @getimagesize($file['tmp_name']);
            $mime = is_array($imgInfo) ? ($imgInfo['mime'] ?? '') : '';
            $mimeMap = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
            ];
            if (!isset($mimeMap[$mime])) {
                respond(['error' => 'Unsupported image format. Please upload JPG, PNG, WebP, or GIF.'], 422);
            }
            $uploadDir = __DIR__ . '/../assets/uploads/menu';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                respond(['error' => 'Failed to create menu upload directory.'], 500);
            }
            $fileName = 'dish_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $mimeMap[$mime];
            $destPath = $uploadDir . '/' . $fileName;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                respond(['error' => 'Failed to store uploaded image on disk.'], 500);
            }
            respond([
                'image_url' => 'assets/uploads/menu/' . $fileName,
                'message' => 'Dish image uploaded successfully'
            ]);
            break;

        case 'save_menu_item':
            requireRoles($pdo, $payload, ['Admin', 'Manager']);
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
            $imageUrl = trim((string)($payload['image_url'] ?? ''));
            if ($imageUrl !== '' && (str_contains($imageUrl, '..') || !str_starts_with($imageUrl, 'assets/uploads/menu/'))) {
                $imageUrl = '';
            }
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
                        image_emoji = ?, image_url = ?, is_popular = ?, is_available = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $categoryId, $sku, $name, $description, $price, $cost,
                    $courseType, $prepTime, $stockQty, $modsJson,
                    $emoji, $imageUrl, $isPopular, $isAvailable, $id
                ]);
                respond(['message' => "Menu item '{$name}' updated"]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO menu_items (
                        category_id, sku, name, description, price, cost,
                        course_type, prep_time_mins, stock_qty, modifiers_json,
                        image_emoji, image_url, is_popular, is_available
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $categoryId, $sku, $name, $description, $price, $cost,
                    $courseType, $prepTime, $stockQty, $modsJson,
                    $emoji, $imageUrl, $isPopular, $isAvailable
                ]);
                respond(['message' => "New dish '{$name}' added to menu"]);
            }
            break;

        case 'toggle_menu_availability':
            requireRoles($pdo, $payload, ['Admin', 'Manager', 'Chef']);
            $id = (int)($payload['id'] ?? 0);
            $pdo->prepare("UPDATE menu_items SET is_available = CASE WHEN is_available = 1 THEN 0 ELSE 1 END WHERE id = ?")
                ->execute([$id]);
            respond(['message' => 'Item availability toggled']);
            break;

        case 'restock_menu_item':
            requireRoles($pdo, $payload, ['Admin', 'Manager', 'Chef']);
            $id = (int)($payload['id'] ?? 0);
            $addQty = max(1, (int)($payload['add_qty'] ?? 25));
            $pdo->prepare("UPDATE menu_items SET stock_qty = stock_qty + ?, is_available = 1 WHERE id = ?")
                ->execute([$addQty, $id]);
            respond(['message' => "Added +{$addQty} portions to inventory"]);
            break;

        case 'delete_menu_item':
            requireRoles($pdo, $payload, ['Admin', 'Manager']);
            $id = (int)($payload['id'] ?? 0);
            $pdo->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$id]);
            respond(['message' => 'Menu item deleted']);
            break;

        case 'save_category':
            requireRoles($pdo, $payload, ['Admin', 'Manager']);
            $id = (int)($payload['id'] ?? 0);
            $name = trim((string)($payload['name'] ?? ''));
            $slugInput = trim((string)($payload['slug'] ?? ''));
            $icon = trim((string)($payload['icon'] ?? 'fa-utensils')) ?: 'fa-utensils';
            $color = trim((string)($payload['color'] ?? '#f59e0b')) ?: '#f59e0b';
            $sortOrder = (int)($payload['sort_order'] ?? 10);
            $isActive = isset($payload['is_active']) ? ((int)$payload['is_active'] ? 1 : 0) : 1;

            if ($name === '') {
                respond(['error' => 'Category name is required.'], 422);
            }
            $slug = $slugInput !== ''
                ? strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', $slugInput))
                : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $slug = trim($slug, '-') ?: 'category-' . rand(100, 999);

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE categories
                    SET name = ?, slug = ?, icon = ?, color = ?, sort_order = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $slug, $icon, $color, $sortOrder, $isActive, $id]);
                respond(['message' => "Category '{$name}' updated"]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO categories (name, slug, icon, color, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $slug, $icon, $color, $sortOrder, $isActive]);
                respond(['message' => "Category '{$name}' created", 'id' => (int)$pdo->lastInsertId()]);
            }
            break;

        case 'toggle_category_status':
            requireRoles($pdo, $payload, ['Admin', 'Manager']);
            $id = (int)($payload['id'] ?? 0);
            if ($id <= 0) {
                respond(['error' => 'Invalid category ID.'], 422);
            }
            $pdo->prepare("UPDATE categories SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?")
                ->execute([$id]);
            respond(['message' => 'Category visibility status updated']);
            break;

        case 'delete_category':
            requireRoles($pdo, $payload, ['Admin', 'Manager']);
            $id = (int)($payload['id'] ?? 0);
            if ($id <= 0) {
                respond(['error' => 'Invalid category ID.'], 422);
            }

            $catStmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $catStmt->execute([$id]);
            $cat = $catStmt->fetch();
            if (!$cat) {
                respond(['error' => 'Category not found.'], 404);
            }

            $totalCats = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
            if ($totalCats <= 1) {
                respond(['error' => 'Cannot delete the last remaining menu category.'], 422);
            }

            $dishCountStmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE category_id = ?");
            $dishCountStmt->execute([$id]);
            $dishCount = (int)$dishCountStmt->fetchColumn();

            $reassignedMsg = '';
            if ($dishCount > 0) {
                $reassignTo = (int)($payload['reassign_category_id'] ?? 0);
                if ($reassignTo <= 0 || $reassignTo === $id) {
                    $fallbackStmt = $pdo->prepare("SELECT id, name FROM categories WHERE id != ? ORDER BY sort_order ASC, id ASC LIMIT 1");
                    $fallbackStmt->execute([$id]);
                    $fallbackCat = $fallbackStmt->fetch();
                } else {
                    $fallbackStmt = $pdo->prepare("SELECT id, name FROM categories WHERE id = ?");
                    $fallbackStmt->execute([$reassignTo]);
                    $fallbackCat = $fallbackStmt->fetch();
                }

                if (!$fallbackCat) {
                    respond(['error' => 'Unable to find a target category to reassign dishes.'], 422);
                }

                $pdo->prepare("UPDATE menu_items SET category_id = ? WHERE category_id = ?")
                    ->execute([(int)$fallbackCat['id'], $id]);
                $reassignedMsg = " ({$dishCount} dish(es) moved to '{$fallbackCat['name']}')";
            }

            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            respond(['message' => "Category '{$cat['name']}' deleted{$reassignedMsg}"]);
            break;

        // =====================================================================
        // 6. SALES PERSONNEL / STAFF MANAGEMENT & PIN VERIFICATION
        // =====================================================================
        case 'save_staff':
            requireRoles($pdo, $payload, ['Admin', 'Manager']);
            $id = (int)($payload['id'] ?? 0);
            $branchId = (int)($payload['branch_id'] ?? 1);
            $empCode = strtoupper(trim((string)($payload['employee_code'] ?? '')));
            $fullName = trim((string)($payload['full_name'] ?? ''));
            $role = in_array($payload['role'] ?? '', ['Admin', 'Manager', 'Cashier', 'Waiter', 'Chef'], true)
                ? $payload['role']
                : 'Waiter';
            $pinCode = trim((string)($payload['pin_code'] ?? ''));
            $email = trim((string)($payload['email'] ?? ''));
            $phone = trim((string)($payload['phone'] ?? ''));
            $avatarColor = trim((string)($payload['avatar_color'] ?? '#f59e0b'));
            $hourlyRate = max(0, (float)($payload['hourly_rate'] ?? 18.00));
            $commissionRate = max(0, (float)($payload['commission_rate'] ?? 2.50));
            $isActive = isset($payload['is_active']) ? ((int)$payload['is_active'] ? 1 : 0) : 1;

            if ($id > 0 && ($pinCode === '' || $pinCode === '••••')) {
                $existingPinStmt = $pdo->prepare("SELECT pin_code FROM staff WHERE id = ? LIMIT 1");
                $existingPinStmt->execute([$id]);
                $pinCode = (string)($existingPinStmt->fetchColumn() ?: '');
            } elseif ($id <= 0 && ($pinCode === '' || $pinCode === '••••')) {
                $pinCode = isProductionMode() ? '' : '1234';
            }

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
                SELECT s.*, b.name AS branch_name, b.code AS branch_code
                FROM staff s
                LEFT JOIN branches b ON b.id = s.branch_id
                WHERE s.pin_code = ? AND s.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$pin]);
            $staffMember = $stmt->fetch();

            if (!$staffMember) {
                $errMsg = isProductionMode()
                    ? 'Invalid POS PIN code.'
                    : 'Invalid POS PIN code. Try 1111 (Hajia Rabi Adam), 2101 (Zainab), or 2103 (Yasmine).';
                respond(['error' => $errMsg], 401);
            }
            if (isProductionMode()) {
                $staffMember['pin_code'] = '••••';
            }
            respond([
                'message' => "Welcome back, {$staffMember['full_name']} ({$staffMember['role']})",
                'staff' => $staffMember
            ]);
            break;

        case 'login':
            $identifier = trim((string)($payload['identifier'] ?? ''));
            $pin = trim((string)($payload['pin_code'] ?? ''));

            if ($pin === '') {
                respond(['error' => 'Please enter your POS PIN / Password to sign in.'], 422);
            }

            if ($identifier !== '') {
                $stmt = $pdo->prepare("
                    SELECT s.*, b.name AS branch_name, b.code AS branch_code
                    FROM staff s
                    LEFT JOIN branches b ON b.id = s.branch_id
                    WHERE (LOWER(s.email) = LOWER(?) OR LOWER(s.employee_code) = LOWER(?))
                      AND s.pin_code = ?
                      AND s.is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([$identifier, $identifier, $pin]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT s.*, b.name AS branch_name, b.code AS branch_code
                    FROM staff s
                    LEFT JOIN branches b ON b.id = s.branch_id
                    WHERE s.pin_code = ? AND s.is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([$pin]);
            }

            $staffMember = $stmt->fetch();
            if (!$staffMember) {
                $errMsg = isProductionMode()
                    ? 'Invalid login credentials. Please verify your Email / Employee ID and POS PIN.'
                    : 'Invalid login credentials. Click any demo credential row on the right or check your Email / Employee ID and PIN.';
                respond(['error' => $errMsg], 401);
            }
            if (isProductionMode()) {
                $staffMember['pin_code'] = '••••';
            }

            respond([
                'message' => "Signed in as {$staffMember['full_name']} ({$staffMember['role']} • {$staffMember['branch_name']})",
                'staff' => $staffMember
            ]);
            break;

        // =====================================================================
        // 7. BRANCHES & COMPANY PROFILE
        // =====================================================================
        case 'save_branch':
            requireRoles($pdo, $payload, ['Admin']);
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
            requireRoles($pdo, $payload, ['Admin']);
            $name = trim((string)($payload['name'] ?? ''));
            $ownerName = trim((string)($payload['owner_name'] ?? 'Hajia Rabi Adam'));
            $tagline = trim((string)($payload['tagline'] ?? ''));
            $logoText = trim((string)($payload['logo_text'] ?? 'SD'));
            $currencySymbol = trim((string)($payload['currency_symbol'] ?? 'GHS '));
            $currencyCode = trim((string)($payload['currency_code'] ?? 'GHS'));
            $taxRate = max(0, (float)($payload['tax_rate'] ?? 6.00));
            $serviceRate = max(0, (float)($payload['service_charge_rate'] ?? 2.50));
            $email = trim((string)($payload['email'] ?? ''));
            $phone = trim((string)($payload['phone'] ?? ''));
            $address = trim((string)($payload['address'] ?? ''));
            $receiptFooter = trim((string)($payload['receipt_footer'] ?? ''));

            if ($name === '') {
                respond(['error' => 'Company name cannot be empty'], 422);
            }

            $stmt = $pdo->prepare("
                UPDATE company_profile
                SET name = ?, owner_name = ?, tagline = ?, logo_text = ?, currency_symbol = ?, currency_code = ?,
                    tax_rate = ?, service_charge_rate = ?, email = ?, phone = ?, address = ?, receipt_footer = ?
                WHERE id = 1
            ");
            $stmt->execute([
                $name, $ownerName, $tagline, $logoText, $currencySymbol, $currencyCode,
                $taxRate, $serviceRate, $email, $phone, $address, $receiptFooter
            ]);

            respond(['message' => 'Company HQ profile & tax settings saved']);
            break;

        // =====================================================================
        // 7B. OPERATING EXPENDITURES MANAGEMENT
        // =====================================================================
        case 'save_expenditure':
            $actor = requireRoles($pdo, $payload, ['Admin', 'Manager', 'Cashier']);
            $id = (int)($payload['id'] ?? 0);
            $branchId = (int)($payload['branch_id'] ?? $actor['branch_id'] ?? 1);
            if ($actor['role'] !== 'Admin') {
                $branchId = (int)$actor['branch_id'];
            }
            $staffId = (int)$actor['id'];
            $category = trim((string)($payload['category'] ?? 'Ingredients & Dairy')) ?: 'Ingredients & Dairy';
            $title = trim((string)($payload['title'] ?? ''));
            $vendorName = trim((string)($payload['vendor_name'] ?? ''));
            $paymentMethod = in_array($payload['payment_method'] ?? '', ['Cash', 'MoMo', 'QR Pay', 'Card', 'Bank Transfer'], true)
                ? (string)$payload['payment_method']
                : 'MoMo';
            $amount = max(0, (float)($payload['amount'] ?? 0));
            $expenseDate = trim((string)($payload['expense_date'] ?? date('Y-m-d'))) ?: date('Y-m-d');
            $notes = trim((string)($payload['notes'] ?? ''));

            if ($title === '' || $amount <= 0) {
                respond(['error' => 'Expenditure title and a positive amount are required.'], 422);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE expenditures
                    SET branch_id = ?, category = ?, title = ?, vendor_name = ?, payment_method = ?,
                        amount = ?, expense_date = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $branchId, $category, $title, $vendorName, $paymentMethod,
                    $amount, $expenseDate, $notes, $id
                ]);
                respond(['message' => "Expenditure '{$title}' updated"]);
            } else {
                $maxExpId = (int)$pdo->query("SELECT COALESCE(MAX(id), 6000) FROM expenditures")->fetchColumn();
                $refNo = 'EXP-' . (6001 + $maxExpId);
                $stmt = $pdo->prepare("
                    INSERT INTO expenditures (
                        reference_no, branch_id, staff_id, category, title, vendor_name,
                        payment_method, amount, expense_date, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $refNo, $branchId, $staffId, $category, $title, $vendorName,
                    $paymentMethod, $amount, $expenseDate, $notes
                ]);
                respond(['message' => "Expenditure {$refNo} ({$title}) recorded"]);
            }
            break;

        case 'delete_expenditure':
            requireRoles($pdo, $payload, ['Admin', 'Manager']);
            $id = (int)($payload['id'] ?? 0);
            if ($id <= 0) {
                respond(['error' => 'Invalid expenditure ID'], 422);
            }
            $pdo->prepare("DELETE FROM expenditures WHERE id = ?")->execute([$id]);
            respond(['message' => 'Expenditure record deleted']);
            break;

        case 'reset_demo_data':
            requireRoles($pdo, $payload, ['Admin']);
            Database::resetAndSeed();
            respond(['message' => 'Demo database reset to fresh factory state']);
            break;

        // =====================================================================
        // 8. FULL BUSINESS INTELLIGENCE & FINANCIAL REPORTS ENGINE
        // =====================================================================
        case 'reports_query':
            $actor = requireRoles($pdo, $payload, ['Admin', 'Manager']);
            $fromDate = trim((string)($_GET['from_date'] ?? $payload['from_date'] ?? ''));
            $toDate = trim((string)($_GET['to_date'] ?? $payload['to_date'] ?? ''));
            $branchId = (int)($_GET['branch_id'] ?? $payload['branch_id'] ?? 0);
            // Branch Managers are scoped to their own branch
            if ($actor['role'] === 'Manager') {
                $branchId = (int)$actor['branch_id'];
            }
            $orderType = trim((string)($_GET['order_type'] ?? $payload['order_type'] ?? 'all'));
            $paymentMethod = trim((string)($_GET['payment_method'] ?? $payload['payment_method'] ?? 'all'));
            $paymentStatus = trim((string)($_GET['payment_status'] ?? $payload['payment_status'] ?? 'all'));
            $orderStatus = trim((string)($_GET['order_status'] ?? $payload['order_status'] ?? 'all'));
            $staffId = (int)($_GET['staff_id'] ?? $payload['staff_id'] ?? 0);
            $search = trim((string)($_GET['search'] ?? $payload['search'] ?? ''));

            $where = ['1=1'];
            $params = [];

            if ($fromDate !== '') {
                $where[] = 'o.created_at >= ?';
                $params[] = $fromDate . ' 00:00:00';
            }
            if ($toDate !== '') {
                $where[] = 'o.created_at <= ?';
                $params[] = $toDate . ' 23:59:59';
            }
            if ($branchId > 0) {
                $where[] = 'o.branch_id = ?';
                $params[] = $branchId;
            }
            if ($orderType !== '' && $orderType !== 'all') {
                $where[] = 'o.order_type = ?';
                $params[] = $orderType;
            }
            if ($paymentMethod !== '' && $paymentMethod !== 'all') {
                $where[] = 'o.payment_method = ?';
                $params[] = $paymentMethod;
            }
            if ($paymentStatus !== '' && $paymentStatus !== 'all') {
                $where[] = 'o.payment_status = ?';
                $params[] = $paymentStatus;
            }
            if ($orderStatus !== '' && $orderStatus !== 'all') {
                $where[] = 'o.status = ?';
                $params[] = $orderStatus;
            }
            if ($staffId > 0) {
                $where[] = 'o.staff_id = ?';
                $params[] = $staffId;
            }
            if ($search !== '') {
                $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.notes LIKE ?)';
                $like = '%' . $search . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $whereSql = implode(' AND ', $where);

            $stmt = $pdo->prepare("
                SELECT o.*,
                       b.name AS branch_name, b.code AS branch_code,
                       s.full_name AS staff_name, s.role AS staff_role, s.commission_rate AS staff_commission_rate,
                       t.table_number, t.zone AS table_zone
                FROM orders o
                LEFT JOIN branches b ON b.id = o.branch_id
                LEFT JOIN staff s ON s.id = o.staff_id
                LEFT JOIN restaurant_tables t ON t.id = o.table_id
                WHERE {$whereSql}
                ORDER BY o.created_at DESC, o.id DESC
            ");
            $stmt->execute($params);
            $orders = $stmt->fetchAll();

            // Fetch order items using JOIN on orders so we never exceed SQLite's 999 placeholder limit
            $allOrderItems = [];
            if (!empty($orders)) {
                $itemStmt = $pdo->prepare("
                    SELECT oi.*, m.category_id, c.name AS category_name, c.color AS category_color
                    FROM order_items oi
                    INNER JOIN orders o ON o.id = oi.order_id
                    LEFT JOIN menu_items m ON m.id = oi.menu_item_id
                    LEFT JOIN categories c ON c.id = m.category_id
                    WHERE {$whereSql}
                    ORDER BY oi.id ASC
                ");
                $itemStmt->execute($params);
                $allOrderItems = $itemStmt->fetchAll();
            }

            $itemsByOrder = [];
            foreach ($allOrderItems as $oi) {
                $itemsByOrder[$oi['order_id']][] = $oi;
            }

            // Query matching expenditures for the same date / branch / payment filter
            $expWhere = ['1=1'];
            $expParams = [];
            if ($fromDate !== '') {
                $expWhere[] = 'e.expense_date >= ?';
                $expParams[] = $fromDate;
            }
            if ($toDate !== '') {
                $expWhere[] = 'e.expense_date <= ?';
                $expParams[] = $toDate;
            }
            if ($branchId > 0) {
                $expWhere[] = 'e.branch_id = ?';
                $expParams[] = $branchId;
            }
            if ($paymentMethod !== '' && $paymentMethod !== 'all') {
                $expWhere[] = 'e.payment_method = ?';
                $expParams[] = $paymentMethod;
            }
            $expWhereSql = implode(' AND ', $expWhere);
            $expQuery = $pdo->prepare("
                SELECT e.*, b.name AS branch_name, b.code AS branch_code, s.full_name AS staff_name
                FROM expenditures e
                LEFT JOIN branches b ON b.id = e.branch_id
                LEFT JOIN staff s ON s.id = e.staff_id
                WHERE {$expWhereSql}
                ORDER BY e.expense_date DESC, e.id DESC
            ");
            $expQuery->execute($expParams);
            $matchedExpenditures = $expQuery->fetchAll();

            $totalExpenditures = 0.0;
            $expByCategory = [];
            $expByDay = [];
            $expByBranch = [];
            foreach ($matchedExpenditures as $ex) {
                $amt = (float)$ex['amount'];
                $totalExpenditures += $amt;
                $cat = $ex['category'] ?: 'General';
                if (!isset($expByCategory[$cat])) {
                    $expByCategory[$cat] = ['category' => $cat, 'count' => 0, 'amount' => 0.0];
                }
                $expByCategory[$cat]['count']++;
                $expByCategory[$cat]['amount'] = round($expByCategory[$cat]['amount'] + $amt, 2);

                $dKey = substr((string)$ex['expense_date'], 0, 10);
                $expByDay[$dKey] = ($expByDay[$dKey] ?? 0.0) + $amt;

                $ebId = (int)$ex['branch_id'];
                $expByBranch[$ebId] = ($expByBranch[$ebId] ?? 0.0) + $amt;
            }
            usort($expByCategory, fn($a, $b) => $b['amount'] <=> $a['amount']);

            // Initialize Aggregations
            $kpis = [
                'total_transactions' => count($orders),
                'valid_orders' => 0,
                'cancelled_orders' => 0,
                'total_guests' => 0,
                'gross_revenue' => 0.0,
                'net_subtotal' => 0.0,
                'total_discounts' => 0.0,
                'total_tax' => 0.0,
                'total_service_charge' => 0.0,
                'total_tips' => 0.0,
                'total_cogs' => 0.0,
                'gross_profit' => 0.0,
                'gross_margin_pct' => 0.0,
                'total_expenditures' => round($totalExpenditures, 2),
                'expenditures_count' => count($matchedExpenditures),
                'net_operating_profit' => 0.0,
                'net_operating_margin_pct' => 0.0,
                'avg_order_value' => 0.0,
                'avg_daily_revenue' => 0.0,
                'paid_amount' => 0.0,
                'unpaid_amount' => 0.0,
            ];

            $dailyMap = [];
            $paymentMethodMap = [
                'Cash' => ['method' => 'Cash', 'count' => 0, 'amount' => 0.0],
                'MoMo' => ['method' => 'MoMo', 'count' => 0, 'amount' => 0.0],
                'QR Pay' => ['method' => 'QR Pay', 'count' => 0, 'amount' => 0.0],
                'Card' => ['method' => 'Card', 'count' => 0, 'amount' => 0.0],
                'Split' => ['method' => 'Split', 'count' => 0, 'amount' => 0.0],
            ];
            $orderTypeMap = [
                'Dine-In' => ['type' => 'Dine-In', 'count' => 0, 'amount' => 0.0, 'guests' => 0],
                'Takeaway' => ['type' => 'Takeaway', 'count' => 0, 'amount' => 0.0, 'guests' => 0],
                'Delivery' => ['type' => 'Delivery', 'count' => 0, 'amount' => 0.0, 'guests' => 0],
            ];
            $hourlyMap = array_fill(0, 24, ['hour' => 0, 'label' => '', 'count' => 0, 'amount' => 0.0]);
            for ($h = 0; $h < 24; $h++) {
                $hourlyMap[$h]['hour'] = $h;
                $hourlyMap[$h]['label'] = sprintf('%02d:00', $h);
            }

            $staffAgg = [];
            $branchAgg = [];
            $itemAgg = [];
            $categoryAgg = [];

            foreach ($orders as &$ord) {
                $oItems = $itemsByOrder[$ord['id']] ?? [];
                $ord['items'] = $oItems;

                $orderCogs = 0.0;
                $orderQty = 0;
                foreach ($oItems as $it) {
                    $q = (int)$it['quantity'];
                    $c = (float)$it['unit_cost'] * $q;
                    $orderCogs += $c;
                    $orderQty += $q;
                }
                $ord['order_cogs'] = round($orderCogs, 2);
                $ord['items_count'] = $orderQty;
                $ord['net_sales'] = round((float)$ord['subtotal'] - (float)$ord['discount_amount'], 2);
                $ord['gross_profit'] = round($ord['net_sales'] - $orderCogs, 2);

                if ($ord['status'] === 'Cancelled') {
                    $kpis['cancelled_orders']++;
                    continue;
                }

                $kpis['valid_orders']++;
                $kpis['total_guests'] += (int)$ord['guests_count'];
                $kpis['gross_revenue'] += (float)$ord['total_amount'];
                $kpis['net_subtotal'] += $ord['net_sales'];
                $kpis['total_discounts'] += (float)$ord['discount_amount'];
                $kpis['total_tax'] += (float)$ord['tax_amount'];
                $kpis['total_service_charge'] += (float)$ord['service_charge'];
                $kpis['total_tips'] += (float)$ord['tip_amount'];
                $kpis['total_cogs'] += $orderCogs;

                if ($ord['payment_status'] === 'Paid') {
                    $kpis['paid_amount'] += (float)$ord['total_amount'];
                } elseif ($ord['payment_status'] === 'Unpaid') {
                    $kpis['unpaid_amount'] += (float)$ord['total_amount'];
                }

                // Daily Bucket
                $dayKey = substr((string)$ord['created_at'], 0, 10);
                if (!isset($dailyMap[$dayKey])) {
                    $dailyMap[$dayKey] = [
                        'date' => $dayKey,
                        'orders_count' => 0,
                        'gross_revenue' => 0.0,
                        'net_sales' => 0.0,
                        'cogs' => 0.0,
                        'gross_profit' => 0.0,
                        'expenditures' => round($expByDay[$dayKey] ?? 0.0, 2),
                        'tax' => 0.0,
                        'tips' => 0.0,
                    ];
                }
                $dailyMap[$dayKey]['orders_count']++;
                $dailyMap[$dayKey]['gross_revenue'] = round($dailyMap[$dayKey]['gross_revenue'] + (float)$ord['total_amount'], 2);
                $dailyMap[$dayKey]['net_sales'] = round($dailyMap[$dayKey]['net_sales'] + $ord['net_sales'], 2);
                $dailyMap[$dayKey]['cogs'] = round($dailyMap[$dayKey]['cogs'] + $orderCogs, 2);
                $dailyMap[$dayKey]['gross_profit'] = round($dailyMap[$dayKey]['gross_profit'] + $ord['gross_profit'], 2);
                $dailyMap[$dayKey]['tax'] = round($dailyMap[$dayKey]['tax'] + (float)$ord['tax_amount'], 2);
                $dailyMap[$dayKey]['tips'] = round($dailyMap[$dayKey]['tips'] + (float)$ord['tip_amount'], 2);

                // Hourly Bucket
                $hourStr = substr((string)$ord['created_at'], 11, 2);
                $hr = is_numeric($hourStr) ? (int)$hourStr : 12;
                if (isset($hourlyMap[$hr])) {
                    $hourlyMap[$hr]['count']++;
                    $hourlyMap[$hr]['amount'] = round($hourlyMap[$hr]['amount'] + (float)$ord['total_amount'], 2);
                }

                // Payment Method Bucket
                $pm = $ord['payment_method'] ?: 'Cash';
                if (!isset($paymentMethodMap[$pm])) {
                    $paymentMethodMap[$pm] = ['method' => $pm, 'count' => 0, 'amount' => 0.0];
                }
                $paymentMethodMap[$pm]['count']++;
                $paymentMethodMap[$pm]['amount'] = round($paymentMethodMap[$pm]['amount'] + (float)$ord['total_amount'], 2);

                // Order Type Bucket
                $ot = $ord['order_type'] ?: 'Dine-In';
                if (!isset($orderTypeMap[$ot])) {
                    $orderTypeMap[$ot] = ['type' => $ot, 'count' => 0, 'amount' => 0.0, 'guests' => 0];
                }
                $orderTypeMap[$ot]['count']++;
                $orderTypeMap[$ot]['amount'] = round($orderTypeMap[$ot]['amount'] + (float)$ord['total_amount'], 2);
                $orderTypeMap[$ot]['guests'] += (int)$ord['guests_count'];

                // Staff Bucket
                $sid = (int)$ord['staff_id'];
                if (!isset($staffAgg[$sid])) {
                    $staffAgg[$sid] = [
                        'staff_id' => $sid,
                        'staff_name' => $ord['staff_name'] ?: 'Staff',
                        'role' => $ord['staff_role'] ?: 'Waiter',
                        'commission_rate' => (float)($ord['staff_commission_rate'] ?? 2.5),
                        'orders_count' => 0,
                        'gross_sales' => 0.0,
                        'tips_collected' => 0.0,
                        'commission_earned' => 0.0,
                    ];
                }
                $staffAgg[$sid]['orders_count']++;
                $staffAgg[$sid]['gross_sales'] = round($staffAgg[$sid]['gross_sales'] + (float)$ord['total_amount'], 2);
                $staffAgg[$sid]['tips_collected'] = round($staffAgg[$sid]['tips_collected'] + (float)$ord['tip_amount'], 2);
                $staffAgg[$sid]['commission_earned'] = round(
                    $staffAgg[$sid]['gross_sales'] * ($staffAgg[$sid]['commission_rate'] / 100),
                    2
                );

                // Branch Bucket
                $bid = (int)$ord['branch_id'];
                if (!isset($branchAgg[$bid])) {
                    $branchAgg[$bid] = [
                        'branch_id' => $bid,
                        'branch_name' => $ord['branch_name'] ?: 'Branch',
                        'branch_code' => $ord['branch_code'] ?: 'BR',
                        'orders_count' => 0,
                        'gross_revenue' => 0.0,
                        'net_sales' => 0.0,
                        'expenditures' => round($expByBranch[$bid] ?? 0.0, 2),
                        'tax' => 0.0,
                        'tips' => 0.0,
                    ];
                }
                $branchAgg[$bid]['orders_count']++;
                $branchAgg[$bid]['gross_revenue'] = round($branchAgg[$bid]['gross_revenue'] + (float)$ord['total_amount'], 2);
                $branchAgg[$bid]['net_sales'] = round($branchAgg[$bid]['net_sales'] + $ord['net_sales'], 2);
                $branchAgg[$bid]['tax'] = round($branchAgg[$bid]['tax'] + (float)$ord['tax_amount'], 2);
                $branchAgg[$bid]['tips'] = round($branchAgg[$bid]['tips'] + (float)$ord['tip_amount'], 2);

                // Item & Category Buckets
                foreach ($oItems as $it) {
                    $mid = (int)$it['menu_item_id'];
                    $q = (int)$it['quantity'];
                    $rev = (float)$it['line_total'];
                    $cost = (float)$it['unit_cost'] * $q;
                    $prof = $rev - $cost;

                    if (!isset($itemAgg[$mid])) {
                        $itemAgg[$mid] = [
                            'menu_item_id' => $mid,
                            'item_name' => $it['item_name'],
                            'category_name' => $it['category_name'] ?: 'General',
                            'qty_sold' => 0,
                            'revenue' => 0.0,
                            'cost' => 0.0,
                            'profit' => 0.0,
                        ];
                    }
                    $itemAgg[$mid]['qty_sold'] += $q;
                    $itemAgg[$mid]['revenue'] = round($itemAgg[$mid]['revenue'] + $rev, 2);
                    $itemAgg[$mid]['cost'] = round($itemAgg[$mid]['cost'] + $cost, 2);
                    $itemAgg[$mid]['profit'] = round($itemAgg[$mid]['profit'] + $prof, 2);

                    $catName = $it['category_name'] ?: 'Uncategorized';
                    if (!isset($categoryAgg[$catName])) {
                        $categoryAgg[$catName] = [
                            'category_name' => $catName,
                            'color' => $it['category_color'] ?: '#f59e0b',
                            'qty_sold' => 0,
                            'revenue' => 0.0,
                            'profit' => 0.0,
                        ];
                    }
                    $categoryAgg[$catName]['qty_sold'] += $q;
                    $categoryAgg[$catName]['revenue'] = round($categoryAgg[$catName]['revenue'] + $rev, 2);
                    $categoryAgg[$catName]['profit'] = round($categoryAgg[$catName]['profit'] + $prof, 2);
                }
            }
            unset($ord);

            $distinctDays = max(1, count($dailyMap));
            $kpis['gross_revenue'] = round($kpis['gross_revenue'], 2);
            $kpis['net_subtotal'] = round($kpis['net_subtotal'], 2);
            $kpis['total_discounts'] = round($kpis['total_discounts'], 2);
            $kpis['total_tax'] = round($kpis['total_tax'], 2);
            $kpis['total_service_charge'] = round($kpis['total_service_charge'], 2);
            $kpis['total_tips'] = round($kpis['total_tips'], 2);
            $kpis['total_cogs'] = round($kpis['total_cogs'], 2);
            $kpis['gross_profit'] = round($kpis['net_subtotal'] - $kpis['total_cogs'], 2);
            $kpis['gross_margin_pct'] = $kpis['net_subtotal'] > 0
                ? round(($kpis['gross_profit'] / $kpis['net_subtotal']) * 100, 1)
                : 0.0;
            $kpis['net_operating_profit'] = round($kpis['gross_profit'] - $kpis['total_expenditures'], 2);
            $kpis['net_operating_margin_pct'] = $kpis['net_subtotal'] > 0
                ? round(($kpis['net_operating_profit'] / $kpis['net_subtotal']) * 100, 1)
                : 0.0;
            $kpis['avg_order_value'] = $kpis['valid_orders'] > 0
                ? round($kpis['gross_revenue'] / $kpis['valid_orders'], 2)
                : 0.0;
            $kpis['avg_daily_revenue'] = round($kpis['gross_revenue'] / $distinctDays, 2);
            $kpis['active_days_count'] = $distinctDays;
            $kpis['paid_amount'] = round($kpis['paid_amount'], 2);
            $kpis['unpaid_amount'] = round($kpis['unpaid_amount'], 2);

            ksort($dailyMap);

            // If range exceeds 90 days, roll dailyMap up into monthly buckets for crisp chart rendering
            $trendGranularity = 'daily';
            $trendSeries = array_values($dailyMap);
            if (count($dailyMap) > 90) {
                $trendGranularity = 'monthly';
                $monthlyMap = [];
                foreach ($dailyMap as $dKey => $dRow) {
                    $mKey = substr($dKey, 0, 7); // YYYY-MM
                    if (!isset($monthlyMap[$mKey])) {
                        $monthlyMap[$mKey] = [
                            'date' => $mKey,
                            'orders_count' => 0,
                            'gross_revenue' => 0.0,
                            'net_sales' => 0.0,
                            'cogs' => 0.0,
                            'gross_profit' => 0.0,
                            'expenditures' => 0.0,
                            'tax' => 0.0,
                            'tips' => 0.0,
                        ];
                    }
                    $monthlyMap[$mKey]['orders_count'] += $dRow['orders_count'];
                    $monthlyMap[$mKey]['gross_revenue'] = round($monthlyMap[$mKey]['gross_revenue'] + $dRow['gross_revenue'], 2);
                    $monthlyMap[$mKey]['net_sales'] = round($monthlyMap[$mKey]['net_sales'] + $dRow['net_sales'], 2);
                    $monthlyMap[$mKey]['cogs'] = round($monthlyMap[$mKey]['cogs'] + $dRow['cogs'], 2);
                    $monthlyMap[$mKey]['gross_profit'] = round($monthlyMap[$mKey]['gross_profit'] + $dRow['gross_profit'], 2);
                    $monthlyMap[$mKey]['expenditures'] = round($monthlyMap[$mKey]['expenditures'] + ($dRow['expenditures'] ?? 0.0), 2);
                    $monthlyMap[$mKey]['tax'] = round($monthlyMap[$mKey]['tax'] + $dRow['tax'], 2);
                    $monthlyMap[$mKey]['tips'] = round($monthlyMap[$mKey]['tips'] + $dRow['tips'], 2);
                }
                $trendSeries = array_values($monthlyMap);
            }

            usort($itemAgg, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
            usort($categoryAgg, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
            usort($staffAgg, fn($a, $b) => $b['gross_sales'] <=> $a['gross_sales']);
            usort($branchAgg, fn($a, $b) => $b['gross_revenue'] <=> $a['gross_revenue']);

            respond([
                'kpis' => $kpis,
                'trend_granularity' => $trendGranularity,
                'daily_trend' => $trendSeries,
                'hourly_trend' => array_values($hourlyMap),
                'payment_breakdown' => array_values($paymentMethodMap),
                'order_type_breakdown' => array_values($orderTypeMap),
                'category_breakdown' => array_values($categoryAgg),
                'expenditure_breakdown' => array_values($expByCategory),
                'expenditures' => $matchedExpenditures,
                'top_items' => array_values($itemAgg),
                'staff_performance' => array_values($staffAgg),
                'branch_performance' => array_values($branchAgg),
                'transactions' => $orders,
                'transactions_total_count' => count($orders),
            ]);
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
