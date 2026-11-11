<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$CONFIG = is_file(APP_ROOT . '/config.php') ? (require APP_ROOT . '/config.php') : [];

// Data (database, photos, backups) lives OUTSIDE the website folder by default:
// on the server that is /home/<user>/ayesha-data, next to the subdomain folder.
define('DATA_DIR', rtrim($CONFIG['data_dir'] ?? (dirname(APP_ROOT) . '/ayesha-data'), '/'));
define('APP_VERSION', '1.0.0');

date_default_timezone_set('Asia/Dhaka');
ini_set('display_errors', '0');

foreach ([DATA_DIR, DATA_DIR . '/uploads', DATA_DIR . '/backups'] as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
}
if (!is_dir(DATA_DIR) || !is_writable(DATA_DIR)) {
    http_response_code(500);
    exit('Data folder is missing or not writable: ' . htmlspecialchars(DATA_DIR));
}
// Belt and braces: if the data folder ever ends up inside the web root, block it.
if (!is_file(DATA_DIR . '/.htaccess')) {
    @file_put_contents(DATA_DIR . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
}

session_name('ayesha_sess');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/layout.php';

daily_backup();
