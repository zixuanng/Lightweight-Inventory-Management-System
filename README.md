# Inventory Management System Enterprise (IMSE)

A web-based inventory and supply chain management application built with PHP, MySQL/MariaDB, and vanilla JavaScript. The current `master` branch presents the application as **IMS Enterprise Edition**, with stock tracking by product, batch, and warehouse location.

Use it to record receipts and issues, track expiry dates, manage purchase orders and location transfers, and review stock value and activity. The active application requires no Composer packages, npm installation, framework, or remote CDN.

## Features

| Area | Current functionality |
| --- | --- |
| Dashboard | SKU count, inventory value, low-stock count, expiry alerts, open purchase orders, pending transfers, and ABC counts |
| Stock levels | Product/batch/location balances; search by name, SKU, or barcode; warehouse, ABC, and low-stock filters |
| Products | SKU, optional unique barcode, category, default supplier, reorder level, unit cost, and unit price |
| Warehouses | Multiple warehouses with zone and shelf/bin locations |
| Batches and expiry | Batch numbers, optional manufacturing dates, required expiry dates, and expiry status indicators |
| Stock movements | Stock-in to a selected batch/location; stock-out allocated by earliest expiry first (FEFO) |
| Purchase orders | Multiple line items, supplier selection, status tracking, partial receipts, and automatic inventory updates on receipt |
| Stock transfers | Multiple product/batch lines, source and destination locations, and Pending/In-Transit/Received/Cancelled statuses |
| Analytics | ABC classification, a 30-day movement chart, and category value distribution using native Canvas charts |
| Audit trail | User, action, table, record, JSON change details, IP address, and timestamp; latest 500 entries in the viewer |
| CSV exports | Inventory, stock movement history, purchase order history, and audit logs |
| Interface | Light/dark mode, searchable tables, modal forms, and role-aware controls |

Category and supplier maintenance is available through the API; the current sidebar does not provide dedicated management screens for them.

## Technology stack

- **Backend:** PHP with PDO, session authentication, role checks, and CSRF tokens for write requests.
- **Database:** MySQL/MariaDB with InnoDB tables, foreign keys, and transactions for stock operations.
- **Frontend:** HTML, CSS, vanilla JavaScript, Fetch API, and Canvas.
- **Data model:** 15 tables covering users and roles, product directories, batches, locations, inventory, purchasing, transfers, stock logs, and audit logs.

## Installation

### Requirements

- **PHP 8.0 or later**, with PDO MySQL and sessions enabled. Login uses `str_starts_with()`, so PHP 7.4 is insufficient.
- MySQL or MariaDB with InnoDB and JSON column support.
- Apache/XAMPP or PHP's built-in development server.
- A modern browser with JavaScript enabled.

### 1. Get the master branch

```bash
git clone --branch master --single-branch https://github.com/zixuanng/Lightweight-Inventory-Management-System.git
cd Lightweight-Inventory-Management-System
```

For an existing checkout, switch to `master` before following this guide.

### 2. Initialize the database

Start MySQL/MariaDB, then import [schema.sql](schema.sql) through phpMyAdmin or the command line:

```bash
mysql -u root -p < schema.sql
```

For a local XAMPP root account with no password, omit `-p`.

The script creates and selects `ims_enterprise_db`, then creates the tables and demo records. **Importing it again drops and recreates the application tables, deleting existing application data.** Use it for a fresh installation or an intentional demo reset, not as a migration.

### 3. Configure the connection

Edit [config/db.php](config/db.php) to match your database:

```php
$host   = '127.0.0.1';
$dbname = 'ims_enterprise_db';
$dbuser = 'root';
$dbpass = '';
```

If you change the database name, update the `CREATE DATABASE` and `USE` statements in `schema.sql` as well.

### 4. Start the application

**Option A: XAMPP/Apache**

Place the project contents in `C:\\xampp\\htdocs\\ims\\`, start Apache and MySQL, and open:

```text
http://localhost/ims-enterprise/
```

**Option B: PHP development server**

From the project root, run:

