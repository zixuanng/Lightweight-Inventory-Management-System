/**
 * Lightweight Inventory Management System (IMS)
 * Vanilla JavaScript Engine
 */

document.addEventListener('DOMContentLoaded', () => {
    // Current Application State
    const state = {
        user: null,
        currentTab: 'products',
        products: [],
        categories: [],
        suppliers: [],
        transactions: [],
        users: [],
        lowStockFilterActive: false,
        searchTerm: ''
    };

    // Initialize App
    init();

    async function init() {
        bindEvents();
        await checkAuthSession();
    }

    // -------------------------------------------------------------
    // Session & Authentication Handlers
    // -------------------------------------------------------------
    async function checkAuthSession() {
        try {
            const res = await fetch('api/auth.php?action=me');
            const data = await res.json();
            if (data.user) {
                state.user = data.user;
                renderAuthenticatedUI();
            } else {
                renderLoginUI();
            }
        } catch (err) {
            console.error('Session check failed:', err);
            renderLoginUI();
        }
    }

    function renderLoginUI() {
        document.getElementById('loginSection').style.display = 'flex';
        document.getElementById('appSection').style.display = 'none';
    }

    function renderAuthenticatedUI() {
        document.getElementById('loginSection').style.display = 'none';
        document.getElementById('appSection').style.display = 'flex';

        // Update User Profile display
        document.getElementById('currentUserDisplay').textContent = state.user.username;
        document.getElementById('currentRoleDisplay').textContent = state.user.role;

        // Role-based navigation visibility
        const adminElements = document.querySelectorAll('.admin-only');
        adminElements.forEach(el => {
            el.style.display = (state.user.role === 'Admin') ? 'block' : 'none';
        });

        // Load dashboard stats & initial data
        loadDashboardStats();
        switchTab(state.currentTab);
    }

    // -------------------------------------------------------------
    // Event Listeners Binding
    // -------------------------------------------------------------
    function bindEvents() {
        // Login Form Submission
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', handleLoginSubmit);
        }

        // Logout Action
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', handleLogout);
        }

        // Navigation Tabs
        const navLinks = document.querySelectorAll('.nav-item a');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const tab = link.getAttribute('data-tab');
                if (tab) switchTab(tab);
            });
        });

        // Search & Low-Stock Filters
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                state.searchTerm = e.target.value.toLowerCase();
                renderCurrentTabTable();
            });
        }

        const lowStockFilterBtn = document.getElementById('lowStockFilterBtn');
        if (lowStockFilterBtn) {
            lowStockFilterBtn.addEventListener('click', () => {
                state.lowStockFilterActive = !state.lowStockFilterActive;
                if (state.lowStockFilterActive) {
                    lowStockFilterBtn.classList.add('btn-filter-active');
                    lowStockFilterBtn.innerHTML = '⚡ Showing Low Stock (Click to Reset)';
                } else {
                    lowStockFilterBtn.classList.remove('btn-filter-active');
                    lowStockFilterBtn.innerHTML = '⚠️ Filter Low Stock';
                }
                renderCurrentTabTable();
            });
        }

        // Action Buttons
        document.getElementById('btnAddProduct')?.addEventListener('click', () => openProductModal());
        document.getElementById('btnAddCategory')?.addEventListener('click', () => openCategoryModal());
        document.getElementById('btnAddSupplier')?.addEventListener('click', () => openSupplierModal());
        document.getElementById('btnAddUser')?.addEventListener('click', () => openUserModal());
        document.getElementById('btnNewTransaction')?.addEventListener('click', () => openTransactionModal());

        // Form Submit Listeners
        document.getElementById('productForm')?.addEventListener('submit', handleProductSubmit);
        document.getElementById('categoryForm')?.addEventListener('submit', handleCategorySubmit);
        document.getElementById('supplierForm')?.addEventListener('submit', handleSupplierSubmit);
        document.getElementById('userForm')?.addEventListener('submit', handleUserSubmit);
        document.getElementById('transactionForm')?.addEventListener('submit', handleTransactionSubmit);

        // Modal Close Buttons
        document.querySelectorAll('.close-modal, .btn-close-modal').forEach(btn => {
            btn.addEventListener('click', closeAllModals);
        });
    }

    // -------------------------------------------------------------
    // Auth Handlers
    // -------------------------------------------------------------
    async function handleLoginSubmit(e) {
        e.preventDefault();
        const username = e.target.username.value.trim();
        const password = e.target.password.value.trim();

        if (!username || !password) {
            showToast('Please enter both username and password.', 'error');
            return;
        }

        try {
            const res = await fetch('api/auth.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });

            const data = await res.json();
            if (res.ok) {
                state.user = data.user;
                showToast('Login successful!', 'success');
                renderAuthenticatedUI();
            } else {
                showToast(data.error || 'Login failed', 'error');
            }
        } catch (err) {
            showToast('Network error during login.', 'error');
        }
    }

    async function handleLogout() {
        try {
            await fetch('api/auth.php?action=logout', { method: 'POST' });
            state.user = null;
            showToast('Logged out.', 'success');
            renderLoginUI();
        } catch (err) {
            console.error('Logout error:', err);
        }
    }

    // -------------------------------------------------------------
    // Dashboard & Stats Async Fetcher
    // -------------------------------------------------------------
    async function loadDashboardStats() {
        try {
            const res = await fetch('api/dashboard.php');
            if (!res.ok) return;
            const stats = await res.json();

            document.getElementById('statTotalSkus').textContent = stats.total_skus;
            document.getElementById('statLowStockCount').textContent = stats.low_stock_count;
            document.getElementById('statTotalValue').textContent = '$' + parseFloat(stats.total_inventory_value).toLocaleString('en-US', { minimumFractionDigits: 2 });
        } catch (err) {
            console.error('Failed to load dashboard metrics:', err);
        }
    }

    // -------------------------------------------------------------
    // Tab Navigation & Data Loaders
    // -------------------------------------------------------------
    async function switchTab(tab) {
        state.currentTab = tab;

        // Update Nav UI
        document.querySelectorAll('.nav-item').forEach(item => {
            const link = item.querySelector('a');
            if (link && link.getAttribute('data-tab') === tab) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        // Set Title
        const titles = {
            products: 'Inventory & Stock List',
            categories: 'Categories Management',
            suppliers: 'Suppliers Directory',
            transactions: 'Stock Movement Audit Logs',
            users: 'User Account Management'
        };
        document.getElementById('pageTitle').textContent = titles[tab] || 'Dashboard';

        // Hide/Show Table Section Action Buttons based on tab
        document.getElementById('btnAddProduct').style.display = (tab === 'products' && state.user.role === 'Admin') ? 'inline-flex' : 'none';
        document.getElementById('btnAddCategory').style.display = (tab === 'categories' && state.user.role === 'Admin') ? 'inline-flex' : 'none';
        document.getElementById('btnAddSupplier').style.display = (tab === 'suppliers' && state.user.role === 'Admin') ? 'inline-flex' : 'none';
        document.getElementById('btnAddUser').style.display = (tab === 'users' && state.user.role === 'Admin') ? 'inline-flex' : 'none';
        document.getElementById('lowStockFilterBtn').style.display = (tab === 'products') ? 'inline-flex' : 'none';

        // Fetch Tab Data
        await fetchTabData(tab);
    }

    async function fetchTabData(tab) {
        try {
            if (tab === 'products') {
                const res = await fetch('api/products.php');
                state.products = await res.json();
                await fetchCategoriesAndSuppliers(); // Ensure dropdown options are loaded
            } else if (tab === 'categories') {
                const res = await fetch('api/categories.php');
                state.categories = await res.json();
            } else if (tab === 'suppliers') {
                const res = await fetch('api/suppliers.php');
                state.suppliers = await res.json();
            } else if (tab === 'transactions') {
                const res = await fetch('api/transactions.php');
                state.transactions = await res.json();
            } else if (tab === 'users' && state.user.role === 'Admin') {
                const res = await fetch('api/users.php');
                state.users = await res.json();
            }
            renderCurrentTabTable();
        } catch (err) {
            showToast('Failed to fetch data for ' + tab, 'error');
        }
    }

    async function fetchCategoriesAndSuppliers() {
        try {
            const [cRes, sRes] = await Promise.all([
                fetch('api/categories.php'),
                fetch('api/suppliers.php')
            ]);
            state.categories = await cRes.json();
            state.suppliers = await sRes.json();
        } catch (err) {
            console.error('Failed loading lookup data:', err);
        }
    }

    // -------------------------------------------------------------
    // Table Rendering Engine
    // -------------------------------------------------------------
    function renderCurrentTabTable() {
        const container = document.getElementById('tableContainer');
        const term = state.searchTerm;

        if (state.currentTab === 'products') {
            let filtered = state.products.filter(p => {
                const matchSearch = p.name.toLowerCase().includes(term) || p.sku.toLowerCase().includes(term);
                const isLow = parseInt(p.quantity) <= parseInt(p.reorder_level);
                const matchLowStock = state.lowStockFilterActive ? isLow : true;
                return matchSearch && matchLowStock;
            });

            if (filtered.length === 0) {
                container.innerHTML = `<div style="padding: 2rem; text-align: center; color: var(--text-muted);">No products found matching criteria.</div>`;
                return;
            }

            let html = `
                <table class="table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Supplier</th>
                            <th>Quantity</th>
                            <th>Reorder Level</th>
                            <th>Unit Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            filtered.forEach(p => {
                const qty = parseInt(p.quantity);
                const reorder = parseInt(p.reorder_level);
                const isLow = qty <= reorder;
                const trClass = isLow ? 'tr-low-stock' : '';
                const badge = isLow ? `<span class="badge badge-danger">⚠️ LOW STOCK</span>` : `<span class="badge badge-success">OK</span>`;
                const price = '$' + parseFloat(p.unit_price).toFixed(2);

                html += `
                    <tr class="${trClass}">
                        <td><strong>${escapeHtml(p.sku)}</strong></td>
                        <td>${escapeHtml(p.name)}</td>
                        <td>${escapeHtml(p.category_name || 'Uncategorized')}</td>
                        <td>${escapeHtml(p.supplier_name || 'None')}</td>
                        <td><strong>${qty}</strong></td>
                        <td>${reorder}</td>
                        <td>${price}</td>
                        <td>${badge}</td>
                        <td>
                            <button class="btn btn-sm btn-success btn-adjust-stock" data-id="${p.product_id}">⚡ Adjust Stock</button>
                            ${state.user.role === 'Admin' ? `
                                <button class="btn btn-sm btn-secondary btn-edit-product" data-id="${p.product_id}">✏️ Edit</button>
                                <button class="btn btn-sm btn-danger btn-delete-product" data-id="${p.product_id}">🗑️</button>
                            ` : ''}
                        </td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            container.innerHTML = html;

            // Bind Row Action Buttons
            container.querySelectorAll('.btn-adjust-stock').forEach(btn => {
                btn.addEventListener('click', () => openTransactionModal(btn.getAttribute('data-id')));
            });
            container.querySelectorAll('.btn-edit-product').forEach(btn => {
                btn.addEventListener('click', () => openProductModal(btn.getAttribute('data-id')));
            });
            container.querySelectorAll('.btn-delete-product').forEach(btn => {
                btn.addEventListener('click', () => deleteProduct(btn.getAttribute('data-id')));
            });

        } else if (state.currentTab === 'categories') {
            let filtered = state.categories.filter(c => c.category_name.toLowerCase().includes(term));
            let html = `
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            ${state.user.role === 'Admin' ? '<th>Actions</th>' : ''}
                        </tr>
                    </thead>
                    <tbody>
            `;

            filtered.forEach(c => {
                html += `
                    <tr>
                        <td>${c.category_id}</td>
                        <td><strong>${escapeHtml(c.category_name)}</strong></td>
                        <td>${escapeHtml(c.description || '-')}</td>
                        ${state.user.role === 'Admin' ? `
                            <td>
                                <button class="btn btn-sm btn-secondary btn-edit-cat" data-id="${c.category_id}">✏️ Edit</button>
                                <button class="btn btn-sm btn-danger btn-delete-cat" data-id="${c.category_id}">🗑️</button>
                            </td>
                        ` : ''}
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            container.innerHTML = html;

            container.querySelectorAll('.btn-edit-cat').forEach(btn => {
                btn.addEventListener('click', () => openCategoryModal(btn.getAttribute('data-id')));
            });
            container.querySelectorAll('.btn-delete-cat').forEach(btn => {
                btn.addEventListener('click', () => deleteCategory(btn.getAttribute('data-id')));
            });

        } else if (state.currentTab === 'suppliers') {
            let filtered = state.suppliers.filter(s => s.supplier_name.toLowerCase().includes(term));
            let html = `
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Supplier Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            ${state.user.role === 'Admin' ? '<th>Actions</th>' : ''}
                        </tr>
                    </thead>
                    <tbody>
            `;

            filtered.forEach(s => {
                html += `
                    <tr>
                        <td>${s.supplier_id}</td>
                        <td><strong>${escapeHtml(s.supplier_name)}</strong></td>
                        <td>${escapeHtml(s.email || '-')}</td>
                        <td>${escapeHtml(s.phone || '-')}</td>
                        ${state.user.role === 'Admin' ? `
                            <td>
                                <button class="btn btn-sm btn-secondary btn-edit-sup" data-id="${s.supplier_id}">✏️ Edit</button>
                                <button class="btn btn-sm btn-danger btn-delete-sup" data-id="${s.supplier_id}">🗑️</button>
                            </td>
                        ` : ''}
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            container.innerHTML = html;

            container.querySelectorAll('.btn-edit-sup').forEach(btn => {
                btn.addEventListener('click', () => openSupplierModal(btn.getAttribute('data-id')));
            });
            container.querySelectorAll('.btn-delete-sup').forEach(btn => {
                btn.addEventListener('click', () => deleteSupplier(btn.getAttribute('data-id')));
            });

        } else if (state.currentTab === 'transactions') {
            let filtered = state.transactions.filter(t => 
                t.product_name.toLowerCase().includes(term) || 
                t.product_sku.toLowerCase().includes(term) ||
                t.username.toLowerCase().includes(term)
            );

            let html = `
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Product SKU & Name</th>
                            <th>Quantity</th>
                            <th>User</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            filtered.forEach(t => {
                const typeBadge = t.transaction_type === 'STOCK_IN' 
                    ? `<span class="badge badge-success">📥 IN</span>` 
                    : `<span class="badge badge-danger">📤 OUT</span>`;

                html += `
                    <tr>
                        <td>${t.transaction_id}</td>
                        <td>${t.created_at}</td>
                        <td>${typeBadge}</td>
                        <td><strong>[${escapeHtml(t.product_sku)}]</strong> ${escapeHtml(t.product_name)}</td>
                        <td><strong>${t.quantity}</strong></td>
                        <td>${escapeHtml(t.username)}</td>
                        <td>${escapeHtml(t.notes || '-')}</td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            container.innerHTML = html;

        } else if (state.currentTab === 'users' && state.user.role === 'Admin') {
            let filtered = state.users.filter(u => u.username.toLowerCase().includes(term));
            let html = `
                <table class="table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            filtered.forEach(u => {
                const roleBadge = u.role === 'Admin' ? `<span class="badge badge-info">Admin</span>` : `<span class="badge badge-success">Staff</span>`;
                html += `
                    <tr>
                        <td>${u.user_id}</td>
                        <td><strong>${escapeHtml(u.username)}</strong></td>
                        <td>${roleBadge}</td>
                        <td>${u.created_at}</td>
                        <td>
                            <button class="btn btn-sm btn-secondary btn-edit-user" data-id="${u.user_id}">✏️ Edit Role/Pass</button>
                            ${u.user_id != state.user.user_id ? `<button class="btn btn-sm btn-danger btn-delete-user" data-id="${u.user_id}">🗑️</button>` : ''}
                        </td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            container.innerHTML = html;

            container.querySelectorAll('.btn-edit-user').forEach(btn => {
                btn.addEventListener('click', () => openUserModal(btn.getAttribute('data-id')));
            });
            container.querySelectorAll('.btn-delete-user').forEach(btn => {
                btn.addEventListener('click', () => deleteUser(btn.getAttribute('data-id')));
            });
        }
    }

    // -------------------------------------------------------------
    // Modals & Form Logic
    // -------------------------------------------------------------
    function openModal(modalId) {
        closeAllModals();
        const m = document.getElementById(modalId);
        if (m) m.classList.add('active');
    }

    function closeAllModals() {
        document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.remove('active'));
    }

    // Product Modal
    async function openProductModal(productId = null) {
        await fetchCategoriesAndSuppliers();

        // Populate Categories & Suppliers Select
        const catSelect = document.getElementById('prodCategory');
        const supSelect = document.getElementById('prodSupplier');

        catSelect.innerHTML = '<option value="">-- Select Category --</option>' + 
            state.categories.map(c => `<option value="${c.category_id}">${escapeHtml(c.category_name)}</option>`).join('');

        supSelect.innerHTML = '<option value="">-- Select Supplier --</option>' + 
            state.suppliers.map(s => `<option value="${s.supplier_id}">${escapeHtml(s.supplier_name)}</option>`).join('');

        const form = document.getElementById('productForm');
        form.reset();

        if (productId) {
            document.getElementById('productModalTitle').textContent = 'Edit Product';
            const prod = state.products.find(p => p.product_id == productId);
            if (prod) {
                document.getElementById('prodId').value = prod.product_id;
                document.getElementById('prodSku').value = prod.sku;
                document.getElementById('prodName').value = prod.name;
                document.getElementById('prodCategory').value = prod.category_id || '';
                document.getElementById('prodSupplier').value = prod.supplier_id || '';
                document.getElementById('prodQuantity').value = prod.quantity;
                document.getElementById('prodReorder').value = prod.reorder_level;
                document.getElementById('prodPrice').value = prod.unit_price;
            }
        } else {
            document.getElementById('productModalTitle').textContent = 'Add New Product';
            document.getElementById('prodId').value = '';
        }
        openModal('productModal');
    }

    async function handleProductSubmit(e) {
        e.preventDefault();
        const payload = {
            product_id: document.getElementById('prodId').value,
            sku: document.getElementById('prodSku').value.trim(),
            name: document.getElementById('prodName').value.trim(),
            category_id: document.getElementById('prodCategory').value,
            supplier_id: document.getElementById('prodSupplier').value,
            quantity: document.getElementById('prodQuantity').value,
            reorder_level: document.getElementById('prodReorder').value,
            unit_price: document.getElementById('prodPrice').value
        };

        try {
            const res = await fetch('api/products.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (res.ok) {
                showToast(data.message, 'success');
                closeAllModals();
                loadDashboardStats();
                fetchTabData('products');
            } else {
                showToast(data.error || 'Failed to save product.', 'error');
            }
        } catch (err) {
            showToast('Error saving product.', 'error');
        }
    }

    async function deleteProduct(id) {
        if (!confirm('Are you sure you want to delete this product?')) return;
        try {
            const res = await fetch(`api/products.php?id=${id}`, { method: 'DELETE' });
            const data = await res.json();
            if (res.ok) {
                showToast(data.message, 'success');
                loadDashboardStats();
                fetchTabData('products');
            } else {
                showToast(data.error || 'Delete failed', 'error');
            }
        } catch (err) {
            showToast('Error deleting product.', 'error');
        }
    }

    // Transaction / Stock Adjustment Modal
    async function openTransactionModal(productId = null) {
        if (state.products.length === 0) {
            const res = await fetch('api/products.php');
            state.products = await res.json();
        }

        const select = document.getElementById('txProduct');
        select.innerHTML = '<option value="">-- Select Product --</option>' + 
            state.products.map(p => `<option value="${p.product_id}">[${escapeHtml(p.sku)}] ${escapeHtml(p.name)} (Current Stock: ${p.quantity})</option>`).join('');

        const form = document.getElementById('transactionForm');
        form.reset();

        if (productId) {
            select.value = productId;
        }

        openModal('transactionModal');
    }

    async function handleTransactionSubmit(e) {
        e.preventDefault();
        const payload = {
            product_id: document.getElementById('txProduct').value,
            transaction_type: document.getElementById('txType').value,
            quantity: document.getElementById('txQuantity').value,
            notes: document.getElementById('txNotes').value.trim()
        };

        try {
            const res = await fetch('api/transactions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (res.ok) {
                showToast(data.message, 'success');
                closeAllModals();
                loadDashboardStats();
                fetchTabData(state.currentTab);
            } else {
                showToast(data.error || 'Transaction failed.', 'error');
            }
        } catch (err) {
            showToast('Network error processing transaction.', 'error');
        }
    }

    // Category Modal Handlers
    function openCategoryModal(catId = null) {
        document.getElementById('categoryForm').reset();
        if (catId) {
            document.getElementById('categoryModalTitle').textContent = 'Edit Category';
            const cat = state.categories.find(c => c.category_id == catId);
            if (cat) {
                document.getElementById('catId').value = cat.category_id;
                document.getElementById('catName').value = cat.category_name;
                document.getElementById('catDesc').value = cat.description || '';
            }
        } else {
            document.getElementById('categoryModalTitle').textContent = 'Add Category';
            document.getElementById('catId').value = '';
        }
        openModal('categoryModal');
    }

    async function handleCategorySubmit(e) {
        e.preventDefault();
        const payload = {
            category_id: document.getElementById('catId').value,
            category_name: document.getElementById('catName').value.trim(),
            description: document.getElementById('catDesc').value.trim()
        };
        try {
            const res = await fetch('api/categories.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (res.ok) {
                showToast(data.message, 'success');
                closeAllModals();
                fetchTabData('categories');
            } else {
                showToast(data.error || 'Error saving category.', 'error');
            }
        } catch (err) {
            showToast('Network error.', 'error');
        }
    }

    async function deleteCategory(id) {
        if (!confirm('Are you sure you want to delete this category?')) return;
        try {
            const res = await fetch(`api/categories.php?id=${id}`, { method: 'DELETE' });
            const data = await res.json();
            if (res.ok) {
                showToast(data.message, 'success');
                fetchTabData('categories');
            } else {
                showToast(data.error || 'Delete failed', 'error');
            }
        } catch (err) {
            showToast('Error deleting category.', 'error');
        }
    }

    // Supplier Modal Handlers
    function openSupplierModal(supId = null) {
        document.getElementById('supplierForm').reset();
        if (supId) {
            document.getElementById('supplierModalTitle').textContent = 'Edit Supplier';
            const sup = state.suppliers.find(s => s.supplier_id == supId);
            if (sup) {
                document.getElementById('supId').value = sup.supplier_id;
                document.getElementById('supName').value = sup.supplier_name;
                document.getElementById('supEmail').value = sup.email || '';
                document.getElementById('supPhone').value = sup.phone || '';
            }
        } else {
            document.getElementById('supplierModalTitle').textContent = 'Add Supplier';
            document.getElementById('supId').value = '';
        }
        openModal('supplierModal');
    }

    async function handleSupplierSubmit(e) {
        e.preventDefault();
        const payload = {
            supplier_id: document.getElementById('supId').value,
            supplier_name: document.getElementById('supName').value.trim(),
            email: document.getElementById('supEmail').value.trim(),
            phone: document.getElementById('supPhone').value.trim()
        };
        try {
            const res = await fetch('api/suppliers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (res.ok) {
                showToast(data.message, 'success');
                closeAllModals();
                fetchTabData('suppliers');
            } else {
                showToast(data.error || 'Error saving supplier.', 'error');
            }
        } catch (err) {
            showToast('Network error.', 'error');
        }
    }

    async function deleteSupplier(id) {
        if (!confirm('Are you sure you want to delete this supplier?')) return;
        try {
            const res = await fetch(`api/suppliers.php?id=${id}`, { method: 'DELETE' });
            const data = await res.json();
            if (res.ok) {
                showToast(data.message, 'success');
                fetchTabData('suppliers');
            } else {
                showToast(data.error || 'Delete failed', 'error');
            }
        } catch (err) {
            showToast('Error deleting supplier.', 'error');
        }
    }

    // User Modal Handlers (Admin)
    function openUserModal(userId = null) {
        document.getElementById('userForm').reset();
        if (userId) {
            document.getElementById('userModalTitle').textContent = 'Edit User';
            const user = state.users.find(u => u.user_id == userId);
            if (user) {
                document.getElementById('userId').value = user.user_id;
                document.getElementById('userName').value = user.username;
                document.getElementById('userName').disabled = true; // Username immutable
                document.getElementById('userRole').value = user.role;
            }
        } else {
            document.getElementById('userModalTitle').textContent = 'Add User';
            document.getElementById('userId').value = '';
            document.getElementById('userName').disabled = false;
        }
        openModal('userModal');
    }

    async function handleUserSubmit(e) {
        e.preventDefault();
        const payload = {
            user_id: document.getElementById('userId').value,
            username: document.getElementById('userName').value.trim(),
            password: document.getElementById('userPass').value.trim(),
            role: document.getElementById('userRole').value
        };
        try {
            const res = await fetch('api/users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (res.ok) {
                showToast(data.message, 'success');
                closeAllModals();
                fetchTabData('users');
            } else {
                showToast(data.error || 'Error saving user.', 'error');
            }
        } catch (err) {
            showToast('Network error.', 'error');
        }
    }

    async function deleteUser(id) {
        if (!confirm('Are you sure you want to delete this user?')) return;
        try {
            const res = await fetch(`api/users.php?id=${id}`, { method: 'DELETE' });
            const data = await res.json();
            if (res.ok) {
                showToast(data.message, 'success');
                fetchTabData('users');
            } else {
                showToast(data.error || 'Delete failed', 'error');
            }
        } catch (err) {
            showToast('Error deleting user.', 'error');
        }
    }

    // -------------------------------------------------------------
    // Helper Utilities & UI Feedback
    // -------------------------------------------------------------
    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<span>${escapeHtml(message)}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
