<?php require_once __DIR__ . '/config/auth.php'; ?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IMS Enterprise Edition</title>
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════
     LOGIN SCREEN
══════════════════════════════════════════════════════════════ -->
<div id="loginSection" class="login-wrapper" style="display:none;">
  <div class="login-card">
    <div class="login-logo">📦</div>
    <h1>IMS Enterprise</h1>
    <p class="subtitle">Advanced Inventory &amp; Supply Chain Management</p>
    <form id="loginForm">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="e.g. superadmin" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;padding:.75rem;">Sign In →</button>
    </form>
    <div style="margin-top:1.5rem;font-size:.78rem;color:var(--text-muted);border-top:1px solid var(--border);padding-top:1rem;">
      <strong>Default Accounts:</strong><br>
      Super Admin: <code>superadmin</code> / <code>admin123</code><br>
      Warehouse Manager: <code>manager</code> / <code>manager123</code><br>
      Inventory Clerk: <code>clerk</code> / <code>clerk123</code><br>
      Auditor: <code>auditor</code> / <code>auditor123</code>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MAIN APPLICATION
══════════════════════════════════════════════════════════════ -->
<div id="appSection" class="app-container" style="display:none;">

  <!-- ── Sidebar ── -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">📦</div>
      <div>
        <div class="brand-text">IMS Enterprise</div>
        <div class="brand-sub">Supply Chain System</div>
      </div>
    </div>

    <ul class="sidebar-nav">
      <li class="sidebar-section-label">Overview</li>
      <li class="nav-item">
        <a href="#" data-tab="dashboard"><span class="nav-icon">📊</span> Dashboard</a>
      </li>

      <li class="sidebar-section-label">Inventory</li>
      <li class="nav-item">
        <a href="#" data-tab="inventory"><span class="nav-icon">📦</span> Stock Levels</a>
      </li>
      <li class="nav-item">
        <a href="#" data-tab="products"><span class="nav-icon">🏷️</span> Products</a>
      </li>
      <li class="nav-item">
        <a href="#" data-tab="batches"><span class="nav-icon">🗓️</span> Batches &amp; Expiry</a>
      </li>
      <li class="nav-item">
        <a href="#" data-tab="warehouses"><span class="nav-icon">🏭</span> Warehouses</a>
      </li>

      <li class="sidebar-section-label">Operations</li>
      <li class="nav-item" data-role-min="clerk">
        <a href="#" data-tab="transfers"><span class="nav-icon">🔀</span> Stock Transfers</a>
      </li>
      <li class="nav-item" data-role-min="clerk">
        <a href="#" data-tab="pos"><span class="nav-icon">📋</span> Purchase Orders</a>
      </li>


      <li class="sidebar-section-label">Insights</li>
      <li class="nav-item">
        <a href="#" data-tab="analytics"><span class="nav-icon">📈</span> Analytics &amp; ABC</a>
      </li>
      <li class="nav-item">
        <a href="#" data-tab="auditlog"><span class="nav-icon">📜</span> Audit Log</a>
      </li>

      <li class="sidebar-section-label" data-role-min="admin">Admin</li>
      <li class="nav-item" data-role-min="admin">
        <a href="#" data-tab="users"><span class="nav-icon">👥</span> User Management</a>
      </li>
    </ul>

    <div class="sidebar-footer">
      <div class="user-info">
        <div id="sidebarUserName" class="user-name">User</div>
        <div id="sidebarUserRole" class="user-role">Role</div>
      </div>
      <button id="logoutBtn" class="btn btn-secondary btn-sm" style="width:100%;">Sign Out</button>
    </div>
  </aside>

  <!-- ── Main Content ── -->
  <div class="main-content">
    <header class="topbar">
      <h1 id="pageTitle" class="page-title">Dashboard</h1>
      <div class="topbar-actions">
        <button id="themeToggle" class="btn btn-ghost" title="Toggle Dark/Light Mode">🌙</button>
        <a href="api/v1/export.php?type=inventory" class="btn btn-secondary btn-sm">📥 Export Stock</a>
        <a href="api/v1/export.php?type=audit"     class="btn btn-secondary btn-sm">📜 Audit CSV</a>
      </div>
    </header>

    <div class="content-area">
      <div id="mainContent">
        <div class="empty-state"><div class="empty-icon">⏳</div><p>Loading…</p></div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════════ -->

