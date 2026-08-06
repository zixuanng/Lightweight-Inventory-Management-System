<?php
require_once __DIR__ . '/config/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lightweight Inventory Management System (IMS)</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- 1. LOGIN SCREEN -->
    <div id="loginSection" class="login-wrapper" style="display: none;">
        <div class="login-card">
            <div class="login-header">
                <h1>📦 Inventory System</h1>
                <p>Sign in to access your business stock dashboard</p>
            </div>
            <form id="loginForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="e.g. admin or staff" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Sign In</button>
            </form>
            <div style="margin-top: 1.5rem; text-align: center; font-size: 0.8rem; color: var(--text-muted);">
                <p><strong>Default Credentials:</strong></p>
                <p>Admin: <code>admin</code> / <code>admin123</code></p>
                <p>Staff: <code>staff</code> / <code>staff123</code></p>
            </div>
        </div>
    </div>

    <!-- 2. MAIN APPLICATION DASHBOARD SHELL -->
    <div id="appSection" class="app-container" style="display: none;">
        <!-- Navigation Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div style="font-size: 1.5rem;">📦</div>
                <div class="sidebar-brand">Lightweight IMS</div>
            </div>
            <ul class="sidebar-nav">
                <li class="nav-item active"><a href="#" data-tab="products"><span>📊</span> Products Stock</a></li>
                <li class="nav-item"><a href="#" data-tab="categories"><span>📁</span> Categories</a></li>
                <li class="nav-item"><a href="#" data-tab="suppliers"><span>🚚</span> Suppliers</a></li>
                <li class="nav-item"><a href="#" data-tab="transactions"><span>📝</span> Stock Audit Logs</a></li>
                <li class="nav-item admin-only"><a href="#" data-tab="users"><span>👥</span> User Accounts</a></li>
            </ul>
            <div class="sidebar-user">
                <div class="user-info">
                    <div id="currentUserDisplay" class="user-name">User</div>
                    <div id="currentRoleDisplay" class="user-role">Role</div>
                </div>
                <button id="logoutBtn" class="btn btn-secondary btn-sm" style="width: 100%;">Sign Out</button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <header class="top-bar">
                <h1 id="pageTitle" class="page-title">Inventory Overview</h1>
                <div class="top-actions">
                    <button id="btnNewTransaction" class="btn btn-success">⚡ Quick Stock Movement</button>
                    <a href="api/export.php?type=stock" class="btn btn-secondary" title="Export current inventory state to CSV">📥 Export Stock CSV</a>
                    <a href="api/export.php?type=audit" class="btn btn-secondary" title="Export audit transaction log to CSV">📜 Export Audit CSV</a>
                </div>
            </header>

            <div class="content-body">
                <!-- Metrics Grid -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-info">
                            <h3>Total SKUs</h3>
                            <div id="statTotalSkus" class="metric-value">0</div>
                        </div>
                        <div class="metric-icon icon-blue">📦</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-info">
                            <h3>Low Stock Items</h3>
                            <div id="statLowStockCount" class="metric-value">0</div>
                        </div>
                        <div class="metric-icon icon-amber">⚠️</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-info">
                            <h3>Total Inventory Value</h3>
                            <div id="statTotalValue" class="metric-value">$0.00</div>
                        </div>
                        <div class="metric-icon icon-green">💰</div>
                    </div>
                </div>

                <!-- Table Controls (Search, Filters, Action Buttons) -->
                <div class="table-controls">
                    <div class="filter-group">
                        <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search by name or SKU..." style="min-width: 250px;">
                        <button id="lowStockFilterBtn" class="btn btn-secondary">⚠️ Filter Low Stock</button>
                    </div>
                    <div>
                        <button id="btnAddProduct" class="btn btn-primary" style="display: none;">+ Add Product</button>
                        <button id="btnAddCategory" class="btn btn-primary" style="display: none;">+ Add Category</button>
                        <button id="btnAddSupplier" class="btn btn-primary" style="display: none;">+ Add Supplier</button>
                        <button id="btnAddUser" class="btn btn-primary" style="display: none;">+ Add User Account</button>
                    </div>
                </div>

                <!-- Main Data Table Wrapper -->
                <div class="card-table-wrapper">
                    <div id="tableContainer" class="table-responsive">
                        <!-- Dynamic JavaScript Table Rendered Here -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- 3. MODALS -->

    <!-- Product Modal (Add/Edit) -->
    <div id="productModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="productModalTitle">Add New Product</h2>
                <button class="close-modal">&times;</button>
            </div>
            <form id="productForm">
                <div class="modal-body">
                    <input type="hidden" id="prodId">
                    <div class="form-group">
                        <label for="prodSku">SKU Code *</label>
                        <input type="text" id="prodSku" class="form-control" placeholder="e.g. ELEC-1001" required>
                    </div>
                    <div class="form-group">
                        <label for="prodName">Product Name *</label>
                        <input type="text" id="prodName" class="form-control" placeholder="e.g. Wireless Ergonomic Mouse" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="prodCategory">Category</label>
                            <select id="prodCategory" class="form-control"></select>
                        </div>
                        <div class="form-group">
                            <label for="prodSupplier">Supplier</label>
                            <select id="prodSupplier" class="form-control"></select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="prodQuantity">Initial Quantity</label>
                            <input type="number" id="prodQuantity" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="form-group">
                            <label for="prodReorder">Reorder Threshold</label>
                            <input type="number" id="prodReorder" class="form-control" min="0" value="10" required>
                        </div>
                        <div class="form-group">
                            <label for="prodPrice">Unit Price ($)</label>
                            <input type="number" id="prodPrice" class="form-control" min="0" step="0.01" value="0.00" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Movement / Transaction Modal -->
    <div id="transactionModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Log Stock Movement</h2>
                <button class="close-modal">&times;</button>
            </div>
            <form id="transactionForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="txProduct">Product *</label>
                        <select id="txProduct" class="form-control" required></select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="txType">Movement Type *</label>
                            <select id="txType" class="form-control" required>
                                <option value="STOCK_IN">📥 STOCK_IN (Inbound Intake)</option>
                                <option value="STOCK_OUT">📤 STOCK_OUT (Outbound Fulfillment)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="txQuantity">Quantity *</label>
                            <input type="number" id="txQuantity" class="form-control" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="txNotes">Audit Notes / Reference</label>
                        <textarea id="txNotes" class="form-control" rows="3" placeholder="e.g. PO #4501 or Client Order fulfillment"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-close-modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Submit Movement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Category Modal -->
    <div id="categoryModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="categoryModalTitle">Add Category</h2>
                <button class="close-modal">&times;</button>
            </div>
            <form id="categoryForm">
                <div class="modal-body">
                    <input type="hidden" id="catId">
                    <div class="form-group">
                        <label for="catName">Category Name *</label>
                        <input type="text" id="catName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="catDesc">Description</label>
                        <textarea id="catDesc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Supplier Modal -->
    <div id="supplierModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="supplierModalTitle">Add Supplier</h2>
                <button class="close-modal">&times;</button>
            </div>
            <form id="supplierForm">
                <div class="modal-body">
                    <input type="hidden" id="supId">
                    <div class="form-group">
                        <label for="supName">Supplier Name *</label>
                        <input type="text" id="supName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="supEmail">Email Address</label>
                        <input type="email" id="supEmail" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="supPhone">Phone Number</label>
                        <input type="text" id="supPhone" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Modal (Admin) -->
    <div id="userModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="userModalTitle">Add User Account</h2>
                <button class="close-modal">&times;</button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <input type="hidden" id="userId">
                    <div class="form-group">
                        <label for="userName">Username *</label>
                        <input type="text" id="userName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="userPass">Password (Leave blank to keep unchanged when editing)</label>
                        <input type="password" id="userPass" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="userRole">Role *</label>
                        <select id="userRole" class="form-control" required>
                            <option value="Staff">Staff (Read-only stock, transaction logging)</option>
                            <option value="Admin">Admin (Full CRUD privileges)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="assets/js/app.js"></script>
</body>
</html>
