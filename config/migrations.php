<?php
// One-time schema migrations. Pages no longer run ALTER TABLE on every request:
// runMigrations() compares the stored schema_version with SCHEMA_VERSION and only
// does work when the code is newer than the database.

define('SCHEMA_VERSION', 2);

function runMigrations($conn) {
    if ((int)getSetting('schema_version', 0) >= SCHEMA_VERSION) {
        return;
    }

    // Only one request migrates at a time
    $lock = $conn->query("SELECT GET_LOCK('inventory_schema_migration', 30) AS l")->fetch_assoc();
    if (!$lock || (int)$lock['l'] !== 1) {
        return;
    }

    try {
        $current = (int)getSetting('schema_version', 0);
        $ok = true;
        if ($ok && $current < 1) {
            $ok = migrateToV1($conn);
        }
        if ($ok && $current < 2) {
            $ok = migrateToV2($conn);
        }
        if ($ok) {
            $conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('schema_version', '" . SCHEMA_VERSION . "')
                          ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            getSettings(true);
        }
    } finally {
        $conn->query("DO RELEASE_LOCK('inventory_schema_migration')");
    }
}

function migrationLog($message) {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($dir . '/migration.log', date('Y-m-d H:i:s') . " | $message\n", FILE_APPEND | LOCK_EX);
}

// Runs a list of statements. Statements marked optional may fail (e.g. constraint already exists).
function runMigrationStatements($conn, array $statements) {
    $ok = true;
    foreach ($statements as $statement) {
        [$sql, $optional] = is_array($statement) ? $statement : [$statement, false];
        try {
            $conn->query($sql);
        } catch (mysqli_sql_exception $e) {
            if (!$optional) {
                $ok = false;
                migrationLog('FAILED: ' . $e->getMessage() . ' | SQL: ' . preg_replace('/\s+/', ' ', $sql));
            }
        }
    }
    return $ok;
}

function migrateToV1($conn) {
    $repair_statuses = "'pending_approval', 'booked', 'item_received', 'in_progress', 'completed', 'in_transit', 'delivered', 'cancelled', 'rejected'";

    $ok = runMigrationStatements($conn, [
        "CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",

        // Users
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email",
        "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'employee', 'technician', 'sales', 'customer') DEFAULT 'employee'",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0",

        // Repairs
        "CREATE TABLE IF NOT EXISTS repairs (
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
            item_photo VARCHAR(255) DEFAULT NULL,
            problem_description TEXT NOT NULL,
            diagnosis TEXT,
            repair_notes TEXT,
            estimated_cost DECIMAL(10, 2),
            final_cost DECIMAL(10, 2),
            status ENUM($repair_statuses) DEFAULT 'booked',
            priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
            received_by INT,
            technician_id INT,
            received_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            item_received_date DATETIME,
            started_date DATETIME,
            completed_date DATETIME,
            in_transit_date DATETIME,
            delivered_date DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
        )",
        "ALTER TABLE repairs ADD COLUMN IF NOT EXISTS item_photo VARCHAR(255) DEFAULT NULL AFTER serial_number",
        "ALTER TABLE repairs ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL AFTER customer_email",
        "ALTER TABLE repairs ADD COLUMN IF NOT EXISTS item_received_date DATETIME DEFAULT NULL AFTER received_date",
        "ALTER TABLE repairs ADD COLUMN IF NOT EXISTS in_transit_date DATETIME DEFAULT NULL AFTER completed_date",
        "ALTER TABLE repairs MODIFY COLUMN status ENUM($repair_statuses) DEFAULT 'booked'",
        "ALTER TABLE repairs ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT NULL AFTER final_cost",
        "ALTER TABLE repairs ADD COLUMN IF NOT EXISTS payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending' AFTER payment_method",
        "ALTER TABLE repairs ADD COLUMN IF NOT EXISTS payment_date DATETIME DEFAULT NULL AFTER payment_status",
        "ALTER TABLE repairs ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL",
        "ALTER TABLE repairs ADD COLUMN IF NOT EXISTS reviewed_by INT DEFAULT NULL",
        "ALTER TABLE repairs ADD COLUMN IF NOT EXISTS reviewed_date DATETIME DEFAULT NULL",
        ["ALTER TABLE repairs ADD CONSTRAINT fk_repairs_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL", true],
        "CREATE INDEX IF NOT EXISTS idx_repairs_status ON repairs (status)",
        "CREATE INDEX IF NOT EXISTS idx_repairs_customer ON repairs (customer_id)",

        "CREATE TABLE IF NOT EXISTS repair_solutions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            repair_id INT NOT NULL,
            technician_id INT NOT NULL,
            problem_keywords TEXT,
            solution_description TEXT NOT NULL,
            steps_taken TEXT,
            parts_used TEXT,
            time_required VARCHAR(100),
            difficulty_level ENUM('easy', 'medium', 'hard', 'expert') DEFAULT 'medium',
            success_rate INT DEFAULT 100,
            tags VARCHAR(255),
            is_verified BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        ["ALTER TABLE repair_solutions ADD CONSTRAINT fk_repair_solutions_repair FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE", true],

        // Sales: link online orders to the customer, track pay-on-collection orders
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_email VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS payment_status ENUM('paid', 'pending') NOT NULL DEFAULT 'paid'",
        "CREATE INDEX IF NOT EXISTS idx_sales_date ON sales (sale_date)",
        "CREATE INDEX IF NOT EXISTS idx_sales_customer ON sales (customer_id)",

        // Receipts are documents generated automatically from a payment (one per payment)
        "ALTER TABLE documents ADD COLUMN IF NOT EXISTS source_type VARCHAR(20) DEFAULT NULL",
        "ALTER TABLE documents ADD COLUMN IF NOT EXISTS source_id INT DEFAULT NULL",
        "ALTER TABLE documents ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL",
        "ALTER TABLE documents ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT NULL",
        "CREATE UNIQUE INDEX IF NOT EXISTS uniq_documents_source ON documents (source_type, source_id)",
        "CREATE INDEX IF NOT EXISTS idx_documents_customer ON documents (customer_id)",

        // Low-stock alerts are emailed once per product until it is restocked
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS low_stock_alerted TINYINT(1) NOT NULL DEFAULT 0",
        "CREATE INDEX IF NOT EXISTS idx_products_stock ON products (status, quantity)",

        // Outgoing emails are queued and sent after the page has been delivered
        "CREATE TABLE IF NOT EXISTS email_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            to_email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            last_error TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME DEFAULT NULL,
            INDEX idx_email_queue_status (status, attempts)
        )",

        "CREATE TABLE IF NOT EXISTS company_info (
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
        )",
        "INSERT INTO company_info (name) SELECT 'Sims-Tech Zambia' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM company_info)",

        // Carry over SMTP settings previously saved on the Email Settings page
        ["INSERT IGNORE INTO system_settings (setting_key, setting_value) SELECT setting_name, setting_value FROM email_settings", true],
        "INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
            ('smtp_host', 'smtp.gmail.com'), ('smtp_port', '587'), ('smtp_encryption', 'tls'),
            ('smtp_username', 'Arnoldchama36@gmail.com'), ('smtp_from_email', 'Arnoldchama36@gmail.com'),
            ('low_stock_email_alerts', '1'), ('repair_request_email_alerts', '1')",
    ]);

    getSettings(true);
    return $ok;
}

// Fail closed: any account created without an explicit role becomes a customer, never staff
function migrateToV2($conn) {
    return runMigrationStatements($conn, [
        "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'employee', 'technician', 'sales', 'customer') DEFAULT 'customer'",
    ]);
}
