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
        paymentMethod: 'Card',
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

        // Active modal item for modifiers
        pendingMenuItem: null,
        selectedModifiers: []
    };

    // =========================================================================
    // API & UTILITIES
    // =========================================================================
    async function api(action, payload = null, queryParams = '') {
        const url = `api/index.php?action=${encodeURIComponent(action)}${queryParams}`;
        const options = payload
            ? {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
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
        const sym = state.company?.currency_symbol || '$';
        const num = Number(val || 0);
        return `${sym}${num.toFixed(2)}`;
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
        setupClock();
        setupKeyboardShortcuts();
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
        renderTopBar();
        renderPosView();
        renderKdsView();
        renderTablesView();
        renderSalesView();
        renderMenuStudioView();
        renderStaffView();
        renderBranchesView();
        renderCompanyView();
        updateBadgeCounts();
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
        state.activeTab = tabName;
        document.querySelectorAll('.view-section').forEach(sec => {
            sec.classList.toggle('hidden', sec.id !== `view-${tabName}`);
        });
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });
    }

    async function switchBranch(branchId) {
        state.activeBranchId = Number(branchId);
        state.selectedTableId = '';
        const branchStaff = state.staff.find(s => Number(s.branch_id) === state.activeBranchId);
        if (branchStaff) {
            state.activeStaff = branchStaff;
        }
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

        // Parse any (+$X) upcharge inside selected modifiers
        let extraPrice = 0;
        allMods.forEach(m => {
            const match = m.match(/\+\$(\d+(?:\.\d+)?)/);
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
        const validOrders = state.orders.filter(o => o.status !== 'Cancelled');
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
            const methods = ['Card', 'Cash', 'QR Pay', 'Split'];
            breakdownEl.innerHTML = methods.map(m => {
                const sum = validOrders.filter(o => o.payment_method === m).reduce((acc, o) => acc + Number(o.total_amount), 0);
                const pct = grossRev > 0 ? Math.round((sum / grossRev) * 100) : 0;
                return `
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-medium text-slate-300">${m}</span>
                            <span class="font-mono-num text-slate-400">${money(sum)} (${pct}%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                            <div class="h-full bg-amber-500 rounded-full" style="width:${pct}%"></div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Orders Table
        const tbody = document.getElementById('salesTableBody');
        if (!tbody) return;

        const q = state.salesSearch.toLowerCase().trim();
        const filtered = state.orders.filter(o => {
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
                            ${o.payment_status === 'Unpaid' ? `
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
            const res = await api('settle_order_payment', { order_id: orderId, payment_method: 'Card', tip_amount: 10 });
            await loadBootstrap(state.activeBranchId);
            toast(res.message, 'success');
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    function viewReceipt(orderId) {
        const ord = state.orders.find(o => Number(o.id) === Number(orderId));
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
                        <button onclick="LiterosApp.editMenuItem(${item.id})"
                                class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-slate-200 mr-1">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button onclick="LiterosApp.deleteMenuItem(${item.id})"
                                class="px-2.5 py-1 rounded-lg bg-rose-950/60 hover:bg-rose-600 text-xs text-rose-300 hover:text-white">
                            <i class="fa-solid fa-trash"></i>
                        </button>
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
            document.getElementById('quickPinInput').value = '';
            closeModal('pinModal');
            renderAll();
            toast(res.message, 'success');
        } catch (err) {
            toast(err.message, 'error');
        }
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
        form.tagline.value = c.tagline || '';
        form.logo_text.value = c.logo_text || 'LR';
        form.currency_symbol.value = c.currency_symbol || '$';
        form.currency_code.value = c.currency_code || 'USD';
        form.tax_rate.value = c.tax_rate ?? 8.25;
        form.service_charge_rate.value = c.service_charge_rate ?? 5.0;
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
                ['modifierModal', 'checkoutModal', 'receiptModal', 'menuItemModal', 'staffModal', 'branchModal', 'tableModal', 'pinModal']
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
        openNewBranchModal,
        editBranch,
        submitBranchForm,
        submitCompanyProfile,
        resetDemoDatabase,
        openModal,
        closeModal,
        setSalesStatusFilter: (st) => { state.salesStatusFilter = st; renderSalesView(); },
        setSalesSearch: (q) => { state.salesSearch = q; renderSalesView(); }
    };
})();

window.addEventListener('DOMContentLoaded', () => LiterosApp.init());
