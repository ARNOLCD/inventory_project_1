# Access Control Summary

## Customer User Access Rights

### ✅ **Customer Users CAN Access:**
- `customer_dashboard.php` - View their repair status, order history, and cart
- `customer_book_repair.php` - Book repair services for their devices
- `repair_payment.php` - Pay for completed repairs
- `products.php` - Browse and purchase products (customer view only)
- `checkout.php` - Complete product purchases
- `receipt.php` - View/print **their own** payment receipts only
- `change_password.php` - Change own password
- `login.php` - Authentication
- `signup.php` - Create customer account
- `logout.php` - Logout
- `reset_password.php` - Password reset
- `index.php` - Public landing page
- `contact_submit.php` - Public contact form

### ❌ **Customer Users CANNOT Access:**
- `dashboard.php` - Main staff dashboard
- `categories.php` - Category management
- `services.php` - Service management
- `pos.php` - Point of sale system (staff only)
- `sales.php` - Sales history and reports (staff only)
- `reports.php` - Business reports and analytics (staff only)
- `repairs.php` - Staff repair management (staff only)
- `repair_solutions.php` - Technician solution documentation (staff only)
- `documents.php` - Document management including invoices
- `document_templates.php` - Document templates
- `create_document.php` - Create documents including invoices
- `view_document.php` - View documents including invoices
- `users.php` - User management
- `settings.php` - System settings
- `backup.php` - Database backup
- `email_settings.php` - Email configuration
- All diagnostic and maintenance files
- AJAX endpoints for staff functions

## Staff/Admin User Access Rights

Internal roles: **Admin, Employee, Technician, Sales Person** (`STAFF_ROLES` in `config/session.php`).

### ✅ **Staff/Admin Users CAN Access:**
- All inventory management pages
- Dashboard, products, categories, services
- POS, sales, reports, receipts (record payments, print/resend receipts)
- Repair management, repair requests (accept/deny)
- Document management
- User management (admin only) - admin sets each user's default password and role; internal users must change it at first login
- System Information, backups (admin only)
- Deleting repair tickets (admin only)

### ❌ **Staff/Admin Users CANNOT Access:**
- `customer_dashboard.php` - Customer-specific dashboard
- `customer_book_repair.php` - Customer repair booking
- `repair_payment.php` - Customer repair payment
- `checkout.php` - Customer checkout process

## Security Layers

### 1. **Session-Based Access Control** (`config/session.php`)
- `requireLogin()` - Ensures user is authenticated
- `requireStaff()` - Restricts to internal roles (admin, employee, technician, sales)
- `requireAdmin()` - Restricts to admin role only
- `isCustomer()` - Fail-closed: any logged-in user without a staff role is treated as a customer
- `isStaff()` - Checks for an internal staff role
- Users flagged `must_change_password` can only reach `change_password.php` / `logout.php` until they set their own password
- Password reset requires the emailed, single-use, 1-hour token (tokens are stored hashed)

### 2. **Additional URL-Based Blocking** (`config/access_control.php`)
- Customers use an **allowlist**: only the pages listed under "Customer Users CAN Access" are permitted; every other page (including new pages added later) redirects to `customer_dashboard.php`, and AJAX endpoints return 403
- Customer shop view shows only "In stock"/"Out of stock" (no stock quantities or low-stock filter)
- Automatically blocks staff from accessing customer-only pages
- Works even if users try direct URL access
- Prevents directory access to config and includes folders

### 3. **Role-Based Redirection**
- Login system redirects based on user role
- Signup creates customer accounts with proper redirect
- Existing session checks redirect unauthorized users

### 4. **Protected Navigation**
- Sidebar shows different menu options based on role
- Customer sidebar only shows repair-related functions
- Staff sidebar shows full inventory management options

## Customer Repair Booking Form

### Required Fields:
- **Device Type** (dropdown: laptop, phone, desktop, tablet, printer, other)
- **Problem Description** (text area)
- **Email** (required and editable)
- **Phone** (required and editable)

