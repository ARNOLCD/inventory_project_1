# Project notes

PHP 8.2 + MariaDB 10.4 on XAMPP (Windows). No framework, no Composer, no test suite.

## Verification
- Lint all PHP: `for f in $(git ls-files '*.php'; git ls-files --others --exclude-standard '*.php'); do C:/xampp/php/php.exe -l "$f" | grep -v "^No syntax errors"; done`
- Smoke test public pages: `curl -s -o /dev/null -w "%{http_code}" http://localhost/inventory_project_1-main/login.php`
- Note: loading any page that includes `config/database.php` runs pending schema migrations against the live `actech_inventory` database.

## Architecture
- `config/database.php` - connection, output buffering, shared helpers (`REPAIR_STATUSES`, `generateTicketNumber`), loads settings + runs migrations.
- `config/migrations.php` - one-time schema changes. Bump `SCHEMA_VERSION` and add a `migrateToVn()` step; never put `ALTER TABLE` in pages.
- `config/settings.php` - `getSetting()/saveSettings()` (table `system_settings`), branding: `companyName()`, `companyLogo()`, `appUrl()`, `e()` escaping.
- `config/session.php` + `config/access_control.php` - roles (`STAFF_ROLES`: admin, employee, technician, sales; plus customer). Customers use a page allowlist; forced password change gate.
- `config/email.php` - SMTP settings come from the database (System Information > Email). Use `queueEmail()` / `queueEmailToStaff()`; emails are sent after the response via `email_queue`. `emailLayout()` for branded HTML.
- `config/receipts.php` - `issueReceipt($conn, 'sale'|'repair', $id)` creates one receipt per payment and emails it; `receipt.php` is the printable view.
- `config/alerts.php` - `checkLowStock()` after any stock change; `getStaffNotifications()` for the header bell.

## Rules
- Never commit credentials; SMTP password lives only in the `system_settings` table.
- Only admins may delete repairs, manage users or open System Information (`settings.php`).
