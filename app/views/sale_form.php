<?php
defined('APP_ROOT') || exit;

$cid = (int)($_POST['customer_id'] ?? get('customer_id'));
$customer = $cid ? one('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$cid]) : null;
$products = all('SELECT * FROM products WHERE deleted_at IS NULL ORDER BY name');
$error = '';
$d = [
    'product_id' => '', 'product_name' => '', 'price' => '', 'down_payment' => '0',
    'profit_rate' => setting('default_rate', '2'), 'months' => '12',
    'sale_date' => today(), 'first_due' => add_months(today(), 1), 'old_paid' => '', 'note' => '',
];

if (is_post()) {
    csrf_check();
    foreach ($d as $k => $_) $d[$k] = post($k);
    $price = num('price'); $down = num('down_payment'); $rate = num('profit_rate'); $months = (int)num('months');
    $oldPaid = num('old_paid');
    $pid = (int)$d['product_id'];
    $prod = $pid ? one('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$pid]) : null;
    if ($prod && $d['product_name'] === '') $d['product_name'] = $prod['name'];

    if (!$customer)                     $error = 'ক্রেতা বেছে নিন।';
    elseif ($d['product_name'] === '')  $error = 'পণ্যের নাম লিখুন বা তালিকা থেকে বেছে নিন।';
    elseif ($price <= 0)                $error = 'পণ্যের দাম লিখুন।';
    elseif ($down > $price)             $error = 'ডাউন পেমেন্ট দামের চেয়ে বেশি হতে পারে না।';
    elseif ($months < 1 || $months > 120) $error = 'কিস্তির মাস ১ থেকে ১২০ এর মধ্যে দিন।';
    elseif (!valid_date($d['sale_date']) || !valid_date($d['first_due'])) $error = 'তারিখ ঠিকভাবে দিন।';
    elseif ($rate < 0 || $rate > 20)    $error = 'লাভের হার ০ থেকে ২০% এর মধ্যে দিন।';
    else {
        $calc = calc_sale($price, $down, $rate, $months);
        if ($oldPaid > $calc['total']) {
            $error = 'আগে পরিশোধিত টাকা মোট কিস্তির চেয়ে বেশি হতে পারে না।';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            q('INSERT INTO sales (customer_id, product_id, product_name, qty, price, down_payment, profit_rate, months, profit, total_payable, installment_amount, sale_date, first_due, note)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
              [$cid, $prod['id'] ?? null, $d['product_name'], 1, $price, $down, $rate, $months, $calc['profit'], $calc['total'], $calc['installment'], $d['sale_date'], $d['first_due'], $d['note']]);
            $sid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO installments (sale_id, no, due_date, amount) VALUES (?,?,?,?)');
            for ($i = 1; $i <= $months; $i++) {
                $amt = $i < $months ? $calc['installment'] : $calc['total'] - $calc['installment'] * ($months - 1);
                $ins->execute([$sid, $i, add_months($d['first_due'], $i - 1), max($amt, 0)]);
            }
            if ($prod) q('UPDATE products SET stock = stock - 1 WHERE id = ?', [$prod['id']]);
            if ($oldPaid > 0) {
                q('INSERT INTO payments (sale_id, customer_id, amount, pay_date, method, note) VALUES (?,?,?,?,?,?)',
                  [$sid, $cid, $oldPaid, $d['sale_date'], 'পুরনো খাতা', 'খাতা থেকে তোলা আগের পরিশোধ']);
            }
            $pdo->commit();
            flash('কিস্তিতে বিক্রি সেভ হয়েছে। মাসিক কিস্তি ' . money($calc['installment']) . '।');
            redirect('sale', ['id' => $sid]);
        }
    }
}
$customers = $customer ? [] : all('SELECT id, name, phone FROM customers WHERE deleted_at IS NULL ORDER BY name');

layout_start('কিস্তিতে বিক্রি', 'customers');
?>
<p class="crumb"><a href="<?= url('customers') ?>">ক্রেতা</a><?= $customer ? ' / <a href="' . url('customer', ['id' => $cid]) . '">' . e($customer['name']) . '</a>' : '' ?></p>
<?php page_head('কিস্তিতে বিক্রি', $customer ? e($customer['name']) . ' (#' . $cid . ') এর জন্য নতুন কিস্তি' : 'ক্রেতা ও পণ্য বেছে নিয়ে কিস্তির হিসাব করুন'); ?>
<?php if ($error): ?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>

<form method="post" id="saleForm" class="grid g-main">
  <?= csrf_field() ?>
  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>পণ্য ও দাম</h2></div>
      <div class="card-b form-grid">
        <?php if ($customer): ?>
          <input type="hidden" name="customer_id" value="<?= $cid ?>">
        <?php else: ?>
          <div class="f full"><label for="customer_id">ক্রেতা *</label>
            <select id="customer_id" name="customer_id" required>
              <option value="">ক্রেতা বেছে নিন</option>
              <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>">#<?= (int)$c['id'] ?> · <?= e($c['name']) ?> <?= $c['phone'] ? '· ' . e($c['phone']) : '' ?></option><?php endforeach; ?>
            </select>
            <p class="hint">তালিকায় না থাকলে আগে <a href="<?= url('customer_form') ?>">নতুন ক্রেতা যোগ করুন</a>।</p>
          </div>
        <?php endif; ?>
        <div class="f full"><label for="product_id">স্টক থেকে পণ্য <span class="opt">ঐচ্ছিক, বেছে নিলে স্টক ১টি কমবে</span></label>
          <select id="product_id" name="product_id">
            <option value="">স্টকের বাইরে / নিজে লিখব</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= (int)$p['id'] ?>" data-price="<?= (float)$p['sale_price'] ?>" data-name="<?= e($p['name']) ?>" <?= (string)$p['id'] === (string)$d['product_id'] ? 'selected' : '' ?> <?= $p['stock'] <= 0 ? 'disabled' : '' ?>>
                <?= e($p['name']) ?> · <?= money($p['sale_price']) ?> · স্টক <?= (int)$p['stock'] ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="f full"><label for="product_name">পণ্যের নাম *</label><input id="product_name" name="product_name" value="<?= e($d['product_name']) ?>" placeholder="যেমন: Samsung A15 মোবাইল" required></div>
        <div class="f"><label for="price">পণ্যের দাম (৳) *</label><input id="price" name="price" type="number" min="0" step="1" value="<?= e($d['price']) ?>" required></div>
        <div class="f"><label for="down_payment">ডাউন পেমেন্ট (৳)</label><input id="down_payment" name="down_payment" type="number" min="0" step="1" value="<?= e($d['down_payment']) ?>"></div>
        <div class="f"><label for="profit_rate">মাসিক লাভের হার (%)</label><input id="profit_rate" name="profit_rate" type="number" min="0" max="20" step="0.1" value="<?= e($d['profit_rate']) ?>"></div>
        <div class="f"><label for="months">কত মাসের কিস্তি</label><input id="months" name="months" type="number" min="1" max="120" value="<?= e($d['months']) ?>"></div>
      </div>
    </section>

    <section class="card">
      <div class="card-h"><h2>তারিখ ও অন্যান্য</h2></div>
      <div class="card-b form-grid">
        <div class="f"><label for="sale_date">বিক্রির তারিখ</label><input id="sale_date" name="sale_date" type="date" value="<?= e($d['sale_date']) ?>"></div>
        <div class="f"><label for="first_due">প্রথম কিস্তির তারিখ</label><input id="first_due" name="first_due" type="date" value="<?= e($d['first_due']) ?>"><p class="hint">এরপর প্রতি মাসে এই তারিখে কিস্তি।</p></div>
        <div class="f full"><label for="old_paid">খাতা থেকে তোলা: আগে কত কিস্তি পরিশোধ হয়েছে (৳) <span class="opt">ঐচ্ছিক</span></label><input id="old_paid" name="old_paid" type="number" min="0" step="1" value="<?= e($d['old_paid']) ?>"><p class="hint">পুরনো খাতার হিসাব তোলার সময় ব্যবহার করুন। ডাউন পেমেন্ট এখানে ধরবেন না।</p></div>
        <div class="f full"><label for="note">নোট <span class="opt">ঐচ্ছিক</span></label><input id="note" name="note" value="<?= e($d['note']) ?>" placeholder="যেমন: IMEI নম্বর, ওয়ারেন্টি"></div>
      </div>
    </section>
  </div>

  <div class="stack">
    <section class="card" style="position:sticky;top:80px">
      <div class="card-h"><h2>কিস্তির হিসাব</h2></div>
      <div class="card-b">
        <div class="calc">
          <dl>
            <dt>বাকি দাম (দাম − ডাউন)</dt><dd id="c_fin">৳0</dd>
            <dt>লাভ</dt><dd id="c_profit">৳0</dd>
            <hr>
            <dt class="fw" style="color:var(--ink)">মোট কিস্তির টাকা</dt><dd id="c_total" class="fw">৳0</dd>
            <dt>মাসিক কিস্তি</dt><dd id="c_inst" class="big">—</dd>
          </dl>
        </div>
        <p class="hint t-muted" style="font-size:12.5px;margin-top:10px">লাভ = বাকি দাম × মাসিক হার × মাস। শেষ কিস্তিতে কয়েক টাকা কম-বেশি হতে পারে, যাতে মোট হিসাব মেলে।</p>
        <button class="btn" type="submit" style="width:100%;margin-top:14px">কিস্তি সেভ করুন</button>
      </div>
    </section>
  </div>
</form>
<?php layout_end();
