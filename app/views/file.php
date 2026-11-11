<?php
defined('APP_ROOT') || exit;

$f = get('f');
if (!preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $f) || !is_file(DATA_DIR . '/uploads/' . $f)) {
    http_response_code(404);
    exit;
}
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . $types[pathinfo($f, PATHINFO_EXTENSION)]);
header('Cache-Control: private, max-age=86400');
header('Content-Length: ' . filesize(DATA_DIR . '/uploads/' . $f));
readfile(DATA_DIR . '/uploads/' . $f);
exit;
