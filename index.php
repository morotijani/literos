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
    <script>
        (function () {
            try {
                const savedTheme = localStorage.getItem('literos_theme') || 'dark';
                document.documentElement.classList.remove('dark', 'light');
                document.documentElement.classList.add(savedTheme === 'light' ? 'light' : 'dark');
            } catch (_) {}
        })();
    </script>

    <!-- Chart.js 4 for Interactive Financial & Analytical Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
                SD
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 id="brandCompanyName" class="font-bold text-sm tracking-tight text-white">Shakes and Dessert</h1>
                    <span class="px-2 py-0.5 text-[10px] font-mono-num uppercase tracking-wider rounded-md bg-amber-500/15 text-amber-300 border border-amber-500/30 font-bold">
                        KUMASI • GHS
                    </span>
                </div>
                <p id="brandTagline" class="text-[11px] text-slate-400">Owner: Hajia Rabi Adam • Ayiduase, Sawaba & Dechemso</p>
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

        <!-- Right: Active Operator PIN Switcher, Live Clock & Logout -->
        <div class="flex items-center gap-2.5">
            <button onclick="LiterosApp.openModal('pinModal')"
                    id="activeStaffPill"
                    title="Click to switch operator via POS PIN"
                    class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-white/10 transition">
            </button>

            <div class="hidden sm:flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-900/60 border border-white/5 font-mono-num text-xs text-slate-300">
                <i class="fa-regular fa-clock text-amber-400"></i>
                <span id="liveClockDisplay">--:--:--</span>
            </div>

            <button type="button"
                    id="themeToggleBtn"
                    onclick="LiterosApp.toggleTheme()"
                    title="Switch between Dark Mode and Light Mode"
                    class="flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-xs font-bold text-slate-200 transition">
                <i id="themeToggleIcon" class="fa-solid fa-sun text-amber-400"></i>
                <span id="themeToggleLabel" class="hidden xl:inline">Light Mode</span>
            </button>

            <button onclick="LiterosApp.logoutSession()"
                    title="Lock POS & Sign Out to Login Screen"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-slate-950 border border-rose-500/30 text-xs font-extrabold transition">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="hidden md:inline">Logout</span>
            </button>
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
                    <i class="fa-solid fa-receipt text-base w-5 text-center"></i>
                    <span class="hidden lg:inline">Sales & Receipts</span>
                </button>

                <button data-tab="expenditures" onclick="LiterosApp.switchTab('expenditures')"
                        class="nav-btn w-full flex items-center justify-between px-3.5 py-3 rounded-xl text-xs font-semibold text-slate-300 hover:text-white border border-transparent">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-wallet text-base w-5 text-center text-rose-400"></i>
                        <span class="hidden lg:inline">Expenditures</span>
                    </div>
                </button>

                <button data-tab="coffers" onclick="LiterosApp.switchTab('coffers')"
                        class="nav-btn w-full flex items-center justify-between px-3.5 py-3 rounded-xl text-xs font-semibold text-slate-300 hover:text-white border border-transparent">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-vault text-base w-5 text-center text-emerald-400"></i>
                        <span class="hidden lg:inline">Finance & Coffers</span>
                    </div>
                </button>

                <button data-tab="reports" onclick="LiterosApp.switchTab('reports')"
                        class="nav-btn w-full flex items-center justify-between px-3.5 py-3 rounded-xl text-xs font-semibold text-slate-300 hover:text-white border border-transparent">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-chart-pie text-base w-5 text-center text-amber-400"></i>
                        <span class="hidden lg:inline">Reports & BI</span>
                    </div>
                    <span class="hidden lg:inline px-1.5 py-0.5 text-[9px] font-mono-num font-bold rounded bg-amber-500/20 text-amber-300 border border-amber-500/30">PRO</span>
                </button>

                <div id="navManagementDivider" class="pt-2 pb-1 px-3 hidden lg:block">
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

            <!-- Bottom Quick Help & Logout Card -->
            <div class="space-y-2">
                <div class="hidden lg:block p-3 rounded-2xl bg-slate-900/90 border border-white/5 text-[11px] text-slate-400 space-y-1.5">
                    <div class="flex items-center justify-between text-slate-200 font-semibold">
                        <span>Quick Keys</span>
                        <i class="fa-solid fa-keyboard text-amber-400"></i>
                    </div>
                    <div>Press <kbd class="px-1.5 py-0.5 rounded bg-slate-800 text-amber-300 font-mono-num">/</kbd> to search menu</div>
                    <div>Press <kbd class="px-1.5 py-0.5 rounded bg-slate-800 text-amber-300 font-mono-num">Esc</kbd> to close modals</div>
                </div>

                <button onclick="LiterosApp.logoutSession()"
                        class="w-full flex items-center justify-center lg:justify-start gap-3 px-3.5 py-2.5 rounded-xl bg-rose-950/50 hover:bg-rose-600 text-rose-300 hover:text-white border border-rose-500/25 text-xs font-bold transition">
                    <i class="fa-solid fa-power-off w-5 text-center"></i>
                    <span class="hidden lg:inline">Lock & Sign Out</span>
                </button>
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
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 mb-4 shrink-0">
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input id="posSearchInput"
                                   type="text"
                                   oninput="LiterosApp.setPosSearch(this.value)"
                                   placeholder="Search dish name, SKU (e.g. WFM-201), or ingredient... (Press '/')"
                                   class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-slate-900/90 border border-white/10 focus:border-amber-500 text-xs text-white outline-none">
                        </div>
                        <div class="flex items-center gap-2">
                            <button id="btnPosQuickDish" onclick="LiterosApp.openNewMenuModal()"
                                    class="px-3.5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 text-xs font-semibold text-amber-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-plus"></i>Quick Dish
                            </button>
                        </div>
                    </div>

                    <!-- Horizontal Categories Pill Strip -->
                    <div id="posCategoriesBar" class="flex items-center gap-2 overflow-x-auto shrink-0 pt-1 pb-3 mb-3"></div>

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
                                <button onclick="LiterosApp.setQuickTip(0)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300">¢0</button>
                                <button onclick="LiterosApp.setQuickTip(20)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-emerald-500 hover:text-slate-950 text-slate-300">+¢20</button>
                                <button onclick="LiterosApp.setQuickTip(50)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-emerald-500 hover:text-slate-950 text-slate-300">+¢50</button>
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
                            <button id="btnPosSendToKitchen" onclick="LiterosApp.submitOrder('Unpaid')"
                                    class="py-3 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-300 font-bold text-xs border border-amber-500/30 flex items-center justify-center gap-1.5 transition">
                                <i class="fa-solid fa-fire-burner"></i>
                                Send to Kitchen
                            </button>
                            <button id="btnPosPayAndSettle" onclick="LiterosApp.openCheckoutModal()"
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
                        <button id="btnAddFloorTable" onclick="LiterosApp.openModal('tableModal')"
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
                 VIEW 4A-EXP: OPERATING EXPENDITURES & VENDOR PAYOUTS
                 ============================================================= -->
            <section id="view-expenditures" class="view-section hidden p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                            <i class="fa-solid fa-wallet text-rose-400"></i>
                            Branch Operating Expenditures & Supplier Payouts
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Track dairy & ingredients restocking, packaging, ECG electricity/diesel, maintenance, and vendor disbursements via Cash or MoMo</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="LiterosApp.exportExpendituresCsv()"
                                class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-white/10 text-xs font-bold text-slate-200 flex items-center gap-2">
                            <i class="fa-solid fa-file-csv text-amber-400"></i>Export Expenses CSV
                        </button>
                        <button onclick="LiterosApp.openNewExpenditureModal()"
                                class="px-4 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-rose-500/20 flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i>Record Expenditure
                        </button>
                    </div>
                </div>

                <!-- Expenditure Summary KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="glass-panel rounded-2xl p-5 border border-rose-500/25">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>Total Branch Expenditure (All-Time)</span>
                            <i class="fa-solid fa-arrow-trend-down text-rose-400"></i>
                        </div>
                        <div id="expMetricTotalAmount" class="font-mono-num text-2xl font-extrabold text-rose-400 mt-1">GHS 0.00</div>
                        <div id="expMetricTotalCount" class="text-[11px] text-slate-400 mt-2 font-mono-num">0 recorded vouchers</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-5 border border-amber-500/25">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>2026 YTD Operating Expenses</span>
                            <i class="fa-solid fa-calendar-check text-amber-400"></i>
                        </div>
                        <div id="expMetricYtdAmount" class="font-mono-num text-2xl font-extrabold text-amber-400 mt-1">GHS 0.00</div>
                        <div class="text-[11px] text-slate-400 mt-2">Current fiscal year (Jan – Nov 2026)</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-5 border border-white/10">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>Paid via Mobile Money (MoMo)</span>
                            <i class="fa-solid fa-mobile-screen-button text-yellow-400"></i>
                        </div>
                        <div id="expMetricMomoAmount" class="font-mono-num text-2xl font-extrabold text-yellow-300 mt-1">GHS 0.00</div>
                        <div class="text-[11px] text-slate-400 mt-2">Supplier MoMo transfers & ECG top-ups</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-5 border border-white/10">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>Paid via Physical Cash</span>
                            <i class="fa-solid fa-money-bill-wave text-emerald-400"></i>
                        </div>
                        <div id="expMetricCashAmount" class="font-mono-num text-2xl font-extrabold text-emerald-400 mt-1">GHS 0.00</div>
                        <div class="text-[11px] text-slate-400 mt-2">Kejetia wholesale & direct market payouts</div>
                    </div>
                </div>

                <!-- Expenditures Ledger Table -->
                <div class="glass-panel rounded-2xl border border-white/10 overflow-hidden">
                    <div class="p-4 border-b border-white/10 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                        <input type="text"
                               oninput="LiterosApp.setExpenditureSearch(this.value)"
                               placeholder="Search by Ref # (EXP-5001), title, vendor (e.g. Kumasi ColdStore), or category..."
                               class="px-3.5 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none w-full sm:w-96">
                        <div class="flex items-center gap-2">
                            <select onchange="LiterosApp.setExpenditureCategoryFilter(this.value)"
                                    class="px-3.5 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-slate-200 outline-none">
                                <option value="all">All Expense Categories</option>
                                <option value="Ingredients & Dairy">Ingredients & Dairy</option>
                                <option value="Fruits, Nuts & Toppings">Fruits, Nuts & Toppings</option>
                                <option value="Meat & Shawarma Supplies">Meat & Shawarma Supplies</option>
                                <option value="Packaging & Cups">Packaging & Cups</option>
                                <option value="Utilities & Generator Fuel">Utilities & Generator Fuel</option>
                                <option value="Equipment & Maintenance">Equipment & Maintenance</option>
                                <option value="Staff Welfare & Logistics">Staff Welfare & Logistics</option>
                            </select>
                            <select onchange="LiterosApp.setExpenditureMethodFilter(this.value)"
                                    class="px-3.5 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-slate-200 outline-none">
                                <option value="all">All Payment Methods</option>
                                <option value="MoMo">MoMo (Mobile Money)</option>
                                <option value="Cash">Cash</option>
                                <option value="QR Pay">QR Pay</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/10 text-[11px] uppercase tracking-wider text-slate-400 bg-slate-950/60">
                                    <th class="py-3 px-4">Voucher Ref</th>
                                    <th class="py-3 px-4">Date</th>
                                    <th class="py-3 px-4">Branch</th>
                                    <th class="py-3 px-4">Category</th>
                                    <th class="py-3 px-4">Description & Supplier</th>
                                    <th class="py-3 px-4">Approved By</th>
                                    <th class="py-3 px-4">Payment</th>
                                    <th class="py-3 px-4 text-right">Amount</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="expendituresTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- =============================================================
                 VIEW 4A-2: FINANCE & COFFERS TREASURY VAULT
                 ============================================================= -->
            <section id="view-coffers" class="view-section hidden p-6 space-y-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                            <i class="fa-solid fa-vault text-emerald-400"></i>
                            Finance & Coffers Treasury Vault (Shakes and Dessert)
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Track all sales revenue inflows, manual coffer deposits, market/goods purchase withdrawals (~GHS 8,500+/mo), and normal withdrawals
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button onclick="LiterosApp.openCofferModal('Deposit')"
                                class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs flex items-center gap-1.5 shadow-lg shadow-emerald-500/20 transition">
                            <i class="fa-solid fa-circle-plus"></i>+ Add Money to Coffers
                        </button>
                        <button onclick="LiterosApp.openCofferModal('Withdrawal', 'Withdrawal for Goods / Market')"
                                class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs flex items-center gap-1.5 shadow-lg shadow-amber-500/20 transition">
                            <i class="fa-solid fa-basket-shopping"></i>- Withdraw for Goods / Market
                        </button>
                        <button onclick="LiterosApp.openCofferModal('Withdrawal', 'Normal Withdrawal')"
                                class="px-3.5 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500 text-rose-300 hover:text-slate-950 border border-rose-500/40 font-extrabold text-xs flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-money-bill-transfer"></i>- Normal Withdrawal
                        </button>
                        <button onclick="LiterosApp.exportCoffersCsv()"
                                class="px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 text-xs font-semibold text-slate-300 flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-file-csv text-emerald-400"></i>Export CSV
                        </button>
                    </div>
                </div>

                <!-- 6 Executive Coffers & Revenue Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                    <div class="glass-panel rounded-2xl p-4 border border-emerald-500/25">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>All Paid Sales Revenue</span>
                            <i class="fa-solid fa-sack-dollar text-emerald-400"></i>
                        </div>
                        <div id="cofKpiTotalRevenue" class="font-mono-num text-lg font-extrabold text-emerald-400 mt-1">GHS 0.00</div>
                        <div id="cofKpiRevenueSub" class="text-[10px] text-slate-400 mt-1 font-mono-num">Cash, MoMo & QR Pay</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-4 border border-cyan-500/25">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Manual Coffer Deposits (+)</span>
                            <i class="fa-solid fa-hand-holding-dollar text-cyan-400"></i>
                        </div>
                        <div id="cofKpiManualDeposits" class="font-mono-num text-lg font-extrabold text-cyan-300 mt-1">GHS 0.00</div>
                        <div id="cofKpiManualDepositsSub" class="text-[10px] text-slate-400 mt-1 font-mono-num">0 manual additions</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-4 border border-white/10">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Gross Coffers Inflow</span>
                            <i class="fa-solid fa-arrow-trend-up text-amber-400"></i>
                        </div>
                        <div id="cofKpiGrossInflow" class="font-mono-num text-lg font-extrabold text-white mt-1">GHS 0.00</div>
                        <div class="text-[10px] text-slate-400 mt-1">Sales Revenue + Manual Deposits</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-4 border border-amber-500/30">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Goods / Market Withdrawals</span>
                            <i class="fa-solid fa-basket-shopping text-amber-400"></i>
                        </div>
                        <div id="cofKpiGoodsWithdrawals" class="font-mono-num text-lg font-extrabold text-amber-300 mt-1">-GHS 0.00</div>
                        <div id="cofKpiGoodsWithdrawalsSub" class="text-[10px] text-amber-200/80 mt-1 font-mono-num">Avg ~GHS 8,500+/mo</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-4 border border-rose-500/30">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Normal Withdrawals (-)</span>
                            <i class="fa-solid fa-money-bill-wave text-rose-400"></i>
                        </div>
                        <div id="cofKpiNormalWithdrawals" class="font-mono-num text-lg font-extrabold text-rose-400 mt-1">-GHS 0.00</div>
                        <div id="cofKpiNormalWithdrawalsSub" class="text-[10px] text-slate-400 mt-1 font-mono-num">0 normal withdrawals</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-4 border border-emerald-500/40 bg-emerald-500/[0.04]">
                        <div class="flex items-center justify-between text-[11px] text-emerald-300 font-semibold">
                            <span>Net Available Coffers</span>
                            <i class="fa-solid fa-vault text-emerald-400"></i>
                        </div>
                        <div id="cofKpiNetBalance" class="font-mono-num text-lg font-extrabold text-white mt-1">GHS 0.00</div>
                        <div id="cofKpiNetBalanceSub" class="text-[10px] text-emerald-300 mt-1 font-mono-num">After withdrawals & expenses</div>
                    </div>
                </div>

                <!-- Revenue by Payment Channel Breakdown Bar -->
                <div class="glass-panel rounded-2xl p-4 border border-white/10 grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="p-3 rounded-xl bg-slate-900/70 border border-white/5 flex items-center justify-between">
                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Physical Cash Sales (60%)</div>
                            <div id="cofRevCash" class="font-mono-num text-sm font-extrabold text-emerald-400 mt-0.5">GHS 0.00</div>
                        </div>
                        <i class="fa-solid fa-money-bill-1-wave text-emerald-400/70 text-lg"></i>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/70 border border-white/5 flex items-center justify-between">
                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">MoMo Mobile Money (35%)</div>
                            <div id="cofRevMomo" class="font-mono-num text-sm font-extrabold text-amber-300 mt-0.5">GHS 0.00</div>
                        </div>
                        <i class="fa-solid fa-mobile-screen-button text-amber-400/70 text-lg"></i>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/70 border border-white/5 flex items-center justify-between">
                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">QR Pay Collections (5%)</div>
                            <div id="cofRevQr" class="font-mono-num text-sm font-extrabold text-cyan-300 mt-0.5">GHS 0.00</div>
                        </div>
                        <i class="fa-solid fa-qrcode text-cyan-400/70 text-lg"></i>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/70 border border-white/5 flex items-center justify-between">
                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Total Coffer Withdrawals</div>
                            <div id="cofTotalWithdrawalsAll" class="font-mono-num text-sm font-extrabold text-rose-400 mt-0.5">-GHS 0.00</div>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square text-rose-400/70 text-lg"></i>
                    </div>
                </div>

                <!-- Coffers Filter & Search Bar -->
                <div class="glass-panel rounded-2xl p-4 border border-white/10 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <select id="coffersPurposeFilter"
                                onchange="LiterosApp.setCoffersPurposeFilter(this.value)"
                                class="px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-semibold outline-none focus:border-amber-500">
                            <option value="all">All Coffer Entries (Deposits & Withdrawals)</option>
                            <option value="Withdrawal">All Withdrawals Only (-)</option>
                            <option value="Withdrawal for Goods / Market">Withdrawal for Goods / Market (~GHS 8,500+/mo)</option>
                            <option value="Normal Withdrawal">Normal Withdrawals Only</option>
                            <option value="Deposit">Manual Coffer Deposits Only (+)</option>
                        </select>

                        <select id="coffersYearFilter"
                                onchange="LiterosApp.setCoffersYearFilter(this.value)"
                                class="px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-semibold outline-none focus:border-amber-500">
                            <option value="all">All Years (2024–2026)</option>
                            <option value="2026">2026</option>
                            <option value="2025">2025</option>
                            <option value="2024">2024</option>
                        </select>

                        <select id="coffersChannelFilter"
                                onchange="LiterosApp.setCoffersChannelFilter(this.value)"
                                class="px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-semibold outline-none focus:border-amber-500">
                            <option value="all">All Channels (Cash / MoMo / Bank)</option>
                            <option value="Cash">Cash</option>
                            <option value="MoMo">MoMo</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <div class="relative w-full lg:w-72">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text"
                               id="coffersSearchInput"
                               oninput="LiterosApp.setCoffersSearch(this.value)"
                               placeholder="Search ref, market items, description..."
                               class="w-full pl-8 pr-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none focus:border-amber-500">
                    </div>
                </div>

                <!-- Coffers Ledger Table -->
                <div class="glass-panel rounded-2xl border border-white/10 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/10 text-[11px] uppercase tracking-wider text-slate-400 bg-slate-950/60">
                                    <th class="py-3.5 px-4">Ref #</th>
                                    <th class="py-3.5 px-4">Date</th>
                                    <th class="py-3.5 px-4">Branch</th>
                                    <th class="py-3.5 px-4">Type & Purpose</th>
                                    <th class="py-3.5 px-4">Description & Purpose Details</th>
                                    <th class="py-3.5 px-4">Recipient / Source</th>
                                    <th class="py-3.5 px-4">Channel</th>
                                    <th class="py-3.5 px-4">Authorized By</th>
                                    <th class="py-3.5 px-4 text-right">Amount (GHS)</th>
                                    <th class="py-3.5 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="coffersTableBody"></tbody>
                            <tfoot id="coffersTableFoot" class="bg-slate-950/90 border-t-2 border-amber-500/40 font-mono-num text-xs font-extrabold"></tfoot>
                        </table>
                    </div>
                </div>
            </section>

            <!-- =============================================================
                 VIEW 4B: FULL REPORTS & BUSINESS INTELLIGENCE SUITE
                 ============================================================= -->
            <section id="view-reports" class="view-section hidden p-6 space-y-6">
                <!-- Header & Export Actions -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                            <i class="fa-solid fa-chart-pie text-amber-400"></i>
                            Executive Financial Reports & Business Intelligence
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Filter Jan 2024 – Nov 2026 historical sales & expenditures across Ayiduase, Sawaba & Dechemso with live charts & audit tables
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button onclick="LiterosApp.setReportDatePreset('today')"
                                class="px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 text-xs font-semibold text-slate-300 hover:text-amber-300 transition">
                            Today
                        </button>
                        <button onclick="LiterosApp.setReportDatePreset('7days')"
                                class="px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 text-xs font-semibold text-slate-300 hover:text-amber-300 transition">
                            7 Days
                        </button>
                        <button onclick="LiterosApp.setReportDatePreset('30days')"
                                class="px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 text-xs font-semibold text-slate-300 hover:text-amber-300 transition">
                            30 Days
                        </button>
                        <button onclick="LiterosApp.setReportDatePreset('2024')"
                                class="px-2.5 py-1.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-xs font-bold text-emerald-300 transition">
                            2024 (Boom)
                        </button>
                        <button onclick="LiterosApp.setReportDatePreset('2025')"
                                class="px-2.5 py-1.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-xs font-bold text-emerald-300 transition">
                            2025 (Peak)
                        </button>
                        <button onclick="LiterosApp.setReportDatePreset('2026')"
                                class="px-2.5 py-1.5 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-xs font-bold text-amber-300 transition">
                            2026 (YTD)
                        </button>
                        <button onclick="LiterosApp.setReportDatePreset('all')"
                                class="px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 text-xs font-semibold text-slate-300 hover:text-amber-300 transition">
                            All Time (2024–2026)
                        </button>
                        <button onclick="LiterosApp.exportFullReportCsv()"
                                class="px-3.5 py-1.5 rounded-xl bg-emerald-500/20 hover:bg-emerald-500 text-emerald-300 hover:text-slate-950 border border-emerald-500/40 text-xs font-bold flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-file-csv"></i>Export CSV
                        </button>
                        <button onclick="window.print()"
                                class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-print"></i>Print
                        </button>
                    </div>
                </div>

                <!-- Multi-Dimensional Filter Control Bar -->
                <form id="reportsFilterForm" onsubmit="LiterosApp.runReportQuery(event)"
                      class="glass-panel rounded-2xl p-5 border border-white/10 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">From Date</label>
                            <input type="date" id="repFromDate" name="from_date"
                                   class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num outline-none focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">To Date</label>
                            <input type="date" id="repToDate" name="to_date"
                                   class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num outline-none focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Branch Location</label>
                            <select id="repBranchSelect" name="branch_id"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none focus:border-amber-500">
                                <option value="0">All Branches (Consolidated)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Transaction Channel</label>
                            <select id="repOrderType" name="order_type"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none focus:border-amber-500">
                                <option value="all">All Order Channels</option>
                                <option value="Dine-In">Dine-In</option>
                                <option value="Takeaway">Takeaway</option>
                                <option value="Delivery">Delivery</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Payment Tender</label>
                            <select id="repPaymentMethod" name="payment_method"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none focus:border-amber-500">
                                <option value="all">All Payment Methods</option>
                                <option value="Cash">Physical Cash (60%)</option>
                                <option value="MoMo">MoMo / Mobile Money (35%)</option>
                                <option value="QR Pay">QR Pay (5%)</option>
                                <option value="Card">Card</option>
                                <option value="Split">Split Bill</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Payment Status</label>
                            <select id="repPaymentStatus" name="payment_status"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none focus:border-amber-500">
                                <option value="all">All Payment Statuses</option>
                                <option value="Paid">Paid</option>
                                <option value="Unpaid">Unpaid / Open Tab</option>
                                <option value="Refunded">Refunded</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Order Status</label>
                            <select id="repOrderStatus" name="order_status"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none focus:border-amber-500">
                                <option value="all">All Order Statuses</option>
                                <option value="Completed">Completed</option>
                                <option value="Preparing">Preparing</option>
                                <option value="Ready">Ready</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">Sales Personnel</label>
                            <select id="repStaffSelect" name="staff_id"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none focus:border-amber-500">
                                <option value="0">All Personnel</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-2 border-t border-white/5">
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="repSearchInput" name="search"
                                   placeholder="Filter by Order # (e.g. ORD-1008), Customer Name, or Order Notes..."
                                   class="w-full pl-9 pr-4 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none focus:border-amber-500">
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="LiterosApp.resetReportFilters()"
                                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300">
                                <i class="fa-solid fa-rotate-left mr-1"></i>Reset Filters
                            </button>
                            <button type="submit"
                                    class="px-6 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-amber-500/20 flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-filter"></i>Apply Report Filters
                            </button>
                        </div>
                    </div>
                </form>

                <!-- 6 Executive Financial KPI Cards (Including Expenditures & Net Operating Profit) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                    <div class="glass-panel rounded-2xl p-4 border border-emerald-500/25">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Gross Sales Revenue</span>
                            <i class="fa-solid fa-sack-dollar text-emerald-400"></i>
                        </div>
                        <div id="repKpiGrossRevenue" class="font-mono-num text-lg font-extrabold text-emerald-400 mt-1">GHS 0.00</div>
                        <div id="repKpiPaidSplit" class="text-[10px] text-slate-400 mt-1 font-mono-num">Daily Avg: GHS 0.00</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-4 border border-white/10">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Net Food & Drink Sales</span>
                            <i class="fa-solid fa-receipt text-amber-400"></i>
                        </div>
                        <div id="repKpiNetSales" class="font-mono-num text-lg font-extrabold text-white mt-1">GHS 0.00</div>
                        <div id="repKpiDiscounts" class="text-[10px] text-slate-400 mt-1 font-mono-num">Tax+Svc: GHS 0.00</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-4 border border-amber-500/25">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Gross Profit (After COGS)</span>
                            <i class="fa-solid fa-chart-line text-amber-400"></i>
                        </div>
                        <div id="repKpiGrossProfit" class="font-mono-num text-lg font-extrabold text-amber-400 mt-1">GHS 0.00</div>
                        <div id="repKpiMarginPct" class="text-[10px] text-emerald-300 mt-1 font-mono-num">COGS: GHS 0.00 (0% Margin)</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-4 border border-rose-500/25">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Operating Expenditures</span>
                            <i class="fa-solid fa-wallet text-rose-400"></i>
                        </div>
                        <div id="repKpiTaxService" class="font-mono-num text-lg font-extrabold text-rose-400 mt-1">GHS 0.00</div>
                        <div id="repKpiTaxBreakdown" class="text-[10px] text-slate-400 mt-1 font-mono-num">0 expense vouchers</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-4 border border-cyan-500/25">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Net Operating Profit</span>
                            <i class="fa-solid fa-vault text-cyan-400"></i>
                        </div>
                        <div id="repKpiTips" class="font-mono-num text-lg font-extrabold text-cyan-300 mt-1">GHS 0.00</div>
                        <div id="repKpiGuestsCount" class="text-[10px] text-slate-400 mt-1 font-mono-num">After COGS & Expenditures</div>
                    </div>

                    <div class="glass-panel rounded-2xl p-4 border border-white/10">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Orders & Avg Check</span>
                            <i class="fa-solid fa-basket-shopping text-indigo-400"></i>
                        </div>
                        <div id="repKpiOrdersCount" class="font-mono-num text-lg font-extrabold text-white mt-1">0</div>
                        <div id="repKpiAvgCheck" class="text-[10px] text-amber-300 mt-1 font-mono-num">Avg Batch: GHS 0.00</div>
                    </div>
                </div>

                <!-- Interactive Visual Charts Grid (4 Charts) -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <!-- Chart 1: Daily Revenue, Net Sales & Profit Trend -->
                    <div class="lg:col-span-2 glass-panel rounded-2xl p-5 border border-white/10">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-bold text-white">Daily Revenue, Net Sales & Gross Profit Timeline</h3>
                                <p class="text-[11px] text-slate-400">Tracks daily total collections vs. net profit across selected dates</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg bg-amber-500/15 text-amber-300 text-[10px] font-mono-num font-bold">TIME-SERIES</span>
                        </div>
                        <div class="h-64 relative">
                            <canvas id="repDailyTrendChart"></canvas>
                        </div>
                    </div>

                    <!-- Chart 2: Payment Method Tender Mix -->
                    <div class="glass-panel rounded-2xl p-5 border border-white/10">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-bold text-white">Payment Tender Distribution</h3>
                                <p class="text-[11px] text-slate-400">Revenue split by Card, Cash, QR Pay & Split</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/15 text-emerald-300 text-[10px] font-mono-num font-bold">TENDER</span>
                        </div>
                        <div class="h-64 relative flex items-center justify-center">
                            <canvas id="repPaymentChart"></canvas>
                        </div>
                    </div>

                    <!-- Chart 3: Order Channel Mix (Dine-In vs Takeaway vs Delivery) -->
                    <div class="glass-panel rounded-2xl p-5 border border-white/10">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-bold text-white">Transaction Type / Channel Mix</h3>
                                <p class="text-[11px] text-slate-400">Dine-In vs. Takeaway vs. Delivery revenue</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg bg-cyan-500/15 text-cyan-300 text-[10px] font-mono-num font-bold">CHANNELS</span>
                        </div>
                        <div class="h-60 relative flex items-center justify-center">
                            <canvas id="repOrderTypeChart"></canvas>
                        </div>
                    </div>

                    <!-- Chart 4: Category & Hourly Sales Velocity -->
                    <div class="lg:col-span-2 glass-panel rounded-2xl p-5 border border-white/10">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-bold text-white">Category Revenue & Profit Contribution</h3>
                                <p class="text-[11px] text-slate-400">Compares gross revenue and net profit generated per culinary category</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg bg-indigo-500/15 text-indigo-300 text-[10px] font-mono-num font-bold">MENU MIX</span>
                        </div>
                        <div class="h-60 relative">
                            <canvas id="repCategoryChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Analytical Breakdown Tables (Top Dishes + Staff Commissions + Branch Comparison) -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                    <!-- Top Selling Dishes Table -->
                    <div class="glass-panel rounded-2xl border border-white/10 overflow-hidden">
                        <div class="p-4 border-b border-white/10 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-white"><i class="fa-solid fa-ranking-star text-amber-400 mr-1.5"></i>Item-Level Sales & Profit Margin Breakdown</h3>
                                <p class="text-[11px] text-slate-400">Ranked by revenue generated within the filtered date window</p>
                            </div>
                        </div>
                        <div class="overflow-x-auto max-h-80">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-white/10 text-[10px] uppercase tracking-wider text-slate-400 bg-slate-950/70 sticky top-0">
                                        <th class="py-2.5 px-3.5">Dish Name</th>
                                        <th class="py-2.5 px-3.5 text-center">Qty Sold</th>
                                        <th class="py-2.5 px-3.5 text-right">Revenue</th>
                                        <th class="py-2.5 px-3.5 text-right">COGS Cost</th>
                                        <th class="py-2.5 px-3.5 text-right">Net Profit</th>
                                    </tr>
                                </thead>
                                <tbody id="repTopItemsTableBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Staff Sales & Commission Performance Table -->
                    <div class="glass-panel rounded-2xl border border-white/10 overflow-hidden">
                        <div class="p-4 border-b border-white/10 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-white"><i class="fa-solid fa-user-check text-emerald-400 mr-1.5"></i>Sales Personnel & Commission Audit</h3>
                                <p class="text-[11px] text-slate-400">Server order volume, tips collected, and commission payout for period</p>
                            </div>
                        </div>
                        <div class="overflow-x-auto max-h-80">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-white/10 text-[10px] uppercase tracking-wider text-slate-400 bg-slate-950/70 sticky top-0">
                                        <th class="py-2.5 px-3.5">Personnel</th>
                                        <th class="py-2.5 px-3.5 text-center">Orders</th>
                                        <th class="py-2.5 px-3.5 text-right">Gross Sales</th>
                                        <th class="py-2.5 px-3.5 text-right">Tips</th>
                                        <th class="py-2.5 px-3.5 text-right">Commission</th>
                                    </tr>
                                </thead>
                                <tbody id="repStaffPerformanceBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Master Filtered Transactions & Line-Item Audit Table (With Pagination & Footer Totals Row) -->
                <div class="glass-panel rounded-2xl border border-white/10 overflow-hidden">
                    <div class="p-4 border-b border-white/10 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-white">
                                <i class="fa-solid fa-table-list text-amber-400 mr-1.5"></i>
                                Detailed Sales Orders & Transactions Ledger
                            </h3>
                            <p class="text-[11px] text-slate-400">Complete line-by-line financial breakdown with pagination, page subtotals & grand total summary</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span id="repTransactionsCountBadge" class="px-3 py-1.5 rounded-xl bg-slate-900 border border-white/10 font-mono-num text-xs font-bold text-amber-300">
                                0 matching transactions
                            </span>
                            <div class="flex items-center gap-1.5 bg-slate-900 border border-white/10 rounded-xl px-2.5 py-1">
                                <span class="text-[11px] text-slate-400">Show:</span>
                                <select id="repPageSizeSelect"
                                        onchange="LiterosApp.setReportPageSize(this.value)"
                                        class="bg-transparent text-xs font-mono-num font-bold text-white outline-none cursor-pointer">
                                    <option value="50" class="bg-slate-900">50 / page</option>
                                    <option value="100" selected class="bg-slate-900">100 / page</option>
                                    <option value="250" class="bg-slate-900">250 / page</option>
                                    <option value="500" class="bg-slate-900">500 / page</option>
                                    <option value="all" class="bg-slate-900">All Rows (Full List)</option>
                                </select>
                            </div>
                            <div id="repTopQuickPager" class="flex items-center gap-1"></div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/10 text-[10px] uppercase tracking-wider text-slate-400 bg-slate-950/80">
                                    <th class="py-3 px-3.5">Order #</th>
                                    <th class="py-3 px-3.5">Date & Time</th>
                                    <th class="py-3 px-3.5">Branch</th>
                                    <th class="py-3 px-3.5">Customer / Table</th>
                                    <th class="py-3 px-3.5">Channel</th>
                                    <th class="py-3 px-3.5">Personnel</th>
                                    <th class="py-3 px-3.5">Tender</th>
                                    <th class="py-3 px-3.5">Status</th>
                                    <th class="py-3 px-3.5 text-right">Subtotal</th>
                                    <th class="py-3 px-3.5 text-right">Discount</th>
                                    <th class="py-3 px-3.5 text-right">Tax+Svc</th>
                                    <th class="py-3 px-3.5 text-right">Tip</th>
                                    <th class="py-3 px-3.5 text-right">Total Amount</th>
                                    <th class="py-3 px-3.5 text-right">Receipt</th>
                                </tr>
                            </thead>
                            <tbody id="repTransactionsTableBody"></tbody>
                            <tfoot id="repTransactionsTableFoot" class="bg-slate-950/95 border-t-2 border-amber-500/40 font-mono-num text-xs font-extrabold text-white"></tfoot>
                        </table>
                    </div>

                    <!-- Bottom Interactive Pagination Bar -->
                    <div id="repPaginationBar" class="p-4 border-t border-white/10 bg-slate-950/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3"></div>
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
                            Menu Engineering, Categories, Cost Margin & Inventory Studio
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Create, edit, reorder, or delete menu categories and manage dishes, GP% margins, modifiers, and portion stock</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <button id="btnMenuStudioAddCategory" onclick="LiterosApp.openNewCategoryModal()"
                                class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-amber-500/40 text-amber-300 font-bold text-xs flex items-center gap-2 transition">
                            <i class="fa-solid fa-folder-plus"></i>+ New Category
                        </button>
                        <button id="btnMenuStudioAddDish" onclick="LiterosApp.openNewMenuModal()"
                                class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs flex items-center gap-2 transition">
                            <i class="fa-solid fa-plus"></i>Add New Dish
                        </button>
                    </div>
                </div>

                <!-- Menu Categories CRUD Section -->
                <div class="glass-panel rounded-2xl border border-white/10 p-5 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/10 pb-3">
                        <div>
                            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-layer-group text-amber-400"></i>
                                Culinary & Dessert Categories Directory (Full CRUD)
                            </h3>
                            <p class="text-[11px] text-slate-400">Click any category card to filter the dishes below, or use Edit / Toggle / Delete to manage POS categories</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="menuCategoriesCountBadge" class="px-2.5 py-1 rounded-lg bg-slate-900 border border-white/10 font-mono-num text-xs font-bold text-amber-300">0 Categories</span>
                            <button id="btnMenuCategoryCardAdd" onclick="LiterosApp.openNewCategoryModal()"
                                    class="px-3 py-1.5 rounded-xl bg-amber-500/15 hover:bg-amber-500 text-amber-300 hover:text-slate-950 border border-amber-500/30 text-xs font-extrabold transition">
                                <i class="fa-solid fa-plus mr-1"></i>Create Category
                            </button>
                        </div>
                    </div>

                    <div id="menuCategoriesCrudGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3.5"></div>
                </div>

                <!-- Dishes Filter Bar + Dishes Table -->
                <div class="glass-panel rounded-2xl border border-white/10 overflow-hidden">
                    <div class="p-4 border-b border-white/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-950/40">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold text-white mr-1"><i class="fa-solid fa-filter text-amber-400 mr-1.5"></i>Filter Dishes:</span>
                            <select id="menuStudioCategoryFilterSelect"
                                    onchange="LiterosApp.setMenuStudioCategoryFilter(this.value)"
                                    class="px-3 py-1.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-semibold outline-none focus:border-amber-500">
                                <option value="all">All Categories</option>
                            </select>
                        </div>
                        <div class="relative w-full sm:w-64">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text"
                                   id="menuStudioSearchInput"
                                   oninput="LiterosApp.setMenuStudioSearch(this.value)"
                                   placeholder="Search dish name or SKU..."
                                   class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white outline-none focus:border-amber-500">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/10 text-[11px] uppercase tracking-wider text-slate-400 bg-slate-950/60">
                                    <th class="py-3.5 px-4">Dish / SKU</th>
                                    <th class="py-3.5 px-4">Category</th>
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
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Company / Restaurant Brand Name</label>
                            <input name="name" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Owner / Proprietor Name</label>
                            <input name="owner_name" required placeholder="Hajia Rabi Adam" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-xs text-amber-300 font-bold">
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
                <div class="grid grid-cols-5 gap-2">
                    <button type="button" data-method="Cash" onclick="LiterosApp.selectPaymentMethod('Cash')"
                            class="pay-method-btn p-2.5 rounded-xl border bg-amber-500/20 border-amber-500 text-amber-300 text-xs font-semibold flex flex-col items-center gap-1.5">
                        <i class="fa-solid fa-money-bill-wave text-base"></i>Cash
                    </button>
                    <button type="button" data-method="MoMo" onclick="LiterosApp.selectPaymentMethod('MoMo')"
                            class="pay-method-btn p-2.5 rounded-xl border bg-slate-900 border-white/10 text-slate-400 text-xs font-semibold flex flex-col items-center gap-1.5">
                        <i class="fa-solid fa-mobile-screen-button text-base"></i>MoMo
                    </button>
                    <button type="button" data-method="QR Pay" onclick="LiterosApp.selectPaymentMethod('QR Pay')"
                            class="pay-method-btn p-2.5 rounded-xl border bg-slate-900 border-white/10 text-slate-400 text-xs font-semibold flex flex-col items-center gap-1.5">
                        <i class="fa-solid fa-qrcode text-base"></i>QR Pay
                    </button>
                    <button type="button" data-method="Card" onclick="LiterosApp.selectPaymentMethod('Card')"
                            class="pay-method-btn p-2.5 rounded-xl border bg-slate-900 border-white/10 text-slate-400 text-xs font-semibold flex flex-col items-center gap-1.5">
                        <i class="fa-solid fa-credit-card text-base"></i>Card
                    </button>
                    <button type="button" data-method="Split" onclick="LiterosApp.selectPaymentMethod('Split')"
                            class="pay-method-btn p-2.5 rounded-xl border bg-slate-900 border-white/10 text-slate-400 text-xs font-semibold flex flex-col items-center gap-1.5">
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
              class="glass-panel rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 border border-white/15 space-y-3.5">
            <input type="hidden" name="id">
            <input type="hidden" name="image_url" id="menuImageUrlInput" value="">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <h3 id="menuModalTitle" class="font-bold text-base text-white">Create Culinary Dish</h3>
                <button type="button" onclick="LiterosApp.closeModal('menuItemModal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Dish Name</label>
                <input name="name" required placeholder="e.g. Crazy Biscoff Shake" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
            </div>

            <!-- Dish Visual Mode: Emoji Icon vs Uploaded Image -->
            <div class="p-3 rounded-xl bg-slate-900/70 border border-white/10 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <label class="text-xs font-semibold text-slate-300">Dish Display Visual</label>
                    <div class="inline-flex items-center gap-1 bg-slate-950 p-1 rounded-lg border border-white/10">
                        <button type="button" id="btnVisualModeEmoji" onclick="LiterosApp.setMenuVisualMode('emoji')"
                                class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-amber-500 text-slate-950 transition">
                            <i class="fa-regular fa-face-smile mr-1"></i>Emoji Icon
                        </button>
                        <button type="button" id="btnVisualModeImage" onclick="LiterosApp.setMenuVisualMode('image')"
                                class="px-2.5 py-1 rounded-md text-[11px] font-semibold text-slate-400 hover:text-white transition">
                            <i class="fa-regular fa-image mr-1"></i>Upload Image
                        </button>
                    </div>
                </div>

                <!-- Emoji Panel -->
                <div id="menuVisualEmojiPanel" class="flex items-center gap-3">
                    <div id="menuEmojiPreviewBox"
                         class="w-12 h-12 rounded-xl bg-slate-950 border border-white/10 flex items-center justify-center text-2xl shrink-0 select-none">🍽️</div>
                    <div class="flex-1">
                        <label class="block text-[11px] text-slate-400 mb-1">Emoji Icon</label>
                        <input name="image_emoji" value="🍽️" oninput="LiterosApp.updateMenuEmojiPreview(this.value)"
                               class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-white/10 text-xs text-white">
                    </div>
                </div>

                <!-- Image Upload Panel -->
                <div id="menuVisualImagePanel" class="hidden flex items-center gap-3">
                    <div id="menuImagePreviewBox"
                         class="w-16 h-16 rounded-xl bg-slate-950 border border-dashed border-white/20 flex items-center justify-center overflow-hidden shrink-0">
                        <img id="menuImagePreviewTag" src="" alt="Dish preview" class="hidden w-full h-full object-cover">
                        <i id="menuImagePlaceholderIcon" class="fa-regular fa-image text-slate-500 text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0 space-y-1.5">
                        <input type="file" id="menuImageFileInput" accept="image/png,image/jpeg,image/webp,image/gif"
                               onchange="LiterosApp.handleMenuImageSelect(event)" class="hidden">
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" onclick="document.getElementById('menuImageFileInput').click()"
                                    class="px-3 py-1.5 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 text-amber-300 text-xs font-semibold flex items-center gap-1.5 transition">
                                <i class="fa-solid fa-cloud-arrow-up"></i>Choose Photo
                            </button>
                            <button type="button" id="btnClearMenuImage" onclick="LiterosApp.clearMenuImage()"
                                    class="hidden px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-rose-600/80 text-slate-300 hover:text-white text-xs transition">
                                Remove
                            </button>
                        </div>
                        <p id="menuImageStatusText" class="text-[11px] text-slate-400 truncate">JPG, PNG, WebP or GIF (max 5 MB)</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs text-slate-400">Category</label>
                        <button type="button" onclick="LiterosApp.openNewCategoryModal()" class="text-[10px] font-bold text-amber-400 hover:underline">+ New</button>
                    </div>
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

    <!-- 4B. Create / Edit Category Modal (Full Category CRUD) -->
    <div id="categoryModal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <form id="categoryForm" onsubmit="LiterosApp.submitCategoryForm(event)"
              class="glass-panel rounded-2xl max-w-md w-full p-6 border border-white/15 space-y-4">
            <input type="hidden" name="id">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-400 flex items-center justify-center">
                        <i id="categoryModalPreviewIcon" class="fa-solid fa-layer-group"></i>
                    </div>
                    <div>
                        <h3 id="categoryModalTitle" class="font-bold text-base text-white">Create Menu Category</h3>
                        <p class="text-[11px] text-slate-400">Appears on POS filter bar, Menu Studio & BI Reports</p>
                    </div>
                </div>
                <button type="button" onclick="LiterosApp.closeModal('categoryModal')" class="text-slate-400 hover:text-white">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <label class="block text-xs text-slate-400 mb-1">Category Name *</label>
                    <input name="name" required placeholder="e.g. Signature Milkshakes & Boba"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Sort Order</label>
                    <input name="sort_order" type="number" value="10"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">URL / Code Slug</label>
                    <input name="slug" placeholder="auto-generated"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-slate-300 font-mono-num">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Icon Class (FA6)</label>
                    <input id="categoryIconInput" name="icon" value="fa-ice-cream"
                           oninput="document.getElementById('categoryModalPreviewIcon').className = 'fa-solid ' + this.value"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Badge Color</label>
                    <input name="color" type="color" value="#f59e0b"
                           class="w-full h-9 rounded-xl bg-slate-900 border border-white/10 cursor-pointer">
                </div>
            </div>

            <div>
                <label class="block text-[11px] text-slate-400 mb-1.5">Quick Pick Hospitality Icon:</label>
                <div class="grid grid-cols-6 gap-1.5">
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-blender')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="Blender / Shakes"><i class="fa-solid fa-blender"></i></button>
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-ice-cream')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="Ice Cream / Gelato"><i class="fa-solid fa-ice-cream"></i></button>
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-cake-candles')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="Cakes & Pastries"><i class="fa-solid fa-cake-candles"></i></button>
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-burger')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="Burgers & Wraps"><i class="fa-solid fa-burger"></i></button>
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-utensils')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="Kitchen / Dining"><i class="fa-solid fa-utensils"></i></button>
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-mug-hot')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="Coffee / Hot Drinks"><i class="fa-solid fa-mug-hot"></i></button>
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-martini-glass-citrus')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="Mocktails / Smoothies"><i class="fa-solid fa-martini-glass-citrus"></i></button>
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-cookie-bite')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="Cookies & Waffles"><i class="fa-solid fa-cookie-bite"></i></button>
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-pizza-slice')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="Pizza & Savory"><i class="fa-solid fa-pizza-slice"></i></button>
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-bowl-food')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="Rice & Bowls"><i class="fa-solid fa-bowl-food"></i></button>
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-drumstick-bite')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="Grill & Wings"><i class="fa-solid fa-drumstick-bite"></i></button>
                    <button type="button" onclick="LiterosApp.pickCategoryIcon('fa-champagne-glasses')" class="py-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-200 text-xs" title="VIP / Party Platters"><i class="fa-solid fa-champagne-glasses"></i></button>
                </div>
            </div>

            <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer pt-1">
                <input type="checkbox" name="is_active" checked class="accent-amber-500">
                <span>Active & Visible on POS Terminal Filter Bar</span>
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="LiterosApp.closeModal('categoryModal')" class="px-4 py-2 rounded-xl bg-slate-800 text-xs text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs">Save Category</button>
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

    <!-- 7B. Record / Edit Expenditure Modal -->
    <div id="expenditureModal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <form id="expenditureForm" onsubmit="LiterosApp.submitExpenditureForm(event)"
              class="glass-panel rounded-2xl max-w-lg w-full p-6 border border-white/15 space-y-3.5">
            <input type="hidden" name="id">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <h3 id="expenditureModalTitle" class="font-bold text-base text-white">
                    <i class="fa-solid fa-wallet text-rose-400 mr-1.5"></i>Record Branch Expenditure
                </h3>
                <button type="button" onclick="LiterosApp.closeModal('expenditureModal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Branch Location</label>
                    <select id="expFormBranchSelect" name="branch_id" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white"></select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Expense Category</label>
                    <select name="category" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                        <option value="Ingredients & Dairy">Ingredients & Dairy</option>
                        <option value="Fruits, Nuts & Toppings">Fruits, Nuts & Toppings</option>
                        <option value="Meat & Shawarma Supplies">Meat & Shawarma Supplies</option>
                        <option value="Packaging & Cups">Packaging & Cups</option>
                        <option value="Utilities & Generator Fuel">Utilities & Generator Fuel</option>
                        <option value="Equipment & Maintenance">Equipment & Maintenance</option>
                        <option value="Staff Welfare & Logistics">Staff Welfare & Logistics</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Expenditure Description / Title</label>
                <input name="title" required placeholder="e.g. Bulk Whipping Cream, Biscoff Spread & Nutella Restock"
                       class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Amount (GHS)</label>
                    <input name="amount" type="number" step="0.01" required placeholder="4500.00"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-rose-300 font-mono-num font-bold">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Payment Method</label>
                    <select name="payment_method" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                        <option value="MoMo">MoMo (Mobile Money)</option>
                        <option value="Cash">Physical Cash</option>
                        <option value="QR Pay">QR Pay</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Expense Date</label>
                    <input name="expense_date" type="date" required
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Supplier / Vendor Name</label>
                    <input name="vendor_name" placeholder="e.g. Kumasi ColdStore & Dairy Ltd"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Approval / Voucher Note</label>
                    <input name="notes" placeholder="e.g. Approved by Hajia Rabi Adam"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="LiterosApp.closeModal('expenditureModal')" class="px-4 py-2 rounded-xl bg-slate-800 text-xs text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-slate-950 font-extrabold text-xs">Save Expenditure</button>
            </div>
        </form>
    </div>

    <!-- 7B. Finance & Coffers Deposit / Withdrawal Modal -->
    <div id="cofferModal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <form id="cofferForm" onsubmit="LiterosApp.submitCofferForm(event)"
              class="glass-panel rounded-2xl max-w-lg w-full p-6 border border-white/15 space-y-4">
            <input type="hidden" name="id">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 flex items-center justify-center">
                        <i class="fa-solid fa-vault"></i>
                    </div>
                    <div>
                        <h3 id="cofferModalTitle" class="font-bold text-base text-white">Coffers Treasury Entry</h3>
                        <p class="text-[11px] text-slate-400">Add money manually to coffers or record a goods/market or normal withdrawal</p>
                    </div>
                </div>
                <button type="button" onclick="LiterosApp.closeModal('cofferModal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Entry Action *</label>
                    <select id="cofferEntryTypeSelect" name="entry_type"
                            onchange="LiterosApp.handleCofferEntryTypeChange(this.value)"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-bold">
                        <option value="Withdrawal">Withdraw from Coffers (-)</option>
                        <option value="Deposit">Add Money to Coffers (+)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Purpose / Withdrawal Type *</label>
                    <select id="cofferPurposeTypeSelect" name="purpose_type"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-amber-300 font-bold">
                        <option value="Withdrawal for Goods / Market">Withdrawal for Goods / Market (Ingredients / Cooking)</option>
                        <option value="Normal Withdrawal">Normal Withdrawal (General / Owner / Petty / Bank)</option>
                        <option value="Manual Coffer Deposit">Manual Coffer Deposit (+)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Amount (GHS) *</label>
                    <input name="amount" type="number" step="0.01" required placeholder="4500.00"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num font-bold">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Payment Channel</label>
                    <select name="payment_channel" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                        <option value="Cash">Physical Cash</option>
                        <option value="MoMo">MoMo (Mobile Money)</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="QR Pay">QR Pay</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Date *</label>
                    <input name="transaction_date" type="date" required
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white font-mono-num">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Branch Location</label>
                    <select id="cofferFormBranchSelect" name="branch_id" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white"></select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Recipient / Market Vendor / Source</label>
                    <input name="recipient_or_source" placeholder="e.g. Kejetia Central Market / Hajia Rabi"
                           class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Detailed Purpose & Description *</label>
                <input name="description" required placeholder="e.g. Monthly market withdrawal to buy flour, Biscoff, Nutella, cream & cooking supplies"
                       class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="LiterosApp.closeModal('cofferModal')" class="px-4 py-2 rounded-xl bg-slate-800 text-xs text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs">Save Coffers Entry</button>
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
                <p class="text-[11px] text-slate-400 mt-1">Demo PINs: <strong class="text-amber-300">1111</strong> (Hajia Rabi), <strong class="text-emerald-300">1001</strong> (Zainab), <strong class="text-indigo-300">2001</strong> (Matilda)</p>
            </div>
            <input id="quickPinInput" type="password" maxlength="8" required placeholder="••••"
                   class="w-full py-3 rounded-xl bg-slate-950 border border-white/15 text-center font-mono-num text-xl tracking-widest text-white outline-none focus:border-amber-500">
            <div class="flex gap-2">
                <button type="button" onclick="LiterosApp.closeModal('pinModal')" class="flex-1 py-2.5 rounded-xl bg-slate-800 text-xs text-slate-300">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-amber-500 text-slate-950 font-extrabold text-xs">Unlock POS</button>
            </div>
        </form>
    </div>

    <!-- 9. Full-Screen Hospitality Authentication & Operator Login Screen -->
    <div id="loginScreenOverlay" class="fixed inset-0 z-50 bg-[#07090e]/95 backdrop-blur-xl flex items-center justify-center p-4 overflow-y-auto">
        <div class="max-w-5xl w-full grid grid-cols-1 lg:grid-cols-12 rounded-3xl overflow-hidden border border-white/15 shadow-2xl bg-[#0d111a]">

            <!-- Left Column: Sign-In Form & Tactile PIN Keypad (5 Cols) -->
            <div class="lg:col-span-5 p-7 sm:p-8 flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-white/10 bg-gradient-to-b from-slate-900/90 to-slate-950">
                <div>
                    <!-- Brand Header -->
                    <div class="flex items-center justify-between gap-3 mb-6">
                        <div class="flex items-center gap-3">
                            <div id="loginBrandLogo"
                                 class="w-11 h-11 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 text-slate-950 font-extrabold text-base flex items-center justify-center shadow-lg shadow-amber-500/25">
                                SD
                            </div>
                            <div>
                                <h2 id="loginBrandName" class="text-base font-extrabold text-white tracking-tight">Shakes and Dessert</h2>
                                <p class="text-[11px] text-amber-400 font-medium">Kumasi, Ghana • Owner: Hajia Rabi Adam</p>
                            </div>
                        </div>
                        <button type="button"
                                id="loginThemeToggleBtn"
                                onclick="LiterosApp.toggleTheme()"
                                title="Switch between Dark Mode and Light Mode"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 text-[11px] font-bold text-slate-200 transition shrink-0">
                            <i id="loginThemeToggleIcon" class="fa-solid fa-sun text-amber-400"></i>
                            <span id="loginThemeToggleLabel">Light</span>
                        </button>
                    </div>

                    <form id="loginAuthForm" onsubmit="LiterosApp.submitLoginScreen(event)" class="space-y-4">
                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                                Email or Employee Code <span class="text-slate-500 font-normal">(Optional for PIN-only)</span>
                            </label>
                            <div class="relative">
                                <i class="fa-solid fa-id-card-clip absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                                <input id="loginIdentifierInput"
                                       type="text"
                                       placeholder="e.g. hajia.rabi@shakesanddessert.com.gh or SD-HQ-01"
                                       class="w-full pl-9 pr-3.5 py-2.5 rounded-xl bg-slate-950 border border-white/10 focus:border-amber-500 text-xs text-white outline-none">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                    POS PIN / Password
                                </label>
                                <button type="button" onclick="LiterosApp.clearLoginPin()" class="text-[11px] text-amber-400 hover:underline">
                                    Clear PIN
                                </button>
                            </div>
                            <div class="relative">
                                <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-amber-400 text-xs"></i>
                                <input id="loginPinInput"
                                       type="password"
                                       required
                                       maxlength="10"
                                       placeholder="Enter 4-digit PIN (e.g. 1111)"
                                       class="w-full pl-9 pr-4 py-3 rounded-xl bg-slate-950 border border-amber-500/40 focus:border-amber-400 font-mono-num text-base tracking-widest text-white outline-none">
                            </div>
                        </div>

                        <!-- Touch PIN Keypad -->
                        <div class="grid grid-cols-3 gap-2 pt-1">
                            <button type="button" onclick="LiterosApp.appendLoginPin('1')" class="py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-sm font-bold text-white transition">1</button>
                            <button type="button" onclick="LiterosApp.appendLoginPin('2')" class="py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-sm font-bold text-white transition">2</button>
                            <button type="button" onclick="LiterosApp.appendLoginPin('3')" class="py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-sm font-bold text-white transition">3</button>
                            <button type="button" onclick="LiterosApp.appendLoginPin('4')" class="py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-sm font-bold text-white transition">4</button>
                            <button type="button" onclick="LiterosApp.appendLoginPin('5')" class="py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-sm font-bold text-white transition">5</button>
                            <button type="button" onclick="LiterosApp.appendLoginPin('6')" class="py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-sm font-bold text-white transition">6</button>
                            <button type="button" onclick="LiterosApp.appendLoginPin('7')" class="py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-sm font-bold text-white transition">7</button>
                            <button type="button" onclick="LiterosApp.appendLoginPin('8')" class="py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-sm font-bold text-white transition">8</button>
                            <button type="button" onclick="LiterosApp.appendLoginPin('9')" class="py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-sm font-bold text-white transition">9</button>
                            <button type="button" onclick="LiterosApp.clearLoginPin()" class="py-2.5 rounded-xl bg-rose-950/50 hover:bg-rose-900/60 border border-rose-500/30 text-xs font-bold text-rose-300 transition">CLR</button>
                            <button type="button" onclick="LiterosApp.appendLoginPin('0')" class="py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-sm font-bold text-white transition">0</button>
                            <button type="button" onclick="LiterosApp.backspaceLoginPin()" class="py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 text-xs font-bold text-slate-300 transition"><i class="fa-solid fa-delete-left"></i></button>
                        </div>

                        <button type="submit"
                                class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-extrabold text-xs shadow-lg shadow-amber-500/25 flex items-center justify-center gap-2 transition">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            Sign In & Unlock POS Terminal
                        </button>
                    </form>
                </div>

                <div class="pt-5 mt-5 border-t border-white/10 flex items-center justify-between text-[11px] text-slate-400">
                    <span><i class="fa-solid fa-shield-halved text-emerald-400 mr-1"></i>Role-Based Shift Tracking</span>
                    <span class="font-mono-num">Kumasi • GHS (¢)</span>
                </div>
            </div>

            <!-- Right Column: Seeded Demo Credentials Directory (7 Cols) -->
            <div class="lg:col-span-7 p-7 sm:p-8 flex flex-col justify-between bg-[#090c14]">
                <div>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-white/10">
                        <div>
                            <span class="px-2.5 py-0.5 rounded-md bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-[10px] font-bold uppercase tracking-wider">
                                Shakes and Dessert • 19 Seeded Personnel
                            </span>
                            <h3 class="text-base font-bold text-white mt-1.5">Click Any Demo Account Below to Auto-Fill & Sign In</h3>
                            <p class="text-xs text-slate-400">Owner Hajia Rabi Adam + Ayiduase (Zainab, Yasmine, Aisha, Latif, Samuel, Fati, Dorithy), Sawaba (Baki, Aisha, Maabena, Fati) & Dechemso (Kudus, Matilda, Daniel, Mary, Nat, Theresah, Belinda):</p>
                        </div>
                    </div>

                    <!-- Branch Filter Pills for Login Credentials -->
                    <div id="loginBranchFilterBar" class="flex flex-wrap items-center gap-1.5 mt-3">
                        <button type="button" data-bfilter="all" onclick="LiterosApp.setLoginBranchFilter('all')" class="login-bfilter-btn px-2.5 py-1 rounded-lg bg-amber-500 text-slate-950 text-[11px] font-extrabold">All Staff (19)</button>
                        <button type="button" data-bfilter="1" onclick="LiterosApp.setLoginBranchFilter('1')" class="login-bfilter-btn px-2.5 py-1 rounded-lg bg-slate-900 border border-white/10 text-slate-300 text-[11px] font-semibold">Branch 1: Ayiduase</button>
                        <button type="button" data-bfilter="2" onclick="LiterosApp.setLoginBranchFilter('2')" class="login-bfilter-btn px-2.5 py-1 rounded-lg bg-slate-900 border border-white/10 text-slate-300 text-[11px] font-semibold">Branch 2: Sawaba</button>
                        <button type="button" data-bfilter="3" onclick="LiterosApp.setLoginBranchFilter('3')" class="login-bfilter-btn px-2.5 py-1 rounded-lg bg-slate-900 border border-white/10 text-slate-300 text-[11px] font-semibold">Branch 3: Dechemso</button>
                    </div>

                    <!-- Dynamic Demo Credentials Table / Cards -->
                    <div id="loginDemoCredentialsList" class="mt-3 space-y-2 max-h-[360px] overflow-y-auto pr-1"></div>
                </div>

                <div class="mt-5 p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/25 flex items-start gap-3 text-xs text-amber-200">
                    <i class="fa-solid fa-circle-info text-amber-400 mt-0.5"></i>
                    <div>
                        <strong>Tip:</strong> Sign in as <strong>Hajia Rabi Adam (PIN: 1111)</strong> for full Executive HQ & Multi-Branch Reports (2024–2026), or click any Branch Manager, Cashier, Waitress, or Chef row above.
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Toast Notification Stack -->
    <div id="toastContainer" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm"></div>

    <!-- Application Engine -->
    <script src="assets/js/app.js"></script>
</body>
</html>
