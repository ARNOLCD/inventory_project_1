# AC-TECHNOLOGY - Inventory Management System

A comprehensive inventory management system built with HTML, CSS, JavaScript, PHP, and MySQL for AC-TECHNOLOGY.

## Features

### Authentication System
- Secure login for Admin and Employees
- Role-based access control
- Session management

### Inventory Management
- **Products**: Add, edit, delete products with images
- **Categories**: Organize products by category
- **Services**: Manage service offerings
- **Serial Numbers**: Track items by serial number
- **Specifications**: Detailed product specifications

### Sales & POS
- Point of Sale (POS) interface
- Record sales transactions
- Support for products and services
- Multiple payment methods (Cash, Card, Mobile Money)
- Invoice generation

### Analytics & Reports
- Daily, weekly, monthly, yearly sales tracking
- Visual charts and graphs
- Top selling products
- Sales by category
- Employee performance tracking
- Low stock notifications

### Dynamic Content
- Admin can add/edit/delete products and services
- Image uploads for products and services
- Company information management
- Social media links

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP, WAMP, or similar (for local development)

### Setup Steps

1. **Clone or copy the project** to your web server directory:
   ```
   htdocs/Inventory_Mgt/  (for XAMPP)
   www/Inventory_Mgt/     (for WAMP)
   ```

2. **Start your web server** (Apache and MySQL)

3. **Access the application** in your browser:
   ```
   http://localhost/Inventory_Mgt/
   ```

4. **Database Setup**: Import `mysql/database.sql` via phpMyAdmin or MySQL CLI before first use.

5. **Default Login Credentials**:
   - **Username**: `admin`
   - **Password**: `admin123`

6. **Add the company logo**: 
   - Save the AC-TECHNOLOGY logo as `logo.png` in `assets/images/` folder
   - Or upload via Settings page in admin panel

## Project Structure

```
Inventory_Mgt/
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   └── images/
│       └── logo.png
├── config/
│   ├── database.php
│   └── session.php
├── includes/
│   ├── header.php
│   └── sidebar.php
├── uploads/
│   ├── products/
│   └── services/
├── ajax/
│   └── get_sale.php
├── index.php          (Landing page)
├── login.php          (Authentication)
├── logout.php
├── dashboard.php      (Main dashboard)
├── products.php       (Product management)
├── categories.php     (Category management)
├── services.php       (Service management)
├── pos.php            (Point of Sale)
├── sales.php          (Sales history)
├── reports.php        (Analytics & Reports)
├── users.php          (User management - Admin only)
├── settings.php       (System settings - Admin only)
├── contact_submit.php
└── README.md
```

## User Roles

### Admin
- Full access to all features
- User management
- System settings
- Delete permissions

### Employee
- Dashboard access
- Product/Service viewing and editing
- POS and sales recording
- Reports viewing

## Products & Services

### Product Categories
- Laptops
- Power Packs
- Power Cables
- Phone Chargers
- Headsets
- SSDs
- Hard Drives
- Accessories

### Services Offered
- Laptop Repair
- Phone Repair
- Passport Photos
- Printing Services

## Security Features
- Password hashing (bcrypt)
- SQL injection prevention (prepared statements)
- XSS protection (htmlspecialchars)
- Session-based authentication
- Role-based access control

## Customization

### Changing Colors
Edit the CSS variables in `assets/css/style.css`:
```css
:root {
    --primary-color: #1a365d;
    --secondary-color: #2b6cb0;
    --accent-color: #3182ce;
    /* ... */
}
```

### Adding New Categories
1. Login as admin
2. Go to Categories
3. Click "Add Category"

### Updating Company Info
1. Login as admin
2. Go to Settings
3. Update company details and logo

## Support

For any issues or questions, contact AC-TECHNOLOGY.

## License

This project is proprietary software developed for AC-TECHNOLOGY.

---

**Developed for AC-TECHNOLOGY** - Your Trusted Technology Partner
# AC_Tech_inventory_system
# AC-tech-inventory
# AC-tech-inventory
