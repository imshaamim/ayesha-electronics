<?php
defined('APP_ROOT') || exit;

$cid = (int)($_POST['customer_id'] ?? get('customer_id'));
$customer = $cid ? one('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$cid]) : null;
$products = all('SELECT * FROM products WHERE deleted_at IS NULL ORDER BY name');
$error = '';
$d = [
    'memo_no' => $customer['memo_no'] ?? '', 'product_id' => '', 'product_name' => '',
    'price' => '', 'cost_price' => '', 'down_payment' => '0', 'months' => '12', 'installment' => '',
    'sale_date' => today(), 'first_due' => add_months(today(), 1), 'old_paid' => '', 'note' => '',
];

if (is_post()) {
    csrf_check();
    foreach ($d as $k => $_) $d[$k] = post($k);
    $price = num('price'); $down = num('down_payment'); $months = (int)num('months');
    $inst = num('installment'); $cost = num('cost_price'); $oldPaid = num('old_paid');
    $pid = (int)$d['product_id'];
    $prod = $pid ? one('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$pid]) : null;
    if ($prod && $d['product_name'] === '') $d['product_name'] = $prod['name'];
    $calc = calc_sale($price, $down, max($months, 1), $inst, $cost);

    if (!$customer)                       $error = 'ক্রেতা বেছে নিন।';
    elseif ($d['product_name'] === '')    $error = 'পণ্যের নাম লিখুন বা তালিকা থেকে বেছে নিন।';
    elseif ($price <= 0)                  $error = 'বিক্রয়মূল্য লিখুন।';
    elseif ($down > $price)               $error = 'ডাউন পেমেন্ট বিক্রয়মূল্যের চেয়ে বেশি হতে পারে না।';
    elseif ($months < 1 || $months > 120) $error = 'কিস্তির সংখ্যা ১ থেকে ১২০ এর মধ্যে দিন।';
    elseif ($calc['total'] > 0 && $calc['last'] <= 0) $error = 'মাসিক কিস্তি বেশি হয়ে গেছে। ' . $months . 'টি কিস্তিতে মোট ' . money($calc['total']) . ' হয়, কিস্তির পরিমাণ বা সংখ্যা কমান।';
    elseif (!valid_date($d['sale_date']) || !valid_date($d['first_due'])) $error = 'তারিখ ঠিকভাবে দিন।';
    elseif ($oldPaid > $calc['total'])    $error = 'আগে পরিশোধিত টাকা কিস্তির মোট টাকার চেয়ে বেশি হতে পারে না।';
    else {
        $pdo = db();
        $pdo->beginTransaction();
        q('INSERT INTO sales (customer_id, memo_no, product_id, product_name, qty, price, cost_price, down_payment, profit_rate, months, profit, total_payable, installment_amount, sale_date, first_due, note)
           VALUES (?,?,?,?,1,?,?,?,0,?,?,?,?,?,?,?)',
          [$cid, $d['memo_no'], $prod['id'] ?? null, $d['product_name'], $price, $cost, $down, $months, $calc['profit'], $calc['total'], $calc['installment'], $d['sale_date'], $d['first_due'], $d['note']]);
        $sid = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO installments (sale_id, no, due_date, amount) VALUES (?,?,?,?)');
        for ($i = 1; $i <= $months; $i++) {
            $ins->execute([$sid, $i, add_months($d['first_due'], $i - 1), installment_amount($calc, $i, $months)]);
        }
        if ($prod) q('UPDATE products SET stock = stock - 1 WHERE id = ?', [$prod['id']]);
        if ($oldPaid > 0) {
            q('INSERT INTO payments (sale_id, customer_id, amount, pay_date, method, note) VALUES (?,?,?,?,?,?)',
              [$sid, $cid, $oldPaid, $d['sale_date'], 'পুরনো খাতা', 'খাতা থেকে তোলা আগের পরিশোধ']);
        }
        if ($d['memo_no'] !== '' && ($customer['memo_no'] ?? '') === '') {
            q('UPDATE customers SET memo_no = ? WHERE id = ?', [$d['memo_no'], $cid]);
        }
        $pdo->commit();
        flash('কিস্তিতে বিক্রি সেভ হয়েছে। মাসিক কিস্তি ' . money($calc['installment']) . '।');
        redirect('sale', ['id' => $sid]);
    }
}
$customers = $customer ? [] : all('SELECT id, memo_no, name, phone FROM customers WHERE deleted_at IS NULL ORDER BY name');

layout_start('কিস্তিতে বিক্রি', 'customers');
?>
<p class="crumb"><a href="<?= url('customers') ?>">ক্রেতা</a><?= $customer ? ' / <a href="' . url('customer', ['id' => $cid]) . '">' . e($customer['name']) . '</a>' : '' ?></p>
<?php page_head('কিস্তিতে বিক্রি', $customer ? e($customer['name']) . ' (#' . $cid . ') এর জন্য নতুন কিস্তি' : 'ক্রেতা ও পণ্য বেছে নিয়ে কিস্তির হিসাব করুন'); ?>
<?php if ($error): ?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>

<form method="post" id="saleForm" class="grid g-main">
  <?= csrf_field() ?>
  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>পণ্য ও মেমো</h2></div>
      <div class="card-b form-grid">
        <?php if ($customer): ?>
          <input type="hidden" name="customer_id" value="<?= $cid ?>">
        <?php else: ?>
          <div class="f full"><label for="customer_id">ক্রেতা *</label>
            <select id="customer_id" name="customer_id" required>
              <option value="">ক্রেতা বেছে নিন</option>
              <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" data-memo="<?= e($c['memo_no']) ?>"><?= $c['memo_no'] ? 'মেমো ' . e($c['memo_no']) . ' · ' : '' ?><?= e($c['name']) ?> <?= $c['phone'] ? '· ' . e($c['phone']) : '' ?></option><?php endforeach; ?>
            </select>
            <p class="hint">তালিকায় না থাকলে আগে <a href="<?= url('customer_form') ?>">নতুন ক্রেতা যোগ করুন</a>।</p>
          </div>
        <?php endif; ?>
        <div class="f"><label for="memo_no">মেমো নম্বর</label><input id="memo_no" name="memo_no" value="<?= e($d['memo_no']) ?>" placeholder="আপনার মেমো বইয়ের নম্বর"></div>
        <div class="f"><label for="product_id">স্টক থেকে পণ্য <span class="opt">ঐচ্ছিক</span></label>
          <select id="product_id" name="product_id">
            <option value="">স্টকের বাইরে / নিজে লিখব</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= (int)$p['id'] ?>" data-cost="<?= (float)$p['cost_price'] ?>" data-name="<?= e($p['name']) ?>" <?= (string)$p['id'] === (string)$d['product_id'] ? 'selected' : '' ?> <?= $p['stock'] <= 0 ? 'disabled' : '' ?>>
                <?= e($p['name']) ?> · স্টক <?= (int)$p['stock'] ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="hint">বেছে নিলে স্টক থেকে ১টি কমবে।</p>
        </div>
        <div class="f full"><label for="product_name">পণ্যের নাম *</label><input id="product_name" name="product_name" value="<?= e($d['product_name']) ?>" placeholder="যেমন: Samsung A15 মোবাইল" required></div>
      </div>
    </section>

    <section class="card">
      <div class="card-h"><h2>দাম ও কিস্তি</h2><span class="t-muted" style="font-size:13px">সব আপনি নিজে ঠিক করবেন</span></div>
      <div class="card-b form-grid">
        <div class="f"><label for="price">বিক্রয়মূল্য (৳) *</label><input id="price" name="price" type="number" min="0" step="1" value="<?= e($d['price']) ?>" required><p class="hint">ক্রেতা মোট যত টাকা দেবেন, লাভসহ।</p></div>
        <div class="f"><label for="down_payment">ডাউন পেমেন্ট / অগ্রিম (৳)</label><input id="down_payment" name="down_payment" type="number" min="0" step="1" value="<?= e($d['down_payment']) ?>"><p class="hint">বিক্রির দিন যা নিলেন।</p></div>
        <div class="f"><label for="months">কিস্তির সংখ্যা (মাস)</label><input id="months" name="months" type="number" min="1" max="120" value="<?= e($d['months']) ?>"></div>
        <div class="f"><label for="installment">মাসিক কিস্তির পরিমাণ (৳)</label><input id="installment" name="installment" type="number" min="0" step="1" value="<?= e($d['installment']) ?>" placeholder="নিজে থেকে হিসাব হবে"><p class="hint">খালি রাখলে বাকি টাকা সমান ভাগ হবে। বদলালে শেষ কিস্তিতে বাকিটা মিলবে।</p></div>
        <div class="f full"><label for="cost_price">আপনার ক্রয়মূল্য (৳) <span class="opt">ঐচ্ছিক, শুধু লাভ দেখার জন্য</span></label><input id="cost_price" name="cost_price" type="number" min="0" step="1" value="<?= e($d['cost_price']) ?>"><p class="hint">সাপ্লায়ারের দাম। ক্রেতা এটা দেখবেন না, কিস্তির হিসাবেও এর প্রভাব নেই।</p></div>
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
            <dt>বিক্রয়মূল্য</dt><dd id="c_price">৳0</dd>
            <dt>ডাউন পেমেন্ট</dt><dd id="c_down">− ৳0</dd>
            <hr>
            <dt class="fw" style="color:var(--ink)">কিস্তিতে বাকি</dt><dd id="c_total" class="fw">৳0</dd>
            <dt>মাসিক কিস্তি</dt><dd id="c_inst" class="big">—</dd>
            <dt>শেষ কিস্তি</dt><dd id="c_last">—</dd>
            <dt id="c_profit_l" hidden>আপনার লাভ</dt><dd id="c_profit" hidden>—</dd>
          </dl>
        </div>
        <p class="flash err" id="c_warn" style="margin:10px 0 0;font-size:13.5px" hidden></p>
        <button class="btn" type="submit" style="width:100%;margin-top:14px">কিস্তি সেভ করুন</button>
      </div>
    </section>
  </div>
</form>
<?php layout_end();
