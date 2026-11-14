/**
 * LITEROS — Frontend Application Engine
 * Handles State, POS Cart, Modifier Dialogs, KDS Live Timers, Floor Map,
 * Menu Studio, Staff Leaderboard, Multi-Branch Switcher, and Company HQ.
 */

const LiterosApp = (() => {
    const state = {
        driver: 'mysql',
        activeTab: 'pos',
        activeBranchId: 1,
        activeStaff: null,
        company: {},
        branches: [],
        branchMetrics: [],
        categories: [],
        menuItems: [],
        staff: [],
        tables: [],
        orders: [],
        expenditures: [],
        expenditureSummary: {},
        shifts: [],

        // POS Filters & Cart
        posCategoryId: 'all',
        posSearch: '',
        cart: [],
        orderType: 'Dine-In',
        selectedTableId: '',
        customerName: 'Walk-in Guest',
        guestsCount: 2,
        discountPercent: 0,
        tipAmount: 0,
        paymentMethod: 'Cash',
        paymentStatus: 'Paid',
        orderNotes: '',

        // Floor Zone Filter
        floorZone: 'all',

        // Menu Filter
        menuCategoryFilter: 'all',
        menuSearch: '',

        // Sales History Filter
        salesStatusFilter: 'all',
        salesSearch: '',

        // Expenditures Filter
        expCategoryFilter: 'all',
        expMethodFilter: 'all',
        expSearch: '',

        // Login Credentials Branch Filter
        loginBranchFilter: 'all',

        // Active modal item for modifiers
        pendingMenuItem: null,
        selectedModifiers: []
    };

    // =========================================================================
    // ROLE-BASED ACCESS CONTROL (RBAC) MATRIX & API UTILITIES
    // =========================================================================
    const ROLE_PERMISSIONS = {
        Admin: {
            label: 'Executive Owner / Admin (Full Access)',
            tabs: ['pos', 'kds', 'tables', 'sales', 'expenditures', 'reports', 'menu', 'staff', 'branches', 'company'],
            defaultTab: 'pos',
            canSettlePayment: true,
            canApplyDiscount: true,
            canManageMenu: true,
            canAddTable: true,
            canSwitchBranch: true,
            canDeleteExpenditure: true,
            ownOrdersOnly: false
        },
        Manager: {
            label: 'Branch Manager (Branch Operations, Expenses & BI)',
            tabs: ['pos', 'kds', 'tables', 'sales', 'expenditures', 'reports', 'menu', 'staff'],
            defaultTab: 'pos',
            canSettlePayment: true,
            canApplyDiscount: true,
            canManageMenu: true,
            canAddTable: true,
            canSwitchBranch: false,
            canDeleteExpenditure: true,
            ownOrdersOnly: false
        },
        Cashier: {
            label: 'POS Cashier (Register, MoMo/Cash Billing & Expenses)',
            tabs: ['pos', 'tables', 'sales', 'expenditures'],
            defaultTab: 'pos',
            canSettlePayment: true,
            canApplyDiscount: true,
            canManageMenu: false,
            canAddTable: false,
            canSwitchBranch: false,
            canDeleteExpenditure: false,
            ownOrdersOnly: false
        },
        Waiter: {
            label: 'Waitress / Server (Tables, Order Taking & Own Checks)',
            tabs: ['pos', 'tables', 'kds', 'sales'],
            defaultTab: 'tables',
            canSettlePayment: false,
            canApplyDiscount: false,
            canManageMenu: false,
            canAddTable: false,
            canSwitchBranch: false,
            canDeleteExpenditure: false,
            ownOrdersOnly: true
        },
        Chef: {
            label: 'Kitchen Chef (KDS Tickets & 86/Stock Control)',
            tabs: ['kds', 'menu'],
            defaultTab: 'kds',
            canSettlePayment: false,
            canApplyDiscount: false,
            canManageMenu: false,
            canAddTable: false,
            canSwitchBranch: false,
            canDeleteExpenditure: false,
            ownOrdersOnly: false
        }
    };

    function getCurrentRoleConfig() {
        const role = state.activeStaff?.role || 'Waiter';
        return ROLE_PERMISSIONS[role] || ROLE_PERMISSIONS.Waiter;
    }

    async function api(action, payload = null, queryParams = '') {
        const actorId = state.activeStaff?.id || 0;
        const actorParam = actorId ? `&actor_staff_id=${actorId}` : '';
        const url = `api/index.php?action=${encodeURIComponent(action)}${actorParam}${queryParams}`;

        let bodyObj = payload;
        if (payload && typeof payload === 'object' && !payload.actor_staff_id && actorId) {
            bodyObj = { ...payload, actor_staff_id: actorId };
        }

        const options = bodyObj
            ? {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(bodyObj)
            }
            : { method: 'GET' };

        const res = await fetch(url, options);
        const data = await res.json();
        if (!res.ok || data.ok === false) {
            throw new Error(data.error || 'Request failed');
        }
        return data;
    }

    function money(val) {
        const sym = state.company?.currency_symbol || 'GHS ';
        const num = Number(val || 0);
        return `${sym}${num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function toast(msg, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const el = document.createElement('div');
        const colors = {
            success: 'border-emerald-500/40 bg-emerald-950/90 text-emerald-200',
            warning: 'border-amber-500/40 bg-amber-950/90 text-amber-200',
            error: 'border-rose-500/40 bg-rose-950/90 text-rose-200',
            info: 'border-indigo-500/40 bg-indigo-950/90 text-indigo-200'
        };
        const icons = {
            success: 'fa-circle-check text-emerald-400',
            warning: 'fa-triangle-exclamation text-amber-400',
            error: 'fa-circle-xmark text-rose-400',
            info: 'fa-bell text-indigo-400'
        };
        el.className = `flex items-center gap-3 px-4 py-3 rounded-xl border shadow-2xl backdrop-blur-md transition-all duration-300 ${colors[type] || colors.success}`;
        el.innerHTML = `
            <i class="fa-solid ${icons[type] || icons.success} text-lg"></i>
            <span class="text-sm font-medium">${escapeHtml(msg)}</span>
        `;
        container.appendChild(el);
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(8px)';
            setTimeout(() => el.remove(), 280);
        }, 3400);
    }

    function getActiveBranch() {
        return state.branches.find(b => Number(b.id) === Number(state.activeBranchId)) || state.branches[0] || {};
    }

    function getEffectiveTaxRate() {
        const branch = getActiveBranch();
        if (branch && branch.tax_override !== null && branch.tax_override !== '') {
            return Number(branch.tax_override);
        }
        return Number(state.company?.tax_rate ?? 8.25);
    }

    function getEffectiveServiceRate() {
        if (state.orderType !== 'Dine-In') return 0;
        return Number(state.company?.service_charge_rate ?? 5.0);
    }

    function minutesElapsed(createdAt) {
        if (!createdAt) return 1;
        const parsed = new Date(String(createdAt).replace(' ', 'T'));
        if (isNaN(parsed.getTime())) return 5;
        const diff = Math.floor((Date.now() - parsed.getTime()) / 60000);
        return Math.max(1, diff);
    }

    // =========================================================================
    // INITIALIZATION & BOOTSTRAP
    // =========================================================================
    async function init() {
        await loadBootstrap();
        restoreAuthSession();
        setupClock();
        setupKeyboardShortcuts();
    }

    function restoreAuthSession() {
        try {
            const savedId = localStorage.getItem('literos_auth_staff_id');
            if (savedId) {
                const found = state.staff.find(s => Number(s.id) === Number(savedId));
                if (found) {
                    state.activeStaff = found;
                    document.getElementById('loginScreenOverlay')?.classList.add('hidden');
                    renderAll();
                    return;
                }
            }
        } catch (_) {}
        // Show login screen if not signed in
        document.getElementById('loginScreenOverlay')?.classList.remove('hidden');
    }

    async function loadBootstrap(branchId = state.activeBranchId) {
        try {
            const data = await api('bootstrap', null, `&branch_id=${branchId}`);
            state.driver = data.driver || 'mysql';
            state.activeBranchId = Number(data.active_branch_id || 1);
            state.company = data.company || {};
            state.branches = data.branches || [];
            state.branchMetrics = data.branch_metrics || [];
            state.categories = data.categories || [];
            state.menuItems = data.menuItems || data.menu_items || [];
            state.staff = data.staff || [];
            state.tables = data.tables || [];
            state.orders = data.orders || [];
            state.expenditures = data.expenditures || [];
            state.expenditureSummary = data.expenditure_summary || {};
            state.shifts = data.shifts || [];

            if (!state.activeStaff && state.staff.length > 0) {
                const branchStaff = state.staff.find(s => Number(s.branch_id) === state.activeBranchId);
                state.activeStaff = branchStaff || state.staff[0];
            }

            renderAll();
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    function renderAll() {
        applyRolePermissions();
        renderTopBar();
        renderLoginCredentialsScreen();
        renderPosView();
        renderKdsView();
        renderTablesView();
        renderSalesView();
        renderExpendituresView();
        renderReportsFilterOptions();
        renderMenuStudioView();
        renderStaffView();
        renderBranchesView();
        renderCompanyView();
        updateBadgeCounts();
        if (state.activeTab === 'reports' && getCurrentRoleConfig().tabs.includes('reports')) {
            loadReportsData();
        }
    }

    function applyRolePermissions() {
        const cfg = getCurrentRoleConfig();

        // 1. Show/hide navigation buttons based on role's allowed tabs
        document.querySelectorAll('.nav-btn').forEach(btn => {
            const tab = btn.dataset.tab;
            const allowed = cfg.tabs.includes(tab);
            btn.classList.toggle('hidden', !allowed);
        });

        // Show/hide Management section header in sidebar
        const hasMgmtTabs = cfg.tabs.some(t => ['menu', 'staff', 'branches', 'company'].includes(t));
        const mgmtDivider = document.getElementById('navManagementDivider');
        if (mgmtDivider) {
            mgmtDivider.classList.toggle('hidden', !hasMgmtTabs);
        }

        // 2. If current activeTab is not allowed for this role, redirect to role's defaultTab
        if (!cfg.tabs.includes(state.activeTab)) {
            state.activeTab = cfg.defaultTab;
            document.querySelectorAll('.view-section').forEach(sec => {
                sec.classList.toggle('hidden', sec.id !== `view-${state.activeTab}`);
            });
            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === state.activeTab);
            });
        }

        // 3. Branch switcher lock for non-Admin roles (staff are locked to their assigned branch)
        const branchSelect = document.getElementById('globalBranchSelect');
        if (branchSelect) {
            branchSelect.disabled = !cfg.canSwitchBranch;
            branchSelect.title = cfg.canSwitchBranch
                ? 'Switch active branch'
                : `Branch locked to your assigned location (${state.activeStaff?.role || 'Staff'})`;
        }

        // 4. POS Quick Dish button & Pay/Settle button visibility
        const quickDishBtn = document.getElementById('btnPosQuickDish');
        if (quickDishBtn) {
            quickDishBtn.classList.toggle('hidden', !cfg.canManageMenu);
        }

        const payBtn = document.getElementById('btnPosPayAndSettle');
        const sendKitchenBtn = document.getElementById('btnPosSendToKitchen');
        if (payBtn && sendKitchenBtn) {
            if (!cfg.canSettlePayment) {
                payBtn.classList.add('hidden');
                sendKitchenBtn.classList.add('col-span-2');
            } else {
                payBtn.classList.remove('hidden');
                sendKitchenBtn.classList.remove('col-span-2');
            }
        }

        // 5. Add Floor Table button visibility
        const addTableBtn = document.getElementById('btnAddFloorTable');
        if (addTableBtn) {
            addTableBtn.classList.toggle('hidden', !cfg.canAddTable);
        }

        // 6. Menu Studio Add Dish button visibility (Chefs can 86/restock but not create/delete dishes)
        const menuAddBtn = document.getElementById('btnMenuStudioAddDish');
        if (menuAddBtn) {
            menuAddBtn.classList.toggle('hidden', !cfg.canManageMenu);
        }
    }

    // =========================================================================
    // TOP BAR & NAVIGATION
    // =========================================================================
    function renderTopBar() {
        const comp = state.company;
        const branch = getActiveBranch();

        document.getElementById('brandLogoText').textContent = comp.logo_text || 'LR';
        document.getElementById('brandCompanyName').textContent = comp.name || 'Literos Hospitality';
        document.getElementById('brandTagline').textContent = comp.tagline || 'Restaurant OS';

        // Driver badge
        const dbBadge = document.getElementById('dbEngineBadge');
        if (dbBadge) {
            dbBadge.innerHTML = state.driver === 'mysql'
                ? `<span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> MySQL 8 Connected`
                : `<span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span> PDO Local Active`;
        }

        // Branch selector dropdown
        const branchSelect = document.getElementById('globalBranchSelect');
        if (branchSelect) {
            branchSelect.innerHTML = state.branches.map(b => `
                <option value="${b.id}" ${Number(b.id) === state.activeBranchId ? 'selected' : ''}>
                    ${escapeHtml(b.code)} — ${escapeHtml(b.name)} (${escapeHtml(b.city)})
                </option>
            `).join('');
        }

        // Active Staff Pill
        const staffPill = document.getElementById('activeStaffPill');
        if (staffPill && state.activeStaff) {
            const st = state.activeStaff;
            staffPill.innerHTML = `
                <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold text-slate-950"
                      style="background:${escapeHtml(st.avatar_color || '#f59e0b')}">
                    ${escapeHtml(st.full_name.slice(0, 2).toUpperCase())}
                </span>
                <div class="text-left leading-tight">
                    <div class="text-xs font-semibold text-white">${escapeHtml(st.full_name)}</div>
                    <div class="text-[10px] text-amber-400 font-medium">${escapeHtml(st.role)} • PIN ${escapeHtml(st.pin_code)}</div>
                </div>
            `;
        }
    }

    function switchTab(tabName) {
        const cfg = getCurrentRoleConfig();
        if (!cfg.tabs.includes(tabName)) {
            toast(`Access Denied: ${state.activeStaff?.role || 'Your role'} cannot access that module.`, 'error');
            return;
        }
        state.activeTab = tabName;
        document.querySelectorAll('.view-section').forEach(sec => {
            sec.classList.toggle('hidden', sec.id !== `view-${tabName}`);
        });
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });
        if (tabName === 'reports') {
            loadReportsData();
        }
    }

    async function switchBranch(branchId) {
        const cfg = getCurrentRoleConfig();
        if (!cfg.canSwitchBranch) {
            toast('Access Denied: Only Executive Admins can switch across branches.', 'error');
            renderTopBar();
            return;
        }
        state.activeBranchId = Number(branchId);
        state.selectedTableId = '';
        await loadBootstrap(state.activeBranchId);
        const branch = getActiveBranch();
        toast(`Switched terminal to ${branch.name} (${branch.city})`, 'info');
    }

    function updateBadgeCounts() {
        const activeKdsCount = state.orders.filter(o => ['Pending', 'Preparing', 'Ready'].includes(o.status)).length;
        const occupiedTables = state.tables.filter(t => t.status === 'Occupied').length;

        const kdsBadge = document.getElementById('navBadgeKds');
        if (kdsBadge) {
            kdsBadge.textContent = activeKdsCount;
            kdsBadge.classList.toggle('hidden', activeKdsCount === 0);
        }

        const tblBadge = document.getElementById('navBadgeTables');
        if (tblBadge) {
            tblBadge.textContent = `${occupiedTables}/${state.tables.length}`;
        }
    }

    // =========================================================================
    // 1. POS TERMINAL & SMART CART
    // =========================================================================
    function renderPosView() {
        // Categories Bar
        const catContainer = document.getElementById('posCategoriesBar');
        if (catContainer) {
            const allCount = state.menuItems.filter(m => Number(m.is_available) === 1).length;
            let html = `
                <button onclick="LiterosApp.setPosCategory('all')"
                        class="cat-pill px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 shrink-0 ${state.posCategoryId === 'all' ? 'active' : 'bg-slate-900/80 text-slate-300 hover:text-white'}">
                    <i class="fa-solid fa-border-all"></i>
                    <span>All Dishes</span>
                    <span class="px-1.5 py-0.5 text-[10px] rounded-md bg-black/25 font-mono-num">${allCount}</span>
                </button>
                <button onclick="LiterosApp.setPosCategory('popular')"
                        class="cat-pill px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 shrink-0 ${state.posCategoryId === 'popular' ? 'active' : 'bg-slate-900/80 text-slate-300 hover:text-white'}">
                    <i class="fa-solid fa-fire text-amber-400"></i>
                    <span>Chef Favorites</span>
                </button>
            `;
            state.categories.forEach(cat => {
                const count = state.menuItems.filter(m => Number(m.category_id) === Number(cat.id)).length;
                const active = String(state.posCategoryId) === String(cat.id);
                html += `
                    <button onclick="LiterosApp.setPosCategory(${cat.id})"
                            class="cat-pill px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 shrink-0 ${active ? 'active' : 'bg-slate-900/80 text-slate-300 hover:text-white'}">
                        <i class="fa-solid ${escapeHtml(cat.icon)}"></i>
                        <span>${escapeHtml(cat.name)}</span>
                        <span class="px-1.5 py-0.5 text-[10px] rounded-md bg-black/25 font-mono-num">${count}</span>
                    </button>
                `;
            });
            catContainer.innerHTML = html;
        }

        // Menu Grid
        const grid = document.getElementById('posMenuGrid');
        if (grid) {
            const q = state.posSearch.toLowerCase().trim();
            const filtered = state.menuItems.filter(item => {
                if (state.posCategoryId === 'popular' && Number(item.is_popular) !== 1) return false;
                if (state.posCategoryId !== 'all' && state.posCategoryId !== 'popular' && Number(item.category_id) !== Number(state.posCategoryId)) {
                    return false;
                }
                if (q && !item.name.toLowerCase().includes(q) && !item.sku.toLowerCase().includes(q) && !item.description.toLowerCase().includes(q)) {
                    return false;
                }
                return true;
            });

            if (filtered.length === 0) {
                grid.innerHTML = `
                    <div class="col-span-full py-16 text-center text-slate-400">
                        <i class="fa-solid fa-utensils text-3xl mb-3 text-slate-600"></i>
                        <p class="text-sm">No dishes match your filter. Try clearing search or adding a dish in Menu Studio.</p>
                    </div>
                `;
            } else {
                grid.innerHTML = filtered.map(item => {
                    const soldOut = Number(item.is_available) === 0 || Number(item.stock_qty) <= 0;
                    const lowStock = Number(item.stock_qty) <= Number(item.low_stock_threshold) && !soldOut;
                    const hasMods = Array.isArray(item.modifiers) && item.modifiers.length > 0;

                    return `
                        <div onclick="${soldOut ? '' : `LiterosApp.handleDishClick(${item.id})`}"
                             class="pos-card rounded-2xl p-4 flex flex-col justify-between cursor-pointer relative overflow-hidden ${soldOut ? 'opacity-45 cursor-not-allowed' : ''}">
                            <div>
                                <div class="flex items-start justify-between gap-2 mb-2.5">
                                    <span class="text-3xl select-none">${escapeHtml(item.image_emoji || '🍽️')}</span>
                                    <div class="flex flex-col items-end gap-1">
                                        <span class="font-mono-num text-sm font-bold text-amber-400 bg-amber-500/10 border border-amber-500/25 px-2.5 py-0.5 rounded-lg">
                                            ${money(item.price)}
                                        </span>
                                        <span class="text-[10px] font-mono-num text-slate-400">${escapeHtml(item.sku)}</span>
                                    </div>
                                </div>
                                <h4 class="font-semibold text-sm text-white leading-snug mb-1">${escapeHtml(item.name)}</h4>
                                <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed mb-3">${escapeHtml(item.description)}</p>
                            </div>
                            <div class="flex items-center justify-between pt-2.5 border-t border-white/5 text-[11px]">
                                <span class="flex items-center gap-1.5 ${lowStock ? 'text-rose-400 font-semibold' : 'text-slate-400'}">
                                    <i class="fa-solid fa-cubes-stacked text-[10px]"></i>
                                    ${soldOut ? '86\'d / Sold Out' : `${item.stock_qty} left`}
                                </span>
                                <div class="flex items-center gap-1.5">
                                    ${Number(item.is_popular) === 1 ? '<span class="px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-300 text-[10px] font-semibold">★ Popular</span>' : ''}
                                    ${hasMods ? `<button onclick="event.stopPropagation(); LiterosApp.openModifierModal(${item.id})" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 text-[10px] border border-white/10" title="Customize Modifiers"><i class="fa-solid fa-sliders mr-1"></i>Mods</button>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // Table dropdown in POS Ticket
        const tableSelect = document.getElementById('posTableSelect');
        if (tableSelect) {
            tableSelect.innerHTML = `<option value="">-- Counter / Walk-in --</option>` +
                state.tables.map(t => `
                    <option value="${t.id}" ${String(t.id) === String(state.selectedTableId) ? 'selected' : ''}>
                        ${escapeHtml(t.table_number)} (${escapeHtml(t.zone)} • ${t.capacity}p) [${escapeHtml(t.status)}]
                    </option>
                `).join('');
        }

        renderCartTicket();
    }

    function setPosCategory(catId) {
        state.posCategoryId = catId;
        renderPosView();
    }

    function setPosSearch(val) {
        state.posSearch = val;
        renderPosView();
    }

    function handleDishClick(menuId) {
        const item = state.menuItems.find(m => Number(m.id) === Number(menuId));
        if (!item) return;
        addToCart(item, '', 0);
    }

    function openModifierModal(menuId) {
        const item = state.menuItems.find(m => Number(m.id) === Number(menuId));
        if (!item) return;
        state.pendingMenuItem = item;
        state.selectedModifiers = [];

        document.getElementById('modModalDishTitle').textContent = `${item.image_emoji} ${item.name}`;
        document.getElementById('modModalDishPrice').textContent = money(item.price);
        document.getElementById('modCustomNote').value = '';

        const list = document.getElementById('modOptionsList');
        const mods = Array.isArray(item.modifiers) ? item.modifiers : [];
        list.innerHTML = mods.map((m, idx) => `
            <label class="flex items-center justify-between p-3 rounded-xl bg-slate-900/90 border border-white/10 cursor-pointer hover:border-amber-500/40">
                <div class="flex items-center gap-2.5">
                    <input type="checkbox" value="${escapeHtml(m)}" onchange="LiterosApp.toggleModifierOption(this.value)"
                           class="w-4 h-4 accent-amber-500 rounded">
                    <span class="text-sm text-slate-200">${escapeHtml(m)}</span>
                </div>
            </label>
        `).join('');

        document.getElementById('modifierModal').classList.remove('hidden');
    }

    function toggleModifierOption(modStr) {
        const idx = state.selectedModifiers.indexOf(modStr);
        if (idx >= 0) {
            state.selectedModifiers.splice(idx, 1);
        } else {
            state.selectedModifiers.push(modStr);
        }
    }

    function confirmModifierModal() {
        if (!state.pendingMenuItem) return;
        const customNote = document.getElementById('modCustomNote').value.trim();
        const allMods = [...state.selectedModifiers];
        if (customNote) allMods.push(customNote);

        // Parse any (+$X) or (+GHS X) or (+¢X) upcharge inside selected modifiers
        let extraPrice = 0;
        allMods.forEach(m => {
            const match = m.match(/\+(?:\$|GHS\s*|¢\s*)(\d+(?:\.\d+)?)/i);
            if (match) extraPrice += parseFloat(match[1]);
        });

        addToCart(state.pendingMenuItem, allMods.join(', '), extraPrice);
        closeModal('modifierModal');
    }

    function addToCart(item, modifiers = '', extraPrice = 0) {
        const key = `${item.id}::${modifiers}`;
        const existing = state.cart.find(c => c.key === key);
        if (existing) {
            existing.quantity += 1;
        } else {
            state.cart.push({
                key,
                menu_item_id: Number(item.id),
                name: item.name,
                emoji: item.image_emoji || '🍽️',
                unit_price: Number(item.price) + extraPrice,
                extra_price: extraPrice,
                quantity: 1,
                modifiers
            });
        }
        renderCartTicket();
    }

    function updateCartQty(key, delta) {
        const idx = state.cart.findIndex(c => c.key === key);
        if (idx === -1) return;
        state.cart[idx].quantity += delta;
        if (state.cart[idx].quantity <= 0) {
            state.cart.splice(idx, 1);
        }
        renderCartTicket();
    }

    function clearCart() {
        state.cart = [];
        state.discountPercent = 0;
        state.tipAmount = 0;
        state.orderNotes = '';
        renderCartTicket();
    }

    function setOrderType(type) {
        state.orderType = type;
        document.querySelectorAll('.order-type-btn').forEach(btn => {
            const active = btn.dataset.type === type;
            btn.className = `order-type-btn flex-1 py-2 px-3 rounded-xl text-xs font-semibold transition-all ${
                active
                    ? 'bg-amber-500 text-slate-950 font-bold shadow-lg shadow-amber-500/20'
                    : 'bg-slate-900/70 text-slate-400 hover:text-white'
            }`;
        });
        renderCartTicket();
    }

    function setDiscountPercent(pct) {
        state.discountPercent = Number(pct);
        renderCartTicket();
    }

    function setQuickTip(amount) {
        state.tipAmount = Number(amount);
        renderCartTicket();
    }

    function getCartTotals() {
        const subtotal = state.cart.reduce((sum, i) => sum + i.unit_price * i.quantity, 0);
        const discountAmount = +(subtotal * (state.discountPercent / 100)).toFixed(2);
        const taxable = Math.max(0, subtotal - discountAmount);
        const taxRate = getEffectiveTaxRate();
        const serviceRate = getEffectiveServiceRate();
        const taxAmount = +(taxable * (taxRate / 100)).toFixed(2);
        const serviceCharge = +(taxable * (serviceRate / 100)).toFixed(2);
        const total = +(taxable + taxAmount + serviceCharge + state.tipAmount).toFixed(2);

        return { subtotal, discountAmount, taxRate, taxAmount, serviceRate, serviceCharge, tipAmount: state.tipAmount, total };
    }

    function renderCartTicket() {
        const listEl = document.getElementById('posCartItemsList');
        const countEl = document.getElementById('posCartCountBadge');
        const totalItems = state.cart.reduce((acc, i) => acc + i.quantity, 0);
        if (countEl) countEl.textContent = `${totalItems} item${totalItems === 1 ? '' : 's'}`;

        if (listEl) {
            if (state.cart.length === 0) {
                listEl.innerHTML = `
                    <div class="py-12 text-center text-slate-500">
                        <div class="w-12 h-12 rounded-2xl bg-slate-900/80 border border-white/5 flex items-center justify-center mx-auto mb-3">
                            <i class="fa-solid fa-receipt text-slate-600 text-lg"></i>
                        </div>
                        <p class="text-xs font-medium text-slate-400">Ticket is empty</p>
                        <p class="text-[11px] text-slate-500 mt-1">Tap any dish card on the left to build an order</p>
                    </div>
                `;
            } else {
                listEl.innerHTML = state.cart.map(item => `
                    <div class="p-3 rounded-xl bg-slate-900/85 border border-white/5 flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span>${escapeHtml(item.emoji)}</span>
                                <span class="text-xs font-semibold text-white truncate">${escapeHtml(item.name)}</span>
                            </div>
                            ${item.modifiers ? `<div class="text-[11px] text-amber-400/90 mt-0.5">↳ ${escapeHtml(item.modifiers)}</div>` : ''}
                            <div class="text-[11px] text-slate-400 font-mono-num mt-1">${money(item.unit_price)} each</div>
                        </div>
                        <div class="flex flex-col items-end gap-1.5">
                            <div class="flex items-center gap-1 bg-slate-950 rounded-lg p-0.5 border border-white/10">
                                <button onclick="LiterosApp.updateCartQty('${escapeHtml(item.key)}', -1)"
                                        class="w-6 h-6 rounded-md hover:bg-slate-800 text-slate-300 text-xs flex items-center justify-center">
                                    <i class="fa-solid fa-minus text-[10px]"></i>
                                </button>
                                <span class="w-6 text-center font-mono-num text-xs font-bold text-white">${item.quantity}</span>
                                <button onclick="LiterosApp.updateCartQty('${escapeHtml(item.key)}', 1)"
                                        class="w-6 h-6 rounded-md hover:bg-amber-500 hover:text-slate-950 text-slate-300 text-xs flex items-center justify-center">
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                </button>
                            </div>
                            <span class="font-mono-num text-xs font-bold text-emerald-400">${money(item.unit_price * item.quantity)}</span>
                        </div>
                    </div>
                `).join('');
            }
        }

        const totals = getCartTotals();
        document.getElementById('cartSubtotal').textContent = money(totals.subtotal);
        document.getElementById('cartDiscount').textContent = `-${money(totals.discountAmount)}`;
        document.getElementById('cartTaxLabel').textContent = `Tax (${totals.taxRate}%)`;
        document.getElementById('cartTax').textContent = money(totals.taxAmount);
        document.getElementById('cartServiceLabel').textContent = `Service (${totals.serviceRate}%)`;
        document.getElementById('cartService').textContent = money(totals.serviceCharge);
        document.getElementById('cartTip').textContent = money(totals.tipAmount);
        document.getElementById('cartGrandTotal').textContent = money(totals.total);
    }

    function openCheckoutModal() {
        if (state.cart.length === 0) {
            toast('Add at least one dish to the ticket before firing order', 'warning');
            return;
        }
        const totals = getCartTotals();
        document.getElementById('checkoutModalTotal').textContent = money(totals.total);
        document.getElementById('checkoutTenderedInput').value = totals.total.toFixed(2);
        document.getElementById('checkoutChangeDue').textContent = money(0);
        document.getElementById('checkoutSplitPerPerson').textContent = money(totals.total / Math.max(1, state.guestsCount));
        document.getElementById('checkoutModal').classList.remove('hidden');
    }

    function updateTenderedCalculation() {
        const totals = getCartTotals();
        const tendered = parseFloat(document.getElementById('checkoutTenderedInput').value || 0);
        const change = Math.max(0, tendered - totals.total);
        document.getElementById('checkoutChangeDue').textContent = money(change);
    }

    function selectPaymentMethod(method) {
        state.paymentMethod = method;
        document.querySelectorAll('.pay-method-btn').forEach(btn => {
            const active = btn.dataset.method === method;
            btn.className = `pay-method-btn p-3 rounded-xl border text-xs font-semibold flex flex-col items-center gap-1.5 transition-all ${
                active
                    ? 'bg-amber-500/20 border-amber-500 text-amber-300'
                    : 'bg-slate-900 border-white/10 text-slate-400 hover:text-white'
            }`;
        });
    }

    async function submitOrder(paymentStatus = 'Paid') {
        if (state.cart.length === 0) {
            toast('Please add items to the ticket first', 'warning');
            return;
        }

        const totals = getCartTotals();
        const tableSelect = document.getElementById('posTableSelect');
        const customerInput = document.getElementById('posCustomerName');
        const notesInput = document.getElementById('posOrderNotes');

        const payload = {
            branch_id: state.activeBranchId,
            table_id: tableSelect?.value ? Number(tableSelect.value) : null,
            staff_id: state.activeStaff?.id || 1,
            customer_name: customerInput?.value || 'Walk-in Guest',
            guests_count: state.guestsCount,
            order_type: state.orderType,
            payment_status: paymentStatus,
            payment_method: state.paymentMethod,
            discount_amount: totals.discountAmount,
            tip_amount: totals.tipAmount,
            amount_tendered: parseFloat(document.getElementById('checkoutTenderedInput')?.value || totals.total),
            notes: notesInput?.value || '',
            items: state.cart.map(c => ({
                menu_item_id: c.menu_item_id,
                quantity: c.quantity,
                modifiers: c.modifiers,
                extra_price: c.extra_price
            }))
        };

        try {
            const res = await api('create_order', payload);
            closeModal('checkoutModal');
            clearCart();
            if (notesInput) notesInput.value = '';
            await loadBootstrap(state.activeBranchId);
            toast(res.message, 'success');

            // Open thermal receipt preview for the newly created order
            if (res.order_id) {
                viewReceipt(res.order_id);
            }
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 2. KITCHEN DISPLAY SYSTEM (KDS)
    // =========================================================================
    function renderKdsView() {
        const container = document.getElementById('kdsTicketsGrid');
        if (!container) return;

        const activeTickets = state.orders.filter(o => ['Pending', 'Preparing', 'Ready'].includes(o.status));

        document.getElementById('kdsStatCooking').textContent = activeTickets.filter(o => o.status === 'Preparing' || o.status === 'Pending').length;
        document.getElementById('kdsStatReady').textContent = activeTickets.filter(o => o.status === 'Ready').length;

        if (activeTickets.length === 0) {
            container.innerHTML = `
                <div class="col-span-full py-20 text-center glass-panel rounded-2xl">
                    <i class="fa-solid fa-kitchen-set text-4xl text-emerald-400 mb-3"></i>
                    <h3 class="text-lg font-bold text-white">All Kitchen Tickets Cleared!</h3>
                    <p class="text-xs text-slate-400 mt-1">New orders fired from the POS Terminal will appear here instantaneously.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = activeTickets.map(ord => {
            const mins = minutesElapsed(ord.created_at);
            const isUrgent = mins >= 20 && ord.status !== 'Ready';
            const statusColors = {
                Pending: 'bg-amber-500/20 text-amber-300 border-amber-500/40',
                Preparing: 'bg-rose-500/20 text-rose-300 border-rose-500/40',
                Ready: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
            };

            return `
                <div class="glass-panel rounded-2xl p-5 flex flex-col justify-between border-2 ${isUrgent ? 'kds-urgent border-rose-500/60' : 'border-white/10'}">
                    <div>
                        <div class="flex items-start justify-between gap-2 pb-3 border-b border-white/10">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono-num text-base font-extrabold text-white">${escapeHtml(ord.order_number)}</span>
                                    <span class="px-2 py-0.5 rounded-md text-[11px] font-bold border ${statusColors[ord.status] || ''}">
                                        ${escapeHtml(ord.status.toUpperCase())}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-300 font-semibold mt-1">
                                    ${ord.table_number ? `<i class="fa-solid fa-chair text-amber-400 mr-1"></i>Table ${escapeHtml(ord.table_number)}` : escapeHtml(ord.order_type)}
                                    • ${escapeHtml(ord.customer_name)}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="px-2.5 py-1 rounded-lg font-mono-num text-xs font-bold ${isUrgent ? 'bg-rose-500 text-white' : 'bg-slate-800 text-amber-300'}">
                                    <i class="fa-regular fa-clock mr-1"></i>${mins}m
                                </span>
                                <div class="text-[10px] text-slate-400 mt-1">Server: ${escapeHtml(ord.staff_name || 'Staff')}</div>
                            </div>
                        </div>

                        ${ord.notes ? `
                            <div class="mt-3 p-2.5 rounded-xl bg-amber-500/15 border border-amber-500/30 text-xs text-amber-200 flex items-start gap-2">
                                <i class="fa-solid fa-circle-exclamation text-amber-400 mt-0.5"></i>
                                <span>${escapeHtml(ord.notes)}</span>
                            </div>
                        ` : ''}

                        <ul class="mt-3 space-y-2">
                            ${(ord.items || []).map(item => `
                                <li class="flex items-start justify-between gap-2 py-1.5 border-b border-white/5 last:border-none">
                                    <div>
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-amber-500/20 text-amber-300 font-mono-num text-xs font-bold mr-2">
                                            ${item.quantity}x
                                        </span>
                                        <span class="text-sm font-semibold text-white">${escapeHtml(item.item_name)}</span>
                                        ${item.modifiers ? `<div class="text-xs text-rose-300 ml-8 mt-0.5">★ ${escapeHtml(item.modifiers)}</div>` : ''}
                                    </div>
                                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-300">${escapeHtml(item.kitchen_status)}</span>
                                </li>
                            `).join('')}
                        </ul>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-4 mt-4 border-t border-white/10">
                        ${ord.status !== 'Ready' ? `
                            <button onclick="LiterosApp.updateOrderStatus(${ord.id}, 'Ready')"
                                    class="col-span-2 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs flex items-center justify-center gap-2 transition">
                                <i class="fa-solid fa-bell-concierge"></i>
                                Mark Order Ready to Serve
                            </button>
                        ` : `
                            <button onclick="LiterosApp.updateOrderStatus(${ord.id}, 'Completed')"
                                    class="col-span-2 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs flex items-center justify-center gap-2 transition">
                                <i class="fa-solid fa-check-double"></i>
                                Bump & Complete Ticket
                            </button>
                        `}
                    </div>
                </div>
            `;
        }).join('');
    }

    async function updateOrderStatus(orderId, status) {
        try {
            const res = await api('update_order_status', { order_id: orderId, status });
            await loadBootstrap(state.activeBranchId);
            toast(res.message, 'success');
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 3. INTERACTIVE FLOOR PLAN & TABLES
    // =========================================================================
    function renderTablesView() {
        const grid = document.getElementById('floorTablesGrid');
        if (!grid) return;

        const filtered = state.tables.filter(t => state.floorZone === 'all' || t.zone === state.floorZone);

        const counts = {
            Available: state.tables.filter(t => t.status === 'Available').length,
            Occupied: state.tables.filter(t => t.status === 'Occupied').length,
            Reserved: state.tables.filter(t => t.status === 'Reserved').length,
            Cleaning: state.tables.filter(t => t.status === 'Needs Cleaning').length
        };
        document.getElementById('floorCountAvailable').textContent = counts.Available;
        document.getElementById('floorCountOccupied').textContent = counts.Occupied;
        document.getElementById('floorCountReserved').textContent = counts.Reserved;
        document.getElementById('floorCountCleaning').textContent = counts.Cleaning;

        grid.innerHTML = filtered.map(t => {
            const styles = {
                'Available': 'border-emerald-500/40 bg-emerald-950/20 hover:border-emerald-400',
                'Occupied': 'border-rose-500/50 bg-rose-950/25 hover:border-rose-400',
                'Reserved': 'border-amber-500/50 bg-amber-950/25 hover:border-amber-400',
                'Needs Cleaning': 'border-indigo-500/40 bg-indigo-950/25 hover:border-indigo-400'
            };
            const dotColors = {
                'Available': 'bg-emerald-400',
                'Occupied': 'bg-rose-500 animate-pulse',
                'Reserved': 'bg-amber-400',
                'Needs Cleaning': 'bg-indigo-400'
            };

            return `
                <div class="table-node rounded-2xl p-5 border-2 flex flex-col justify-between ${styles[t.status] || styles.Available}">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="px-2.5 py-1 rounded-lg bg-slate-900/90 border border-white/10 font-mono-num text-sm font-extrabold text-white">
                                ${escapeHtml(t.table_number)}
                            </span>
                            <span class="flex items-center gap-1.5 text-xs font-semibold text-slate-200">
                                <span class="w-2 h-2 rounded-full ${dotColors[t.status] || 'bg-emerald-400'}"></span>
                                ${escapeHtml(t.status)}
                            </span>
                        </div>
                        <div class="text-xs text-slate-400 flex items-center gap-3 mb-3">
                            <span><i class="fa-solid fa-location-dot mr-1 text-slate-500"></i>${escapeHtml(t.zone)}</span>
                            <span><i class="fa-solid fa-users mr-1 text-slate-500"></i>Cap: ${t.capacity}p</span>
                        </div>

                        ${t.active_order ? `
                            <div class="p-3 rounded-xl bg-slate-950/85 border border-white/10 mb-3">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-mono-num font-bold text-amber-400">${escapeHtml(t.active_order.order_number)}</span>
                                    <span class="font-mono-num font-bold text-emerald-400">${money(t.active_order.total_amount)}</span>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1 truncate">${escapeHtml(t.active_order.customer_name)}</div>
                            </div>
                        ` : `
                            <div class="p-3 rounded-xl bg-slate-950/40 border border-white/5 mb-3 text-[11px] text-slate-500">
                                Ready for seating (${t.capacity} seats)
                            </div>
                        `}
                    </div>

                    <div class="flex items-center gap-1.5 pt-2 border-t border-white/10">
                        <button onclick="LiterosApp.openPosForTable(${t.id}, '${escapeHtml(t.table_number)}', ${t.capacity})"
                                class="flex-1 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                            <i class="fa-solid fa-cash-register mr-1"></i>Order
                        </button>
                        <select onchange="LiterosApp.changeTableStatus(${t.id}, this.value, ${t.capacity})"
                                class="bg-slate-900 border border-white/15 rounded-xl px-2 py-2 text-xs text-slate-200 outline-none">
                            <option value="Available" ${t.status === 'Available' ? 'selected' : ''}>Available</option>
                            <option value="Occupied" ${t.status === 'Occupied' ? 'selected' : ''}>Occupied</option>
                            <option value="Reserved" ${t.status === 'Reserved' ? 'selected' : ''}>Reserved</option>
                            <option value="Needs Cleaning" ${t.status === 'Needs Cleaning' ? 'selected' : ''}>Cleaning</option>
                        </select>
                    </div>
                </div>
            `;
        }).join('');
    }

    function setFloorZone(zone) {
        state.floorZone = zone;
        document.querySelectorAll('.floor-zone-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.zone === zone);
        });
        renderTablesView();
    }

    function openPosForTable(tableId, tableNum, cap) {
        state.selectedTableId = tableId;
        state.orderType = 'Dine-In';
        state.guestsCount = cap;
        const custInput = document.getElementById('posCustomerName');
        if (custInput) custInput.value = `Table ${tableNum} Guests`;
        switchTab('pos');
        renderPosView();
        toast(`Selected Table ${tableNum} in POS Terminal`, 'info');
    }

    async function changeTableStatus(tableId, status, cap) {
        try {
            await api('update_table_status', {
                table_id: tableId,
                status,
                current_guests: status === 'Occupied' ? cap : 0,
                assigned_staff_id: state.activeStaff?.id || null
            });
            await loadBootstrap(state.activeBranchId);
            toast(`Table status updated to ${status}`);
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function submitNewTable(e) {
        e.preventDefault();
        const form = e.target;
        const payload = {
            branch_id: state.activeBranchId,
            table_number: form.table_number.value,
            zone: form.zone.value,
            capacity: Number(form.capacity.value)
        };
        try {
            const res = await api('save_table', payload);
            form.reset();
            closeModal('tableModal');
            await loadBootstrap(state.activeBranchId);
            toast(res.message);
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 4. SALES LEDGER, ANALYTICS & THERMAL RECEIPTS
    // =========================================================================
    function renderSalesView() {
        const cfg = getCurrentRoleConfig();
        const myId = Number(state.activeStaff?.id || 0);

        // Waiters only see their own orders & tips; Cashiers/Managers/Admins see branch orders
        const scopedOrders = cfg.ownOrdersOnly
            ? state.orders.filter(o => Number(o.staff_id) === myId)
            : state.orders;

        const validOrders = scopedOrders.filter(o => o.status !== 'Cancelled');
        const grossRev = validOrders.reduce((s, o) => s + Number(o.total_amount), 0);
        const totalTax = validOrders.reduce((s, o) => s + Number(o.tax_amount), 0);
        const totalTips = validOrders.reduce((s, o) => s + Number(o.tip_amount), 0);
        const avgTicket = validOrders.length > 0 ? grossRev / validOrders.length : 0;

        document.getElementById('metricGrossRevenue').textContent = money(grossRev);
        document.getElementById('metricAvgTicket').textContent = money(avgTicket);
        document.getElementById('metricTotalOrders').textContent = validOrders.length;
        document.getElementById('metricTotalTips').textContent = `${money(totalTips)} tips • ${money(totalTax)} tax`;

        // Payment Method Breakdown Bars
        const breakdownEl = document.getElementById('paymentMethodBreakdown');
        if (breakdownEl) {
            const methods = ['Cash', 'MoMo', 'QR Pay', 'Card', 'Split'];
            breakdownEl.innerHTML = methods.map(m => {
                const sum = validOrders.filter(o => o.payment_method === m).reduce((acc, o) => acc + Number(o.total_amount), 0);
                const pct = grossRev > 0 ? Math.round((sum / grossRev) * 100) : 0;
                const barColor = m === 'MoMo' ? 'bg-yellow-400' : (m === 'Cash' ? 'bg-emerald-400' : 'bg-amber-500');
                return `
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-medium text-slate-300">${m === 'MoMo' ? 'MoMo (Mobile Money)' : m}</span>
                            <span class="font-mono-num text-slate-400">${money(sum)} (${pct}%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                            <div class="h-full ${barColor} rounded-full" style="width:${pct}%"></div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Orders Table
        const tbody = document.getElementById('salesTableBody');
        if (!tbody) return;

        const q = state.salesSearch.toLowerCase().trim();
        const filtered = scopedOrders.filter(o => {
            if (state.salesStatusFilter !== 'all' && o.status !== state.salesStatusFilter) return false;
            if (q && !o.order_number.toLowerCase().includes(q) && !o.customer_name.toLowerCase().includes(q) && !(o.staff_name || '').toLowerCase().includes(q)) {
                return false;
            }
            return true;
        });

        tbody.innerHTML = filtered.map(o => {
            const statusBadges = {
                Preparing: 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                Ready: 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30',
                Completed: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                Cancelled: 'bg-rose-500/15 text-rose-300 border-rose-500/30'
            };
            const payBadge = o.payment_status === 'Paid'
                ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'
                : 'bg-rose-500/20 text-rose-300 border-rose-500/40';

            return `
                <tr class="border-b border-white/5 hover:bg-white/[0.02] transition">
                    <td class="py-3.5 px-4 font-mono-num text-xs font-bold text-amber-400">${escapeHtml(o.order_number)}</td>
                    <td class="py-3.5 px-4">
                        <div class="text-xs font-semibold text-white">${escapeHtml(o.customer_name)}</div>
                        <div class="text-[11px] text-slate-400">${escapeHtml(o.order_type)} ${o.table_number ? `• Table ${escapeHtml(o.table_number)}` : ''}</div>
                    </td>
                    <td class="py-3.5 px-4 text-xs text-slate-300">${escapeHtml(o.staff_name || 'Staff')}</td>
                    <td class="py-3.5 px-4">
                        <span class="px-2.5 py-0.5 rounded-md text-[11px] font-semibold border ${statusBadges[o.status] || 'bg-slate-800 text-slate-300'}">
                            ${escapeHtml(o.status)}
                        </span>
                    </td>
                    <td class="py-3.5 px-4">
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold border ${payBadge}">
                            ${escapeHtml(o.payment_status)} (${escapeHtml(o.payment_method)})
                        </span>
                    </td>
                    <td class="py-3.5 px-4 font-mono-num text-xs font-bold text-white text-right">${money(o.total_amount)}</td>
                    <td class="py-3.5 px-4 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <button onclick="LiterosApp.viewReceipt(${o.id})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs border border-white/10"
                                    title="Print / View Thermal Receipt">
                                <i class="fa-solid fa-print mr-1"></i>Receipt
                            </button>
                            ${(o.payment_status === 'Unpaid' && cfg.canSettlePayment) ? `
                                <button onclick="LiterosApp.quickSettleOrder(${o.id})"
                                        class="px-2.5 py-1 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs">
                                    Pay
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    async function quickSettleOrder(orderId) {
        try {
            const res = await api('settle_order_payment', { order_id: orderId, payment_method: 'MoMo', tip_amount: 20 });
            await loadBootstrap(state.activeBranchId);
            toast(res.message, 'success');
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 4A-EXP. OPERATING EXPENDITURES MODULE
    // =========================================================================
    function renderExpendituresView() {
        const cfg = getCurrentRoleConfig();
        const branchSel = document.getElementById('expFormBranchSelect');
        if (branchSel) {
            branchSel.innerHTML = state.branches.map(b =>
                `<option value="${b.id}" ${Number(b.id) === Number(state.activeBranchId) ? 'selected' : ''}>${escapeHtml(b.code)} — ${escapeHtml(b.name)}</option>`
            ).join('');
            branchSel.disabled = !cfg.canSwitchBranch;
        }

        const sum = state.expenditureSummary || {};
        const elTot = document.getElementById('expMetricTotalAmount');
        const elCnt = document.getElementById('expMetricTotalCount');
        const elYtd = document.getElementById('expMetricYtdAmount');
        const elMomo = document.getElementById('expMetricMomoAmount');
        const elCash = document.getElementById('expMetricCashAmount');

        if (elTot) elTot.textContent = money(sum.total_amount || 0);
        if (elCnt) elCnt.textContent = `${sum.total_count || 0} recorded expenditure vouchers`;
        if (elYtd) elYtd.textContent = money(sum.ytd_2026_amount || 0);
        if (elMomo) elMomo.textContent = money(sum.momo_amount || 0);
        if (elCash) elCash.textContent = money(sum.cash_amount || 0);

        const tbody = document.getElementById('expendituresTableBody');
        if (!tbody) return;

        const q = (state.expSearch || '').toLowerCase().trim();
        const filtered = (state.expenditures || []).filter(e => {
            if (state.expCategoryFilter !== 'all' && e.category !== state.expCategoryFilter) return false;
            if (state.expMethodFilter !== 'all' && e.payment_method !== state.expMethodFilter) return false;
            if (q && !(e.reference_no || '').toLowerCase().includes(q)
                && !(e.title || '').toLowerCase().includes(q)
                && !(e.vendor_name || '').toLowerCase().includes(q)
                && !(e.category || '').toLowerCase().includes(q)) {
                return false;
            }
            return true;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="py-12 text-center text-xs text-slate-500">No expenditure vouchers match your filter criteria.</td></tr>`;
            return;
        }

        tbody.innerHTML = filtered.map(e => {
            const methodBadge = e.payment_method === 'MoMo'
                ? 'bg-yellow-500/15 text-yellow-300 border-yellow-500/30'
                : (e.payment_method === 'Cash'
                    ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'
                    : 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30');

            return `
                <tr class="border-b border-white/5 hover:bg-white/[0.02] transition">
                    <td class="py-3.5 px-4 font-mono-num text-xs font-bold text-rose-400">${escapeHtml(e.reference_no)}</td>
                    <td class="py-3.5 px-4 font-mono-num text-xs text-slate-300">${escapeHtml(e.expense_date)}</td>
                    <td class="py-3.5 px-4 text-xs text-slate-300">${escapeHtml(e.branch_code || e.branch_name || '')}</td>
                    <td class="py-3.5 px-4">
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-900 border border-white/10 text-[11px] font-semibold text-amber-300">
                            ${escapeHtml(e.category)}
                        </span>
                    </td>
                    <td class="py-3.5 px-4">
                        <div class="text-xs font-semibold text-white">${escapeHtml(e.title)}</div>
                        <div class="text-[11px] text-slate-400">${escapeHtml(e.vendor_name || 'Direct Vendor')} ${e.notes ? `• ${escapeHtml(e.notes)}` : ''}</div>
                    </td>
                    <td class="py-3.5 px-4 text-xs text-slate-300">${escapeHtml(e.staff_name || 'Manager')}</td>
                    <td class="py-3.5 px-4">
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold border ${methodBadge}">
                            ${escapeHtml(e.payment_method)}
                        </span>
                    </td>
                    <td class="py-3.5 px-4 font-mono-num text-xs font-extrabold text-rose-400 text-right">-${money(e.amount)}</td>
                    <td class="py-3.5 px-4 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <button onclick="LiterosApp.editExpenditure(${e.id})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-slate-200">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            ${cfg.canDeleteExpenditure ? `
                                <button onclick="LiterosApp.deleteExpenditure(${e.id})"
                                        class="px-2.5 py-1 rounded-lg bg-rose-950/60 hover:bg-rose-600 text-xs text-rose-300 hover:text-white">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function openNewExpenditureModal() {
        const form = document.getElementById('expenditureForm');
        if (!form) return;
        form.reset();
        form.id.value = '';
        form.branch_id.value = state.activeBranchId;
        form.expense_date.value = new Date().toISOString().slice(0, 10);
        form.payment_method.value = 'MoMo';
        document.getElementById('expenditureModalTitle').innerHTML = `<i class="fa-solid fa-wallet text-rose-400 mr-1.5"></i>Record Branch Expenditure`;
        document.getElementById('expenditureModal').classList.remove('hidden');
    }

    function editExpenditure(id) {
        const exp = (state.expenditures || []).find(e => Number(e.id) === Number(id));
        if (!exp) return;
        const form = document.getElementById('expenditureForm');
        if (!form) return;
        form.id.value = exp.id;
        form.branch_id.value = exp.branch_id;
        form.category.value = exp.category;
        form.title.value = exp.title;
        form.amount.value = exp.amount;
        form.payment_method.value = exp.payment_method || 'MoMo';
        form.expense_date.value = exp.expense_date;
        form.vendor_name.value = exp.vendor_name || '';
        form.notes.value = exp.notes || '';
        document.getElementById('expenditureModalTitle').innerHTML = `<i class="fa-solid fa-pen text-amber-400 mr-1.5"></i>Edit Expenditure: ${escapeHtml(exp.reference_no)}`;
        document.getElementById('expenditureModal').classList.remove('hidden');
    }

    async function submitExpenditureForm(e) {
        e.preventDefault();
        const form = e.target;
        const payload = {
            id: form.id.value ? Number(form.id.value) : 0,
            branch_id: Number(form.branch_id.value || state.activeBranchId),
            category: form.category.value,
            title: form.title.value,
            amount: parseFloat(form.amount.value),
            payment_method: form.payment_method.value,
            expense_date: form.expense_date.value,
            vendor_name: form.vendor_name.value,
            notes: form.notes.value
        };
        try {
            const res = await api('save_expenditure', payload);
            closeModal('expenditureModal');
            await loadBootstrap(state.activeBranchId);
            toast(res.message, 'success');
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function deleteExpenditure(id) {
        try {
            const res = await api('delete_expenditure', { id });
            await loadBootstrap(state.activeBranchId);
            toast(res.message, 'info');
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    function exportExpendituresCsv() {
        const headers = ['Voucher Ref', 'Expense Date', 'Branch', 'Category', 'Title', 'Vendor', 'Payment Method', 'Amount', 'Approved By', 'Notes'];
        const rows = (state.expenditures || []).map(e => [
            e.reference_no,
            e.expense_date,
            `"${(e.branch_name || '').replace(/"/g, '""')}"`,
            `"${(e.category || '').replace(/"/g, '""')}"`,
            `"${(e.title || '').replace(/"/g, '""')}"`,
            `"${(e.vendor_name || '').replace(/"/g, '""')}"`,
            e.payment_method,
            e.amount,
            `"${(e.staff_name || '').replace(/"/g, '""')}"`,
            `"${(e.notes || '').replace(/"/g, '""')}"`
        ]);
        const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `shakes_and_dessert_expenditures_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        toast('Expenditures ledger exported to CSV');
    }

    function viewReceipt(orderId) {
        let ord = state.orders.find(o => Number(o.id) === Number(orderId));
        if (!ord && latestReportData && Array.isArray(latestReportData.transactions)) {
            ord = latestReportData.transactions.find(o => Number(o.id) === Number(orderId));
        }
        if (!ord) return;
        const comp = state.company;
        const branch = state.branches.find(b => Number(b.id) === Number(ord.branch_id)) || getActiveBranch();

        const container = document.getElementById('receiptModalContent');
        container.innerHTML = `
            <div class="text-center border-b-2 border-dashed border-slate-300 pb-3 mb-3">
                <div class="text-lg font-extrabold tracking-tight uppercase">${escapeHtml(comp.name || 'Literos Restaurant')}</div>
                <div class="text-xs text-slate-600">${escapeHtml(branch.name || '')} — ${escapeHtml(branch.address || '')}</div>
                <div class="text-xs text-slate-600">Tel: ${escapeHtml(branch.phone || comp.phone || '')}</div>
            </div>
            <div class="text-xs space-y-1 border-b border-dashed border-slate-300 pb-3 mb-3">
                <div class="flex justify-between"><span>RECEIPT:</span><span class="font-bold">${escapeHtml(ord.order_number)}</span></div>
                <div class="flex justify-between"><span>DATE:</span><span>${escapeHtml(ord.created_at)}</span></div>
                <div class="flex justify-between"><span>TYPE:</span><span>${escapeHtml(ord.order_type)} ${ord.table_number ? `(${escapeHtml(ord.table_number)})` : ''}</span></div>
                <div class="flex justify-between"><span>SERVER:</span><span>${escapeHtml(ord.staff_name || 'Staff')}</span></div>
                <div class="flex justify-between"><span>GUEST:</span><span>${escapeHtml(ord.customer_name)}</span></div>
            </div>
            <div class="text-xs space-y-1.5 border-b-2 border-dashed border-slate-300 pb-3 mb-3">
                ${(ord.items || []).map(i => `
                    <div>
                        <div class="flex justify-between font-semibold">
                            <span>${i.quantity}x ${escapeHtml(i.item_name)}</span>
                            <span>${money(i.line_total)}</span>
                        </div>
                        ${i.modifiers ? `<div class="text-[10px] text-slate-600 pl-3">* ${escapeHtml(i.modifiers)}</div>` : ''}
                    </div>
                `).join('')}
            </div>
            <div class="text-xs space-y-1 border-b border-dashed border-slate-300 pb-3 mb-3">
                <div class="flex justify-between"><span>SUBTOTAL</span><span>${money(ord.subtotal)}</span></div>
                ${Number(ord.discount_amount) > 0 ? `<div class="flex justify-between"><span>DISCOUNT</span><span>-${money(ord.discount_amount)}</span></div>` : ''}
                <div class="flex justify-between"><span>TAX</span><span>${money(ord.tax_amount)}</span></div>
                <div class="flex justify-between"><span>SERVICE CHARGE</span><span>${money(ord.service_charge)}</span></div>
                <div class="flex justify-between"><span>TIP</span><span>${money(ord.tip_amount)}</span></div>
                <div class="flex justify-between text-sm font-extrabold pt-1 border-t border-slate-300">
                    <span>TOTAL (${escapeHtml(ord.payment_status.toUpperCase())})</span>
                    <span>${money(ord.total_amount)}</span>
                </div>
                <div class="flex justify-between text-[11px] text-slate-600">
                    <span>PAYMENT METHOD</span>
                    <span>${escapeHtml(ord.payment_method)}</span>
                </div>
            </div>
            <div class="text-center text-[11px] text-slate-600 leading-relaxed">
                ${escapeHtml(comp.receipt_footer || 'Thank you for dining with us!')}
            </div>
        `;
        document.getElementById('receiptModal').classList.remove('hidden');
    }

    function exportSalesCsv() {
        const headers = ['Order Number', 'Branch', 'Customer', 'Type', 'Server', 'Status', 'Payment', 'Subtotal', 'Tax', 'Tip', 'Total', 'Created At'];
        const rows = state.orders.map(o => [
            o.order_number,
            o.branch_name || '',
            `"${(o.customer_name || '').replace(/"/g, '""')}"`,
            o.order_type,
            `"${(o.staff_name || '').replace(/"/g, '""')}"`,
            o.status,
            `${o.payment_status} (${o.payment_method})`,
            o.subtotal,
            o.tax_amount,
            o.tip_amount,
            o.total_amount,
            o.created_at
        ]);
        const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `literos_sales_export_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        toast('Sales ledger exported to CSV');
    }

    // =========================================================================
    // 5. MENU & RECIPE STUDIO
    // =========================================================================
    function renderMenuStudioView() {
        const cfg = getCurrentRoleConfig();
        const catSelect = document.getElementById('menuFormCategorySelect');
        if (catSelect) {
            catSelect.innerHTML = state.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        }

        const tbody = document.getElementById('menuStudioTableBody');
        if (!tbody) return;

        tbody.innerHTML = state.menuItems.map(item => {
            const margin = Number(item.price) > 0
                ? Math.round(((Number(item.price) - Number(item.cost)) / Number(item.price)) * 100)
                : 0;
            const lowStock = Number(item.stock_qty) <= Number(item.low_stock_threshold);

            return `
                <tr class="border-b border-white/5 hover:bg-white/[0.02] transition">
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">${escapeHtml(item.image_emoji)}</span>
                            <div>
                                <div class="text-xs font-bold text-white">${escapeHtml(item.name)}</div>
                                <div class="text-[11px] text-slate-400 font-mono-num">${escapeHtml(item.sku)} • ${escapeHtml(item.category_name || '')}</div>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-4 font-mono-num text-xs font-bold text-amber-400">${money(item.price)}</td>
                    <td class="py-3 px-4 font-mono-num text-xs text-slate-300">${money(item.cost)}</td>
                    <td class="py-3 px-4">
                        <span class="px-2 py-0.5 rounded-md text-xs font-mono-num font-semibold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                            ${margin}% GP
                        </span>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-2">
                            <span class="font-mono-num text-xs font-bold ${lowStock ? 'text-rose-400' : 'text-slate-200'}">${item.stock_qty}</span>
                            <button onclick="LiterosApp.restockItem(${item.id})"
                                    class="px-2 py-0.5 rounded bg-slate-800 hover:bg-amber-500 hover:text-slate-950 text-[10px] font-semibold text-slate-300 transition"
                                    title="Quick +25 Restock">
                                +25
                            </button>
                        </div>
                    </td>
                    <td class="py-3 px-4">
                        <button onclick="LiterosApp.toggleAvailability(${item.id})"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold border transition ${
                                    Number(item.is_available) === 1
                                        ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'
                                        : 'bg-rose-500/20 text-rose-300 border-rose-500/40'
                                }">
                            ${Number(item.is_available) === 1 ? 'In Stock' : '86\'d / Off'}
                        </button>
                    </td>
                    <td class="py-3 px-4 text-right">
                        ${cfg.canManageMenu ? `
                            <button onclick="LiterosApp.editMenuItem(${item.id})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-slate-200 mr-1">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button onclick="LiterosApp.deleteMenuItem(${item.id})"
                                    class="px-2.5 py-1 rounded-lg bg-rose-950/60 hover:bg-rose-600 text-xs text-rose-300 hover:text-white">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        ` : `<span class="text-[10px] text-slate-500 font-semibold">Stock/86 Only</span>`}
                    </td>
                </tr>
            `;
        }).join('');
    }

    function openNewMenuModal() {
        const form = document.getElementById('menuItemForm');
        form.reset();
        form.id.value = '';
        document.getElementById('menuModalTitle').textContent = 'Create Culinary Dish';
        document.getElementById('menuItemModal').classList.remove('hidden');
    }

    function editMenuItem(id) {
        const item = state.menuItems.find(m => Number(m.id) === Number(id));
        if (!item) return;
        const form = document.getElementById('menuItemForm');
        form.id.value = item.id;
        form.category_id.value = item.category_id;
        form.sku.value = item.sku;
        form.name.value = item.name;
        form.description.value = item.description;
        form.price.value = item.price;
        form.cost.value = item.cost;
        form.course_type.value = item.course_type;
        form.prep_time_mins.value = item.prep_time_mins;
        form.stock_qty.value = item.stock_qty;
        form.image_emoji.value = item.image_emoji;
        form.modifiers.value = (item.modifiers || []).join(', ');
        form.is_popular.checked = Number(item.is_popular) === 1;

        document.getElementById('menuModalTitle').textContent = `Edit Dish: ${item.name}`;
        document.getElementById('menuItemModal').classList.remove('hidden');
    }

    async function submitMenuItem(e) {
        e.preventDefault();
        const form = e.target;
        const payload = {
            id: form.id.value ? Number(form.id.value) : 0,
            category_id: Number(form.category_id.value),
            sku: form.sku.value,
            name: form.name.value,
            description: form.description.value,
            price: parseFloat(form.price.value),
            cost: parseFloat(form.cost.value),
            course_type: form.course_type.value,
            prep_time_mins: Number(form.prep_time_mins.value),
            stock_qty: Number(form.stock_qty.value),
            image_emoji: form.image_emoji.value,
            modifiers: form.modifiers.value,
            is_popular: form.is_popular.checked ? 1 : 0,
            is_available: 1
        };
        try {
            const res = await api('save_menu_item', payload);
            closeModal('menuItemModal');
            await loadBootstrap(state.activeBranchId);
            toast(res.message);
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function toggleAvailability(id) {
        try {
            const res = await api('toggle_menu_availability', { id });
            await loadBootstrap(state.activeBranchId);
            toast(res.message);
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function restockItem(id) {
        try {
            const res = await api('restock_menu_item', { id, add_qty: 25 });
            await loadBootstrap(state.activeBranchId);
            toast(res.message);
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function deleteMenuItem(id) {
        try {
            const res = await api('delete_menu_item', { id });
            await loadBootstrap(state.activeBranchId);
            toast(res.message, 'info');
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 6. SALES PERSONNEL / STAFF & PIN SWITCHER
    // =========================================================================
    function renderStaffView() {
        const branchSelect = document.getElementById('staffFormBranchSelect');
        if (branchSelect) {
            branchSelect.innerHTML = state.branches.map(b => `<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
        }

        const grid = document.getElementById('staffCardsGrid');
        if (!grid) return;

        grid.innerHTML = state.staff.map(s => {
            const commissionEarned = (Number(s.total_sales) * (Number(s.commission_rate) / 100));
            const isCurrent = state.activeStaff && Number(state.activeStaff.id) === Number(s.id);

            return `
                <div class="glass-panel rounded-2xl p-5 flex flex-col justify-between border ${isCurrent ? 'border-amber-500/60' : 'border-white/10'}">
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-base font-extrabold text-slate-950 shadow-lg"
                                     style="background:${escapeHtml(s.avatar_color || '#f59e0b')}">
                                    ${escapeHtml(s.full_name.slice(0, 2).toUpperCase())}
                                </div>
                                <div>
                                    <h4 class="font-bold text-white text-sm flex items-center gap-2">
                                        ${escapeHtml(s.full_name)}
                                        ${isCurrent ? '<span class="px-2 py-0.5 text-[10px] rounded-md bg-amber-500 text-slate-950 font-extrabold">ACTIVE</span>' : ''}
                                    </h4>
                                    <p class="text-xs text-amber-400 font-semibold">${escapeHtml(s.role)} • ${escapeHtml(s.branch_name || '')}</p>
                                    <p class="text-[11px] text-slate-400 font-mono-num mt-0.5">${escapeHtml(s.employee_code)} • PIN: <span class="text-white font-bold">${escapeHtml(s.pin_code)}</span></p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 rounded-xl bg-slate-950/80 border border-white/5 mb-4 text-center">
                            <div>
                                <div class="text-[10px] text-slate-400">Orders</div>
                                <div class="font-mono-num text-xs font-bold text-white mt-0.5">${s.total_orders}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-slate-400">Sales Volume</div>
                                <div class="font-mono-num text-xs font-bold text-emerald-400 mt-0.5">${money(s.total_sales)}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-slate-400">Commission (${s.commission_rate}%)</div>
                                <div class="font-mono-num text-xs font-bold text-amber-300 mt-0.5">${money(commissionEarned)}</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-3 border-t border-white/10">
                        <button onclick="LiterosApp.setActiveOperator(${s.id})"
                                class="flex-1 py-2 rounded-xl bg-slate-800 hover:bg-amber-500 hover:text-slate-950 text-xs font-bold text-slate-200 transition">
                            <i class="fa-solid fa-id-badge mr-1"></i>Switch Operator
                        </button>
                        <button onclick="LiterosApp.editStaffMember(${s.id})"
                                class="px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-xs text-slate-300 border border-white/10">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    function setActiveOperator(staffId) {
        const s = state.staff.find(item => Number(item.id) === Number(staffId));
        if (!s) return;
        state.activeStaff = s;
        renderAll();
        toast(`POS Operator switched to ${s.full_name} (${s.role})`);
    }

    function openNewStaffModal() {
        const form = document.getElementById('staffForm');
        form.reset();
        form.id.value = '';
        form.branch_id.value = state.activeBranchId;
        document.getElementById('staffModalTitle').textContent = 'Onboard Sales Personnel';
        document.getElementById('staffModal').classList.remove('hidden');
    }

    function editStaffMember(id) {
        const s = state.staff.find(item => Number(item.id) === Number(id));
        if (!s) return;
        const form = document.getElementById('staffForm');
        form.id.value = s.id;
        form.branch_id.value = s.branch_id;
        form.employee_code.value = s.employee_code;
        form.full_name.value = s.full_name;
        form.role.value = s.role;
        form.pin_code.value = s.pin_code;
        form.email.value = s.email || '';
        form.phone.value = s.phone || '';
        form.hourly_rate.value = s.hourly_rate;
        form.commission_rate.value = s.commission_rate;
        form.avatar_color.value = s.avatar_color || '#f59e0b';

        document.getElementById('staffModalTitle').textContent = `Edit Personnel: ${s.full_name}`;
        document.getElementById('staffModal').classList.remove('hidden');
    }

    async function submitStaffForm(e) {
        e.preventDefault();
        const form = e.target;
        const payload = {
            id: form.id.value ? Number(form.id.value) : 0,
            branch_id: Number(form.branch_id.value),
            employee_code: form.employee_code.value,
            full_name: form.full_name.value,
            role: form.role.value,
            pin_code: form.pin_code.value,
            email: form.email.value,
            phone: form.phone.value,
            hourly_rate: parseFloat(form.hourly_rate.value),
            commission_rate: parseFloat(form.commission_rate.value),
            avatar_color: form.avatar_color.value,
            is_active: 1
        };
        try {
            const res = await api('save_staff', payload);
            closeModal('staffModal');
            await loadBootstrap(state.activeBranchId);
            toast(res.message);
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function submitPinSwitch(e) {
        e.preventDefault();
        const pin = document.getElementById('quickPinInput').value.trim();
        try {
            const res = await api('verify_pin', { pin_code: pin });
            state.activeStaff = res.staff;
            try { localStorage.setItem('literos_auth_staff_id', String(res.staff.id)); } catch (_) {}
            document.getElementById('quickPinInput').value = '';
            closeModal('pinModal');
            renderAll();
            toast(res.message, 'success');
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 6B. FULL LOGIN SCREEN, SEEDED CREDENTIALS DIRECTORY & LOGOUT
    // =========================================================================
    function setLoginBranchFilter(bFilter) {
        state.loginBranchFilter = String(bFilter);
        document.querySelectorAll('.login-bfilter-btn').forEach(btn => {
            const active = String(btn.dataset.bfilter) === state.loginBranchFilter;
            btn.className = `login-bfilter-btn px-2.5 py-1 rounded-lg text-[11px] transition ${
                active
                    ? 'bg-amber-500 text-slate-950 font-extrabold'
                    : 'bg-slate-900 border border-white/10 text-slate-300 font-semibold hover:text-white'
            }`;
        });
        renderLoginCredentialsScreen();
    }

    function renderLoginCredentialsScreen() {
        const comp = state.company || {};
        const logoEl = document.getElementById('loginBrandLogo');
        const nameEl = document.getElementById('loginBrandName');
        if (logoEl) logoEl.textContent = comp.logo_text || 'SD';
        if (nameEl) nameEl.textContent = comp.name || 'Shakes and Dessert';

        const listEl = document.getElementById('loginDemoCredentialsList');
        if (!listEl) return;

        const roleBadges = {
            Admin: 'bg-amber-500/20 text-amber-300 border-amber-500/40',
            Manager: 'bg-purple-500/20 text-purple-300 border-purple-500/40',
            Cashier: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40',
            Waiter: 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40',
            Chef: 'bg-rose-500/20 text-rose-300 border-rose-500/40'
        };

        const filteredStaff = state.staff.filter(s => {
            if (state.loginBranchFilter === 'all') return true;
            if (s.role === 'Admin') return true; // Always show Owner Hajia Rabi Adam at top
            return String(s.branch_id) === String(state.loginBranchFilter);
        });

        listEl.innerHTML = filteredStaff.map(s => {
            const roleCfg = ROLE_PERMISSIONS[s.role] || ROLE_PERMISSIONS.Waiter;
            return `
            <div onclick="LiterosApp.fillLoginDemoCredential('${escapeHtml(s.email || s.employee_code)}', '${escapeHtml(s.pin_code)}')"
                 class="p-3 rounded-2xl bg-slate-900/90 hover:bg-slate-800/90 border border-white/10 hover:border-amber-500/40 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 cursor-pointer transition group">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-extrabold text-slate-950 shrink-0 shadow"
                         style="background:${escapeHtml(s.avatar_color || '#f59e0b')}">
                        ${escapeHtml(s.full_name.slice(0, 2).toUpperCase())}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-white group-hover:text-amber-300 transition">${escapeHtml(s.full_name)}</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border ${roleBadges[s.role] || 'bg-slate-800 text-slate-300'}">
                                ${escapeHtml(s.role === 'Waiter' ? 'Waitress' : s.role)}
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5">
                            <span class="font-mono-num text-slate-300">${escapeHtml(s.email || s.employee_code)}</span>
                            • ${escapeHtml(s.branch_name || 'Kumasi HQ')}
                        </div>
                        <div class="text-[10px] text-amber-400/90 mt-0.5">
                            <i class="fa-solid fa-shield-halved mr-1"></i>${escapeHtml(roleCfg.label)}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between sm:justify-end gap-2.5 pt-2 sm:pt-0 border-t sm:border-t-0 border-white/5">
                    <div class="text-right">
                        <div class="text-[10px] uppercase tracking-wider text-slate-400">ID / PIN Code</div>
                        <div class="font-mono-num text-xs font-extrabold text-amber-400">
                            ${escapeHtml(s.employee_code)} / <span class="px-1.5 py-0.5 rounded bg-amber-500/15 border border-amber-500/30 text-amber-300">${escapeHtml(s.pin_code)}</span>
                        </div>
                    </div>
                    <button type="button"
                            onclick="event.stopPropagation(); LiterosApp.quickLoginAs('${escapeHtml(s.email || s.employee_code)}', '${escapeHtml(s.pin_code)}')"
                            class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs transition shrink-0">
                        Quick Sign-In
                    </button>
                </div>
            </div>
        `}).join('');
    }

    function fillLoginDemoCredential(identifier, pin) {
        const idInput = document.getElementById('loginIdentifierInput');
        const pinInput = document.getElementById('loginPinInput');
        if (idInput) idInput.value = identifier;
        if (pinInput) pinInput.value = pin;
        toast(`Filled credentials for ${identifier} (PIN: ${pin})`, 'info');
    }

    function appendLoginPin(digit) {
        const pinInput = document.getElementById('loginPinInput');
        if (!pinInput) return;
        if (pinInput.value.length < 10) {
            pinInput.value += String(digit);
        }
    }

    function clearLoginPin() {
        const pinInput = document.getElementById('loginPinInput');
        if (pinInput) pinInput.value = '';
    }

    function backspaceLoginPin() {
        const pinInput = document.getElementById('loginPinInput');
        if (pinInput) pinInput.value = pinInput.value.slice(0, -1);
    }

    async function quickLoginAs(identifier, pin) {
        fillLoginDemoCredential(identifier, pin);
        await performLogin(identifier, pin);
    }

    async function submitLoginScreen(e) {
        if (e) e.preventDefault();
        const identifier = document.getElementById('loginIdentifierInput')?.value.trim() || '';
        const pin = document.getElementById('loginPinInput')?.value.trim() || '';
        await performLogin(identifier, pin);
    }

    async function performLogin(identifier, pin) {
        try {
            const res = await api('login', { identifier, pin_code: pin });
            state.activeStaff = res.staff;
            try { localStorage.setItem('literos_auth_staff_id', String(res.staff.id)); } catch (_) {}

            // Route operator to their role's primary default workspace tab
            const roleCfg = getCurrentRoleConfig();
            state.activeTab = roleCfg.defaultTab;
            document.querySelectorAll('.view-section').forEach(sec => {
                sec.classList.toggle('hidden', sec.id !== `view-${state.activeTab}`);
            });
            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === state.activeTab);
            });

            // If staff belongs to a specific branch, switch terminal to their branch
            if (res.staff.branch_id && Number(res.staff.branch_id) !== Number(state.activeBranchId)) {
                state.activeBranchId = Number(res.staff.branch_id);
                await loadBootstrap(state.activeBranchId);
            } else {
                renderAll();
            }

            document.getElementById('loginPinInput').value = '';
            document.getElementById('loginScreenOverlay')?.classList.add('hidden');
            toast(`${res.message} • Workspace: ${roleCfg.label}`, 'success');
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    function logoutSession() {
        const prevName = state.activeStaff?.full_name || 'Operator';
        try { localStorage.removeItem('literos_auth_staff_id'); } catch (_) {}
        const pinInput = document.getElementById('loginPinInput');
        if (pinInput) pinInput.value = '';
        renderLoginCredentialsScreen();
        document.getElementById('loginScreenOverlay')?.classList.remove('hidden');
        toast(`${prevName} signed out. Terminal locked.`, 'info');
    }

    // =========================================================================
    // 7. MULTI-BRANCH MANAGEMENT
    // =========================================================================
    function renderBranchesView() {
        const grid = document.getElementById('branchesGrid');
        if (!grid) return;

        grid.innerHTML = state.branches.map(b => {
            const m = state.branchMetrics.find(bm => Number(bm.id) === Number(b.id)) || {};
            const isCurrent = Number(b.id) === Number(state.activeBranchId);

            return `
                <div class="glass-panel rounded-2xl p-6 flex flex-col justify-between border-2 ${isCurrent ? 'border-amber-500/60' : 'border-white/10'}">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div>
                                <span class="px-2.5 py-0.5 rounded-md bg-amber-500/15 text-amber-300 font-mono-num text-xs font-bold border border-amber-500/30">
                                    ${escapeHtml(b.code)}
                                </span>
                                <h3 class="text-base font-bold text-white mt-2">${escapeHtml(b.name)}</h3>
                                <p class="text-xs text-slate-400"><i class="fa-solid fa-location-dot mr-1 text-amber-400"></i>${escapeHtml(b.address)}</p>
                            </div>
                            ${isCurrent ? `<span class="px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-300 text-xs font-bold border border-emerald-500/30">Active Terminal</span>` : ''}
                        </div>

                        <div class="text-xs text-slate-300 space-y-1 my-4 p-3 rounded-xl bg-slate-950/70 border border-white/5">
                            <div class="flex justify-between"><span>Manager:</span><span class="font-semibold text-white">${escapeHtml(b.manager_name)}</span></div>
                            <div class="flex justify-between"><span>Hours:</span><span>${escapeHtml(b.opening_hours)}</span></div>
                            <div class="flex justify-between"><span>Phone:</span><span>${escapeHtml(b.phone)}</span></div>
                            <div class="flex justify-between"><span>Tax Rate:</span><span class="font-mono-num">${b.tax_override !== null && b.tax_override !== '' ? `${b.tax_override}% (Branch Override)` : `${state.company.tax_rate || 8.25}% (Company Default)`}</span></div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-center mb-4">
                            <div class="p-2.5 rounded-xl bg-slate-900/90 border border-white/5">
                                <div class="text-[10px] text-slate-400">Revenue</div>
                                <div class="font-mono-num text-xs font-bold text-emerald-400 mt-0.5">${money(m.revenue || 0)}</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-900/90 border border-white/5">
                                <div class="text-[10px] text-slate-400">Orders</div>
                                <div class="font-mono-num text-xs font-bold text-white mt-0.5">${m.order_count || 0}</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-900/90 border border-white/5">
                                <div class="text-[10px] text-slate-400">Floor Tables</div>
                                <div class="font-mono-num text-xs font-bold text-amber-300 mt-0.5">${m.occupied_tables || 0}/${m.total_tables || 0}</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-3 border-t border-white/10">
                        <button onclick="LiterosApp.switchBranch(${b.id})"
                                class="flex-1 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                            Switch POS to Branch
                        </button>
                        <button onclick="LiterosApp.editBranch(${b.id})"
                                class="px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs text-slate-200">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    function openNewBranchModal() {
        const form = document.getElementById('branchForm');
        form.reset();
        form.id.value = '';
        document.getElementById('branchModalTitle').textContent = 'Launch New Restaurant Branch';
        document.getElementById('branchModal').classList.remove('hidden');
    }

    function editBranch(id) {
        const b = state.branches.find(item => Number(item.id) === Number(id));
        if (!b) return;
        const form = document.getElementById('branchForm');
        form.id.value = b.id;
        form.code.value = b.code;
        form.name.value = b.name;
        form.city.value = b.city;
        form.address.value = b.address;
        form.phone.value = b.phone;
        form.manager_name.value = b.manager_name;
        form.opening_hours.value = b.opening_hours;
        form.tax_override.value = b.tax_override !== null ? b.tax_override : '';

        document.getElementById('branchModalTitle').textContent = `Edit Branch: ${b.name}`;
        document.getElementById('branchModal').classList.remove('hidden');
    }

    async function submitBranchForm(e) {
        e.preventDefault();
        const form = e.target;
        const payload = {
            id: form.id.value ? Number(form.id.value) : 0,
            code: form.code.value,
            name: form.name.value,
            city: form.city.value,
            address: form.address.value,
            phone: form.phone.value,
            manager_name: form.manager_name.value,
            opening_hours: form.opening_hours.value,
            tax_override: form.tax_override.value
        };
        try {
            const res = await api('save_branch', payload);
            closeModal('branchModal');
            await loadBootstrap(res.branch_id || state.activeBranchId);
            toast(res.message);
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 8. COMPANY HQ PROFILE & CONFIGURATION
    // =========================================================================
    function renderCompanyView() {
        const form = document.getElementById('companyProfileForm');
        if (!form || !state.company) return;
        const c = state.company;
        form.name.value = c.name || '';
        if (form.owner_name) form.owner_name.value = c.owner_name || 'Hajia Rabi Adam';
        form.tagline.value = c.tagline || '';
        form.logo_text.value = c.logo_text || 'SD';
        form.currency_symbol.value = c.currency_symbol || 'GHS ';
        form.currency_code.value = c.currency_code || 'GHS';
        form.tax_rate.value = c.tax_rate ?? 6.0;
        form.service_charge_rate.value = c.service_charge_rate ?? 2.5;
        form.email.value = c.email || '';
        form.phone.value = c.phone || '';
        form.address.value = c.address || '';
        form.receipt_footer.value = c.receipt_footer || '';
    }

    async function submitCompanyProfile(e) {
        e.preventDefault();
        const form = e.target;
        const payload = {
            name: form.name.value,
            owner_name: form.owner_name ? form.owner_name.value : 'Hajia Rabi Adam',
            tagline: form.tagline.value,
            logo_text: form.logo_text.value,
            currency_symbol: form.currency_symbol.value,
            currency_code: form.currency_code.value,
            tax_rate: parseFloat(form.tax_rate.value),
            service_charge_rate: parseFloat(form.service_charge_rate.value),
            email: form.email.value,
            phone: form.phone.value,
            address: form.address.value,
            receipt_footer: form.receipt_footer.value
        };
        try {
            const res = await api('save_company_profile', payload);
            await loadBootstrap(state.activeBranchId);
            toast(res.message);
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function resetDemoDatabase() {
        try {
            const res = await api('reset_demo_data', {});
            clearCart();
            await loadBootstrap(1);
            toast(res.message, 'info');
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 8B. EXECUTIVE REPORTS & BUSINESS INTELLIGENCE SUITE
    // =========================================================================
    const reportCharts = {
        dailyTrend: null,
        paymentMix: null,
        orderTypeMix: null,
        categoryMix: null
    };
    let latestReportData = null;
    let reportPage = 1;
    let reportPageSize = 100; // 50, 100, 250, 500, or 'all'

    function renderReportsFilterOptions() {
        const branchSel = document.getElementById('repBranchSelect');
        if (branchSel) {
            const currentVal = branchSel.value || '0';
            branchSel.innerHTML = `<option value="0">All Branches (Consolidated)</option>` +
                state.branches.map(b => `<option value="${b.id}">${escapeHtml(b.code)} — ${escapeHtml(b.name)}</option>`).join('');
            branchSel.value = currentVal;
        }

        const staffSel = document.getElementById('repStaffSelect');
        if (staffSel) {
            const currentStaff = staffSel.value || '0';
            staffSel.innerHTML = `<option value="0">All Personnel</option>` +
                state.staff.map(s => `<option value="${s.id}">${escapeHtml(s.full_name)} (${escapeHtml(s.role)})</option>`).join('');
            staffSel.value = currentStaff;
        }
    }

    function setReportDatePreset(preset) {
        const fromEl = document.getElementById('repFromDate');
        const toEl = document.getElementById('repToDate');
        if (!fromEl || !toEl) return;

        const now = new Date();
        const fmt = (d) => d.toISOString().slice(0, 10);

        if (preset === 'today') {
            fromEl.value = fmt(now);
            toEl.value = fmt(now);
        } else if (preset === '7days') {
            const past = new Date(now.getTime() - 7 * 86400000);
            fromEl.value = fmt(past);
            toEl.value = fmt(now);
        } else if (preset === '30days') {
            const past = new Date(now.getTime() - 30 * 86400000);
            fromEl.value = fmt(past);
            toEl.value = fmt(now);
        } else if (preset === '2024') {
            fromEl.value = '2024-01-01';
            toEl.value = '2024-12-31';
        } else if (preset === '2025') {
            fromEl.value = '2025-01-01';
            toEl.value = '2025-12-31';
        } else if (preset === '2026') {
            fromEl.value = '2026-01-01';
            toEl.value = fmt(now);
        } else {
            fromEl.value = '';
            toEl.value = '';
        }
        loadReportsData();
    }

    function resetReportFilters() {
        const form = document.getElementById('reportsFilterForm');
        if (form) form.reset();
        loadReportsData();
    }

    async function runReportQuery(e) {
        if (e) e.preventDefault();
        await loadReportsData();
        toast('Report filters applied', 'info');
    }

    async function loadReportsData() {
        const params = new URLSearchParams({
            from_date: document.getElementById('repFromDate')?.value || '',
            to_date: document.getElementById('repToDate')?.value || '',
            branch_id: document.getElementById('repBranchSelect')?.value || '0',
            order_type: document.getElementById('repOrderType')?.value || 'all',
            payment_method: document.getElementById('repPaymentMethod')?.value || 'all',
            payment_status: document.getElementById('repPaymentStatus')?.value || 'all',
            order_status: document.getElementById('repOrderStatus')?.value || 'all',
            staff_id: document.getElementById('repStaffSelect')?.value || '0',
            search: document.getElementById('repSearchInput')?.value || ''
        });

        try {
            const data = await api('reports_query', null, '&' + params.toString());
            latestReportData = data;
            reportPage = 1;
            renderReportKpis(data.kpis);
            renderReportCharts(data);
            renderReportTables(data);
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    function renderReportKpis(k) {
        if (!k) return;
        document.getElementById('repKpiGrossRevenue').textContent = money(k.gross_revenue);
        document.getElementById('repKpiPaidSplit').textContent = `Daily Avg: ${money(k.avg_daily_revenue || 0)} (${k.active_days_count || 1}d)`;

        document.getElementById('repKpiNetSales').textContent = money(k.net_subtotal);
        document.getElementById('repKpiDiscounts').textContent = `Tax+Svc: ${money(k.total_tax + k.total_service_charge)} • Tips: ${money(k.total_tips)}`;

        document.getElementById('repKpiGrossProfit').textContent = money(k.gross_profit);
        document.getElementById('repKpiMarginPct').textContent = `COGS: ${money(k.total_cogs)} (${k.gross_margin_pct}% Margin)`;

        document.getElementById('repKpiTaxService').textContent = `-${money(k.total_expenditures || 0)}`;
        document.getElementById('repKpiTaxBreakdown').textContent = `${k.expenditures_count || 0} expense vouchers recorded`;

        document.getElementById('repKpiTips').textContent = money(k.net_operating_profit ?? k.gross_profit);
        document.getElementById('repKpiGuestsCount').textContent = `${k.net_operating_margin_pct ?? k.gross_margin_pct}% Net Operating Margin`;

        document.getElementById('repKpiOrdersCount').textContent = `${k.valid_orders.toLocaleString()} orders`;
        document.getElementById('repKpiAvgCheck').textContent = `${k.total_guests.toLocaleString()} guests • Avg: ${money(k.avg_order_value)}`;
    }

    function renderReportCharts(data) {
        if (typeof Chart === 'undefined') return;

        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = '"Plus Jakarta Sans", sans-serif';

        // 1. Daily/Monthly Revenue, Gross Profit & Expenditures Trend Chart
        const dailyCtx = document.getElementById('repDailyTrendChart')?.getContext('2d');
        if (dailyCtx) {
            if (reportCharts.dailyTrend) reportCharts.dailyTrend.destroy();
            const labels = (data.daily_trend || []).map(d => d.date);
            const revData = (data.daily_trend || []).map(d => d.gross_revenue);
            const profitData = (data.daily_trend || []).map(d => d.gross_profit);
            const expData = (data.daily_trend || []).map(d => d.expenditures || 0);

            reportCharts.dailyTrend = new Chart(dailyCtx, {
                type: 'bar',
                data: {
                    labels: labels.length ? labels : ['No Data'],
                    datasets: [
                        {
                            label: 'Gross Revenue',
                            data: revData.length ? revData : [0],
                            backgroundColor: 'rgba(245, 158, 11, 0.78)',
                            borderColor: '#f59e0b',
                            borderWidth: 1,
                            borderRadius: 6
                        },
                        {
                            label: 'Gross Profit (After COGS)',
                            data: profitData.length ? profitData : [0],
                            backgroundColor: 'rgba(16, 185, 129, 0.78)',
                            borderColor: '#10b981',
                            borderWidth: 1,
                            borderRadius: 6
                        },
                        {
                            label: 'Operating Expenditures',
                            data: expData.length ? expData : [0],
                            type: 'line',
                            borderColor: '#f43f5e',
                            backgroundColor: 'rgba(244, 63, 94, 0.2)',
                            tension: 0.35,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } }
                    },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,0.04)' } },
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true }
                    }
                }
            });
        }

        // 2. Payment Method Doughnut Chart (Cash 60%, MoMo 35%, QR Pay 5%, etc.)
        const payCtx = document.getElementById('repPaymentChart')?.getContext('2d');
        if (payCtx) {
            if (reportCharts.paymentMix) reportCharts.paymentMix.destroy();
            const pList = (data.payment_breakdown || []).filter(p => p.count > 0 || ['Cash', 'MoMo', 'QR Pay'].includes(p.method));
            reportCharts.paymentMix = new Chart(payCtx, {
                type: 'doughnut',
                data: {
                    labels: pList.map(p => `${p.method} (${p.count})`),
                    datasets: [{
                        data: pList.map(p => p.amount),
                        backgroundColor: ['#10b981', '#facc15', '#06b6d4', '#f59e0b', '#8b5cf6'],
                        borderColor: '#090b10',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '64%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                    }
                }
            });
        }

        // 3. Order Channel Polar/Doughnut Chart
        const typeCtx = document.getElementById('repOrderTypeChart')?.getContext('2d');
        if (typeCtx) {
            if (reportCharts.orderTypeMix) reportCharts.orderTypeMix.destroy();
            const tList = data.order_type_breakdown || [];
            reportCharts.orderTypeMix = new Chart(typeCtx, {
                type: 'doughnut',
                data: {
                    labels: tList.map(t => `${t.type} (${t.count} orders)`),
                    datasets: [{
                        data: tList.map(t => t.amount),
                        backgroundColor: ['#f59e0b', '#06b6d4', '#ec4899'],
                        borderColor: '#090b10',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                    }
                }
            });
        }

        // 4. Category Revenue vs Profit Horizontal Bar Chart
        const catCtx = document.getElementById('repCategoryChart')?.getContext('2d');
        if (catCtx) {
            if (reportCharts.categoryMix) reportCharts.categoryMix.destroy();
            const cList = data.category_breakdown || [];
            reportCharts.categoryMix = new Chart(catCtx, {
                type: 'bar',
                data: {
                    labels: cList.length ? cList.map(c => c.category_name) : ['No Categories'],
                    datasets: [
                        {
                            label: 'Category Revenue',
                            data: cList.length ? cList.map(c => c.revenue) : [0],
                            backgroundColor: 'rgba(99, 102, 241, 0.8)',
                            borderRadius: 6
                        },
                        {
                            label: 'Category Net Profit',
                            data: cList.length ? cList.map(c => c.profit) : [0],
                            backgroundColor: 'rgba(16, 185, 129, 0.8)',
                            borderRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } }
                    },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,0.04)' } },
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true }
                    }
                }
            });
        }
    }

    function renderReportTables(data) {
        // 1. Top Selling Dishes Table
        const itemsBody = document.getElementById('repTopItemsTableBody');
        if (itemsBody) {
            const items = data.top_items || [];
            if (items.length === 0) {
                itemsBody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-xs text-slate-500">No item sales in selected range</td></tr>`;
            } else {
                itemsBody.innerHTML = items.map(it => {
                    const marginPct = it.revenue > 0 ? Math.round((it.profit / it.revenue) * 100) : 0;
                    return `
                        <tr class="border-b border-white/5 hover:bg-white/[0.02]">
                            <td class="py-2.5 px-3.5">
                                <div class="text-xs font-semibold text-white">${escapeHtml(it.item_name)}</div>
                                <div class="text-[10px] text-slate-400">${escapeHtml(it.category_name)}</div>
                            </td>
                            <td class="py-2.5 px-3.5 text-center font-mono-num text-xs font-bold text-amber-300">${it.qty_sold}</td>
                            <td class="py-2.5 px-3.5 text-right font-mono-num text-xs font-semibold text-white">${money(it.revenue)}</td>
                            <td class="py-2.5 px-3.5 text-right font-mono-num text-xs text-slate-400">${money(it.cost)}</td>
                            <td class="py-2.5 px-3.5 text-right font-mono-num text-xs font-bold text-emerald-400">
                                ${money(it.profit)} <span class="text-[10px] text-slate-400">(${marginPct}%)</span>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        }

        // 2. Staff Performance & Commissions Table
        const staffBody = document.getElementById('repStaffPerformanceBody');
        if (staffBody) {
            const staffList = data.staff_performance || [];
            if (staffList.length === 0) {
                staffBody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-xs text-slate-500">No personnel transactions in selected range</td></tr>`;
            } else {
                staffBody.innerHTML = staffList.map(st => `
                    <tr class="border-b border-white/5 hover:bg-white/[0.02]">
                        <td class="py-2.5 px-3.5">
                            <div class="text-xs font-semibold text-white">${escapeHtml(st.staff_name)}</div>
                            <div class="text-[10px] text-amber-400">${escapeHtml(st.role)} • ${st.commission_rate}% comm</div>
                        </td>
                        <td class="py-2.5 px-3.5 text-center font-mono-num text-xs font-bold text-white">${st.orders_count}</td>
                        <td class="py-2.5 px-3.5 text-right font-mono-num text-xs font-bold text-emerald-400">${money(st.gross_sales)}</td>
                        <td class="py-2.5 px-3.5 text-right font-mono-num text-xs text-indigo-300">${money(st.tips_collected)}</td>
                        <td class="py-2.5 px-3.5 text-right font-mono-num text-xs font-bold text-amber-300">${money(st.commission_earned)}</td>
                    </tr>
                `).join('');
            }
        }

        // 3. Detailed Transactions Table + Pagination + Grand Total Footer
        renderReportTransactionsPage();
    }

    function setReportPage(page) {
        if (!latestReportData) return;
        const txs = latestReportData.transactions || [];
        const pageSize = reportPageSize === 'all' ? Math.max(1, txs.length) : Number(reportPageSize || 100);
        const totalPages = Math.max(1, Math.ceil(txs.length / pageSize));
        reportPage = Math.max(1, Math.min(totalPages, Number(page) || 1));
        renderReportTransactionsPage();
    }

    function setReportPageSize(sizeVal) {
        reportPageSize = sizeVal === 'all' ? 'all' : (Number(sizeVal) || 100);
        reportPage = 1;
        renderReportTransactionsPage();
    }

    function renderReportTransactionsPage() {
        const data = latestReportData;
        if (!data) return;

        const txBody = document.getElementById('repTransactionsTableBody');
        const txFoot = document.getElementById('repTransactionsTableFoot');
        const badge = document.getElementById('repTransactionsCountBadge');
        const topPager = document.getElementById('repTopQuickPager');
        const bottomPager = document.getElementById('repPaginationBar');

        const allTxs = data.transactions || [];
        const totalCount = allTxs.length;
        const pageSize = reportPageSize === 'all' ? Math.max(1, totalCount) : Number(reportPageSize || 100);
        const totalPages = Math.max(1, Math.ceil(totalCount / pageSize));
        if (reportPage > totalPages) reportPage = totalPages;

        const startIdx = (reportPage - 1) * pageSize;
        const endIdx = reportPageSize === 'all' ? totalCount : Math.min(totalCount, startIdx + pageSize);
        const pageTxs = allTxs.slice(startIdx, endIdx);

        if (badge) {
            if (totalCount === 0) {
                badge.textContent = '0 matching transactions';
            } else if (reportPageSize === 'all' || totalCount <= pageSize) {
                badge.textContent = `Showing all ${totalCount.toLocaleString()} matching transaction${totalCount === 1 ? '' : 's'}`;
            } else {
                badge.textContent = `Showing ${(startIdx + 1).toLocaleString()}–${endIdx.toLocaleString()} of ${totalCount.toLocaleString()} transactions`;
            }
        }

        // Top quick Prev / Next pager
        if (topPager) {
            if (totalPages <= 1) {
                topPager.innerHTML = '';
            } else {
                topPager.innerHTML = `
                    <button type="button"
                            onclick="LiterosApp.setReportPage(${reportPage - 1})"
                            ${reportPage <= 1 ? 'disabled' : ''}
                            class="px-2.5 py-1 rounded-xl bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed border border-white/10 text-xs font-bold text-slate-200 transition">
                        <i class="fa-solid fa-chevron-left mr-1"></i>Prev
                    </button>
                    <span class="px-2 text-xs font-mono-num text-slate-300">
                        Page <strong class="text-amber-300">${reportPage}</strong> / ${totalPages}
                    </span>
                    <button type="button"
                            onclick="LiterosApp.setReportPage(${reportPage + 1})"
                            ${reportPage >= totalPages ? 'disabled' : ''}
                            class="px-2.5 py-1 rounded-xl bg-amber-500 hover:bg-amber-400 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-extrabold text-slate-950 transition">
                        Next<i class="fa-solid fa-chevron-right ml-1"></i>
                    </button>
                `;
            }
        }

        if (txBody) {
            if (pageTxs.length === 0) {
                txBody.innerHTML = `<tr><td colspan="14" class="py-12 text-center text-xs text-slate-500">No transactions match your current filter criteria.</td></tr>`;
            } else {
                txBody.innerHTML = pageTxs.map(o => {
                    const isCancelled = o.status === 'Cancelled';
                    const statusStyle = {
                        Preparing: 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                        Ready: 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30',
                        Completed: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                        Cancelled: 'bg-rose-500/15 text-rose-300 border-rose-500/30'
                    }[o.status] || 'bg-slate-800 text-slate-300';

                    const taxAndSvc = Number(o.tax_amount) + Number(o.service_charge);

                    return `
                        <tr class="border-b border-white/5 hover:bg-white/[0.02] transition ${isCancelled ? 'opacity-50 line-through' : ''}">
                            <td class="py-3 px-3.5 font-mono-num text-xs font-bold text-amber-400">${escapeHtml(o.order_number)}</td>
                            <td class="py-3 px-3.5 font-mono-num text-[11px] text-slate-300">${escapeHtml(o.created_at)}</td>
                            <td class="py-3 px-3.5 text-xs text-slate-300">${escapeHtml(o.branch_code || o.branch_name || '')}</td>
                            <td class="py-3 px-3.5">
                                <div class="text-xs font-semibold text-white">${escapeHtml(o.customer_name)}</div>
                                <div class="text-[10px] text-slate-400">${o.items_count || 0} items ${o.table_number ? `• ${escapeHtml(o.table_number)}` : ''}</div>
                            </td>
                            <td class="py-3 px-3.5 text-xs text-slate-300">${escapeHtml(o.order_type)}</td>
                            <td class="py-3 px-3.5 text-xs text-slate-300">${escapeHtml(o.staff_name || '')}</td>
                            <td class="py-3 px-3.5 text-xs">
                                <span class="px-2 py-0.5 rounded bg-slate-900 border border-white/10 text-slate-200 font-semibold">
                                    ${escapeHtml(o.payment_method)}
                                </span>
                                <span class="text-[10px] ml-1 ${o.payment_status === 'Paid' ? 'text-emerald-400' : 'text-rose-400'}">
                                    (${escapeHtml(o.payment_status)})
                                </span>
                            </td>
                            <td class="py-3 px-3.5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border ${statusStyle}">
                                    ${escapeHtml(o.status)}
                                </span>
                            </td>
                            <td class="py-3 px-3.5 text-right font-mono-num text-xs text-slate-200">${money(o.subtotal)}</td>
                            <td class="py-3 px-3.5 text-right font-mono-num text-xs text-rose-400">-${money(o.discount_amount)}</td>
                            <td class="py-3 px-3.5 text-right font-mono-num text-xs text-cyan-300">${money(taxAndSvc)}</td>
                            <td class="py-3 px-3.5 text-right font-mono-num text-xs text-indigo-300">${money(o.tip_amount)}</td>
                            <td class="py-3 px-3.5 text-right font-mono-num text-xs font-extrabold text-emerald-400">${money(o.total_amount)}</td>
                            <td class="py-3 px-3.5 text-right">
                                <button onclick="LiterosApp.viewReceipt(${o.id})"
                                        class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-amber-500 hover:text-slate-950 text-slate-200 text-xs transition"
                                        title="View Thermal Receipt">
                                    <i class="fa-solid fa-receipt"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        }

        if (txFoot && data.kpis) {
            const k = data.kpis;
            const validPageTxs = pageTxs.filter(t => t.status !== 'Cancelled');
            const pageSub = validPageTxs.reduce((s, t) => s + Number(t.subtotal), 0);
            const pageDisc = validPageTxs.reduce((s, t) => s + Number(t.discount_amount), 0);
            const pageTaxSvc = validPageTxs.reduce((s, t) => s + Number(t.tax_amount) + Number(t.service_charge), 0);
            const pageTips = validPageTxs.reduce((s, t) => s + Number(t.tip_amount), 0);
            const pageTot = validPageTxs.reduce((s, t) => s + Number(t.total_amount), 0);

            const pageRowHtml = totalPages > 1 ? `
                <tr class="border-b border-white/10 text-slate-300 font-bold bg-slate-900/70">
                    <td colspan="8" class="py-2.5 px-3.5 text-right uppercase tracking-wider text-[11px] text-slate-400">
                        PAGE ${reportPage} SUBTOTAL (${validPageTxs.length} Orders on this page):
                    </td>
                    <td class="py-2.5 px-3.5 text-right">${money(pageSub)}</td>
                    <td class="py-2.5 px-3.5 text-right text-rose-400">-${money(pageDisc)}</td>
                    <td class="py-2.5 px-3.5 text-right text-cyan-300">${money(pageTaxSvc)}</td>
                    <td class="py-2.5 px-3.5 text-right text-indigo-300">${money(pageTips)}</td>
                    <td class="py-2.5 px-3.5 text-right text-amber-300">${money(pageTot)}</td>
                    <td></td>
                </tr>
            ` : '';

            txFoot.innerHTML = `
                ${pageRowHtml}
                <tr>
                    <td colspan="8" class="py-3.5 px-3.5 text-right uppercase tracking-wider text-amber-400">
                        ALL-PAGES GRAND TOTAL (${k.valid_orders.toLocaleString()} Valid Orders / ${k.total_guests.toLocaleString()} Guests):
                    </td>
                    <td class="py-3.5 px-3.5 text-right text-slate-200">${money(k.net_subtotal + k.total_discounts)}</td>
                    <td class="py-3.5 px-3.5 text-right text-rose-400">-${money(k.total_discounts)}</td>
                    <td class="py-3.5 px-3.5 text-right text-cyan-300">${money(k.total_tax + k.total_service_charge)}</td>
                    <td class="py-3.5 px-3.5 text-right text-indigo-300">${money(k.total_tips)}</td>
                    <td class="py-3.5 px-3.5 text-right text-sm text-emerald-400">${money(k.gross_revenue)}</td>
                    <td></td>
                </tr>
            `;
        }

        // Bottom Interactive Pagination Bar
        if (bottomPager) {
            if (totalCount === 0) {
                bottomPager.innerHTML = `<div class="text-xs text-slate-500">No transactions to paginate.</div>`;
                return;
            }

            // Build numbered page buttons window around current reportPage
            const pageButtons = [];
            const maxVisible = 7;
            let startP = Math.max(1, reportPage - 3);
            let endP = Math.min(totalPages, startP + maxVisible - 1);
            if (endP - startP < maxVisible - 1) {
                startP = Math.max(1, endP - maxVisible + 1);
            }

            if (startP > 1) {
                pageButtons.push(`<button type="button" onclick="LiterosApp.setReportPage(1)" class="px-2.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-xs text-slate-300">1</button>`);
                if (startP > 2) {
                    pageButtons.push(`<span class="px-1 text-slate-500 text-xs">…</span>`);
                }
            }

            for (let p = startP; p <= endP; p++) {
                const isActive = p === reportPage;
                pageButtons.push(`
                    <button type="button"
                            onclick="LiterosApp.setReportPage(${p})"
                            class="px-3 py-1.5 rounded-lg font-mono-num text-xs font-bold transition ${
                                isActive
                                    ? 'bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20'
                                    : 'bg-slate-900 hover:bg-slate-800 border border-white/10 text-slate-300'
                            }">
                        ${p}
                    </button>
                `);
            }

            if (endP < totalPages) {
                if (endP < totalPages - 1) {
                    pageButtons.push(`<span class="px-1 text-slate-500 text-xs">…</span>`);
                }
                pageButtons.push(`<button type="button" onclick="LiterosApp.setReportPage(${totalPages})" class="px-2.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/10 font-mono-num text-xs text-slate-300">${totalPages}</button>`);
            }

            bottomPager.innerHTML = `
                <div class="text-xs text-slate-400">
                    Showing <strong class="text-white font-mono-num">${(startIdx + 1).toLocaleString()}</strong> to <strong class="text-white font-mono-num">${endIdx.toLocaleString()}</strong> of <strong class="text-amber-300 font-mono-num">${totalCount.toLocaleString()}</strong> matching orders
                    <span class="text-slate-500 ml-1">(Page ${reportPage} of ${totalPages})</span>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button"
                            onclick="LiterosApp.setReportPage(1)"
                            ${reportPage <= 1 ? 'disabled' : ''}
                            class="px-2.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed border border-white/10 text-xs font-semibold text-slate-300">
                        « First
                    </button>
                    <button type="button"
                            onclick="LiterosApp.setReportPage(${reportPage - 1})"
                            ${reportPage <= 1 ? 'disabled' : ''}
                            class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed border border-white/10 text-xs font-bold text-slate-200">
                        <i class="fa-solid fa-chevron-left mr-1"></i>Previous
                    </button>
                    ${pageButtons.join('')}
                    <button type="button"
                            onclick="LiterosApp.setReportPage(${reportPage + 1})"
                            ${reportPage >= totalPages ? 'disabled' : ''}
                            class="px-3.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-extrabold text-slate-950">
                        Next<i class="fa-solid fa-chevron-right ml-1"></i>
                    </button>
                    <button type="button"
                            onclick="LiterosApp.setReportPage(${totalPages})"
                            ${reportPage >= totalPages ? 'disabled' : ''}
                            class="px-2.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed border border-white/10 text-xs font-semibold text-slate-300">
                        Last »
                    </button>
                </div>
            `;
        }
    }

    function exportFullReportCsv() {
        if (!latestReportData || !Array.isArray(latestReportData.transactions)) {
            toast('Please run the report first', 'warning');
            return;
        }
        const headers = [
            'Order Number', 'Created At', 'Branch', 'Customer Name', 'Guests',
            'Channel', 'Personnel', 'Payment Method', 'Payment Status', 'Order Status',
            'Items Count', 'Subtotal', 'Discount', 'Tax', 'Service Charge', 'Tip', 'COGS', 'Gross Profit', 'Total Amount'
        ];
        const rows = latestReportData.transactions.map(o => [
            o.order_number,
            o.created_at,
            `"${(o.branch_name || '').replace(/"/g, '""')}"`,
            `"${(o.customer_name || '').replace(/"/g, '""')}"`,
            o.guests_count,
            o.order_type,
            `"${(o.staff_name || '').replace(/"/g, '""')}"`,
            o.payment_method,
            o.payment_status,
            o.status,
            o.items_count || 0,
            o.subtotal,
            o.discount_amount,
            o.tax_amount,
            o.service_charge,
            o.tip_amount,
            o.order_cogs || 0,
            o.gross_profit || 0,
            o.total_amount
        ]);
        const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `literos_financial_report_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        toast('Filtered financial report exported to CSV');
    }

    // =========================================================================
    // MODALS, CLOCK & KEYBOARD SHORTCUTS
    // =========================================================================
    function openModal(id) {
        document.getElementById(id)?.classList.remove('hidden');
    }

    function closeModal(id) {
        document.getElementById(id)?.classList.add('hidden');
    }

    function setupClock() {
        const el = document.getElementById('liveClockDisplay');
        const tick = () => {
            if (!el) return;
            const now = new Date();
            el.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        };
        tick();
        setInterval(tick, 1000);
    }

    function setupKeyboardShortcuts() {
        window.addEventListener('keydown', e => {
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                document.getElementById('posSearchInput')?.focus();
            }
            if (e.key === 'Escape') {
                ['modifierModal', 'checkoutModal', 'receiptModal', 'menuItemModal', 'staffModal', 'branchModal', 'tableModal', 'pinModal', 'expenditureModal']
                    .forEach(id => closeModal(id));
            }
        });
    }

    return {
        init,
        switchTab,
        switchBranch,
        setPosCategory,
        setPosSearch,
        handleDishClick,
        openModifierModal,
        toggleModifierOption,
        confirmModifierModal,
        updateCartQty,
        clearCart,
        setOrderType,
        setDiscountPercent,
        setQuickTip,
        openCheckoutModal,
        updateTenderedCalculation,
        selectPaymentMethod,
        submitOrder,
        updateOrderStatus,
        setFloorZone,
        openPosForTable,
        changeTableStatus,
        submitNewTable,
        quickSettleOrder,
        viewReceipt,
        exportSalesCsv,
        openNewExpenditureModal,
        editExpenditure,
        submitExpenditureForm,
        deleteExpenditure,
        exportExpendituresCsv,
        setExpenditureSearch: (q) => { state.expSearch = q; renderExpendituresView(); },
        setExpenditureCategoryFilter: (c) => { state.expCategoryFilter = c; renderExpendituresView(); },
        setExpenditureMethodFilter: (m) => { state.expMethodFilter = m; renderExpendituresView(); },
        openNewMenuModal,
        editMenuItem,
        submitMenuItem,
        toggleAvailability,
        restockItem,
        deleteMenuItem,
        setActiveOperator,
        openNewStaffModal,
        editStaffMember,
        submitStaffForm,
        submitPinSwitch,
        setLoginBranchFilter,
        fillLoginDemoCredential,
        appendLoginPin,
        clearLoginPin,
        backspaceLoginPin,
        quickLoginAs,
        submitLoginScreen,
        logoutSession,
        openNewBranchModal,
        editBranch,
        submitBranchForm,
        submitCompanyProfile,
        resetDemoDatabase,
        setReportDatePreset,
        resetReportFilters,
        runReportQuery,
        loadReportsData,
        setReportPage,
        setReportPageSize,
        exportFullReportCsv,
        openModal,
        closeModal,
        setSalesStatusFilter: (st) => { state.salesStatusFilter = st; renderSalesView(); },
        setSalesSearch: (q) => { state.salesSearch = q; renderSalesView(); }
    };
})();

window.addEventListener('DOMContentLoaded', () => LiterosApp.init());
