<?php
// Local demo data only. Never run on the live server.
// Usage: php tools/seed-demo.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/src/bootstrap.php';

if ((int)val('SELECT COUNT(*) FROM customers') > 0) { echo "Already has data, skipping.\n"; exit; }
mt_srand(7);

$products = [
    ['Walton ফ্রিজ WFC-3F5', 'Walton', 'WFC-3F5', 'ফ্রিজ', 38000, 44500, 6],
    ['Samsung Galaxy A15', 'Samsung', 'A15', 'মোবাইল', 17500, 20990, 9],
    ['Vision LED টিভি 32"', 'Vision', 'REX 32', 'টিভি', 15800, 18900, 4],
    ['Xiaomi Redmi 13C', 'Xiaomi', '13C', 'মোবাইল', 13200, 15499, 2],
    ['Gree এসি 1.5 টন', 'Gree', 'GS-18', 'এসি', 52000, 61000, 1],
    ['Walton ওয়াশিং মেশিন', 'Walton', 'WWM-AFC', 'ওয়াশিং মেশিন', 24000, 28900, 3],
    ['Singer সিলিং ফ্যান', 'Singer', 'SF-56', 'ফ্যান', 3100, 3800, 15],
];
foreach ($products as $p) {
    q('INSERT INTO products (name, brand, model, category, cost_price, sale_price, stock) VALUES (?,?,?,?,?,?,?)', $p);
}

$people = [
    ['রহিম উদ্দিন', '01711-000101', 'কাশিমপুর বাজার রোড'], ['করিম মিয়া', '01811-000202', 'জরুন, কাশিমপুর'],
    ['সালমা বেগম', '01911-000303', 'সারদাগঞ্জ, গাজীপুর'], ['মোঃ হাবিবুর রহমান', '01611-000404', 'লতিফপুর, কাশিমপুর'],
    ['নাসরিন আক্তার', '01511-000505', 'পানিশাইল, কাশিমপুর'], ['জসিম উদ্দিন', '01722-000606', 'নয়াপাড়া, কাশিমপুর'],
    ['ফারুক হোসেন', '01822-000707', 'কোনাবাড়ি, গাজীপুর'], ['রুবিনা ইয়াসমিন', '01922-000808', 'রওশন মার্কেটের পাশে'],
    ['আব্দুল কাদের', '01622-000909', 'হাতিমারা, কাশিমপুর'], ['শাহানা পারভীন', '01522-001010', 'বাগবাড়ি, কাশিমপুর'],
    ['মোঃ সোহেল রানা', '01733-001111', 'মাধবপুর, কাশিমপুর'], ['তানিয়া সুলতানা', '01833-001212', 'কাশিমপুর জেলখানা রোড'],
];
foreach ($people as $i => [$n, $ph, $a]) {
    q("INSERT INTO customers (name, phone, nid, address, father_name, occupation, g_name, g_phone, g_relation, created_at) VALUES (?,?,?,?,?,?,?,?,?, datetime('now', ?))",
      [$n, $ph, (string)(1990000000 + $i * 7919), $a, 'মোঃ আলী', ['দোকানদার', 'গার্মেন্টস কর্মী', 'কৃষক', 'চাকরিজীবী'][$i % 4], 'মোঃ কামাল', '01700-00' . (1000 + $i), 'ভাই', '-' . (200 - $i * 10) . ' days']);
}

// sales: [customer, product, months ago, months, down, payments made, late?]
$plan = [
    [1, 1, 7, 12, 8000, 7], [2, 2, 5, 10, 3000, 3], [3, 3, 9, 12, 2000, 9], [4, 6, 4, 12, 5000, 4],
    [5, 2, 3, 8, 4000, 1], [6, 4, 6, 10, 2500, 6], [7, 5, 2, 12, 15000, 2], [8, 7, 4, 6, 800, 4],
    [9, 3, 11, 10, 3000, 10], [10, 1, 1, 12, 9000, 0], [11, 2, 8, 8, 3000, 8], [12, 6, 6, 12, 5000, 3],
    [1, 7, 2, 4, 500, 2],
];
foreach ($plan as [$cid, $pid, $ago, $months, $down, $paidN]) {
    $p = one('SELECT * FROM products WHERE id = ?', [$pid]);
    $saleDate = date('Y-m-d', strtotime("-$ago months -" . mt_rand(0, 20) . ' days'));
    $first = add_months($saleDate, 1);
    $calc = calc_sale((float)$p['sale_price'], $down, 2, $months);
    q('INSERT INTO sales (customer_id, product_id, product_name, price, down_payment, profit_rate, months, profit, total_payable, installment_amount, sale_date, first_due) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
      [$cid, $pid, $p['name'], $p['sale_price'], $down, 2, $months, $calc['profit'], $calc['total'], $calc['installment'], $saleDate, $first]);
    $sid = (int)db()->lastInsertId();
    for ($i = 1; $i <= $months; $i++) {
        $amt = $i < $months ? $calc['installment'] : $calc['total'] - $calc['installment'] * ($months - 1);
        q('INSERT INTO installments (sale_id, no, due_date, amount) VALUES (?,?,?,?)', [$sid, $i, add_months($first, $i - 1), $amt]);
    }
    q('UPDATE products SET stock = stock - 1 WHERE id = ?', [$pid]);
    for ($k = 0; $k < $paidN; $k++) {
        $due = add_months($first, $k);
        if ($due > today()) break;
        $d = date('Y-m-d', strtotime($due . ' +' . mt_rand(0, 6) . ' days'));
        if ($d > today()) $d = today();
        $late = $d > $due && mt_rand(0, 2) === 0;
        q('INSERT INTO payments (sale_id, customer_id, amount, fine, pay_date, method) VALUES (?,?,?,?,?,?)',
          [$sid, $cid, $calc['installment'], $late ? 100 : 0, $d, ['নগদ', 'নগদ', 'বিকাশ', 'নগদ (মোবাইল)'][mt_rand(0, 3)]]);
    }
}

$sups = [['Walton Plaza', 'Walton', '01700-555001'], ['মেসার্স রাজ ইলেকট্রনিক্স', 'Samsung ডিলার', '01800-555002'], ['গাজীপুর মোবাইল হাব', 'Xiaomi ডিলার', '01900-555003']];
foreach ($sups as $i => [$n, $c, $ph]) {
    q('INSERT INTO suppliers (name, company, phone) VALUES (?,?,?)', [$n, $c, $ph]);
    $sid = (int)db()->lastInsertId();
    q("INSERT INTO supplier_txns (supplier_id, type, amount, txn_date, note) VALUES (?, 'bill', ?, ?, ?)", [$sid, [228000, 156000, 81000][$i], date('Y-m-d', strtotime('-60 days')), 'মেমো নং ' . (1200 + $i)]);
    q("INSERT INTO supplier_txns (supplier_id, type, amount, txn_date, note) VALUES (?, 'pay', ?, ?, 'নগদ')", [$sid, [150000, 156000, 40000][$i], date('Y-m-d', strtotime('-20 days'))]);
}
echo "Demo data added.\n";
