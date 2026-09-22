# MySQL Database Setup

This folder contains the SQL scripts for the AC-TECHNOLOGY Inventory Management System.

## Files

- **`database.sql`** - Complete database schema with all tables, indexes, views, and default data

## Installation Options

### Option 1: Import via phpMyAdmin (Recommended)
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Click "Import" tab
3. Choose the `database.sql` file
4. Click "Go" to execute

### Option 2: MySQL Command Line
```bash
mysql -u root -p < database.sql
```

Or login to MySQL first:
```bash
mysql -u root -p
source /path/to/database.sql
```

## Database Structure

### Tables
| Table | Description |
|-------|-------------|
| `users` | System users (admin and employees) |
| `categories` | Product categories |
| `products` | Inventory products |
| `services` | Service offerings |
| `sales` | Sales transactions |
| `sale_items` | Individual items in each sale |
| `company_info` | Company details and settings |
| `notifications` | System notifications |
| `documents` | Invoices, quotations, receipts |
| `document_items` | Line items for documents |
| `document_templates` | Uploaded document templates |
| `repairs` | Repair tracking tickets |
| `password_resets` | Password reset tokens |
| `backup_settings` | Database backup configuration |
| `email_settings` | Email/SMTP configuration |

### Views
| View | Description |
|------|-------------|
| `low_stock_products` | Products below minimum stock level |
| `daily_sales_summary` | Daily sales aggregation |
| `product_sales_summary` | Product sales performance |

## Default Login
- **Username:** `admin`
- **Password:** `admin123`

## Configuration
Database connection settings are in `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'actech_inventory');
```

Update these values if your MySQL setup differs.
