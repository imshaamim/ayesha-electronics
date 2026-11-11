<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$CONFIG = is_file(APP_ROOT . '/config.php') ? (require APP_ROOT . '/config.php') : [];

// Data (database, photos, backups) lives OUTSIDE the website folder by default:
// on the server that is /home/<user>/ayesha-data, next to the subdomain folder.
define('DATA_DIR', rtrim($CONFIG['data_dir'] ?? (dirname(APP_ROOT) . '/ayesha-data'), '/'));
define('APP_VERSION', '0.0.2');

date_default_timezone_set('Asia/Dhaka');
ini_set('display_errors', '0');

// Clear setup messages instead of a blank error page on a fresh hosting account.
function setup_fail(string $msg): void
{
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    exit('<div style="font-family:sans-serif;max-width:560px;margin:60px auto;padding:20px;border:1px solid #f3d2c4;border-radius:12px;background:#fff1eb;color:#7c2d12;line-height:1.7"><b>সেটআপ সম্পূর্ণ হয়নি</b><br>' . $msg . '</div>');
}
if (PHP_VERSION_ID < 80100) setup_fail('PHP 8.1 বা নতুন ভার্সন দরকার। এখন আছে ' . PHP_VERSION . '। cPanel → Select PHP Version থেকে বদলান।');
if (!extension_loaded('pdo_sqlite')) setup_fail('PHP-তে <b>pdo_sqlite</b> চালু নেই। cPanel → Select PHP Version → Extensions থেকে pdo_sqlite আর sqlite3 টিক দিন।');

foreach ([DATA_DIR, DATA_DIR . '/uploads', DATA_DIR . '/backups'] as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
}
if (!is_dir(DATA_DIR) || !is_writable(DATA_DIR)) {
    setup_fail('ডেটা ফোল্ডার তৈরি বা লেখা যাচ্ছে না: <code>' . htmlspecialchars(DATA_DIR) . '</code><br>File Manager-এ এই ফোল্ডারটি নিজে তৈরি করে Permission 755 দিন।');
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
