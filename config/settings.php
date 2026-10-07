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

// ---------- Branded document layout (receipts, invoices, quotations) ----------
// These helpers return table-based markup so they render the same in the browser,
// in Word (.doc) downloads and in dompdf-generated PDFs.

// Navy banner: white card with the logo on the left, company name + tagline on the right.
// Pass an absolute $logoUrl for Word/PDF downloads; relative paths are fine for pages.
function documentBanner($logoUrl = null) {
    $logoUrl = $logoUrl ?? companyLogo();
    $name = e(companyName());
    $tagline = e(companyInfo()['tagline'] ?? '') ?: 'Savings through maintenance of your computers';
    return '
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
        <tr>
            <td bgcolor="#1a365d" style="background-color: #1a365d; background-image: linear-gradient(115deg, #1a365d 0%, #2456a0 55%, #3182ce 100%); padding: 22px 26px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td valign="middle" width="170">
                            <table cellpadding="0" cellspacing="0" border="0"><tr>
                                <td bgcolor="#ffffff" style="background: #ffffff; border-radius: 12px; padding: 12px 18px;">
                                    <img src="' . e($logoUrl) . '" alt="' . $name . '" style="display: block; height: 58px; max-width: 145px;">
                                </td>
                            </tr></table>
                        </td>
                        <td valign="middle" align="right" style="text-align: right; padding-left: 20px;">
                            <div style="font-family: Arial, sans-serif; font-size: 23px; font-weight: bold; color: #ffffff; letter-spacing: 0.5px;">' . $name . '</div>
                            <div style="font-family: Arial, sans-serif; font-size: 9px; color: #cfe3f7; letter-spacing: 2px; margin-top: 6px; text-transform: uppercase;">' . $tagline . '</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>';
}

// Contact strip under the banner: address lines on the left, email/TPIN/phones on the right
function documentContactInfo() {
    $c = companyInfo();
    $left = '';
    foreach (preg_split('/[\r\n,]+/', (string)($c['address'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $line) {
        $left .= '<div style="margin: 2px 0; font-weight: bold; text-transform: uppercase;">' . e(trim($line)) . '</div>';
    }
    $right = '';
    $emails = array_unique(array_filter([$c['email'] ?? '', 'simstechzambia@gmail.com']));
    if ($emails) {
        $right .= '<div style="margin: 2px 0;"><strong>EMAIL:</strong> ' . e(implode(', ', $emails)) . '</div>';
    }
    if (!empty($c['tpin'])) {
        $right .= '<div style="margin: 2px 0;"><strong>TPIN #:</strong> ' . e($c['tpin']) . '</div>';
    }
    $phones = trim(($c['phone'] ?? '') . ', ' . ($c['mobile'] ?? ''), ' ,');
    if ($phones !== '') {
        $right .= '<div style="margin: 2px 0;"><strong>Mobile:</strong> ' . e($phones) . '</div>';
    }
    return '
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family: Arial, sans-serif; font-size: 11px; color: #2d3748; margin: 12px 0;">
        <tr>
            <td valign="top" width="55%">' . $left . '</td>
            <td valign="top" align="right" style="text-align: right;">' . $right . '</td>
        </tr>
    </table>';
}

// Right-aligned document title, e.g. "RECEIPT   No. REC-..."
function documentTitle($title, $number) {
    return '
    <div style="text-align: right; margin: 12px 0;">
        <span style="font-family: Arial, sans-serif; font-size: 22px; font-weight: bold; color: #1a365d; letter-spacing: 3px;">' . e($title) . '</span>
        <span style="font-family: Arial, sans-serif; font-size: 13px; color: #4a5568; margin-left: 8px;">No. ' . e($number) . '</span>
    </div>';
}

// Row of brand logos (hp, Lenovo, DELL, acer, Microsoft). Pass $src as an absolute URL
// for Word downloads or a local file path for dompdf; defaults to a relative path.
function documentBrands($src = null) {
    $brandFile = __DIR__ . '/../assets/images/doc-brands.png';
    if (is_file($brandFile)) {
        $src = $src ?? 'assets/images/doc-brands.png';
        return '<div style="text-align: center; margin: 12px 0;"><img src="' . e($src) . '" alt="hp Lenovo DELL acer Microsoft" style="height: 34px; max-width: 100%;"></div>';
    }
    return '<div style="text-align: center; padding: 9px; background: #f8f9fa; margin: 12px 0; font-family: Arial, sans-serif; font-weight: bold; font-size: 14px;">'
        . '<span style="color: #0096D6; font-style: italic;">hp</span> &nbsp;&nbsp;&nbsp; '
        . '<span style="color: #E2231A;">Lenovo</span> &nbsp;&nbsp;&nbsp; '
        . '<span style="color: #000000;">DELL</span> &nbsp;&nbsp;&nbsp; '
        . '<span style="color: #83B81A;">acer</span> &nbsp;&nbsp;&nbsp; '
        . '<span style="color: #00A4EF;">Microsoft</span></div>';
}

// Homepage advert video, managed under System Information > Logo & Branding.
// Returns [$type, $src]: 'youtube'/'vimeo' embed URL, 'file' for an upload or direct
// video URL, or ['', ''] when no video is set.
function advertVideo() {
    $v = trim(getSetting('advert_video'));
    if ($v === '') {
        return ['', ''];
    }
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([\w-]{6,})~i', $v, $m)) {
        return ['youtube', 'https://www.youtube.com/embed/' . $m[1]];
    }
    if (preg_match('~vimeo\.com/(\d+)~i', $v, $m)) {
        return ['vimeo', 'https://player.vimeo.com/video/' . $m[1]];
    }
    return ['file', $v];
}

// Bank details block (left column of the details grid)
function documentBankDetails() {
    $c = companyInfo();
    $rows = '';
    foreach ([['BANK', 'bank_name'], ['ACCOUNT NAME', 'account_name'], ['Account No', 'account_number'], ['BRANCH', 'branch'], ['PAY TO SALE', 'pay_to_sale']] as [$label, $key]) {
        if (($c[$key] ?? '') !== '') {
            $rows .= '<p style="margin: 3px 0;"><strong>' . $label . ':</strong> ' . e($c[$key]) . '</p>';
        }
    }
    return '<div style="color: #c53030; font-weight: bold; font-size: 11px; letter-spacing: 1px; margin-bottom: 6px;">BANK DETAILS</div>' . $rows;
}
