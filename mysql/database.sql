-- Sims-Tech Zambia Inventory Management System
-- Database Schema
-- MySQL Database Setup Script

-- Create database
CREATE DATABASE IF NOT EXISTS actech_inventory;
USE actech_inventory;

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'employee', 'customer') DEFAULT 'employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- CATEGORIES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- PRODUCTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_number VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    specifications TEXT,
    category_id INT,
    price DECIMAL(10, 2) NOT NULL,
    cost_price DECIMAL(10, 2),
    quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 5,
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- =====================================================
-- SERVICES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    duration VARCHAR(50),
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- SALES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    customer_name VARCHAR(100),
    customer_phone VARCHAR(20),
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'card', 'mobile_money') DEFAULT 'cash',
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- SALE ITEMS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT,
    service_id INT,
    item_type ENUM('product', 'service') NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

-- =====================================================
-- COMPANY INFO TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS company_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) DEFAULT 'Sims-Tech Zambia',
    tagline VARCHAR(255),
    about_us TEXT,
    address TEXT,
    phone VARCHAR(50),
    mobile VARCHAR(50),
    email VARCHAR(100),
    tpin VARCHAR(50),
    bank_name VARCHAR(100),
    account_name VARCHAR(100),
    account_number VARCHAR(50),
    branch VARCHAR(100),
    pay_to_sale VARCHAR(50),
    logo VARCHAR(255),
    facebook VARCHAR(255),
    twitter VARCHAR(255),
    instagram VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- DOCUMENTS TABLE (Invoices, Quotations, Receipts)
-- =====================================================
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_number VARCHAR(50) UNIQUE NOT NULL,
    document_type ENUM('invoice', 'quotation', 'receipt') NOT NULL,
    user_id INT,
    client_name VARCHAR(100) NOT NULL,
    client_phone VARCHAR(50),
    client_email VARCHAR(100),
    client_address TEXT,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    total_amount DECIMAL(10, 2) NOT NULL,
    notes TEXT,
    status ENUM('draft', 'sent', 'paid', 'cancelled') DEFAULT 'draft',
    valid_until DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- DOCUMENT ITEMS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS document_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
);

-- =====================================================
-- DOCUMENT TEMPLATES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS document_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('invoice', 'quotation', 'receipt', 'other') NOT NULL DEFAULT 'other',
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    description TEXT,
    is_default TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- REPAIRS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS repairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(50) UNIQUE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20),
    customer_email VARCHAR(100),
    customer_id INT,
    device_type VARCHAR(50) NOT NULL,
    device_brand VARCHAR(50),
    device_model VARCHAR(100),
    serial_number VARCHAR(100),
    problem_description TEXT NOT NULL,
    diagnosis TEXT,
    repair_notes TEXT,
    estimated_cost DECIMAL(10, 2),
    final_cost DECIMAL(10, 2),
    status ENUM('booked', 'in_progress', 'completed', 'delivered', 'cancelled') DEFAULT 'booked',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    received_by INT,
    technician_id INT,
    received_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_date DATETIME,
    completed_date DATETIME,
    delivered_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- PASSWORD RESETS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- BACKUP SETTINGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS backup_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- EMAIL SETTINGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS email_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- DEFAULT DATA INSERTS
-- =====================================================

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, full_name, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'Arnoldchama36@gmail.com', 'admin')
ON DUPLICATE KEY UPDATE username = username;

-- Insert default categories
INSERT INTO categories (name, description) VALUES 
('Laptops', 'Various laptop brands and models'),
('Power Packs', 'Portable power banks and battery packs'),
('Power Cables', 'Charging cables and power cords'),
('Phone Chargers', 'Mobile phone chargers and adapters'),
('Headsets', 'Audio headphones and headsets'),
('SSDs', 'Solid State Drives for storage'),
('Hard Drives', 'Traditional hard disk drives'),
('Accessories', 'Other computer and phone accessories')
ON DUPLICATE KEY UPDATE name = name;

-- Insert default services
INSERT INTO services (name, description, price, duration) VALUES 
('Laptop Repair', 'Professional laptop repair and maintenance services', 150.00, '1-3 days'),
('Phone Repair', 'Mobile phone screen replacement and repairs', 100.00, '1-2 days'),
('Passport Photos', 'Professional passport size photo services', 20.00, '30 minutes'),
('Printing Services', 'Document printing, scanning and copying', 5.00, 'Immediate')
ON DUPLICATE KEY UPDATE name = name;

-- Insert default company info
INSERT INTO company_info (name, tagline, about_us, address, phone, mobile, email, tpin, bank_name, account_name, account_number, branch, pay_to_sale) VALUES (
    'Sims-Tech Zambia',
    'SAVINGS THROUGH MAINTENANCE OF YOUR COMPUTERS',
    'Sims-Tech Zambia is a leading technology solutions provider offering quality electronics, computer accessories, and professional repair services. We are committed to delivering excellent products and services to our valued customers.',
    'UNZA MAIN CAMPUS, NEXT TO THE POST OFFICE',
    '0979145428',
    '0968745131',
    'info@actechnology.co.zm',
    '2002530937',
    'STANBIC',
    'Sims-Tech Zambia',
    '6292984114',
    '260006',
    '0973071800'
) ON DUPLICATE KEY UPDATE name = name;

-- =====================================================
-- INDEXES FOR BETTER PERFORMANCE
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);
CREATE INDEX IF NOT EXISTS idx_products_status ON products(status);
CREATE INDEX IF NOT EXISTS idx_sales_date ON sales(sale_date);
CREATE INDEX IF NOT EXISTS idx_sales_user ON sales(user_id);
CREATE INDEX IF NOT EXISTS idx_documents_type ON documents(document_type);
CREATE INDEX IF NOT EXISTS idx_documents_status ON documents(status);
CREATE INDEX IF NOT EXISTS idx_documents_date ON documents(created_at);

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for low stock products
CREATE OR REPLACE VIEW low_stock_products AS
SELECT p.*, c.name as category_name
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
WHERE p.quantity <= p.min_stock_level AND p.status = 'active';

-- View for daily sales summary
CREATE OR REPLACE VIEW daily_sales_summary AS
SELECT 
    DATE(sale_date) as sale_day,
    COUNT(*) as total_transactions,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_transaction
FROM sales
GROUP BY DATE(sale_date)
ORDER BY sale_day DESC;

-- View for product sales
CREATE OR REPLACE VIEW product_sales_summary AS
SELECT 
    p.id,
    p.name,
    p.serial_number,
    COALESCE(SUM(si.quantity), 0) as total_sold,
    COALESCE(SUM(si.total_price), 0) as total_revenue
FROM products p
LEFT JOIN sale_items si ON p.id = si.product_id AND si.item_type = 'product'
GROUP BY p.id
ORDER BY total_sold DESC;