### Optional Fields:
- Device Brand
- Device Model  
- Serial Number
- Device Photo (upload)

### Automatic Information:
- Customer Name (from user account)
- Customer ID (from user account)
- Ticket Number (auto-generated)
- Status (auto-set to 'booked')

## System Information (admin)

`settings.php` (sidebar: System Information) - tabs for Company & Location, About, Contact Details, Logo & Branding, Banking, Services, Email (SMTP + "send from" address, test email, queue stats), Alerts, System. Values are stored in `company_info` / `system_settings` and used across the website, sidebar, receipts and emails. SMTP credentials are stored only in the database.

## Receipts

Every completed payment automatically creates one receipt (`documents`, type `receipt`) and emails it to the client:
- Online checkout paid by card/mobile money (pay-on-collection orders get a receipt when staff mark them paid in Sales History)
- Customer repair payments, and staff **Record Payment** on Repair Tracking
- POS sales (emailed if a customer email is entered)

Receipts are printable at `receipt.php`; customers see theirs under **My Receipts** on their dashboard.

## Notifications & Alerts

- Header bell for internal users: pending repair requests + low-stock products
- Low-stock email to all internal users when a product reaches its minimum level (once per product until restocked)
- New repair request email to all internal users
- All emails are queued (`email_queue`) and sent after the page is delivered; failures retry up to 3 times

## Repair Status Email Notifications

- Sent from `repairs.php` via `notifyRepairCustomer()` -> `sendRepairStatusEmail()` (`config/email.php`)
- Triggered when staff create a ticket (`booked`) and on every status change (`item_received`, `in_progress`, `completed`, `in_transit`, `delivered`, `cancelled`)
- Recipient: the linked customer account's email, falling back to the email entered on the ticket (walk-in customers)

## Customer Repair Payment

### Payment Process:
- Customers can pay for repairs once a final cost is set by staff
- Payment methods available: Mobile Money, Card, Pay on Collection
- Payment status tracks: pending, paid, refunded
- Payment confirmation email sent after successful payment
- Payment button appears on customer dashboard when cost is set and payment is pending

### Payment Status:
- **Pending** - Repair cost set but not yet paid
- **Paid** - Payment completed successfully
- **Refunded** - Payment refunded (if applicable)

## Technician Solution System

### Purpose:
Provide technicians with solution suggestions based on past repairs and enable online solution searches for efficient problem-solving.

### Features:
1. **Solution Documentation**
   - Technicians can document solutions for completed repairs
   - Store problem keywords, solution description, steps taken, parts used
   - Track time required and difficulty level
   - Add tags for categorization

2. **Similar Past Repairs**
   - System automatically finds similar past repairs based on:
     - Device type
     - Device brand
     - Problem keywords
   - Displays solutions from previous successful repairs
   - Shows technician who solved it and difficulty level

3. **Online Solution Search**
   - Quick search on Google for repair solutions
   - YouTube tutorial search integration
   - Auto-generated search queries based on device and problem

### Access:
- **Staff/Admin Only** - `repair_solutions.php` is restricted to staff and admin users
- **Customers Cannot Access** - Blocked in access control configuration

### Database Table:
`repair_solutions` table stores:
- Repair ID reference
- Technician ID reference
- Problem keywords
- Solution description
- Steps taken
- Parts used
- Time required
- Difficulty level (easy, medium, hard, expert)
- Tags
- Verification status

## Implementation Details

### Files Modified for Access Control:
1. `config/session.php` - Added access control inclusion
2. `config/access_control.php` - New file with URL-based blocking
3. `signup.php` - Fixed customer redirect
4. `customer_book_repair.php` - Enhanced form validation
5. Multiple diagnostic files - Added staff/admin protection

### Protection Mechanism:
When any page loads:
1. Session starts automatically
2. Access control file is included
3. Current page is checked against blocked lists
4. User role is verified
5. Automatic redirection if access is denied
6. Page-specific checks (requireStaff, requireAdmin) run

This ensures customers can ONLY book items for repair and view their own repair status, with absolutely no access to the inventory management system.