```bash
php -S 127.0.0.1:8000
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000). MySQL/MariaDB must also be running. The built-in server is for local development.

## Demo accounts and permissions

| Username | Password | Role |
| --- | --- | --- |
| `superadmin` | `admin123` | Super Admin |
| `manager` | `manager123` | Warehouse Manager |
| `clerk` | `clerk123` | Inventory Clerk |
| `auditor` | `auditor123` | Auditor |

Seed passwords are stored with a `NEEDS_REHASH:` marker and converted to bcrypt hashes after each account's first successful login. Change the demo passwords through **User Management** before using real data.

| Operation | Super Admin | Warehouse Manager | Inventory Clerk | Auditor |
| --- | --- | --- | --- | --- |
| View dashboard, stock, products, batches, warehouses, analytics, and audit logs | Yes | Yes | Yes | Yes |
| Export all four CSV report types | Yes | Yes | Yes | Yes |
| Create batches and record stock in/out | Yes | Yes | Yes | No |
| Create/edit products, warehouses, locations, categories, and suppliers | Yes | Yes | No | No |
| Delete batches | Yes | Yes | No | No |
| Create purchase orders and change their status | Yes | Yes | No | No |
| Create transfers and change their status | Yes | Yes | No | No |
| Delete products, warehouses, locations, categories, and suppliers | Yes | No | No | No |
| Manage users | Yes | No | No | No |

The receiving API also permits Inventory Clerks, but the current purchase order screen shows **Receive** only to Warehouse Managers and Super Admins. Delete operations remain subject to database foreign-key constraints.

## Everyday workflows

### Receive stock

1. Open **Stock Levels → STOCK IN**.
2. Select a product and destination location.
3. Select an existing batch, or enter a new batch number and expiry date. Manufacturing date is optional.
4. Enter a positive whole-number quantity, an optional reference, and notes.
5. Submit the movement. The application updates the balance and writes stock and audit records.

A new product or batch starts without stock. Record opening quantities through stock-in.

### Issue stock with FEFO

1. Open **Stock Levels → STOCK OUT**.
2. Select the product, source location, and quantity.
3. Submit the movement.

FEFO means **First Expired, First Out**: the application deducts from batches with the earliest expiry dates first, splitting the issue across batches when necessary. The API also supports allocation across locations when no location is supplied. It checks available stock before processing an issue.

### Purchase and receive goods

1. As a Manager or Super Admin, open **Purchase Orders → Create PO**.
2. Select the supplier and add product lines with ordered quantities and unit costs.
3. Add all rows before filling them in: the current **Add Row** control rebuilds the form.
4. Save the draft, then choose **Send** to mark it `Sent`.
5. On delivery, choose **Receive** and enter the quantity arriving for each line, destination location, batch number, and expiry date.
6. Submit. Each received line updates stock and the order becomes `Partial` or `Completed`.

Receiving more than a line's remaining ordered quantity is rejected. Each line is processed in its own request. **Send** changes the internal status; it does not email the supplier or place an external order.

### Transfer stock between locations

1. Open **Stock Transfers → Create Transfer** as a Manager or Super Admin.
2. Choose different source and destination locations.
3. Add product, batch, and quantity lines. Add all rows before entering line values.
4. Save the `Pending` transfer, then mark it `In-Transit`.
5. Mark it `Received` when the goods arrive.

Balances change only when a transfer is marked `Received`: the source is deducted, the destination is credited, and both sides are logged. Pending and in-transit transfers do not reserve or deduct stock. Cancellation before receipt changes the status without moving inventory.

## How to read the figures

- **Low stock:** the dashboard flags a product when its total quantity across locations is **less than or equal to** its reorder level. The stock-level view computes totals from rows matching its current search/warehouse/ABC filters. Products without inventory rows appear in Products, but not in Stock Levels.
- **Inventory value:** quantity multiplied by the product's current **unit price**, summed across inventory rows. This is a price-based value, not FIFO cost valuation or COGS. The interface displays a `$` symbol.
- **ABC classes:** products are sorted by descending current stock value. A product is A when the cumulative share before adding it is below 80%, B below 95%, and C otherwise. A product crossing a threshold stays in the earlier class. Loading the analytics endpoint recalculates and saves these classes, replacing manual selections.
- **Expiry alerts:** the dashboard counts stocked batches in separate 0–30, 31–60, and 61–90 day windows. The 60/90 day figures are separate bands, despite the interface's “≤” labels. Already-expired batches are shown in batch/stock views rather than those future-expiry counters.
- **Movement chart:** positive and negative stock-log changes over the last 30 days, including transfer entries and adjustments. It shows activity, not historical stock balances.
- **Refresh:** views fetch data on navigation and after relevant actions. There is no background live synchronization.

## Current implementation limits

- **Barcode/QR scanning is not connected to the application.** Barcode fields and text search work. `jsqr.js` and `quagga.min.js` are included in the repository but are not loaded by `index.php` or called by `app.js`.
- **FEFO does not block expired stock.** It sorts by expiry date without excluding expired batches.
- **Transfers do not validate sufficient source stock on receipt.** Check the source product/batch/location balance before completing a transfer; the current implementation can create negative balances.
- **Purchase order status rules are limited.** The status API accepts any defined status, and receiving does not verify the parent order's status. The normal interface guides the workflow, but the API does not fully enforce it.
- **ADJUSTMENT is a deduction in the API**, not a set-to-count operation; the standard movement form exposes stock-in and stock-out.
- **Turnover is API-only and approximate.** The analytics response includes a turnover calculation based on current inventory rows, not historical average stock; it is not displayed in the analytics screen.
- **Audit records are application history**, not a database-enforced tamper-proof log. Existing stock seed examples are illustrative and do not fully reconcile to opening balances.
- Categories and suppliers have API maintenance endpoints but no dedicated interface. Automatic replenishment, barcode/QR label generation, and external supplier integrations are not implemented.

## Project layout

```text
Lightweight-Inventory-Management-System/
  index.php                         Login, navigation shell, and modal forms
  schema.sql                        Database schema and demo records
  config/
    db.php                          PDO connection settings
    auth.php                        Session, role, CSRF, and audit helpers
  api/v1/
    auth/                           Login, logout, and current-session endpoints
    inventory/                      Stock listing and movements
    purchase_orders/                Create, list, status update, and receipt
    transfers/                      Create, list, and status update
    analytics/abc.php               ABC, charts, turnover data, and audit lookup
    products.php                    Product maintenance
    batches.php                     Batch creation, listing, and deletion
    warehouses.php                  Warehouse maintenance
    locations.php                   Zone and shelf/bin maintenance
    categories.php                  Category API
    suppliers.php                   Supplier API
    users.php                       Super Admin user management
    dashboard.php                   Aggregated dashboard figures
    export.php                      CSV exports
  assets/
    css/style.css                   Application styles and themes
    js/app.js                       Interface, API calls, and Canvas charts
    js/jsqr.js                      Bundled QR library, currently unused
    js/quagga.min.js                Bundled barcode library, currently unused
  LICENSE
  README.md
