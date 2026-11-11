<?php
defined('APP_ROOT') || exit;

if (!is_post()) redirect('dashboard');
csrf_check();

$do = post('do');
$id = (int)post('id');
$now = date('Y-m-d H:i:s');

switch ($do) {
    case 'customer_delete':
        q('UPDATE customers SET deleted_at = ? WHERE id = ? AND deleted_at IS NULL', [$now, $id]);
        flash('ক্রেতাকে ট্র্যাশে পাঠানো হয়েছে। ট্র্যাশ থেকে ফিরিয়ে আনা যাবে।');
        redirect('customers');

    case 'product_delete':
        q('UPDATE products SET deleted_at = ? WHERE id = ? AND deleted_at IS NULL', [$now, $id]);
        flash('পণ্যটি ট্র্যাশে পাঠানো হয়েছে।');
        redirect('products');

    case 'sale_delete':
        $s = one('SELECT * FROM sales WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($s) {
            db()->beginTransaction();
            q('UPDATE sales SET deleted_at = ? WHERE id = ?', [$now, $id]);
            if ($s['product_id']) q('UPDATE products SET stock = stock + 1 WHERE id = ?', [$s['product_id']]);
            db()->commit();
            flash('কিস্তির হিসাবটি ট্র্যাশে পাঠানো হয়েছে।');
            redirect('customer', ['id' => $s['customer_id']]);
        }
        redirect('customers');

    case 'payment_add':
        $sid = (int)post('sale_id');
        $s = one('SELECT * FROM sale_balance WHERE id = ?', [$sid]);
        if (!$s) { flash('কিস্তির হিসাব পাওয়া যায়নি।', 'err'); redirect('customers'); }
        $amount = num('amount'); $fine = max(num('fine'), 0); $date = post('pay_date');
        if ($amount <= 0 || $amount > $s['balance'] + 0.5) {
            flash('টাকার পরিমাণ ১ থেকে ' . money($s['balance']) . ' এর মধ্যে দিন।', 'err');
            redirect('sale', ['id' => $sid]);
        }
        if (!valid_date($date)) $date = today();
        $method = in_array(post('method'), ['নগদ', 'বিকাশ', 'নগদ (মোবাইল)', 'রকেট', 'ব্যাংক'], true) ? post('method') : 'নগদ';
        q('INSERT INTO payments (sale_id, customer_id, amount, fine, pay_date, method, note) VALUES (?,?,?,?,?,?,?)',
          [$sid, $s['customer_id'], $amount, $fine, $date, $method, post('note')]);
        $pid = (int)db()->lastInsertId();
        flash(money($amount) . ' জমা হয়েছে।');
        redirect('receipt', ['id' => $pid]);

    case 'payment_delete':
        $p = one('SELECT * FROM payments WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($p) {
            q('UPDATE payments SET deleted_at = ? WHERE id = ?', [$now, $id]);
            flash('পরিশোধটি ট্র্যাশে পাঠানো হয়েছে। হিসাব আবার মিলিয়ে নেওয়া হয়েছে।');
            redirect('sale', ['id' => $p['sale_id']]);
        }
        redirect('dashboard');

    case 'supplier_delete':
        q('UPDATE suppliers SET deleted_at = ? WHERE id = ? AND deleted_at IS NULL', [$now, $id]);
        flash('সাপ্লায়ার ট্র্যাশে পাঠানো হয়েছে।');
        redirect('suppliers');

    case 'stxn_add':
        $sup = (int)post('supplier_id');
        $type = post('type') === 'pay' ? 'pay' : 'bill';
        $amount = num('amount'); $date = post('txn_date');
        if (!one('SELECT id FROM suppliers WHERE id = ? AND deleted_at IS NULL', [$sup])) redirect('suppliers');
        if ($amount <= 0) { flash('টাকার পরিমাণ লিখুন।', 'err'); redirect('supplier', ['id' => $sup]); }
        if (!valid_date($date)) $date = today();
        q('INSERT INTO supplier_txns (supplier_id, type, amount, txn_date, note) VALUES (?,?,?,?,?)', [$sup, $type, $amount, $date, post('note')]);
        flash($type === 'bill' ? 'মাল কেনার হিসাব যোগ হয়েছে।' : 'সাপ্লায়ারকে পরিশোধ যোগ হয়েছে।');
        redirect('supplier', ['id' => $sup]);

    case 'stxn_delete':
        $t = one('SELECT * FROM supplier_txns WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($t) {
            q('UPDATE supplier_txns SET deleted_at = ? WHERE id = ?', [$now, $id]);
            flash('লেনদেনটি ট্র্যাশে পাঠানো হয়েছে।');
            redirect('supplier', ['id' => $t['supplier_id']]);
        }
        redirect('suppliers');

    case 'restore':
        $tables = ['customer' => 'customers', 'product' => 'products', 'sale' => 'sales', 'payment' => 'payments', 'supplier' => 'suppliers', 'stxn' => 'supplier_txns'];
        $type = post('type');
        if (isset($tables[$type])) {
            if ($type === 'sale') {
                $s = one('SELECT * FROM sales WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
                if ($s && $s['product_id']) q('UPDATE products SET stock = stock - 1 WHERE id = ?', [$s['product_id']]);
            }
            q("UPDATE {$tables[$type]} SET deleted_at = NULL WHERE id = ?", [$id]);
            flash('ফিরিয়ে আনা হয়েছে।');
        }
        redirect('trash');
}
redirect('dashboard');
