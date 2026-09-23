# Access Control Summary

## Customer User Access Rights

### ✅ **Customer Users CAN Access:**
- `customer_dashboard.php` - View their repair status and history
- `customer_book_repair.php` - Book repair services for their devices
- `login.php` - Authentication
- `signup.php` - Create customer account
- `logout.php` - Logout
- `reset_password.php` - Password reset
- `index.php` - Public landing page
- `contact_submit.php` - Public contact form

### ❌ **Customer Users CANNOT Access:**
- `dashboard.php` - Main staff dashboard
- `products.php` - Product inventory management
- `categories.php` - Category management
- `services.php` - Service management
- `pos.php` - Point of sale system
- `sales.php` - Sales history and management
- `reports.php` - Business reports
- `repairs.php` - Staff repair management
- `documents.php` - Document management
- `document_templates.php` - Document templates
- `create_document.php` - Create documents
- `view_document.php` - View documents
- `users.php` - User management
- `settings.php` - System settings
- `backup.php` - Database backup
- `email_settings.php` - Email configuration
- All diagnostic and maintenance files
- AJAX endpoints for staff functions

## Staff/Admin User Access Rights

### ✅ **Staff/Admin Users CAN Access:**
- All inventory management pages
- Dashboard, products, categories, services
- POS, sales, reports
- Repair management
- Document management
- User management (admin only)
- Settings and backups (admin only)

### ❌ **Staff/Admin Users CANNOT Access:**
- `customer_dashboard.php` - Customer-specific dashboard
- `customer_book_repair.php` - Customer repair booking

## Security Layers

### 1. **Session-Based Access Control** (`config/session.php`)
- `requireLogin()` - Ensures user is authenticated
- `requireStaff()` - Restricts to admin/employee roles only
- `requireAdmin()` - Restricts to admin role only
- `isCustomer()` - Checks if user has customer role
- `isStaff()` - Checks if user has admin or employee role

### 2. **Additional URL-Based Blocking** (`config/access_control.php`)
- Automatically blocks customers from accessing staff pages
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