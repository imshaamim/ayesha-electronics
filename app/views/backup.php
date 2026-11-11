<?php
defined('APP_ROOT') || exit;

$stamp = date('Y-m-d_His');

if (get('type') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customers_' . $stamp . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel reads Bangla correctly
    fputcsv($out, ['ID', 'নাম', 'মোবাইল', 'NID', 'ঠিকানা', 'মোট কিস্তি', 'পরিশোধ', 'বাকি', 'বকেয়া', 'পরের কিস্তি'], ',', '"', '');
    foreach (all('SELECT * FROM customer_balance ORDER BY id') as $c) {
        fputcsv($out, [$c['id'], $c['name'], $c['phone'], $c['nid'], $c['address'], round($c['total_payable']), round($c['paid']), round($c['balance']), round($c['overdue']), $c['next_due']], ',', '"', '');
    }
    fclose($out);
    exit;
}

if (!class_exists('ZipArchive')) {
    flash('এই হোস্টিংয়ে zip চালু নেই। cPanel → Select PHP Version থেকে zip চালু করুন।', 'err');
    redirect('settings');
}

$tmpDb = sys_get_temp_dir() . '/ayesha_' . bin2hex(random_bytes(6)) . '.db';
$tmpZip = sys_get_temp_dir() . '/ayesha_' . bin2hex(random_bytes(6)) . '.zip';
db()->exec('VACUUM INTO ' . db()->quote($tmpDb));

$zip = new ZipArchive();
$zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFile($tmpDb, 'ayesha-data/data.db');
foreach (glob(DATA_DIR . '/uploads/*') ?: [] as $f) {
    $zip->addFile($f, 'ayesha-data/uploads/' . basename($f));
}
$zip->addFromString('ayesha-data/README.txt',
    "Ayesha Electronics backup, $stamp\n\nTo restore: replace the ayesha-data folder on the server with this one.\n");
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="ayesha-backup_' . $stamp . '.zip"');
header('Content-Length: ' . filesize($tmpZip));
readfile($tmpZip);
@unlink($tmpDb);
@unlink($tmpZip);
exit;
