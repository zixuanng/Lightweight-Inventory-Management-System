/**
 * IMS Enterprise Edition — Vanilla JS Application
 * Zero external dependencies. All modules in one file.
 */
'use strict';

// ─── Global State ─────────────────────────────────────────────────────────────
const State = {
    user:       null,
    csrfToken:  '',
    tab:        'dashboard',
    theme:      localStorage.getItem('ims-theme') || 'light',
    cache: {
        products: [], categories: [], suppliers: [],
        warehouses: [], locations: [], batches: [],
    },
};

// ─── Bootstrap ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    applyTheme(State.theme);
    checkSession();
    document.getElementById('themeToggle')?.addEventListener('click', toggleTheme);
});

// ─── Theme ────────────────────────────────────────────────────────────────────
function applyTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    const btn = document.getElementById('themeToggle');
    if (btn) btn.textContent = t === 'dark' ? '☀️' : '🌙';
}
function toggleTheme() {
    State.theme = State.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('ims-theme', State.theme);
    applyTheme(State.theme);
    if (State.tab === 'analytics') {
        loadAnalytics();
    }
}

// ─── Auth ─────────────────────────────────────────────────────────────────────
async function checkSession() {
    const res = await api('api/v1/auth/me.php');
    if (res?.user) {
        State.user      = res.user;
        State.csrfToken = res.csrf_token || '';
        showApp();
    } else {
        showLogin();
    }
}

function showLogin() {
    document.getElementById('loginSection').style.display = 'flex';
    document.getElementById('appSection').style.display   = 'none';
    const form = document.getElementById('loginForm');
    form.onsubmit = async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true; btn.textContent = 'Signing in…';
        const res = await api('api/v1/auth/login.php', 'POST', {
            username: form.username.value.trim(),
            password: form.password.value.trim(),
        });
        btn.disabled = false; btn.textContent = 'Sign In';
        if (res?.user) {
            State.user = res.user; State.csrfToken = res.csrf_token || '';
            showApp();
        } else {
            toast(res?.error || 'Login failed.', 'error');
        }
    };
}

function showApp() {
    document.getElementById('loginSection').style.display = 'none';
    document.getElementById('appSection').style.display   = 'flex';
    document.getElementById('sidebarUserName').textContent = State.user.username;
    document.getElementById('sidebarUserRole').textContent = State.user.role_name;

    // Show/hide admin-only nav items
    document.querySelectorAll('[data-role-min]').forEach(el => {
        const min = el.getAttribute('data-role-min');
        const allowed = {
            'clerk':   ['Super Admin','Warehouse Manager','Inventory Clerk'],
            'manager': ['Super Admin','Warehouse Manager'],
            'admin':   ['Super Admin'],
        }[min] || [];
        el.style.display = allowed.includes(State.user.role_name) ? 'block' : 'none';
    });

    bindNavLinks();
    bindLogout();
    switchTab('dashboard');
}

function bindLogout() {
    document.getElementById('logoutBtn')?.addEventListener('click', async () => {
        await api('api/v1/auth/logout.php', 'POST');
        State.user = null; State.csrfToken = '';
        showLogin();
        toast('Logged out.', 'success');
    });
}

function bindNavLinks() {
    document.querySelectorAll('.nav-item a[data-tab]').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            switchTab(link.getAttribute('data-tab'));
        });
    });
}

// ─── Tab Router ───────────────────────────────────────────────────────────────
async function switchTab(tab) {
    State.tab = tab;
    document.querySelectorAll('.nav-item').forEach(li => {
        li.classList.toggle('active', li.querySelector('[data-tab]')?.getAttribute('data-tab') === tab);
    });
    const titles = {
        dashboard:  '📊 Dashboard Overview',
        inventory:  '📦 Inventory Stock Levels',
        products:   '🏷️ Products Management',
        warehouses: '🏭 Warehouses & Locations',
        batches:    '🗓️ Batch & Expiry Tracking',
        transfers:  '🔀 Stock Transfers',
        pos:        '📋 Purchase Orders',
        analytics:  '📈 Analytics & ABC Classification',
        auditlog:   '📜 Audit Log',
        users:      '👥 User Management',
    };
    document.getElementById('pageTitle').textContent = titles[tab] || 'IMS Enterprise';

    // Reset main area
    const content = document.getElementById('mainContent');
    content.innerHTML = '<div class="empty-state"><div class="empty-icon">⏳</div><p>Loading…</p></div>';

    const loaders = {
        dashboard:  loadDashboard,
        inventory:  loadInventory,
        products:   loadProducts,
        warehouses: loadWarehouses,
        batches:    loadBatches,
        transfers:  loadTransfers,
        pos:        loadPurchaseOrders,
        analytics:  loadAnalytics,
        auditlog:   loadAuditLog,
        users:      loadUsers,
    };
    if (loaders[tab]) await loaders[tab]();
}

// ─── Core API Helper ──────────────────────────────────────────────────────────
async function api(url, method = 'GET', body = null, isFormData = false) {
    const opts = { method, headers: {} };
    if (State.csrfToken && method !== 'GET') {
        opts.headers['X-CSRF-TOKEN'] = State.csrfToken;
    }
    if (body !== null && !isFormData) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    } else if (isFormData) {
        opts.body = body;
    }
    try {
        const res  = await fetch(url, opts);
        const text = await res.text();
        if (!text) return {};
        return JSON.parse(text);
    } catch (err) {
        console.error('API error:', err);
        return { error: 'Network error. Please check your connection.' };
    }
}

