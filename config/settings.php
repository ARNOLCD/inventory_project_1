<?php
// System settings (key/value in `system_settings`) and company branding helpers.
// Everything is loaded once per request and cached in static variables.

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function getSettings($reload = false) {
    static $settings = null;
    global $conn;
    if ($settings === null || $reload) {
        $settings = [];
        try {
            $result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (mysqli_sql_exception $e) {
            // Table not created yet - migrations will create it
        }
    }
    return $settings;
}

function getSetting($key, $default = '') {
    $settings = getSettings();
    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
}

function saveSettings(array $values) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($values as $key => $value) {
        $value = (string)$value;
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }
    getSettings(true);
}

function companyInfo($reload = false) {
    static $company = null;
    global $conn;
    if ($company === null || $reload) {
        try {
            $company = $conn->query("SELECT * FROM company_info ORDER BY id LIMIT 1")->fetch_assoc() ?: [];
        } catch (mysqli_sql_exception $e) {
            $company = [];
        }
    }
    return $company;
}

function companyName() {
    $name = companyInfo()['name'] ?? '';
    return $name !== '' ? $name : 'Sims-Tech Zambia';
}

// Relative logo path for use in pages (cache-busted so a new upload shows immediately)
function companyLogo() {
    $logo = basename(companyInfo()['logo'] ?? '');
    $file = __DIR__ . '/../assets/images/' . $logo;
    if ($logo !== '' && is_file($file)) {
        return 'assets/images/' . rawurlencode($logo) . '?v=' . filemtime($file);
    }
    return 'assets/images/sims-tech-logo.jpg';
}

// Base URL of the application (used for links in emails)
function appUrl() {
    $configured = getSetting('system_url');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if (basename($dir) === 'ajax') {
            $dir = dirname($dir);
        }
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim($dir, '/');
    }
    return 'http://localhost/' . basename(dirname(__DIR__));
}

// Contact email shown in email footers
function companyContactEmail() {
    return companyInfo()['email'] ?? '' ?: getSetting('smtp_from_email', '');
}