<!-- Stock Adjustment Modal -->
<div id="stockModal" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-head">
      <h2 id="stockModalTitle">Log Stock Movement</h2>
      <button class="modal-close">&times;</button>
    </div>
    <form id="stockForm">
      <div class="modal-body">
        <input type="hidden" id="stockType" value="STOCK_IN">
        <div class="form-group">
          <label>Product *</label>
          <select id="stockProduct" class="form-control" required></select>
        </div>
        <div id="newBatchSection">
          <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:.5rem;">Create new batch for this receipt:</p>
          <div class="form-row form-row-3">
            <div class="form-group"><label>Batch Number</label><input type="text" id="newBatchNum" class="form-control" placeholder="BATCH-001"></div>
            <div class="form-group"><label>Mfg Date</label><input type="date" id="newMfgDate" class="form-control"></div>
            <div class="form-group"><label>Expiry Date *</label><input type="date" id="newExpDate" class="form-control"></div>
          </div>
          <p style="font-size:.78rem;color:var(--text-muted);">— Or select existing batch below —</p>
        </div>
        <div class="form-group" id="stockBatchRow">
          <label>Batch (FEFO order)</label>
          <select id="stockBatch" class="form-control"></select>
        </div>
        <div class="form-group">
          <label>Location *</label>
          <select id="stockLocation" class="form-control" required></select>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label>Quantity *</label>
            <input type="number" id="stockQty" class="form-control" min="1" value="1" required>
          </div>
          <div class="form-group">
            <label>Reference / PO #</label>
            <input type="text" id="stockRef" class="form-control" placeholder="e.g. PO-2026001">
          </div>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea id="stockNotes" class="form-control"></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary btn-cancel-modal">Cancel</button>
        <button type="submit" class="btn btn-success">Submit Movement</button>
      </div>
    </form>
  </div>
</div>

<!-- Product Modal -->
<div id="productModal" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-head">
      <h2 id="prodModalTitle">Add Product</h2>
      <button class="modal-close">&times;</button>
    </div>
    <form id="productForm">
      <div class="modal-body">
        <input type="hidden" id="prodId">
        <div class="form-row form-row-2">
          <div class="form-group"><label>SKU *</label><input type="text" id="prodSku" class="form-control" required></div>
          <div class="form-group"><label>Barcode</label><input type="text" id="prodBarcode" class="form-control" placeholder="e.g. 8901234560011"></div>
        </div>
        <div class="form-group"><label>Product Name *</label><input type="text" id="prodName" class="form-control" required></div>
        <div class="form-row form-row-2">
          <div class="form-group"><label>Category</label><select id="prodCategory" class="form-control"></select></div>
          <div class="form-group"><label>Default Supplier</label><select id="prodSupplier" class="form-control"></select></div>
        </div>
        <div class="form-row form-row-3">
          <div class="form-group"><label>Unit Cost ($)</label><input type="number" id="prodCost" class="form-control" min="0" step="0.01"></div>
          <div class="form-group"><label>Unit Price ($)</label><input type="number" id="prodPrice" class="form-control" min="0" step="0.01"></div>
          <div class="form-group"><label>Reorder Level</label><input type="number" id="prodReorder" class="form-control" min="0" value="10"></div>
        </div>
        <div class="form-group">
          <label>ABC Class</label>
          <select id="prodAbc" class="form-control">
            <option value="A">A — High Value (80%)</option>
            <option value="B">B — Mid Value (15%)</option>
            <option value="C" selected>C — Low Value (5%)</option>
          </select>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary btn-cancel-modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Product</button>
      </div>
    </form>
  </div>
</div>

<!-- Warehouse Modal -->
<div id="warehouseModal" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-head"><h2>Warehouse</h2><button class="modal-close">&times;</button></div>
    <form id="warehouseForm">
      <div class="modal-body">
        <input type="hidden" id="whId">
        <div class="form-row form-row-2">
          <div class="form-group"><label>Code *</label><input type="text" id="whCode" class="form-control" placeholder="WH-HQ" required></div>
          <div class="form-group"><label>Name *</label><input type="text" id="whName" class="form-control" required></div>
        </div>
        <div class="form-group"><label>Address</label><textarea id="whAddress" class="form-control"></textarea></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary btn-cancel-modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Warehouse</button>
      </div>
    </form>
  </div>
</div>