```

Inventory is stored uniquely by `product_id + batch_id + location_id`. Stock movements are recorded in `stock_logs`; administrative and operational actions are recorded in `audit_logs`.

## API overview

Endpoints are relative to the application root. Requests use session cookies. Protected data-changing requests require an `X-CSRF-Token` header or a `csrf_token` field, with the token obtained from login/session responses.

| Endpoint | Purpose |
| --- | --- |
| `api/v1/auth/login.php`, `logout.php`, `me.php` | Authentication and session state |
| `api/v1/dashboard.php` | Dashboard totals |
| `api/v1/inventory/list.php`, `adjust.php` | Stock queries and movements |
| `api/v1/purchase_orders/{create,list,update,receive}.php` | Purchasing workflow |
| `api/v1/transfers/{create,list,update}.php` | Transfer workflow |
| `api/v1/analytics/abc.php` | Classification and analytics |
| `api/v1/analytics/abc.php?type=audit` | Latest 500 audit entries |
| `api/v1/export.php?type=inventory` | Inventory CSV |
| `api/v1/export.php?type=stock_logs` | Full stock movement CSV |
| `api/v1/export.php?type=purchase_orders` | Purchase order line history CSV |
| `api/v1/export.php?type=audit` | Full audit CSV |

The product, batch, warehouse, location, category, supplier, and user endpoints use GET for reads, POST for creation/updates where supported, and DELETE for deletion. Role checks apply on the server.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Database connection failed | MySQL/MariaDB is running; `config/db.php` credentials and database name match the imported schema |
| Login fails after using an older setup guide | Import the master schema into a fresh database and use `superadmin`, `manager`, `clerk`, or `auditor`; the old `admin`/`staff` accounts are not seeded here |
| Undefined function `str_starts_with` | Run PHP 8.0 or later |
| Permission denied | Check the account's role and the permissions table above |
| Invalid or missing CSRF token | Sign out and sign in again to refresh the session token |
| Product missing from Stock Levels | Create a batch and record stock-in at a valid location |
| Delete fails | Referenced records may prevent deletion through foreign-key constraints |
| Expiry demo differs from screenshots or comments | Seed batch dates are fixed; alert counts depend on the database/server date |

## License

The project is distributed under the [MIT License](LICENSE).
