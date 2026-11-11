<?php
defined('APP_ROOT') || exit;

header('Content-Type: application/json; charset=utf-8');
$qs = get('q');
if ($qs === '') { echo '[]'; exit; }
$like = '%' . $qs . '%';
$rows = all("SELECT * FROM customer_balance WHERE name LIKE ? OR phone LIKE ? OR nid LIKE ? OR memo_no LIKE ? OR (' ' || sale_memos || ' ') LIKE ? OR CAST(id AS TEXT) = ?
             ORDER BY (memo_no = ?) DESC, (name LIKE ?) DESC, name LIMIT 8", [$like, $like, $like, $qs . '%', '% ' . $qs . '%', ltrim($qs, '#'), $qs, $qs . '%']);
echo json_encode(array_map(fn($c) => [
    'id'       => (int)$c['id'],
    'name'     => $c['name'],
    'phone'    => $c['phone'],
    'memo'     => $c['memo_no'],
    'initials' => initials($c['name']),
    'photo'    => $c['photo'] ? file_url($c['photo']) : '',
    'balance'  => round((float)$c['balance']),
    'overdue'  => round((float)$c['overdue']),
    'url'      => url('customer', ['id' => $c['id']]),
], $rows), JSON_UNESCAPED_UNICODE);
exit;