<!-- Location Modal -->
<div id="locationModal" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-head"><h2>Location</h2><button class="modal-close">&times;</button></div>
    <form id="locationForm">
      <div class="modal-body">
        <input type="hidden" id="locId">
        <div class="form-group"><label>Warehouse *</label><select id="locWarehouse" class="form-control" required></select></div>
        <div class="form-row form-row-2">
          <div class="form-group"><label>Zone *</label><input type="text" id="locZone" class="form-control" placeholder="A" required></div>
          <div class="form-group"><label>Shelf / Bin *</label><input type="text" id="locShelfBin" class="form-control" placeholder="A-01" required></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary btn-cancel-modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Location</button>
      </div>
    </form>
  </div>
</div>

<!-- Batch Modal -->
<div id="batchModal" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-head"><h2>Add Batch / Lot</h2><button class="modal-close">&times;</button></div>
    <form id="batchForm">
      <div class="modal-body">
        <div class="form-group"><label>Product *</label><select id="batchProduct" class="form-control" required></select></div>
        <div class="form-group"><label>Batch Number *</label><input type="text" id="batchNum" class="form-control" placeholder="BATCH-001" required></div>
        <div class="form-row form-row-2">
          <div class="form-group"><label>Manufacturing Date</label><input type="date" id="batchMfg" class="form-control"></div>
          <div class="form-group"><label>Expiry Date *</label><input type="date" id="batchExp" class="form-control" required></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary btn-cancel-modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Batch</button>
      </div>
    </form>
  </div>
</div>

<!-- Transfer Modal -->
<div id="transferModal" class="modal-backdrop">
  <div class="modal-box modal-lg">
    <div class="modal-head"><h2>Create Stock Transfer</h2><button class="modal-close">&times;</button></div>
    <form id="transferForm">
      <div class="modal-body">
        <div class="form-row form-row-2">
          <div class="form-group"><label>From Location *</label><select id="trFromLoc" class="form-control" required></select></div>
          <div class="form-group"><label>To Location *</label><select id="trToLoc" class="form-control" required></select></div>
        </div>
        <div class="form-group"><label>Notes</label><textarea id="trNotes" class="form-control"></textarea></div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
          <label style="font-weight:600;">Transfer Items</label>
          <button type="button" id="trAddItem" class="btn btn-secondary btn-sm">+ Add Row</button>
        </div>
        <div id="trItems"></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary btn-cancel-modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Transfer</button>
      </div>
    </form>
  </div>
</div>

<!-- PO Modal -->
<div id="poModal" class="modal-backdrop">
  <div class="modal-box modal-lg">
    <div class="modal-head"><h2>Create Purchase Order</h2><button class="modal-close">&times;</button></div>
    <form id="poForm">
      <div class="modal-body">
        <div class="form-group"><label>Supplier *</label><select id="poSupplier" class="form-control" required></select></div>
        <div class="form-group"><label>Notes</label><textarea id="poNotes" class="form-control"></textarea></div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
          <label style="font-weight:600;">Line Items</label>
          <button type="button" id="poAddItem" class="btn btn-secondary btn-sm">+ Add Row</button>
        </div>
        <div id="poItems"></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary btn-cancel-modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create PO (Draft)</button>
      </div>
    </form>
  </div>
</div>

<!-- Receive Goods Modal -->
<div id="receiveModal" class="modal-backdrop">
  <div class="modal-box modal-lg">
    <div class="modal-head">
      <h2 id="recvPoTitle">Receive Goods</h2>
      <button class="modal-close">&times;</button>
    </div>
    <form id="recvForm">
      <div class="modal-body">
        <div id="recvItems"></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary btn-cancel-modal">Cancel</button>
        <button type="submit" class="btn btn-success">✓ Confirm Receipt &amp; Update Inventory</button>
      </div>
    </form>
  </div>
</div>

<!-- User Modal -->
<div id="userModal" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-head">
      <h2 id="userModalTitle">Add User</h2>
      <button class="modal-close">&times;</button>
    </div>
    <form id="userForm">
      <div class="modal-body">
        <input type="hidden" id="userId">
        <div class="form-group"><label>Username *</label><input type="text" id="userUsername" class="form-control" required></div>
        <div class="form-group">
          <label>Password <span style="font-weight:400;color:var(--text-muted)">(leave blank to keep current)</span></label>
          <input type="password" id="userPass" class="form-control" placeholder="New password…">
        </div>
        <div class="form-group"><label>Role *</label><select id="userRole" class="form-control" required></select></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary btn-cancel-modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save User</button>
      </div>
    </form>
  </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>


<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
