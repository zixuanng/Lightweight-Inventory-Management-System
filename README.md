# Lightweight Inventory Management System (IMS)

A lightweight, zero-dependency, web-based Inventory Management System (IMS) built using vanilla PHP, MySQL, HTML5, CSS3, and JavaScript. 

This system allows tracking stock levels, logging inbound (`STOCK_IN`) and outbound (`STOCK_OUT`) stock transactions in real-time, receiving automated low-stock visual alerts, and managing supplier/category directories.

---

## Features

- **Role-Based Access Control (RBAC)**:
  - **Admin**: Full access (CRUD operations on users, products, categories, suppliers; stock adjustments; audit log view; CSV exports).
  - **Staff**: Read-only inventory access and ability to log stock movements.
- **Data Integrity**: Enforced via SQL transactions (`START TRANSACTION` / `COMMIT` / `ROLLBACK`) during stock changes, preventing negative inventory.
- **Real-Time Visual Alerts**: Automatic red badges and table row highlights when product quantities drop below their `reorder_level`.
- **1-Click Filter**: Quickly show only low-stock items requiring attention.
- **CSV Reports**: Export current stock states and historical audit logs.
- **Zero-Dependency**: No external runtimes, NPM packages, framework libraries, or remote CDNs.

---

## Technology Stack

- **Backend**: PHP (Procedural/輕量 OOP, using PDO prepared statements to prevent SQL injection)
- **Database**: MySQL / MariaDB (relational schema enforcing foreign keys and index performance)
- **Frontend**: HTML5, Vanilla CSS3 (CSS Grid & Flexbox, modern layout), Vanilla JavaScript (Fetch API, DOM manipulation)

---

## Installation & Setup Instructions

### Prerequisites
- PHP 7.4 or higher installed.
- MySQL / MariaDB server running (e.g., via XAMPP, WAMP, or standalone).

---

### Step 1: Database Initialization
1. Start your MySQL database server (e.g., click **Start** next to MySQL in the XAMPP Control Panel).
2. Open phpMyAdmin, or connect via your command line interface.
3. Import the database schema and default seed data by executing the contents of [`schema.sql`](schema.sql):
   ```bash
   mysql -u root -p < schema.sql
   ```
   *(If your MySQL root user does not have a password, you can omit the `-p` flag).*

---

### Step 2: Deploy and Run the Web Server

#### Method A: Using XAMPP / Apache (Recommended)
1. Move the `ims` project directory into your XAMPP installation's `htdocs` directory (typically located at `C:\xampp\htdocs\`).
   - The path should look like: `C:\xampp\htdocs\ims\`
2. Open your web browser and navigate to:
   ```text
   http://localhost/ims/
   ```

#### Method B: Using PHP's Built-in Development Server
If you want to run it directly from your terminal workspace:
1. Open your terminal application.
2. Navigate to the root directory of the project.
3. Start the server:
   ```bash
   php -S 127.0.0.1:8000
   ```
4. Open your web browser and navigate to:
   ```text
   http://127.0.0.1:8000
   ```

---

## Default Seed Credentials

You can sign in using either of the default system accounts:

| Username | Password | Role | Permissions |
| :--- | :--- | :--- | :--- |
| **admin** | `admin123` | Admin | Full control over all elements |
| **staff** | `staff123` | Staff | Read stock levels, record transactions |

---

## File Structure

```text
ims/
├── config/
│   ├── db.php              # PDO Database Connection
│   └── auth.php            # Session validation & RBAC middleware helpers
├── api/
│   ├── auth.php            # Authentication endpoints (login, logout, session check)
│   ├── dashboard.php       # Aggregated metric cards endpoint
│   ├── products.php        # Products CRUD endpoint
│   ├── categories.php      # Categories CRUD endpoint
│   ├── suppliers.php       # Suppliers CRUD endpoint
│   ├── transactions.php    # Inbound/Outbound transactions & audit logs
│   ├── users.php           # User accounts management (Admin only)
│   └── export.php          # CSV exports generator
├── assets/
│   ├── css/
│   │   └── style.css       # Clean layout, responsive styles, badges, and modals
│   └── js/
│       └── app.js          # App interface controller, fetch calls, DOM rendering
├── index.php               # Single Page Application container & login screen
├── schema.sql              # SQL database creation script
└── README.md               # User guide & documentation
```

---

## License

This project is licensed under the MIT License. 