// ─── Utility Helpers ──────────────────────────────────────────────────────────
function esc(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmt(n, d = 2) { return '$' + parseFloat(n||0).toLocaleString('en-US', {minimumFractionDigits:d, maximumFractionDigits:d}); }
function fmtNum(n) { return parseInt(n||0).toLocaleString(); }
function badge(text, cls) { return `<span class="badge ${cls}">${esc(text)}</span>`; }

function abcBadge(cls) {
    const map = { A: 'badge-abc-a', B: 'badge-abc-b', C: 'badge-abc-c' };
    return badge('Class ' + (cls||'C'), map[cls] || 'badge-abc-c');
}
function statusBadge(s) {
    const map = {
        Draft:'badge-draft', Sent:'badge-sent', Partial:'badge-partial',
        Completed:'badge-completed', Cancelled:'badge-cancelled',
        Pending:'badge-pending', 'In-Transit':'badge-transit', Received:'badge-received',
    };
    return badge(s, map[s] || 'badge-muted');
}
function expiryBadge(days) {
    if (days < 0)  return badge('Expired', 'badge-danger');
    if (days <= 30) return badge(`${days}d ⚠️`, 'badge-danger');
    if (days <= 60) return badge(`${days}d`, 'badge-warning');
    if (days <= 90) return badge(`${days}d`, 'badge-muted');
    return '';
}
function trExpiryClass(days) {
    if (days < 0)  return 'tr-exp-30';
    if (days <= 30) return 'tr-exp-30';
    if (days <= 60) return 'tr-exp-60';
    if (days <= 90) return 'tr-exp-90';
    return '';
}

function toast(msg, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = `<span>${esc(msg)}</span>`;
    container.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .4s'; setTimeout(() => t.remove(), 400); }, 3500);
}

function openModal(id) {
    document.querySelectorAll('.modal-backdrop.active').forEach(m => m.classList.remove('active'));
    document.getElementById(id)?.classList.add('active');
}
function closeAllModals() {
    document.querySelectorAll('.modal-backdrop.active').forEach(m => m.classList.remove('active'));
}
document.addEventListener('click', e => {
    if (e.target.matches('.modal-close') || e.target.matches('.btn-cancel-modal') || e.target.matches('.modal-backdrop')) {
        closeAllModals();
    }
});

// Pre-load lookup caches
async function preloadCaches() {
    const [prods, cats, sups, whs, locs, bats] = await Promise.all([
        api('api/v1/products.php'),
        api('api/v1/categories.php'),
        api('api/v1/suppliers.php'),
        api('api/v1/warehouses.php'),
        api('api/v1/locations.php'),
        api('api/v1/batches.php'),
    ]);
    State.cache.products   = Array.isArray(prods) ? prods : [];
    State.cache.categories = Array.isArray(cats)  ? cats  : [];
    State.cache.suppliers  = Array.isArray(sups)  ? sups  : [];
    State.cache.warehouses = Array.isArray(whs)   ? whs   : [];
    State.cache.locations  = Array.isArray(locs)  ? locs  : [];
    State.cache.batches    = Array.isArray(bats)  ? bats  : [];
}

function selectOptions(arr, valKey, labelKey, selectedVal = '') {
    return arr.map(r =>
        `<option value="${r[valKey]}" ${r[valKey] == selectedVal ? 'selected' : ''}>${esc(r[labelKey])}</option>`
    ).join('');
}

// ─── DASHBOARD ────────────────────────────────────────────────────────────────
async function loadDashboard() {
    const stats = await api('api/v1/dashboard.php');
    if (stats.error) { showError(stats.error); return; }
    const content = document.getElementById('mainContent');
    content.innerHTML = `
    <div class="metrics-row-top">
      <div class="metric-card card-blue">
        <div class="metric-data">
          <span class="metric-label">Total SKUs</span>
          <div class="metric-value">${fmtNum(stats.total_skus)}</div>
          <div class="metric-sub">Active in catalogue</div>
        </div>
        <div class="metric-icon icon-blue">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
        </div>
      </div>

      <div class="metric-card card-green">
        <div class="metric-data">
          <span class="metric-label">Inventory Value</span>
          <div class="metric-value">${fmt(stats.total_value)}</div>
          <div class="metric-sub">Total stock valuation</div>
        </div>
        <div class="metric-icon icon-green">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
      </div>

      <div class="metric-card card-red">
        <div class="metric-data">
          <span class="metric-label">Low Stock Items</span>
          <div class="metric-value">${fmtNum(stats.low_stock_count)}</div>
          <div class="metric-sub">${stats.low_stock_count > 0 ? '<span class="status-badge-warn">⚠️ Needs attention</span>' : '<span class="status-badge-ok">✓ Optimum levels</span>'}</div>
        </div>
        <div class="metric-icon icon-red">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
      </div>

      <div class="metric-card card-amber">
        <div class="metric-data">
          <span class="metric-label">Expiry Alerts</span>
          <div class="metric-value">${fmtNum((stats.expiry_30||0)+(stats.expiry_60||0)+(stats.expiry_90||0))}</div>
          <div class="expiry-tier-row">
            <span class="expiry-chip r30" title="Expires in ≤30 days">≤30d: <strong>${stats.expiry_30||0}</strong></span>
            <span class="expiry-chip r60" title="Expires in ≤60 days">≤60d: <strong>${stats.expiry_60||0}</strong></span>
            <span class="expiry-chip r90" title="Expires in ≤90 days">≤90d: <strong>${stats.expiry_90||0}</strong></span>
          </div>
        </div>
        <div class="metric-icon icon-amber">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
      </div>
    </div>

    <div class="metrics-row-bottom">
      <div class="metric-card card-indigo">
        <div class="metric-data">
          <span class="metric-label">Open Purchase Orders</span>
          <div class="metric-value">${fmtNum(stats.open_pos)}</div>
          <div class="metric-sub">${stats.open_pos > 0 ? '<span class="status-badge-info">Awaiting delivery</span>' : 'No active POs'}</div>
        </div>
        <div class="metric-icon icon-indigo">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 14h6"/><path d="M9 18h6"/><path d="M9 10h6"/></svg>
        </div>
      </div>

      <div class="metric-card card-purple">
        <div class="metric-data">
          <span class="metric-label">Pending Transfers</span>
          <div class="metric-value">${fmtNum(stats.pending_transfers)}</div>
          <div class="metric-sub">${stats.pending_transfers > 0 ? '<span class="status-badge-purple">Moving between locations</span>' : 'All stock settled'}</div>
        </div>
        <div class="metric-icon icon-purple">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/></svg>
        </div>
      </div>

      <div class="metric-card card-cyan">
        <div class="metric-data">
          <span class="metric-label">ABC Distribution</span>
          <div class="metric-value">${(stats.abc?.A||0)+(stats.abc?.B||0)+(stats.abc?.C||0)} <span class="metric-unit">SKUs</span></div>
          <div class="abc-tier-row">
            <span class="abc-chip chip-a" title="Class A (High Value)">A: <strong>${stats.abc?.A||0}</strong></span>
            <span class="abc-chip chip-b" title="Class B (Medium Value)">B: <strong>${stats.abc?.B||0}</strong></span>
            <span class="abc-chip chip-c" title="Class C (Low Value)">C: <strong>${stats.abc?.C||0}</strong></span>
          </div>
        </div>
        <div class="metric-icon icon-cyan">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect width="4" height="7" x="7" y="10" rx="1"/><rect width="4" height="12" x="15" y="5" rx="1"/></svg>
        </div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
      <div class="card">
        <div class="card-header"><h3>Quick Actions</h3></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:.75rem;">
          <button class="btn btn-success" onclick="switchTab('inventory')">⚡ Log Stock Movement</button>
          <button class="btn btn-primary" onclick="switchTab('pos')">📋 Create Purchase Order</button>
          <button class="btn btn-secondary" onclick="switchTab('transfers')">🔀 Initiate Stock Transfer</button>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h3>CSV Exports</h3></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:.75rem;">
          <a href="api/v1/export.php?type=inventory"      class="btn btn-secondary">📥 Export Inventory</a>
          <a href="api/v1/export.php?type=stock_logs"     class="btn btn-secondary">📜 Export Stock Logs</a>
          <a href="api/v1/export.php?type=purchase_orders" class="btn btn-secondary">📋 Export PO History</a>
          <a href="api/v1/export.php?type=audit"          class="btn btn-secondary">🔍 Export Audit Logs</a>
        </div>
      </div>
    </div>`;
}

// ─── INVENTORY ────────────────────────────────────────────────────────────────
async function loadInventory() {
    await preloadCaches();
    const content = document.getElementById('mainContent');
    content.innerHTML = `
    <div class="table-controls">
      <div class="filter-group">
        <input id="invSearch" class="form-control" placeholder="🔍 Search SKU / Name…" style="min-width:220px;">
        <select id="invWarehouse" class="form-control" style="min-width:170px;">
          <option value="">All Warehouses</option>
          ${selectOptions(State.cache.warehouses,'warehouse_id','name')}
        </select>
        <select id="invAbc" class="form-control">
          <option value="">All ABC</option>
          <option value="A">Class A</option>
          <option value="B">Class B</option>
          <option value="C">Class C</option>
        </select>
        <button id="invLowStock" class="btn btn-secondary">⚠️ Low Stock Only</button>
      </div>
      <div class="action-group">
        ${canClerk() ? '<button class="btn btn-success" id="btnStockIn">📥 STOCK IN</button><button class="btn btn-danger" id="btnStockOut">📤 STOCK OUT</button>' : ''}
      </div>
    </div>
    <div class="card"><div class="table-wrap"><div id="invTable">Loading…</div></div></div>`;

    let lowFilter = false;
    const refresh = () => fetchInventory(
        document.getElementById('invSearch').value,
        document.getElementById('invWarehouse').value,
        document.getElementById('invAbc').value,
        lowFilter
    );

    ['invSearch','invWarehouse','invAbc'].forEach(id =>
        document.getElementById(id)?.addEventListener('change', refresh));
    document.getElementById('invSearch')?.addEventListener('input', refresh);
    document.getElementById('invLowStock')?.addEventListener('click', function() {
        lowFilter = !lowFilter;
        this.classList.toggle('btn-filter-active', lowFilter);
        this.textContent = lowFilter ? '⚠️ Showing Low Stock' : '⚠️ Low Stock Only';
        refresh();
    });
    document.getElementById('btnStockIn')?.addEventListener('click', () => openStockModal('STOCK_IN'));
    document.getElementById('btnStockOut')?.addEventListener('click', () => openStockModal('STOCK_OUT'));
    await refresh();
}

async function fetchInventory(search='', wh='', abc='', low=false) {
    const params = new URLSearchParams({ search, warehouse_id: wh, abc_class: abc, low_stock: low?'1':'' });
    const rows = await api(`api/v1/inventory/list.php?${params}`);
    const el = document.getElementById('invTable');
    if (!el) return;
    if (!Array.isArray(rows) || rows.length === 0) {
        el.innerHTML = '<div class="empty-state"><div class="empty-icon">📦</div><h3>No inventory records found</h3></div>';
        return;
    }
    el.innerHTML = `<table class="table">
      <thead><tr>
        <th>SKU</th><th>Product</th><th>ABC</th><th>Batch</th><th>Expiry</th>
        <th>Warehouse</th><th>Zone / Bin</th><th>Qty</th><th>Status</th>
        ${canClerk() ? '<th>Actions</th>' : ''}
      </tr></thead>
      <tbody>
        ${rows.map(r => {
            const days = parseInt(r.days_to_expiry ?? 999);
            const low  = r.is_low_stock ? 'tr-low-stock' : trExpiryClass(days);
            return `<tr class="${low}">
              <td><strong>${esc(r.sku)}</strong></td>
              <td>${esc(r.product_name)}</td>
              <td>${abcBadge(r.abc_class)}</td>
              <td>${esc(r.batch_number)}</td>
              <td>${esc(r.expiry_date)} ${expiryBadge(days)}</td>
              <td>${esc(r.warehouse_name)}</td>
              <td>${esc(r.zone)} / ${esc(r.shelf_bin)}</td>
              <td><strong>${r.quantity}</strong></td>
              <td>${r.is_low_stock ? badge('LOW STOCK','badge-danger') : badge('OK','badge-success')}</td>
              ${canClerk() ? `<td>
                <button class="btn btn-xs btn-success" onclick="openStockModal('STOCK_IN','${r.product_id}','${r.batch_id}','${r.location_id}')">IN</button>
                <button class="btn btn-xs btn-danger" onclick="openStockModal('STOCK_OUT','${r.product_id}','${r.batch_id}','${r.location_id}')">OUT</button>
              </td>` : ''}
            </tr>`;
        }).join('')}
      </tbody></table>`;
}

function openStockModal(type, productId='', batchId='', locationId='') {
    const m = document.getElementById('stockModal');
    if (!m) return;
    m.querySelector('#stockModalTitle').textContent = type === 'STOCK_IN' ? '📥 Record STOCK IN' : '📤 Record STOCK OUT';
    m.querySelector('#stockType').value = type;

    const prodSel  = m.querySelector('#stockProduct');
    const batchSel = m.querySelector('#stockBatch');
    const locSel   = m.querySelector('#stockLocation');

    prodSel.innerHTML  = '<option value="">-- Select Product --</option>' + selectOptions(State.cache.products,'product_id','name', productId);
    batchSel.innerHTML = '<option value="">-- Select Batch --</option>' + State.cache.batches.filter(b => !productId || b.product_id == productId).map(b => `<option value="${b.batch_id}" ${b.batch_id==batchId?'selected':''}>[${esc(b.batch_number)}] Exp: ${esc(b.expiry_date)}</option>`).join('');
    locSel.innerHTML   = '<option value="">-- Select Location --</option>' + State.cache.locations.map(l => `<option value="${l.location_id}" ${l.location_id==locationId?'selected':''}>${esc(l.warehouse_name)} › ${esc(l.zone)}/${esc(l.shelf_bin)}</option>`).join('');

    // Filter batches when product changes (for STOCK_IN)
    prodSel.onchange = () => {
        const pid = prodSel.value;
        batchSel.innerHTML = '<option value="">-- Select Batch --</option>' +
            State.cache.batches.filter(b => !pid || b.product_id == pid).map(b =>
                `<option value="${b.batch_id}">[${esc(b.batch_number)}] Exp: ${esc(b.expiry_date)}</option>`
            ).join('');
    };

    // For STOCK_IN, show new batch fields
    const newBatchSection = m.querySelector('#newBatchSection');
    if (newBatchSection) {
        newBatchSection.style.display = type === 'STOCK_IN' ? 'block' : 'none';
        m.querySelector('#stockBatchRow').style.display = type === 'STOCK_IN' ? 'none' : 'flex';
    }

    m.querySelector('#stockForm').onsubmit = async (e) => {
        e.preventDefault();
        const payload = {
            transaction_type: type,
            product_id:  m.querySelector('#stockProduct').value,
            quantity:    m.querySelector('#stockQty').value,
            reference_id: m.querySelector('#stockRef').value.trim(),
            notes:       m.querySelector('#stockNotes').value.trim(),
            location_id: m.querySelector('#stockLocation').value,
        };
        if (type === 'STOCK_IN') {
            const nb = m.querySelector('#newBatchNum').value.trim();
            if (nb) {
                // Create batch first
                const bRes = await api('api/v1/batches.php', 'POST', {
                    csrf_token: State.csrfToken,
                    product_id: payload.product_id,
                    batch_number: nb,
                    manufacturing_date: m.querySelector('#newMfgDate').value,
                    expiry_date: m.querySelector('#newExpDate').value,
                });
                if (bRes.error) { toast(bRes.error,'error'); return; }
                payload.batch_id = bRes.batch_id;
            } else {
                payload.batch_id = m.querySelector('#stockBatch').value;
            }
        } else {
            payload.batch_id = m.querySelector('#stockBatch').value;
        }
        const res = await api('api/v1/inventory/adjust.php', 'POST', { ...payload, csrf_token: State.csrfToken });
        if (res.error) { toast(res.error, 'error'); return; }
        toast(res.message, 'success');
        closeAllModals();
        await preloadCaches();
        await fetchInventory();
    };
    openModal('stockModal');
}

// ─── PRODUCTS ─────────────────────────────────────────────────────────────────
async function loadProducts() {
    await preloadCaches();
    const content = document.getElementById('mainContent');
    content.innerHTML = `
    <div class="table-controls">
      <div class="filter-group">
        <input id="prodSearch" class="form-control" placeholder="🔍 Search…" style="min-width:200px;">
        <select id="prodAbc" class="form-control">
          <option value="">All ABC</option>
          <option value="A">Class A</option><option value="B">Class B</option><option value="C">Class C</option>
        </select>
      </div>
      <div class="action-group">
        ${canManager() ? '<button class="btn btn-primary" onclick="openProductModal()">+ Add Product</button>' : ''}
      </div>
    </div>
    <div class="card"><div class="table-wrap"><div id="prodTable">Loading…</div></div></div>`;

    const render = async () => {
        const params = new URLSearchParams({ search: document.getElementById('prodSearch').value, abc_class: document.getElementById('prodAbc').value });
        const rows = await api(`api/v1/products.php?${params}`);
        const el = document.getElementById('prodTable');
        if (!el) return;
        if (!Array.isArray(rows) || !rows.length) { el.innerHTML = '<div class="empty-state"><div class="empty-icon">🏷️</div><h3>No products found</h3></div>'; return; }
        el.innerHTML = `<table class="table"><thead><tr>
          <th>SKU</th><th>Barcode</th><th>Name</th><th>Category</th><th>Supplier</th>
          <th>Total Qty</th><th>Reorder</th><th>Unit Cost</th><th>Unit Price</th><th>ABC</th>
          ${canManager() ? '<th>Actions</th>' : ''}
        </tr></thead><tbody>
          ${rows.map(r => `<tr class="${r.total_quantity<=r.reorder_level?'tr-low-stock':''}">
            <td><strong>${esc(r.sku)}</strong></td>
            <td><small>${esc(r.barcode||'—')}</small></td>
            <td>${esc(r.name)}</td>
            <td>${esc(r.category_name||'—')}</td>
            <td>${esc(r.supplier_name||'—')}</td>
            <td><strong>${r.total_quantity||0}</strong></td>
            <td>${r.reorder_level}</td>
            <td>${fmt(r.unit_cost)}</td>
            <td>${fmt(r.unit_price)}</td>
            <td>${abcBadge(r.abc_class)}</td>
            ${canManager() ? `<td>
              <button class="btn btn-xs btn-secondary" onclick='openProductModal(${JSON.stringify(r)})'>✏️</button>
              ${canAdmin() ? `<button class="btn btn-xs btn-danger" onclick="deleteProduct(${r.product_id})">🗑️</button>` : ''}
            </td>` : ''}
          </tr>`).join('')}
        </tbody></table>`;
    };
    ['prodSearch','prodAbc'].forEach(id => { document.getElementById(id)?.addEventListener('input', render); document.getElementById(id)?.addEventListener('change', render); });
    await render();
}

function openProductModal(p = null) {
    const m = document.getElementById('productModal');
    if (!m) return;
    m.querySelector('#prodModalTitle').textContent = p ? 'Edit Product' : 'Add Product';
    m.querySelector('#prodId').value          = p?.product_id || '';
    m.querySelector('#prodSku').value         = p?.sku || '';
    m.querySelector('#prodBarcode').value     = p?.barcode || '';
    m.querySelector('#prodName').value        = p?.name || '';
    m.querySelector('#prodCost').value        = p?.unit_cost || '';
    m.querySelector('#prodPrice').value       = p?.unit_price || '';
    m.querySelector('#prodReorder').value     = p?.reorder_level || 10;
    m.querySelector('#prodAbc').value         = p?.abc_class || 'C';
    m.querySelector('#prodCategory').innerHTML  = '<option value="">— None —</option>' + selectOptions(State.cache.categories,'category_id','category_name', p?.category_id||'');
    m.querySelector('#prodSupplier').innerHTML  = '<option value="">— None —</option>' + selectOptions(State.cache.suppliers,'supplier_id','supplier_name', p?.default_supplier_id||'');
    m.querySelector('#productForm').onsubmit = async (e) => {
        e.preventDefault();
        const payload = {
            csrf_token: State.csrfToken,
            product_id: m.querySelector('#prodId').value,
            sku: m.querySelector('#prodSku').value.trim(),
            barcode: m.querySelector('#prodBarcode').value.trim(),
            name: m.querySelector('#prodName').value.trim(),
            category_id: m.querySelector('#prodCategory').value,
            default_supplier_id: m.querySelector('#prodSupplier').value,
            unit_cost: m.querySelector('#prodCost').value,
            unit_price: m.querySelector('#prodPrice').value,
            reorder_level: m.querySelector('#prodReorder').value,
            abc_class: m.querySelector('#prodAbc').value,
        };
        const res = await api('api/v1/products.php', 'POST', payload);
        if (res.error) { toast(res.error,'error'); return; }
        toast(res.message,'success'); closeAllModals();
        await preloadCaches(); await loadProducts();
    };
    openModal('productModal');
}

async function deleteProduct(id) {
    if (!confirm('Delete this product? This cannot be undone.')) return;
    const res = await api(`api/v1/products.php?id=${id}`, 'DELETE', { csrf_token: State.csrfToken });
    if (res.error) { toast(res.error,'error'); return; }
    toast(res.message,'success'); await preloadCaches(); await loadProducts();
}

// ─── WAREHOUSES & LOCATIONS ───────────────────────────────────────────────────
async function loadWarehouses() {
    const content = document.getElementById('mainContent');
    const [whs, locs] = await Promise.all([api('api/v1/warehouses.php'), api('api/v1/locations.php')]);
    const whList = Array.isArray(whs) ? whs : [];
    const locList = Array.isArray(locs) ? locs : [];
    content.innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
      <div class="card">
        <div class="card-header"><h3>🏭 Warehouses</h3>${canManager()?'<button class="btn btn-primary btn-sm" onclick="openWarehouseModal()">+ Add</button>':''}</div>
        <div class="table-wrap"><table class="table"><thead><tr><th>Code</th><th>Name</th><th>Locations</th><th>Address</th>${canManager()?'<th></th>':''}</tr></thead>
        <tbody>${whList.map(w=>`<tr>
          <td><strong>${esc(w.code)}</strong></td>
          <td>${esc(w.name)}</td>
          <td>${badge(w.location_count+' loc','badge-info')}</td>
          <td><small>${esc(w.address||'—')}</small></td>
          ${canManager()?`<td><button class="btn btn-xs btn-secondary" onclick='openWarehouseModal(${JSON.stringify(w)})'>✏️</button> ${canAdmin()?`<button class="btn btn-xs btn-danger" onclick="deleteWarehouse(${w.warehouse_id})">🗑️</button>`:''}` :''}
        </tr>`).join('')}</tbody></table></div>
      </div>
      <div class="card">
        <div class="card-header"><h3>📍 Locations</h3>${canManager()?'<button class="btn btn-primary btn-sm" onclick="openLocationModal()">+ Add</button>':''}</div>
        <div class="table-wrap"><table class="table"><thead><tr><th>Warehouse</th><th>Zone</th><th>Shelf/Bin</th>${canManager()?'<th></th>':''}</tr></thead>
        <tbody>${locList.map(l=>`<tr>
          <td>${esc(l.warehouse_name)}</td>
          <td>${esc(l.zone)}</td>
          <td>${esc(l.shelf_bin)}</td>
          ${canManager()?`<td><button class="btn btn-xs btn-secondary" onclick='openLocationModal(${JSON.stringify(l)})'>✏️</button> ${canAdmin()?`<button class="btn btn-xs btn-danger" onclick="deleteLocation(${l.location_id})">🗑️</button>`:''}` :''}
        </tr>`).join('')}</tbody></table></div>
      </div>
    </div>`;
    State.cache.warehouses = whList;
    State.cache.locations  = locList;
}

function openWarehouseModal(w = null) {
    const m = document.getElementById('warehouseModal');
    if (!m) return;
    m.querySelector('#whId').value      = w?.warehouse_id || '';
    m.querySelector('#whCode').value    = w?.code || '';
    m.querySelector('#whName').value    = w?.name || '';
    m.querySelector('#whAddress').value = w?.address || '';
    m.querySelector('#warehouseForm').onsubmit = async (e) => {
        e.preventDefault();
        const res = await api('api/v1/warehouses.php','POST',{
            csrf_token: State.csrfToken,
            warehouse_id: m.querySelector('#whId').value,
            code: m.querySelector('#whCode').value.trim(),
            name: m.querySelector('#whName').value.trim(),
            address: m.querySelector('#whAddress').value.trim(),
        });
        if (res.error) { toast(res.error,'error'); return; }
        toast(res.message,'success'); closeAllModals(); await loadWarehouses();
    };
    openModal('warehouseModal');
}

function openLocationModal(l = null) {
    const m = document.getElementById('locationModal');
    if (!m) return;
    m.querySelector('#locId').value        = l?.location_id || '';
    m.querySelector('#locZone').value      = l?.zone || '';
    m.querySelector('#locShelfBin').value  = l?.shelf_bin || '';
    m.querySelector('#locWarehouse').innerHTML = '<option value="">— Select Warehouse —</option>' + selectOptions(State.cache.warehouses,'warehouse_id','name', l?.warehouse_id||'');
    m.querySelector('#locationForm').onsubmit = async (e) => {
        e.preventDefault();
        const res = await api('api/v1/locations.php','POST',{
            csrf_token: State.csrfToken,
            location_id: m.querySelector('#locId').value,
            warehouse_id: m.querySelector('#locWarehouse').value,
            zone: m.querySelector('#locZone').value.trim(),
            shelf_bin: m.querySelector('#locShelfBin').value.trim(),
        });
        if (res.error) { toast(res.error,'error'); return; }
        toast(res.message,'success'); closeAllModals(); await loadWarehouses();
    };
    openModal('locationModal');
}

async function deleteWarehouse(id) {
    if (!confirm('Delete this warehouse? All locations inside will also be removed.')) return;
    const res = await api(`api/v1/warehouses.php?id=${id}`,'DELETE',{csrf_token:State.csrfToken});
    if (res.error) { toast(res.error,'error'); return; }
    toast(res.message,'success'); await loadWarehouses();
}
async function deleteLocation(id) {
    if (!confirm('Delete this location?')) return;
    const res = await api(`api/v1/locations.php?id=${id}`,'DELETE',{csrf_token:State.csrfToken});
    if (res.error) { toast(res.error,'error'); return; }
    toast(res.message,'success'); await loadWarehouses();
}

// ─── BATCHES ──────────────────────────────────────────────────────────────────
async function loadBatches() {
    await preloadCaches();
    const content = document.getElementById('mainContent');
    content.innerHTML = `
    <div class="table-controls">
      <div class="filter-group">
        <select id="batchProd" class="form-control" style="min-width:200px;">
          <option value="">All Products</option>
          ${selectOptions(State.cache.products,'product_id','name')}
        </select>
      </div>
      <div class="action-group">
        ${canClerk() ? '<button class="btn btn-primary" onclick="openBatchModal()">+ Add Batch</button>' : ''}
      </div>
    </div>
    <div class="card"><div class="table-wrap"><div id="batchTable">Loading…</div></div></div>`;
    const render = async () => {
        const pid = document.getElementById('batchProd').value;
        const rows = await api(`api/v1/batches.php${pid?'?product_id='+pid:''}`);
        const el = document.getElementById('batchTable');
        if (!el) return;
        if (!Array.isArray(rows)||!rows.length) { el.innerHTML='<div class="empty-state"><div class="empty-icon">🗓️</div><h3>No batches found</h3></div>'; return; }
        el.innerHTML = `<table class="table"><thead><tr>
          <th>Batch #</th><th>Product</th><th>Mfg Date</th><th>Expiry Date</th><th>Days Left</th><th>Total Qty</th>
          ${canClerk()?'<th>Actions</th>':''}
        </tr></thead><tbody>
          ${rows.map(r=>{
            const days = parseInt(r.days_to_expiry??999);
            return `<tr class="${trExpiryClass(days)}">
              <td><strong>${esc(r.batch_number)}</strong></td>
              <td>${esc(r.product_name)} <small class="text-muted">[${esc(r.sku)}]</small></td>
              <td>${esc(r.manufacturing_date||'—')}</td>
              <td>${esc(r.expiry_date)}</td>
              <td>${expiryBadge(days)||days+'d'}</td>
              <td><strong>${r.total_quantity||0}</strong></td>
              ${canClerk()?`<td>${canManager()?`<button class="btn btn-xs btn-danger" onclick="deleteBatch(${r.batch_id})">🗑️</button>`:'—'}</td>`:''}
            </tr>`;
          }).join('')}
        </tbody></table>`;
    };
    document.getElementById('batchProd')?.addEventListener('change', render);
    await render();
}

function openBatchModal(productId='') {
    const m = document.getElementById('batchModal');
    if (!m) return;
    m.querySelector('#batchProduct').innerHTML = '<option value="">— Select Product —</option>' + selectOptions(State.cache.products,'product_id','name',productId);
    m.querySelector('#batchForm').onsubmit = async (e) => {
        e.preventDefault();
        const res = await api('api/v1/batches.php','POST',{
            csrf_token: State.csrfToken,
            product_id: m.querySelector('#batchProduct').value,
            batch_number: m.querySelector('#batchNum').value.trim(),
            manufacturing_date: m.querySelector('#batchMfg').value,
            expiry_date: m.querySelector('#batchExp').value,
        });
        if (res.error){toast(res.error,'error');return;}
        toast(res.message,'success'); closeAllModals(); await preloadCaches(); await loadBatches();
    };
    openModal('batchModal');
}

async function deleteBatch(id) {
    if (!confirm('Delete this batch? This will also remove associated inventory records.')) return;
    const res = await api(`api/v1/batches.php?id=${id}`,'DELETE',{csrf_token:State.csrfToken});
    if (res.error){toast(res.error,'error');return;}
    toast(res.message,'success'); await preloadCaches(); await loadBatches();
}

// ─── TRANSFERS ────────────────────────────────────────────────────────────────
async function loadTransfers() {
    await preloadCaches();
    const content = document.getElementById('mainContent');
    content.innerHTML = `
    <div class="table-controls">
      <div class="filter-group"></div>
      <div class="action-group">
        ${canManager() ? '<button class="btn btn-primary" onclick="openTransferModal()">+ Create Transfer</button>' : ''}
      </div>
    </div>
    <div class="card"><div class="table-wrap"><div id="transferTable">Loading…</div></div></div>`;
    await renderTransfers();
}

async function renderTransfers() {
    const rows = await api('api/v1/transfers/list.php');
    const el = document.getElementById('transferTable');
    if (!el) return;
    if (!Array.isArray(rows)||!rows.length){el.innerHTML='<div class="empty-state"><div class="empty-icon">🔀</div><h3>No transfers yet</h3></div>';return;}
    el.innerHTML = `<table class="table"><thead><tr>
      <th>Transfer Code</th><th>From</th><th>To</th><th>Status</th><th>Items</th><th>Initiated By</th><th>Date</th>
      ${canManager()?'<th>Actions</th>':''}
    </tr></thead><tbody>
      ${rows.map(r=>`<tr>
        <td><strong>${esc(r.transfer_code)}</strong></td>
        <td><small>${esc(r.from_warehouse)} › ${esc(r.from_zone)}/${esc(r.from_bin)}</small></td>
        <td><small>${esc(r.to_warehouse)} › ${esc(r.to_zone)}/${esc(r.to_bin)}</small></td>
        <td>${statusBadge(r.status)}</td>
        <td>${r.item_count} items</td>
        <td>${esc(r.initiated_by)}</td>
        <td><small>${esc(r.created_at)}</small></td>
        ${canManager()?`<td>
          ${r.status==='Pending'?`<button class="btn btn-xs btn-primary" onclick="progressTransfer(${r.transfer_id},'In-Transit')">→ In Transit</button>`:''}
          ${r.status==='In-Transit'?`<button class="btn btn-xs btn-success" onclick="progressTransfer(${r.transfer_id},'Received')">✓ Received</button>`:''}
          ${['Pending','In-Transit'].includes(r.status)?`<button class="btn btn-xs btn-danger" onclick="progressTransfer(${r.transfer_id},'Cancelled')">✕</button>`:''}
        </td>` : ''}
      </tr>`).join('')}
    </tbody></table>`;
}

function openTransferModal() {
    const m = document.getElementById('transferModal');
    if (!m) return;
    m.querySelector('#trFromLoc').innerHTML = '<option value="">— From Location —</option>' + State.cache.locations.map(l=>`<option value="${l.location_id}">${esc(l.warehouse_name)} › ${esc(l.zone)}/${esc(l.shelf_bin)}</option>`).join('');
    m.querySelector('#trToLoc').innerHTML   = '<option value="">— To Location —</option>'   + State.cache.locations.map(l=>`<option value="${l.location_id}">${esc(l.warehouse_name)} › ${esc(l.zone)}/${esc(l.shelf_bin)}</option>`).join('');

    let itemCount = 1;
    const renderItems = () => {
        const box = m.querySelector('#trItems');
        box.innerHTML = '';
        for (let i=0;i<itemCount;i++) {
            const row = document.createElement('div');
            row.style.cssText = 'display:grid;grid-template-columns:2fr 2fr 1fr;gap:.5rem;margin-bottom:.5rem;';
            row.innerHTML = `
              <select class="form-control tr-prod" data-i="${i}"><option value="">— Product —</option>${selectOptions(State.cache.products,'product_id','name')}</select>
              <select class="form-control tr-batch" data-i="${i}"><option value="">— Batch —</option></select>
              <input type="number" class="form-control tr-qty" min="1" value="1" placeholder="Qty">`;
            box.appendChild(row);
            row.querySelector('.tr-prod').addEventListener('change', function(){
                const pid = this.value;
                row.querySelector('.tr-batch').innerHTML = '<option value="">— Batch —</option>' +
                    State.cache.batches.filter(b=>!pid||b.product_id==pid).map(b=>`<option value="${b.batch_id}">[${esc(b.batch_number)}] Exp:${esc(b.expiry_date)}</option>`).join('');
            });
        }
    };
    renderItems();
    m.querySelector('#trAddItem').onclick = () => { itemCount++; renderItems(); };

    m.querySelector('#transferForm').onsubmit = async (e) => {
        e.preventDefault();
        const items = [...m.querySelectorAll('#trItems > div')].map(row=>({
            product_id: row.querySelector('.tr-prod').value,
            batch_id:   row.querySelector('.tr-batch').value,
            quantity:   row.querySelector('.tr-qty').value,
        })).filter(item=>item.product_id&&item.batch_id&&item.quantity>0);
        if (!items.length){toast('Add at least one valid item.','error');return;}
        const res = await api('api/v1/transfers/create.php','POST',{
            csrf_token: State.csrfToken,
            from_location_id: m.querySelector('#trFromLoc').value,
            to_location_id:   m.querySelector('#trToLoc').value,
            notes: m.querySelector('#trNotes').value.trim(),
            items,
        });
        if (res.error){toast(res.error,'error');return;}
        toast(res.message,'success'); closeAllModals(); await renderTransfers();
    };
    openModal('transferModal');
}

async function progressTransfer(id, status) {
    if (!confirm(`Set transfer status to "${status}"?`)) return;
    const res = await api('api/v1/transfers/update.php','POST',{ csrf_token:State.csrfToken, transfer_id:id, status });
    if (res.error){toast(res.error,'error');return;}
    toast(res.message,'success'); await renderTransfers();
}

// ─── PURCHASE ORDERS ──────────────────────────────────────────────────────────
async function loadPurchaseOrders() {
    await preloadCaches();
    const content = document.getElementById('mainContent');
    content.innerHTML = `
    <div class="table-controls">
      <div class="filter-group">
        <select id="poStatusFilter" class="form-control"><option value="">All Statuses</option><option value="Draft">Draft</option><option value="Sent">Sent</option><option value="Partial">Partial</option><option value="Completed">Completed</option><option value="Cancelled">Cancelled</option></select>
      </div>
      <div class="action-group">
        ${canManager()?'<button class="btn btn-primary" onclick="openPoModal()">+ Create PO</button>':''}
      </div>
    </div>
    <div class="card"><div class="table-wrap"><div id="poTable">Loading…</div></div></div>`;
    document.getElementById('poStatusFilter')?.addEventListener('change', renderPos);
    await renderPos();
}

async function renderPos() {
    const status = document.getElementById('poStatusFilter')?.value || '';
    const rows = await api(`api/v1/purchase_orders/list.php${status?'?status='+status:''}`);
    const el = document.getElementById('poTable');
    if (!el) return;
    if (!Array.isArray(rows)||!rows.length){el.innerHTML='<div class="empty-state"><div class="empty-icon">📋</div><h3>No purchase orders</h3></div>';return;}
    el.innerHTML = `<table class="table"><thead><tr>
      <th>PO Number</th><th>Supplier</th><th>Status</th><th>Items</th><th>Created By</th><th>Date</th>
      ${canManager()?'<th>Actions</th>':''}
    </tr></thead><tbody>
      ${rows.map(r=>`<tr>
        <td><strong>${esc(r.po_number)}</strong></td>
        <td>${esc(r.supplier_name)}</td>
        <td>${statusBadge(r.status)}</td>
        <td>${r.item_count} items</td>
        <td>${esc(r.created_by)}</td>
        <td><small>${esc(r.created_at)}</small></td>
        ${canManager()?`<td>
          ${r.status==='Draft'?`<button class="btn btn-xs btn-primary" onclick="updatePoStatus(${r.po_id},'Sent')">→ Send</button>`:''}
          ${['Sent','Partial'].includes(r.status)?`<button class="btn btn-xs btn-success" onclick="openReceiveModal(${r.po_id},'${esc(r.po_number)}')">📥 Receive</button>`:''}
          ${['Draft','Sent'].includes(r.status)?`<button class="btn btn-xs btn-danger" onclick="updatePoStatus(${r.po_id},'Cancelled')">✕ Cancel</button>`:''}
        </td>`:''}
      </tr>`).join('')}
    </tbody></table>`;
}

function openPoModal() {
    const m = document.getElementById('poModal');
    if (!m) return;
    m.querySelector('#poSupplier').innerHTML = '<option value="">— Select Supplier —</option>' + selectOptions(State.cache.suppliers,'supplier_id','supplier_name');
    let itemCount = 1;
    const renderItems = () => {
        const box = m.querySelector('#poItems');
        box.innerHTML = `<table class="po-items-table"><thead><tr><th>Product</th><th>Qty</th><th>Unit Cost</th></tr></thead><tbody>${
            Array.from({length:itemCount},(_,i)=>`<tr>
              <td><select class="form-control po-prod"><option value="">— Product —</option>${selectOptions(State.cache.products,'product_id','name')}</select></td>
              <td><input type="number" class="form-control po-qty" min="1" value="1"></td>
              <td><input type="number" class="form-control po-cost" min="0" step="0.01" placeholder="0.00"></td>
            </tr>`).join('')
        }</tbody></table>`;
    };
    renderItems();
    m.querySelector('#poAddItem').onclick = () => { itemCount++; renderItems(); };
    m.querySelector('#poForm').onsubmit = async (e) => {
        e.preventDefault();
        const items = [...m.querySelectorAll('#poItems tbody tr')].map(row=>({
            product_id: row.querySelector('.po-prod').value,
            ordered_quantity: row.querySelector('.po-qty').value,
            unit_cost: row.querySelector('.po-cost').value,
        })).filter(item=>item.product_id&&item.ordered_quantity>0);
        if (!items.length){toast('Add at least one item.','error');return;}
        const res = await api('api/v1/purchase_orders/create.php','POST',{
            csrf_token: State.csrfToken,
            supplier_id: m.querySelector('#poSupplier').value,
            notes: m.querySelector('#poNotes').value.trim(),
            items,
        });
        if (res.error){toast(res.error,'error');return;}
        toast('PO '+res.po_number+' created!','success'); closeAllModals(); await renderPos();
    };
    openModal('poModal');
}

async function updatePoStatus(id, status) {
    if (!confirm(`Set PO status to "${status}"?`)) return;
    const res = await api('api/v1/purchase_orders/update.php','POST',{csrf_token:State.csrfToken,po_id:id,status});
    if (res.error){toast(res.error,'error');return;}
    toast(res.message,'success'); await renderPos();
}

async function openReceiveModal(poId, poNumber) {
    await preloadCaches();
    const items = await api(`api/v1/purchase_orders/list.php?po_id=${poId}&items=1`);
    const m = document.getElementById('receiveModal');
    if (!m) return;
    m.querySelector('#recvPoTitle').textContent = 'Receive Goods — ' + poNumber;
    const locOpts = State.cache.locations.map(l=>`<option value="${l.location_id}">${esc(l.warehouse_name)} › ${esc(l.zone)}/${esc(l.shelf_bin)}</option>`).join('');
    const itemsArr = Array.isArray(items) ? items : [];
    m.querySelector('#recvItems').innerHTML = itemsArr.map(item=>`
      <div class="card" style="padding:1rem;margin-bottom:.75rem;">
        <strong>${esc(item.product_name)}</strong> (${esc(item.sku)})<br>
        <small>Ordered: ${item.ordered_quantity} | Received so far: ${item.received_quantity}</small>
        <input type="hidden" class="recv-item-id" value="${item.po_item_id}">
        <div class="form-row form-row-2" style="margin-top:.75rem;">
          <div class="form-group"><label>Receive Qty</label><input type="number" class="form-control recv-qty" min="0" max="${item.ordered_quantity-item.received_quantity}" value="0"></div>
          <div class="form-group"><label>Destination Location</label><select class="form-control recv-loc"><option value="">—</option>${locOpts}</select></div>
        </div>
        <div class="form-row form-row-3">
          <div class="form-group"><label>Batch Number</label><input type="text" class="form-control recv-batch" placeholder="BATCH-001"></div>
          <div class="form-group"><label>Manufacturing Date</label><input type="date" class="form-control recv-mfg"></div>
          <div class="form-group"><label>Expiry Date *</label><input type="date" class="form-control recv-exp" required></div>
        </div>
      </div>`).join('');

    m.querySelector('#recvForm').onsubmit = async (e) => {
        e.preventDefault();
        const receivables = [...m.querySelectorAll('#recvItems > div')].map(div=>({
            po_item_id: div.querySelector('.recv-item-id').value,
            received_qty: div.querySelector('.recv-qty').value,
            location_id: div.querySelector('.recv-loc').value,
            batch_number: div.querySelector('.recv-batch').value.trim(),
            manufacturing_date: div.querySelector('.recv-mfg').value,
            expiry_date: div.querySelector('.recv-exp').value,
        })).filter(r=>parseInt(r.received_qty)>0 && r.location_id && r.expiry_date);

        if (!receivables.length){toast('Enter quantity, location and expiry for at least one item.','error');return;}
        let allOk = true;
        for (const r of receivables) {
            const res = await api('api/v1/purchase_orders/receive.php','POST',{...r, csrf_token:State.csrfToken});
            if (res.error){toast(res.error,'error'); allOk=false; break;}
        }
        if (allOk){toast('Goods received and inventory updated!','success'); closeAllModals(); await preloadCaches(); await renderPos();}
    };
    openModal('receiveModal');
}

// ─── ANALYTICS & ABC ─────────────────────────────────────────────────────────
async function loadAnalytics() {
    const content = document.getElementById('mainContent');
    content.innerHTML = '<div class="empty-state"><div class="empty-icon">⏳</div><p>Computing analytics…</p></div>';
    const data = await api('api/v1/analytics/abc.php');
    if (data.error) { showError(data.error); return; }
    const products  = data.products  || [];
    const trend     = data.trend_30d || [];
    const catDist   = data.category_distribution || [];

    content.innerHTML = `
    <div class="metrics-row-top" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
      <div class="metric-card card-green">
        <div class="metric-data">
          <span class="metric-label">Class A Products</span>
          <div class="metric-value text-class-a">${products.filter(p=>p.abc_class==='A').length}</div>
          <div class="metric-sub">Top 80% of total value</div>
        </div>
        <div class="metric-icon icon-green">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
      </div>
      <div class="metric-card card-blue">
        <div class="metric-data">
          <span class="metric-label">Class B Products</span>
          <div class="metric-value text-class-b">${products.filter(p=>p.abc_class==='B').length}</div>
          <div class="metric-sub">Next 15% of total value</div>
        </div>
        <div class="metric-icon icon-blue">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>
        </div>
      </div>
      <div class="metric-card card-purple">
        <div class="metric-data">
          <span class="metric-label">Class C Products</span>
          <div class="metric-value text-class-c">${products.filter(p=>p.abc_class==='C').length}</div>
          <div class="metric-sub">Remaining 5% of total value</div>
        </div>
        <div class="metric-icon icon-purple">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </div>
      </div>
    </div>

    <div class="charts-grid">
      <div class="chart-card">
        <div class="chart-title">📊 30-Day Stock Movement Trend</div>
        <div class="chart-area"><canvas id="trendChart" width="400" height="200"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-title">🥧 Category Value Distribution</div>
        <div class="chart-area"><canvas id="catChart" width="400" height="200"></canvas></div>
      </div>
    </div>

    <div class="card" style="margin-top:1.5rem;">
      <div class="card-header"><h3>🔢 ABC Classification Table</h3></div>
      <div class="table-wrap"><table class="table"><thead><tr>
        <th>#</th><th>SKU</th><th>Product</th><th>ABC</th><th>Total Qty</th><th>Unit Price</th><th>Total Value</th><th>Cumulative %</th>
      </tr></thead><tbody>
        ${products.map((p,i)=>`<tr>
          <td>${i+1}</td>
          <td>${esc(p.sku)}</td>
          <td>${esc(p.name)}</td>
          <td>${abcBadge(p.abc_class)}</td>
          <td>${fmtNum(p.total_qty)}</td>
          <td>${fmt(p.unit_price)}</td>
          <td><strong>${fmt(p.total_value)}</strong></td>
          <td>${parseFloat(p.cumulative_pct||0).toFixed(1)}%</td>
        </tr>`).join('')}
      </tbody></table></div>
    </div>`;

    // Draw SVG/Canvas charts after DOM is ready
    setTimeout(() => {
        drawTrendChart(document.getElementById('trendChart'), trend);
        drawCatChart(document.getElementById('catChart'), catDist);
    }, 50);
}

// ─── SVG / CANVAS CHARTS (Pure JS) ───────────────────────────────────────────
function drawTrendChart(canvas, data) {
    if (!canvas || !data.length) return;
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor  = isDark ? '#f8fafc' : '#0f172a';
    const mutedColor = isDark ? '#cbd5e1' : '#64748b';
    const gridColor  = isDark ? '#334155' : '#e2e8f0';

    const ctx = canvas.getContext('2d');
    const W = canvas.offsetWidth || 400;
    const H = 200;
    canvas.width = W; canvas.height = H;
    const pad = { top: 25, right: 20, bottom: 40, left: 50 };
    const maxVal = Math.max(...data.map(d => Math.max(d.total_in||0, d.total_out||0)), 1);
    const xStep  = (W - pad.left - pad.right) / Math.max(data.length - 1, 1);
    const yScale = (H - pad.top - pad.bottom) / maxVal;

    ctx.clearRect(0, 0, W, H);

    // Grid lines & Y-axis labels
    ctx.strokeStyle = gridColor;
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = pad.top + (H - pad.top - pad.bottom) * i / 4;
        ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(W - pad.right, y); ctx.stroke();
        ctx.fillStyle = mutedColor;
        ctx.font = '600 11px sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(Math.round(maxVal * (1 - i/4)), pad.left - 8, y + 4);
    }

    // Lines
    const drawLine = (key, color) => {
        ctx.beginPath(); ctx.strokeStyle = color; ctx.lineWidth = 2.5;
        data.forEach((d, i) => {
            const x = pad.left + i * xStep;
            const y = H - pad.bottom - (d[key]||0) * yScale;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.stroke();
    };
    drawLine('total_in', isDark ? '#34d399' : '#10b981');
    drawLine('total_out', isDark ? '#f87171' : '#ef4444');

    // X-axis labels
    ctx.fillStyle = mutedColor;
    ctx.font = '600 10px sans-serif';
    ctx.textAlign = 'center';
    data.forEach((d, i) => {
        if (i % Math.ceil(data.length/7) === 0) {
            ctx.fillText(d.date?.slice(5) || '', pad.left + i * xStep, H - pad.bottom + 16);
        }
    });

    // Legend
    ctx.fillStyle = isDark ? '#34d399' : '#10b981';
    ctx.fillRect(pad.left, 6, 12, 8);
    ctx.fillStyle = textColor;
    ctx.font = 'bold 11px sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText('In', pad.left + 16, 14);

    ctx.fillStyle = isDark ? '#f87171' : '#ef4444';
    ctx.fillRect(pad.left + 45, 6, 12, 8);
    ctx.fillStyle = textColor;
    ctx.fillText('Out', pad.left + 61, 14);
}

function drawCatChart(canvas, data) {
    if (!canvas || !data.length) return;
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor   = isDark ? '#f8fafc' : '#0f172a';
    const amountColor = isDark ? '#38bdf8' : '#0284c7';
    const gridColor   = isDark ? '#334155' : '#e2e8f0';

    const ctx = canvas.getContext('2d');
    const W = canvas.offsetWidth || 400; const H = 200;
    canvas.width = W; canvas.height = H;
    const colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316'];
    const barW = Math.min(50, (W - 60) / data.length - 8);
    const maxVal = Math.max(...data.map(d => parseFloat(d.total_value||0)), 1);
    const pad = { top: 25, right: 20, bottom: 50, left: 60 };

    ctx.clearRect(0, 0, W, H);
    data.forEach((d, i) => {
        const x   = pad.left + i * (barW + 10);
        const val = parseFloat(d.total_value || 0);
        const barH = (val / maxVal) * (H - pad.top - pad.bottom);
        const y   = H - pad.bottom - barH;

        ctx.fillStyle = colors[i % colors.length];
        ctx.fillRect(x, y, barW, barH);

        // Category Label below bar
        ctx.fillStyle = textColor;
        ctx.font = 'bold 10px sans-serif';
        ctx.textAlign = 'center';
        const label = (d.category_name || '').substring(0, 10);
        ctx.fillText(label, x + barW/2, H - pad.bottom + 16);

        // Amount Label above bar
        ctx.fillStyle = amountColor;
        ctx.font = 'bold 11px sans-serif';
        ctx.fillText('$'+Math.round(val), x + barW/2, y - 5);
    });

    // Baseline axis
    ctx.strokeStyle = gridColor;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(pad.left - 5, H - pad.bottom);
    ctx.lineTo(W - pad.right, H - pad.bottom);
    ctx.stroke();
}





// ─── AUDIT LOG ────────────────────────────────────────────────────────────────
async function loadAuditLog() {
    const content = document.getElementById('mainContent');
    content.innerHTML = `
    <div class="table-controls">
      <div class="filter-group"><input id="auditSearch" class="form-control" placeholder="🔍 Search action / table…" style="min-width:220px;"></div>
      <div class="action-group"><a href="api/v1/export.php?type=audit" class="btn btn-secondary">📥 Export CSV</a></div>
    </div>
    <div class="card"><div class="table-wrap" style="max-height:600px;overflow-y:auto;"><div id="auditTable">Loading…</div></div></div>`;

    const rows = await api('api/v1/analytics/abc.php?audit_only=1').then(()=>null).catch(()=>null);
    // Fetch audit log directly
    const data = await fetch('api/v1/export.php?type=audit').then(()=>null).catch(()=>null);
    // Use separate audit endpoint
    const auditData = await api('api/v1/analytics/abc.php').then(r => null);

    // Simple fetch of audit entries via dashboard approach
    const res = await fetch('api/v1/analytics/abc.php');
    const auditRows = await api('api/v1/products.php').then(()=>null);

    // Direct query approach
    const audit = await api('api/v1/analytics/abc.php?type=audit');
    renderAuditTable(audit?.audit_logs || []);

    document.getElementById('auditSearch')?.addEventListener('input', e => {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('#auditTable tbody tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });
}

function renderAuditTable(rows) {
    const el = document.getElementById('auditTable');
    if (!el) return;
    if (!rows.length) { el.innerHTML = '<div class="empty-state"><div class="empty-icon">📜</div><h3>No audit entries yet</h3></div>'; return; }
    el.innerHTML = `<table class="table"><thead><tr>
      <th>ID</th><th>Timestamp</th><th>User</th><th>Action</th><th>Table</th><th>Record</th><th>Changes</th><th>IP</th>
    </tr></thead><tbody>
      ${rows.map(r=>`<tr>
        <td>${r.audit_id}</td>
        <td><small>${esc(r.timestamp)}</small></td>
        <td>${esc(r.username)}</td>
        <td>${badge(esc(r.action),'badge-info')}</td>
        <td>${esc(r.table_name)}</td>
        <td>${r.record_id}</td>
        <td><code style="font-size:.75rem;">${esc(r.delta_json||'—')}</code></td>
        <td><small>${esc(r.ip_address||'—')}</small></td>
      </tr>`).join('')}
    </tbody></table>`;
}

// ─── USERS ────────────────────────────────────────────────────────────────────
async function loadUsers() {
    const content = document.getElementById('mainContent');
    content.innerHTML = `
    <div class="table-controls">
      <div></div>
      <button class="btn btn-primary" onclick="openUserModal()">+ Add User</button>
    </div>
    <div class="card"><div class="table-wrap"><div id="usersTable">Loading…</div></div></div>`;
    await renderUsers();
}

async function renderUsers() {
    const rows = await api('api/v1/users.php');
    const el = document.getElementById('usersTable');
    if (!el) return;
    if (!Array.isArray(rows)||!rows.length){el.innerHTML='<div class="empty-state"><div class="empty-icon">👥</div><h3>No users</h3></div>';return;}
    el.innerHTML = `<table class="table"><thead><tr>
      <th>User ID</th><th>Username</th><th>Role</th><th>Created</th><th>Actions</th>
    </tr></thead><tbody>
      ${rows.map(r=>`<tr>
        <td>${r.user_id}</td>
        <td><strong>${esc(r.username)}</strong></td>
        <td>${badge(esc(r.role_name),'badge-info')}</td>
        <td><small>${esc(r.created_at)}</small></td>
        <td>
          <button class="btn btn-xs btn-secondary" onclick='openUserModal(${JSON.stringify(r)})'>✏️ Edit</button>
          ${r.user_id != State.user.user_id ? `<button class="btn btn-xs btn-danger" onclick="deleteUser(${r.user_id})">🗑️</button>` : ''}
        </td>
      </tr>`).join('')}
    </tbody></table>`;
}

function openUserModal(u = null) {
    const m = document.getElementById('userModal');
    if (!m) return;
    m.querySelector('#userModalTitle').textContent = u ? 'Edit User' : 'Add User';
    m.querySelector('#userId').value      = u?.user_id || '';
    m.querySelector('#userUsername').value= u?.username || '';
    m.querySelector('#userUsername').disabled = !!u;
    m.querySelector('#userPass').value    = '';
    m.querySelector('#userRole').innerHTML= `
      <option value="1" ${u?.role_id==1?'selected':''}>Super Admin</option>
      <option value="2" ${u?.role_id==2?'selected':''}>Warehouse Manager</option>
      <option value="3" ${u?.role_id==3?'selected':''}>Inventory Clerk</option>
      <option value="4" ${u?.role_id==4?'selected':''}>Auditor</option>`;
    m.querySelector('#userForm').onsubmit = async (e) => {
        e.preventDefault();
        const res = await api('api/v1/users.php','POST',{
            csrf_token: State.csrfToken,
            user_id:   m.querySelector('#userId').value,
            username:  m.querySelector('#userUsername').value.trim(),
            password:  m.querySelector('#userPass').value.trim(),
            role_id:   m.querySelector('#userRole').value,
        });
        if (res.error){toast(res.error,'error');return;}
        toast(res.message,'success'); closeAllModals(); await renderUsers();
    };
    openModal('userModal');
}

async function deleteUser(id) {
    if (!confirm('Delete this user account?')) return;
    const res = await api(`api/v1/users.php?id=${id}`,'DELETE',{csrf_token:State.csrfToken});
    if (res.error){toast(res.error,'error');return;}
    toast(res.message,'success'); await renderUsers();
}

// ─── RBAC Helpers ─────────────────────────────────────────────────────────────
function canAdmin()   { return State.user?.role_name === 'Super Admin'; }
function canManager() { return ['Super Admin','Warehouse Manager'].includes(State.user?.role_name); }
function canClerk()   { return ['Super Admin','Warehouse Manager','Inventory Clerk'].includes(State.user?.role_name); }

// ─── Generic Error Helper ─────────────────────────────────────────────────────
function showError(msg) {
    const content = document.getElementById('mainContent');
    if (content) content.innerHTML = `<div class="empty-state"><div class="empty-icon">⚠️</div><h3>${esc(msg)}</h3></div>`;
}
