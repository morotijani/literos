<?php
/**
 * LITEROS — Lite Restaurant Operating System
 * Full-Featured Multi-Branch Restaurant POS, Kitchen Display (KDS),
 * Interactive Table Floor Plan, Menu Studio, Staff Leaderboard & HQ Suite.
 */
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LITEROS — Lite Restaurant Operating System</title>

    <!-- Google Fonts: Plus Jakarta Sans, Fraunces, JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=JetBrains+Mono:wght@500;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        display: ['"Fraunces"', 'serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    }
                }
            }
        };
    </script>

    <!-- Custom Obsidian & Saffron Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="min-h-screen flex flex-col bg-[#090b10] text-slate-100 select-none">

    <!-- =====================================================================
         TOP GLOBAL COMMAND HEADER
         ===================================================================== -->
    <header class="h-16 glass-panel border-b border-white/10 px-5 flex items-center justify-between sticky top-0 z-30">
        <!-- Brand Identity -->
        <div class="flex items-center gap-3.5">
            <div id="brandLogoText"
                 class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 text-slate-950 font-extrabold text-sm flex items-center justify-center shadow-lg shadow-amber-500/20">
                S&S
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 id="brandCompanyName" class="font-bold text-sm tracking-tight text-white">Saffron & Slate Hospitality</h1>
                    <span class="px-2 py-0.5 text-[10px] font-mono-num uppercase tracking-wider rounded-md bg-amber-500/15 text-amber-300 border border-amber-500/30 font-bold">
                        LITEROS v2.4
                    </span>
                </div>
                <p id="brandTagline" class="text-[11px] text-slate-400">Artisanal Wood-Fired Kitchen & Lounge</p>
            </div>
        </div>

        <!-- Center: Active Branch Switcher & Live DB Badge -->
        <div class="hidden md:flex items-center gap-3">
            <div class="flex items-center gap-2 bg-slate-900/90 border border-white/10 rounded-xl px-3 py-1.5">
                <i class="fa-solid fa-store text-amber-400 text-xs"></i>
                <span class="text-[11px] text-slate-400 font-medium">Branch:</span>
                <select id="globalBranchSelect"
                        onchange="LiterosApp.switchBranch(this.value)"
                        class="bg-transparent text-xs font-bold text-white outline-none cursor-pointer pr-2">
                </select>
            </div>

            <div id="dbEngineBadge"
                 class="px-3 py-1.5 rounded-xl bg-slate-900/80 border border-white/10 text-[11px] font-mono-num text-slate-300 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                MySQL Ready
            </div>
        </div>

        <!-- Right: Active Operator PIN Switcher & Live Clock -->
        <div class="flex items-center gap-3">
            <button onclick="LiterosApp.openModal('pinModal')"
                    id="activeStaffPill"
                    title="Click to switch operator via POS PIN"
                    class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-white/10 transition">
            </button>

            <div class="hidden sm:flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-900/60 border border-white/5 font-mono-num text-xs text-slate-300">
                <i class="fa-regular fa-clock text-amber-400"></i>
                <span id="liveClockDisplay">--:--:--</span>
            </div>
        </div>
    </header>

    <!-- =====================================================================
         MAIN WORKSPACE SHELL (LEFT NAVIGATION RAIL + DYNAMIC VIEWPORT)
         ===================================================================== -->
    <div class="flex-1 flex overflow-hidden">

        <!-- Left Navigation Rail -->
        <aside class="w-20 lg:w-56 glass-panel border-r border-white/10 flex flex-col justify-between py-4 px-2.5 shrink-0">
            <nav class="space-y-1.5">
                <button data-tab="pos" onclick="LiterosApp.switchTab('pos')"
                        class="nav-btn active w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-semibold text-slate-300 hover:text-white border border-transparent">
                    <i class="fa-solid fa-cash-register text-base w-5 text-center"></i>
                    <span class="hidden lg:inline">POS Terminal</span>
                </button>

                <button data-tab="kds" onclick="LiterosApp.switchTab('kds')"
                        class="nav-btn w-full flex items-center justify-between px-3.5 py-3 rounded-xl text-xs font-semibold text-slate-300 hover:text-white border border-transparent">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-fire-burner text-base w-5 text-center"></i>
                        <span class="hidden lg:inline">Kitchen Display</span>
                    </div>
                    <span id="navBadgeKds" class="px-1.5 py-0.5 text-[10px] font-mono-num font-bold rounded-md bg-rose-500 text-white">0</span>
                </button>

                <button data-tab="tables" onclick="LiterosApp.switchTab('tables')"
                        class="nav-btn w-full flex items-center justify-between px-3.5 py-3 rounded-xl text-xs font-semibold text-slate-300 hover:text-white border border-transparent">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-chair text-base w-5 text-center"></i>
                        <span class="hidden lg:inline">Floor & Tables</span>
                    </div>
                    <span id="navBadgeTables" class="hidden lg:inline px-1.5 py-0.5 text-[10px] font-mono-num rounded-md bg-slate-800 text-slate-300">0/0</span>
                </button>

                <button data-tab="sales" onclick="LiterosApp.switchTab('sales')"
                        class="nav-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-semibold text-slate-300 hover:text-white border border-transparent">
                    <i class="fa-solid fa-chart-line text-base w-5 text-center"></i>
                    <span class="hidden lg:inline">Sales & Receipts</span>
                </button>

                <div class="pt-2 pb-1 px-3 hidden lg:block">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Management</div>
                </div>

                <button data-tab="menu" onclick="LiterosApp.switchTab('menu')"
                        class="nav-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-semibold text-slate-300 hover:text-white border border-transparent">
                    <i class="fa-solid fa-utensils text-base w-5 text-center"></i>
                    <span class="hidden lg:inline">Menu Studio</span>
                </button>

                <button data-tab="staff" onclick="LiterosApp.switchTab('staff')"
                        class="nav-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-semibold text-slate-300 hover:text-white border border-transparent">
                    <i class="fa-solid fa-user-tie text-base w-5 text-center"></i>
                    <span class="hidden lg:inline">Sales Personnel</span>
                </button>

                <button data-tab="branches" onclick="LiterosApp.switchTab('branches')"
                        class="nav-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-semibold text-slate-300 hover:text-white border border-transparent">
                    <i class="fa-solid fa-building-columns text-base w-5 text-center"></i>
                    <span class="hidden lg:inline">Branches</span>
                </button>

                <button data-tab="company" onclick="LiterosApp.switchTab('company')"
                        class="nav-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-semibold text-slate-300 hover:text-white border border-transparent">
                    <i class="fa-solid fa-sliders text-base w-5 text-center"></i>
                    <span class="hidden lg:inline">Company HQ</span>
                </button>
            </nav>

            <!-- Bottom Quick Help Card -->
            <div class="hidden lg:block p-3 rounded-2xl bg-slate-900/90 border border-white/5 text-[11px] text-slate-400 space-y-1.5">
                <div class="flex items-center justify-between text-slate-200 font-semibold">
                    <span>Quick Keys</span>
                    <i class="fa-solid fa-keyboard text-amber-400"></i>
                </div>
                <div>Press <kbd class="px-1.5 py-0.5 rounded bg-slate-800 text-amber-300 font-mono-num">/</kbd> to search menu</div>
                <div>Press <kbd class="px-1.5 py-0.5 rounded bg-slate-800 text-amber-300 font-mono-num">Esc</kbd> to close modals</div>
            </div>
        </aside>

        <!-- =================================================================
             DYNAMIC CONTENT VIEWPORT
             ================================================================= -->
        <main class="flex-1 overflow-y-auto">

            <!-- =============================================================
                 VIEW 1: POS REGISTER TERMINAL
                 ============================================================= -->
            <section id="view-pos" class="view-section h-[calc(100vh-4rem)] flex flex-col xl:flex-row overflow-hidden">
                <!-- Left Catalog Area -->
                <div class="flex-1 flex flex-col p-5 overflow-y-auto">
                    <!-- Search & Categories Header -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 mb-4">
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input id="posSearchInput"
                                   type="text"
                                   oninput="LiterosApp.setPosSearch(this.value)"
                                   placeholder="Search dish name, SKU (e.g. WFM-201), or ingredient... (Press '/')"
                                   class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-slate-900/90 border border-white/10 focus:border-amber-500 text-xs text-white outline-none">
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="LiterosApp.openNewMenuModal()"
                                    class="px-3.5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 text-xs font-semibold text-amber-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-plus"></i>Quick Dish
                            </button>
                        </div>
                    </div>

                    <!-- Horizontal Categories Pill Strip -->
                    <div id="posCategoriesBar" class="flex items-center gap-2 overflow-x-auto pb-3 mb-3"></div>

                    <!-- Dish Cards Grid -->
                    <div id="posMenuGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-3.5 pb-8"></div>
                </div>

                <!-- Right Smart Order Ticket Dock -->
                <div class="w-full xl:w-[410px] glass-panel border-t xl:border-t-0 xl:border-l border-white/10 flex flex-col h-full shrink-0">
                    <!-- Ticket Header -->
                    <div class="p-4 border-b border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-receipt text-amber-400"></i>
                                <h2 class="font-bold text-sm text-white">Active Order Ticket</h2>
                            </div>
                            <div class="flex items-center gap-2">
                                <span id="posCartCountBadge" class="px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-300 font-mono-num text-xs font-bold">0 items</span>
                                <button onclick="LiterosApp.clearCart()" class="text-xs text-slate-400 hover:text-rose-400 px-1.5" title="Clear Ticket">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Order Mode Tabs -->
                        <div class="flex items-center gap-1.5 bg-slate-950 p-1 rounded-xl border border-white/5">
                            <button data-type="Dine-In" onclick="LiterosApp.setOrderType('Dine-In')"
                                    class="order-type-btn flex-1 py-2 px-3 rounded-xl text-xs font-bold bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20">
                                <i class="fa-solid fa-utensils mr-1"></i>Dine-In
                            </button>
                            <button data-type="Takeaway" onclick="LiterosApp.setOrderType('Takeaway')"
                                    class="order-type-btn flex-1 py-2 px-3 rounded-xl text-xs font-semibold bg-slate-900/70 text-slate-400 hover:text-white">
                                <i class="fa-solid fa-bag-shopping mr-1"></i>Takeaway
                            </button>
                            <button data-type="Delivery" onclick="LiterosApp.setOrderType('Delivery')"
                                    class="order-type-btn flex-1 py-2 px-3 rounded-xl text-xs font-semibold bg-slate-900/70 text-slate-400 hover:text-white">
                                <i class="fa-solid fa-motorcycle mr-1"></i>Delivery
                            </button>
                        </div>

                        <!-- Table & Guest Info -->
                        <div class="grid grid-cols-2 gap-2">
                            <select id="posTableSelect"
                                    class="bg-slate-900 border border-white/10 rounded-xl px-2.5 py-2 text-xs text-white outline-none">
                            </select>
                            <input id="posCustomerName"
                                   type="text"
                                   value="Walk-in Guest"
                                   placeholder="Guest Name"
                                   class="bg-slate-900 border border-white/10 rounded-xl px-2.5 py-2 text-xs text-white outline-none">
                        </div>
                    </div>

                    <!-- Scrollable Cart Items -->
                    <div id="posCartItemsList" class="flex-1 overflow-y-auto p-4 space-y-2"></div>

                    <!-- Order Notes & Quick Discounts/Tips -->
                    <div class="px-4 py-2.5 border-t border-white/10 bg-slate-950/60 space-y-2">
                        <input id="posOrderNotes"
                               type="text"
                               placeholder="Kitchen note (e.g. Allergy no peanuts, course 1 together)..."
                               class="w-full px-3 py-1.5 rounded-lg bg-slate-900 border border-white/10 text-xs text-slate-200 outline-none">

                        <div class="flex items-center justify-between gap-1 text-[11px]">
                            <span class="text-slate-400">Discount:</span>
                            <div class="flex gap-1">
                                <button onclick="LiterosApp.setDiscountPercent(0)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300">0%</button>
                                <button onclick="LiterosApp.setDiscountPercent(10)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-amber-500 hover:text-slate-950 text-slate-300">10%</button>
                                <button onclick="LiterosApp.setDiscountPercent(20)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-amber-500 hover:text-slate-950 text-slate-300">VIP 20%</button>
                            </div>
                            <span class="text-slate-400 ml-1">Tip:</span>
                            <div class="flex gap-1">
                                <button onclick="LiterosApp.setQuickTip(0)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300">$0</button>
                                <button onclick="LiterosApp.setQuickTip(5)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-emerald-500 hover:text-slate-950 text-slate-300">+$5</button>
                                <button onclick="LiterosApp.setQuickTip(15)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-emerald-500 hover:text-slate-950 text-slate-300">+$15</button>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Totals & Fire Buttons -->
                    <div class="p-4 border-t border-white/10 bg-slate-900/90 space-y-2">
                        <div class="space-y-1 text-xs">
                            <div class="flex justify-between text-slate-400">
                                <span>Subtotal</span>
                                <span id="cartSubtotal" class="font-mono-num text-slate-200">$0.00</span>
                            </div>
                            <div class="flex justify-between text-slate-400">
                                <span>Discount</span>
                                <span id="cartDiscount" class="font-mono-num text-rose-400">-$0.00</span>
                            </div>
                            <div class="flex justify-between text-slate-400">
                                <span id="cartTaxLabel">Tax (8.25%)</span>
                                <span id="cartTax" class="font-mono-num text-slate-200">$0.00</span>
                            </div>
                            <div class="flex justify-between text-slate-400">
                                <span id="cartServiceLabel">Service (5%)</span>
                                <span id="cartService" class="font-mono-num text-slate-200">$0.00</span>
                            </div>
                            <div class="flex justify-between text-slate-400">
                                <span>Server Gratuity / Tip</span>
                                <span id="cartTip" class="font-mono-num text-emerald-400">$0.00</span>
                            </div>
                            <div class="flex justify-between items-baseline pt-2 border-t border-white/10">
                                <span class="text-sm font-bold text-white">Grand Total</span>
                                <span id="cartGrandTotal" class="font-mono-num text-xl font-extrabold text-amber-400">$0.00</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-2">
                            <button onclick="LiterosApp.submitOrder('Unpaid')"
                                    class="py-3 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-300 font-bold text-xs border border-amber-500/30 flex items-center justify-center gap-1.5 transition">
                                <i class="fa-solid fa-fire-burner"></i>
                                Send to Kitchen
                            </button>
                            <button onclick="LiterosApp.openCheckoutModal()"
                                    class="py-3 px-3 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-extrabold text-xs shadow-lg shadow-amber-500/25 flex items-center justify-center gap-1.5 transition">
                                <i class="fa-solid fa-credit-card"></i>
                                Pay & Settle
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- =============================================================
                 VIEW 2: KITCHEN DISPLAY SYSTEM (KDS)
                 ============================================================= -->
            <section id="view-kds" class="view-section hidden p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                            <i class="fa-solid fa-fire-burner text-amber-400"></i>
                            Live Kitchen Display System (KDS)
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Real-time course tickets, special allergen modifiers, and preparation timers</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="px-4 py-2 rounded-xl bg-rose-500/15 border border-rose-500/30 text-xs">
                            <span class="text-slate-300">Cooking:</span>
                            <span id="kdsStatCooking" class="font-mono-num font-extrabold text-rose-400 ml-1">0</span>
                        </div>
                        <div class="px-4 py-2 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-xs">
                            <span class="text-slate-300">Ready for Pickup:</span>
                            <span id="kdsStatReady" class="font-mono-num font-extrabold text-emerald-400 ml-1">0</span>
                        </div>
                    </div>
                </div>

                <div id="kdsTicketsGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5"></div>
            </section>

            <!-- =============================================================
                 VIEW 3: INTERACTIVE FLOOR PLAN & TABLES
                 ============================================================= -->
            <section id="view-tables" class="view-section hidden p-6 space-y-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                            <i class="fa-solid fa-chair text-amber-400"></i>
                            Interactive Dining Floor Plan & Table Occupancy
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Click "Order" on any table to open its live check directly in the POS Terminal</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-3 py-1.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-xs text-emerald-300">
                            Available: <strong id="floorCountAvailable" class="font-mono-num">0</strong>
                        </span>
                        <span class="px-3 py-1.5 rounded-xl bg-rose-500/15 border border-rose-500/30 text-xs text-rose-300">
                            Occupied: <strong id="floorCountOccupied" class="font-mono-num">0</strong>
                        </span>
                        <span class="px-3 py-1.5 rounded-xl bg-amber-500/15 border border-amber-500/30 text-xs text-amber-300">
                            Reserved: <strong id="floorCountReserved" class="font-mono-num">0</strong>
                        </span>
                        <span class="px-3 py-1.5 rounded-xl bg-indigo-500/15 border border-indigo-500/30 text-xs text-indigo-300">
                            Cleaning: <strong id="floorCountCleaning" class="font-mono-num">0</strong>
                        </span>
                        <button onclick="LiterosApp.openModal('tableModal')"
                                class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs">
                            <i class="fa-solid fa-plus mr-1"></i>Add Table
                        </button>
                    </div>
                </div>

                <!-- Zone Filter Pills -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <button data-zone="all" onclick="LiterosApp.setFloorZone('all')" class="floor-zone-btn cat-pill active px-4 py-2 rounded-xl text-xs font-semibold">All Zones</button>
                    <button data-zone="Main Hall" onclick="LiterosApp.setFloorZone('Main Hall')" class="floor-zone-btn cat-pill px-4 py-2 rounded-xl text-xs font-semibold bg-slate-900 text-slate-300">Main Hall</button>
                    <button data-zone="Patio Terrace" onclick="LiterosApp.setFloorZone('Patio Terrace')" class="floor-zone-btn cat-pill px-4 py-2 rounded-xl text-xs font-semibold bg-slate-900 text-slate-300">Patio Terrace</button>
                    <button data-zone="VIP Lounge" onclick="LiterosApp.setFloorZone('VIP Lounge')" class="floor-zone-btn cat-pill px-4 py-2 rounded-xl text-xs font-semibold bg-slate-900 text-slate-300">VIP Lounge</button>
                    <button data-zone="Bar Counter" onclick="LiterosApp.setFloorZone('Bar Counter')" class="floor-zone-btn cat-pill px-4 py-2 rounded-xl text-xs font-semibold bg-slate-900 text-slate-300">Bar Counter</button>
                </div>

                <div id="floorTablesGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4"></div>
            </section>

            <!-- =============================================================
                 VIEW 4: SALES LEDGER, ANALYTICS & RECEIPTS
                 ============================================================= -->
            <section id="view-sales" class="view-section hidden p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                            <i class="fa-solid fa-chart-line text-amber-400"></i>
                            Sales Ledger, Financial Analytics & Thermal Receipts
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Real-time branch revenue, payment method mix, and printable thermal receipts</p>
                    </div>
                    <button onclick="LiterosApp.exportSalesCsv()"
                            class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-white/10 text-xs font-bold text-amber-300 flex items-center gap-2">
                        <i class="fa-solid fa-file-csv"></i>Export CSV Ledger
                    </button>
                </div>

                <!-- KPI Cards + Payment Breakdown -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="glass-panel rounded-2xl p-5 border border-white/10">
                            <div class="text-xs text-slate-400">Gross Branch Revenue</div>
                            <div id="metricGrossRevenue" class="font-mono-num text-2xl font-extrabold text-emerald-400 mt-1">$0.00</div>
                            <div id="metricTotalTips" class="text-[11px] text-slate-400 mt-2 font-mono-num">$0.00 tips • $0.00 tax</div>
                        </div>
                        <div class="glass-panel rounded-2xl p-5 border border-white/10">
                            <div class="text-xs text-slate-400">Average Check Size</div>
                            <div id="metricAvgTicket" class="font-mono-num text-2xl font-extrabold text-amber-400 mt-1">$0.00</div>
                            <div class="text-[11px] text-slate-400 mt-2">Total Valid Orders: <strong id="metricTotalOrders" class="text-white font-mono-num">0</strong></div>
                        </div>
                    </div>

                    <div class="glass-panel rounded-2xl p-5 border border-white/10 space-y-2.5">
                        <div class="text-xs font-bold text-white uppercase tracking-wider mb-1">Payment Tender Mix</div>
                        <div id="paymentMethodBreakdown" class="space-y-2.5"></div>
                    </div>
                </div>

                <!-- Orders Ledger Table -->
                <div class="glass-panel rounded-2xl border border-white/10 overflow-hidden">
                    <div class="p-4 border-b border-white/10 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                        <input type="text"
                               oninput="LiterosApp.setSalesSearch(this.value)"
                               placeholder="Search by Order # (ORD-1001), guest name, or server..."
                               class="px-3.5 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none w-full sm:w-80">
                        <select onchange="LiterosApp.setSalesStatusFilter(this.value)"
                                class="px-3.5 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-slate-200 outline-none">
                            <option value="all">All Order Statuses</option>
                            <option value="Preparing">Preparing</option>
                            <option value="Ready">Ready</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/10 text-[11px] uppercase tracking-wider text-slate-400 bg-slate-950/60">
                                    <th class="py-3 px-4">Order #</th>
                                    <th class="py-3 px-4">Guest & Table</th>
                                    <th class="py-3 px-4">Sales Personnel</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4">Payment</th>
                                    <th class="py-3 px-4 text-right">Total</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="salesTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- =============================================================
                 VIEW 5: MENU & RECIPE STUDIO
                 ============================================================= -->
            <section id="view-menu" class="view-section hidden p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                            <i class="fa-solid fa-utensils text-amber-400"></i>
                            Menu Engineering, Cost Margin & Inventory Studio
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Manage dishes, gross profit (GP%) margins, modifiers, and live portion inventory</p>
                    </div>
                    <button onclick="LiterosApp.openNewMenuModal()"
                            class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i>Add New Dish
                    </button>
                </div>

                <div class="glass-panel rounded-2xl border border-white/10 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/10 text-[11px] uppercase tracking-wider text-slate-400 bg-slate-950/60">
                                    <th class="py-3.5 px-4">Dish / SKU</th>
                                    <th class="py-3.5 px-4">Sell Price</th>
                                    <th class="py-3.5 px-4">Food Cost</th>
                                    <th class="py-3.5 px-4">Gross Margin</th>
                                    <th class="py-3.5 px-4">Portions Left</th>
                                    <th class="py-3.5 px-4">POS Status</th>
                                    <th class="py-3.5 px-4 text-right">Manage</th>
                                </tr>
                            </thead>
                            <tbody id="menuStudioTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- =============================================================
                 VIEW 6: SALES PERSONNEL & STAFF LEADERBOARD
                 ============================================================= -->
            <section id="view-staff" class="view-section hidden p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                            <i class="fa-solid fa-user-tie text-amber-400"></i>
                            Sales Personnel, Roles, Commissions & POS PIN Access
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Track individual server sales volume, commission payouts, and switch active POS operator</p>
                    </div>
                    <button onclick="LiterosApp.openNewStaffModal()"
                            class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs flex items-center gap-2">
                        <i class="fa-solid fa-user-plus"></i>Onboard Personnel
                    </button>
                </div>

                <div id="staffCardsGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5"></div>
            </section>

            <!-- =============================================================
                 VIEW 7: MULTI-BRANCH DIRECTORY
                 ============================================================= -->
            <section id="view-branches" class="view-section hidden p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                            <i class="fa-solid fa-building-columns text-amber-400"></i>
                            Multi-Branch Locations & Regional Tax Overrides
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Create and manage restaurant locations; launching a new branch automatically seeds starter floor tables</p>
                    </div>
                    <button onclick="LiterosApp.openNewBranchModal()"
                            class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i>Launch New Branch
                    </button>
                </div>

                <div id="branchesGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5"></div>
            </section>

            <!-- =============================================================
                 VIEW 8: COMPANY HQ PROFILE & TAX ENGINE
                 ============================================================= -->
            <section id="view-company" class="view-section hidden p-6 max-w-4xl space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                            <i class="fa-solid fa-sliders text-amber-400"></i>
                            Company HQ Profile, Branding & Fiscal Rules
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Configure global hospitality branding, currency, tax rate, service charge, and receipt footer</p>
                    </div>
                    <button onclick="LiterosApp.resetDemoDatabase()"
                            class="px-3.5 py-2 rounded-xl bg-rose-950/70 hover:bg-rose-600 text-rose-300 hover:text-white border border-rose-500/30 text-xs font-semibold transition">
                        <i class="fa-solid fa-rotate-left mr-1"></i>Reset Demo Seed Data
                    </button>
                </div>

                <form id="companyProfileForm" onsubmit="LiterosApp.submitCompanyProfile(event)"
                      class="glass-panel rounded-2xl p-6 border border-white/10 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-400 mb-1">Company / Restaurant Group Name</label>
                            <input name="name" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Logo Monogram (Max 4 chars)</label>
                            <input name="logo_text" maxlength="6" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-bold">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Brand Tagline / Culinary Concept</label>
                        <input name="tagline" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Currency Symbol</label>
                            <input name="currency_symbol" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Currency Code</label>
                            <input name="currency_code" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Default Tax Rate (%)</label>
                            <input name="tax_rate" type="number" step="0.01" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Dine-In Service (%)</label>
                            <input name="service_charge_rate" type="number" step="0.01" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">HQ Email</label>
                            <input name="email" type="email" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">HQ Phone</label>
                            <input name="phone" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Headquarters Address</label>
                        <input name="address" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Thermal Receipt Footer Message</label>
                        <input name="receipt_footer" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                    </div>

                    <div class="pt-3 flex justify-end">
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-amber-500/20">
                            <i class="fa-solid fa-floppy-disk mr-1.5"></i>Save Company Profile & Fiscal Rules
                        </button>
                    </div>
                </form>
            </section>

        </main>
    </div>

    <!-- =====================================================================
         MODALS (MODIFIERS, CHECKOUT, THERMAL RECEIPT, CRUD FORMS, PIN PAD)
         ===================================================================== -->

    <!-- 1. Dish Modifiers Modal -->
    <div id="modifierModal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="glass-panel rounded-2xl max-w-md w-full p-6 border border-white/15 space-y-4">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <div>
                    <h3 id="modModalDishTitle" class="font-bold text-base text-white">Customize Dish</h3>
                    <span id="modModalDishPrice" class="font-mono-num text-xs text-amber-400">$0.00</span>
                </div>
                <button onclick="LiterosApp.closeModal('modifierModal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="modOptionsList" class="space-y-2 max-h-60 overflow-y-auto"></div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Special Kitchen Prep Note</label>
                <input id="modCustomNote" type="text" placeholder="e.g. Sauce on side, extra crispy..."
                       class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button onclick="LiterosApp.closeModal('modifierModal')" class="px-4 py-2 rounded-xl bg-slate-800 text-xs text-slate-300">Cancel</button>
                <button onclick="LiterosApp.confirmModifierModal()" class="px-5 py-2 rounded-xl bg-amber-500 text-slate-950 font-bold text-xs">Add to Ticket</button>
            </div>
        </div>
    </div>

    <!-- 2. POS Payment & Split-Bill Checkout Modal -->
    <div id="checkoutModal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="glass-panel rounded-2xl max-w-md w-full p-6 border border-white/15 space-y-4">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <h3 class="font-bold text-base text-white"><i class="fa-solid fa-cash-register text-amber-400 mr-2"></i>Settle Ticket & Fire Order</h3>
                <button onclick="LiterosApp.closeModal('checkoutModal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="p-4 rounded-2xl bg-slate-950 border border-white/10 flex items-center justify-between">
                <div>
                    <div class="text-xs text-slate-400">Amount Due</div>
                    <div id="checkoutModalTotal" class="font-mono-num text-2xl font-extrabold text-amber-400">$0.00</div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-slate-400">Equal Split / Guest</div>
                    <div id="checkoutSplitPerPerson" class="font-mono-num text-sm font-bold text-slate-200">$0.00</div>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-2">Select Payment Method</label>
                <div class="grid grid-cols-4 gap-2">
                    <button type="button" data-method="Card" onclick="LiterosApp.selectPaymentMethod('Card')"
                            class="pay-method-btn p-3 rounded-xl border bg-amber-500/20 border-amber-500 text-amber-300 text-xs font-semibold flex flex-col items-center gap-1.5">
                        <i class="fa-solid fa-credit-card text-base"></i>Card
                    </button>
                    <button type="button" data-method="Cash" onclick="LiterosApp.selectPaymentMethod('Cash')"
                            class="pay-method-btn p-3 rounded-xl border bg-slate-900 border-white/10 text-slate-400 text-xs font-semibold flex flex-col items-center gap-1.5">
                        <i class="fa-solid fa-money-bill-wave text-base"></i>Cash
                    </button>
                    <button type="button" data-method="QR Pay" onclick="LiterosApp.selectPaymentMethod('QR Pay')"
                            class="pay-method-btn p-3 rounded-xl border bg-slate-900 border-white/10 text-slate-400 text-xs font-semibold flex flex-col items-center gap-1.5">
                        <i class="fa-solid fa-qrcode text-base"></i>QR Pay
                    </button>
                    <button type="button" data-method="Split" onclick="LiterosApp.selectPaymentMethod('Split')"
                            class="pay-method-btn p-3 rounded-xl border bg-slate-900 border-white/10 text-slate-400 text-xs font-semibold flex flex-col items-center gap-1.5">
                        <i class="fa-solid fa-arrows-split-up-and-left text-base"></i>Split
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Amount Tendered</label>
                    <input id="checkoutTenderedInput" type="number" step="0.01" oninput="LiterosApp.updateTenderedCalculation()"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 font-mono-num text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Change Due</label>
                    <div id="checkoutChangeDue" class="px-3 py-2 rounded-xl bg-slate-950 border border-white/10 font-mono-num text-sm font-bold text-emerald-400">$0.00</div>
                </div>
            </div>

            <button onclick="LiterosApp.submitOrder('Paid')"
                    class="w-full py-3.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2">
                <i class="fa-solid fa-check-circle"></i>Complete Payment & Generate Thermal Receipt
            </button>
        </div>
    </div>

    <!-- 3. Thermal Receipt Preview & Print Modal -->
    <div id="receiptModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="max-w-sm w-full space-y-3">
            <div id="receiptModalContent" class="thermal-receipt rounded-2xl p-6"></div>
            <div class="flex items-center justify-between gap-2 no-print">
                <button onclick="LiterosApp.closeModal('receiptModal')"
                        class="flex-1 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-200">
                    Close
                </button>
                <button onclick="window.print()"
                        class="flex-1 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-print"></i>Print Receipt
                </button>
            </div>
        </div>
    </div>

    <!-- 4. Menu Item Create/Edit Modal -->
    <div id="menuItemModal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <form id="menuItemForm" onsubmit="LiterosApp.submitMenuItem(event)"
              class="glass-panel rounded-2xl max-w-lg w-full p-6 border border-white/15 space-y-3.5">
            <input type="hidden" name="id">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <h3 id="menuModalTitle" class="font-bold text-base text-white">Create Culinary Dish</h3>
                <button type="button" onclick="LiterosApp.closeModal('menuItemModal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <label class="block text-xs text-slate-400 mb-1">Dish Name</label>
                    <input name="name" required placeholder="e.g. Truffle Wagyu Burger" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Emoji Icon</label>
                    <input name="image_emoji" value="🍽️" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white text-center">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Category</label>
                    <select id="menuFormCategorySelect" name="category_id" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white"></select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">SKU Code</label>
                    <input name="sku" placeholder="WFM-205" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Course</label>
                    <select name="course_type" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                        <option value="Starter">Starter</option>
                        <option value="Main" selected>Main</option>
                        <option value="Drink">Drink</option>
                        <option value="Dessert">Dessert</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Culinary Description</label>
                <input name="description" placeholder="Ingredients, provenance, and tasting notes..." class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
            </div>

            <div class="grid grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Price ($)</label>
                    <input name="price" type="number" step="0.01" required class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Unit Cost ($)</label>
                    <input name="cost" type="number" step="0.01" value="6.50" required class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Prep (Mins)</label>
                    <input name="prep_time_mins" type="number" value="12" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Stock Qty</label>
                    <input name="stock_qty" type="number" value="40" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Modifiers (comma-separated; use +$X for upcharges)</label>
                <input name="modifiers" placeholder="Medium Rare, Add Truffle (+$4), Gluten-Free" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
            </div>

            <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                <input type="checkbox" name="is_popular" class="accent-amber-500">
                <span>Highlight as Chef's Popular Signature Dish</span>
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="LiterosApp.closeModal('menuItemModal')" class="px-4 py-2 rounded-xl bg-slate-800 text-xs text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-amber-500 text-slate-950 font-bold text-xs">Save Dish</button>
            </div>
        </form>
    </div>

    <!-- 5. Sales Personnel Modal -->
    <div id="staffModal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <form id="staffForm" onsubmit="LiterosApp.submitStaffForm(event)"
              class="glass-panel rounded-2xl max-w-md w-full p-6 border border-white/15 space-y-3.5">
            <input type="hidden" name="id">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <h3 id="staffModalTitle" class="font-bold text-base text-white">Onboard Sales Personnel</h3>
                <button type="button" onclick="LiterosApp.closeModal('staffModal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Full Name</label>
                    <input name="full_name" required placeholder="e.g. Liam Vance" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Role</label>
                    <select name="role" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                        <option value="Waiter">Waiter / Server</option>
                        <option value="Cashier">POS Cashier</option>
                        <option value="Manager">Branch Manager</option>
                        <option value="Chef">Head Chef (KDS)</option>
                        <option value="Admin">Executive Admin</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Assigned Branch</label>
                    <select id="staffFormBranchSelect" name="branch_id" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white"></select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Employee Code</label>
                    <input name="employee_code" placeholder="EMP-009" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">POS PIN Code</label>
                    <input name="pin_code" required maxlength="6" placeholder="8888" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-amber-300 font-mono-num font-bold">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Hourly Wage ($)</label>
                    <input name="hourly_rate" type="number" step="0.5" value="20.00" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Commission (%)</label>
                    <input name="commission_rate" type="number" step="0.5" value="3.0" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Badge Color</label>
                    <input name="avatar_color" type="color" value="#f59e0b" class="w-full h-9 rounded-xl bg-slate-900 border border-white/10 cursor-pointer">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Email</label>
                    <input name="email" type="email" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Phone</label>
                    <input name="phone" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="LiterosApp.closeModal('staffModal')" class="px-4 py-2 rounded-xl bg-slate-800 text-xs text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-amber-500 text-slate-950 font-bold text-xs">Save Personnel</button>
            </div>
        </form>
    </div>

    <!-- 6. Branch Create/Edit Modal -->
    <div id="branchModal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <form id="branchForm" onsubmit="LiterosApp.submitBranchForm(event)"
              class="glass-panel rounded-2xl max-w-md w-full p-6 border border-white/15 space-y-3.5">
            <input type="hidden" name="id">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <h3 id="branchModalTitle" class="font-bold text-base text-white">Launch New Restaurant Branch</h3>
                <button type="button" onclick="LiterosApp.closeModal('branchModal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <label class="block text-xs text-slate-400 mb-1">Branch Name</label>
                    <input name="name" required placeholder="e.g. Beverly Hills Rooftop" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Branch Code</label>
                    <input name="code" placeholder="LAX-BH" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">City</label>
                    <input name="city" required placeholder="Los Angeles" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Branch Manager</label>
                    <input name="manager_name" required placeholder="Chloe Laurent" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Street Address</label>
                <input name="address" required placeholder="450 N Canon Dr, Beverly Hills, CA" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Phone</label>
                    <input name="phone" required placeholder="+1 (310) 555-0199" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Opening Hours</label>
                    <input name="opening_hours" value="10:00 AM - 11:30 PM" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Tax Override (%)</label>
                    <input name="tax_override" type="number" step="0.01" placeholder="Optional" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="LiterosApp.closeModal('branchModal')" class="px-4 py-2 rounded-xl bg-slate-800 text-xs text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-amber-500 text-slate-950 font-bold text-xs">Save Branch</button>
            </div>
        </form>
    </div>

    <!-- 7. Add Floor Table Modal -->
    <div id="tableModal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <form onsubmit="LiterosApp.submitNewTable(event)"
              class="glass-panel rounded-2xl max-w-sm w-full p-6 border border-white/15 space-y-3.5">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <h3 class="font-bold text-base text-white">Add Floor Table</h3>
                <button type="button" onclick="LiterosApp.closeModal('tableModal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Table Number / Code</label>
                <input name="table_number" required placeholder="e.g. T-08 or VIP-3" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Dining Zone</label>
                <select name="zone" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                    <option value="Main Hall">Main Hall</option>
                    <option value="Patio Terrace">Patio Terrace</option>
                    <option value="VIP Lounge">VIP Lounge</option>
                    <option value="Bar Counter">Bar Counter</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Seating Capacity</label>
                <input name="capacity" type="number" value="4" min="1" max="20" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="LiterosApp.closeModal('tableModal')" class="px-4 py-2 rounded-xl bg-slate-800 text-xs text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-amber-500 text-slate-950 font-bold text-xs">Add Table</button>
            </div>
        </form>
    </div>

    <!-- 8. Quick Operator PIN Switcher Modal -->
    <div id="pinModal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <form onsubmit="LiterosApp.submitPinSwitch(event)"
              class="glass-panel rounded-2xl max-w-xs w-full p-6 border border-white/15 space-y-4 text-center">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/20 border border-amber-500/40 text-amber-400 flex items-center justify-center mx-auto">
                <i class="fa-solid fa-key text-lg"></i>
            </div>
            <div>
                <h3 class="font-bold text-base text-white">Operator PIN Switch</h3>
                <p class="text-[11px] text-slate-400 mt-1">Demo PINs: <strong class="text-amber-300">1111</strong> (Admin), <strong class="text-emerald-300">2222</strong> (Cashier), <strong class="text-indigo-300">3333</strong> (Waiter)</p>
            </div>
            <input id="quickPinInput" type="password" maxlength="8" required placeholder="••••"
                   class="w-full py-3 rounded-xl bg-slate-950 border border-white/15 text-center font-mono-num text-xl tracking-widest text-white outline-none focus:border-amber-500">
            <div class="flex gap-2">
                <button type="button" onclick="LiterosApp.closeModal('pinModal')" class="flex-1 py-2.5 rounded-xl bg-slate-800 text-xs text-slate-300">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-amber-500 text-slate-950 font-extrabold text-xs">Unlock POS</button>
            </div>
        </form>
    </div>

    <!-- Toast Notification Stack -->
    <div id="toastContainer" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm"></div>

    <!-- Application Engine -->
    <script src="assets/js/app.js"></script>
</body>
</html>
