<?php
defined('APP_ROOT') || exit;

$t = today();
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$tot = one('SELECT COALESCE(SUM(total_payable),0) AS sold, COALESCE(SUM(paid),0) AS paid,
                   COALESCE(SUM(balance),0) AS balance, COALESCE(SUM(overdue),0) AS overdue,
                   COUNT(*) AS sales, COALESCE(SUM(balance > 0.5),0) AS active
            FROM sale_balance');
$cust = one('SELECT COUNT(*) AS n, COALESCE(SUM(balance > 0.5),0) AS active, COALESCE(SUM(overdue > 0.5),0) AS late FROM customer_balance');

$collectedToday = (float)val('SELECT COALESCE(SUM(p.amount + p.fine),0) FROM payments p JOIN sale_paid s ON s.id = p.sale_id WHERE p.deleted_at IS NULL AND p.pay_date = ?', [$t]);
$collectedMonth = (float)val('SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN sale_paid s ON s.id = p.sale_id WHERE p.deleted_at IS NULL AND p.pay_date BETWEEN ? AND ?', [$monthStart, $monthEnd]);
$fineMonth = (float)val('SELECT COALESCE(SUM(p.fine),0) FROM payments p JOIN sale_paid s ON s.id = p.sale_id WHERE p.deleted_at IS NULL AND p.pay_date BETWEEN ? AND ?', [$monthStart, $monthEnd]);
$expectedMonth = (float)val('SELECT COALESCE(SUM(i.amount),0) FROM installments i JOIN sale_paid s ON s.id = i.sale_id WHERE i.due_date BETWEEN ? AND ?', [$monthStart, $monthEnd]);

$sup = one("SELECT COALESCE(SUM(CASE WHEN t.type='bill' THEN t.amount ELSE 0 END),0) AS bills,
                   COALESCE(SUM(CASE WHEN t.type='pay' THEN t.amount ELSE 0 END),0) AS paid
            FROM supplier_txns t JOIN suppliers s ON s.id = t.supplier_id
            WHERE t.deleted_at IS NULL AND s.deleted_at IS NULL");
$supplierDue = max($sup['bills'] - $sup['paid'], 0);
$stock = one('SELECT COALESCE(SUM(stock * cost_price),0) AS value, COALESCE(SUM(stock),0) AS units, COALESCE(SUM(stock <= 2),0) AS low FROM products WHERE deleted_at IS NULL');

$overdueList = all('SELECT * FROM customer_balance WHERE overdue > 0.5 ORDER BY overdue DESC LIMIT 7');
$upcoming = all('SELECT * FROM sale_balance WHERE balance > 0.5 AND next_due BETWEEN ? AND ? ORDER BY next_due LIMIT 7', [$t, date('Y-m-d', strtotime('+7 days'))]);
$recent = all('SELECT p.*, s.customer_name, s.product_name FROM payments p JOIN sale_paid s ON s.id = p.sale_id WHERE p.deleted_at IS NULL ORDER BY p.pay_date DESC, p.id DESC LIMIT 6');

// Last 6 months of collection
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("first day of -$i month"));
    $months[$m] = 0.0;
}
foreach (all("SELECT substr(p.pay_date,1,7) AS m, SUM(p.amount) AS v FROM payments p JOIN sale_paid s ON s.id = p.sale_id
              WHERE p.deleted_at IS NULL AND p.pay_date >= ? GROUP BY m", [array_key_first($months) . '-01']) as $row) {
    if (isset($months[$row['m']])) $months[$row['m']] = (float)$row['v'];
}
$maxM = max(array_merge([1], array_values($months)));
$pct = $tot['sold'] > 0 ? min(100, round($tot['paid'] / $tot['sold'] * 100)) : 0;
$monthPct = $expectedMonth > 0 ? min(100, round($collectedMonth / $expectedMonth * 100)) : 0;

layout_start('ড্যাশবোর্ড', 'dashboard');
$lastBackup = setting('last_backup_at');
if (!$lastBackup || strtotime($lastBackup) < strtotime('-7 days')): ?>
  <div class="flash warn-box" role="status">
    <span><?= $lastBackup ? 'শেষ ব্যাকআপ নেওয়া হয়েছে ' . e(date('d M', strtotime($lastBackup))) . '। এক সপ্তাহ পার হয়ে গেছে।' : 'এখনো কোনো ব্যাকআপ ডাউনলোড করা হয়নি।' ?></span>
    <a class="btn dark sm" href="<?= url('backup', ['type' => 'zip']) ?>">এখন ব্যাকআপ নিন</a>
  </div>
<?php endif;
$hour = (int)date('G');
page_head(
    'ড্যাশবোর্ড',
    e(date('l, d F Y')) . ' · আজকের হিসাবের সারসংক্ষেপ',
    '<a class="btn ghost sm" href="' . url('reports') . '">রিপোর্ট দেখুন</a><a class="btn dark sm" href="' . url('customers', ['f' => 'late']) . '">বকেয়া তালিকা</a>'
);
?>

<div class="stats">
  <div class="stat hero">
    <div class="lbl">ক্রেতাদের কাছে মোট পাওনা</div>
    <b class="money"><?= money($tot['balance']) ?></b>
    <div class="foot"><?= (int)$cust['active'] ?> জন ক্রেতার কাছে বাকি আছে</div>
  </div>
  <div class="stat bad">
    <div class="lbl"><span class="dot red"></span>মেয়াদ পার হওয়া বকেয়া</div>
    <b class="money"><?= money($tot['overdue']) ?></b>
    <div class="foot"><?= (int)$cust['late'] ?> জন সময়মতো দেননি</div>
  </div>
  <div class="stat">
    <div class="lbl"><span class="dot"></span>এ মাসে আদায়</div>
    <b class="money"><?= money($collectedMonth) ?></b>
    <div class="foot">আদায়যোগ্য <?= money($expectedMonth) ?> · আজ <?= money($collectedToday) ?></div>
  </div>
  <div class="stat">
    <div class="lbl"><span class="dot ink"></span>সাপ্লায়ারদের দেনা</div>
    <b class="money"><?= money($supplierDue) ?></b>
    <div class="foot">আপনি সাপ্লায়ারদের দেবেন</div>
  </div>
</div>

<div class="grid g-main">
  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>মাসভিত্তিক কিস্তি আদায়</h2><a class="link" href="<?= url('reports') ?>">বিস্তারিত</a></div>
      <div class="card-b">
        <div class="bars" role="img" aria-label="গত ৬ মাসের আদায়">
          <?php foreach ($months as $m => $v): ?>
            <div class="col <?= $m === date('Y-m') ? 'cur' : '' ?>">
              <span class="v"><?= $v > 0 ? money($v) : '' ?></span>
              <div class="bar" style="height:<?= max(2, round($v / $maxM * 130)) ?>px"></div>
              <span class="m"><?= e(date('M', strtotime($m . '-01'))) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="split">
        <div><div class="lbl">এ মাসের লক্ষ্য পূরণ</div><b><?= $monthPct ?>%</b><div class="meter" style="margin-top:6px"><i style="width:<?= $monthPct ?>%"></i></div></div>
        <div><div class="lbl">জরিমানা আদায় (এ মাসে)</div><b class="money"><?= money($fineMonth) ?></b></div>
      </div>
    </section>

    <section class="card">
      <div class="card-h"><h2>সবচেয়ে বেশি বকেয়া</h2><a class="link" href="<?= url('customers', ['f' => 'late']) ?>">সব দেখুন</a></div>
      <?php if (!$overdueList): ?>
        <p class="empty-row">কোনো বকেয়া নেই। সবাই সময়মতো কিস্তি দিয়েছেন।</p>
      <?php else: ?>
      <ul class="rows">
        <?php foreach ($overdueList as $c): ?>
          <li><a class="row-link" href="<?= url('customer', ['id' => $c['id']]) ?>">
            <?= avatar($c['photo'], $c['name']) ?>
            <span class="grow"><b><?= e($c['name']) ?></b><small>#<?= (int)$c['id'] ?> · <?= e($c['phone'] ?: 'ফোন নেই') ?></small></span>
            <span class="end"><b class="t-red money"><?= money($c['overdue']) ?></b><small>মোট বাকি <?= money($c['balance']) ?></small></span>
          </a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  </div>

  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>ব্যবসার সারসংক্ষেপ</h2></div>
      <div class="card-b">
        <dl class="kv" style="grid-template-columns:1fr auto;row-gap:10px">
          <dt>মোট কিস্তিতে বিক্রি</dt><dd class="money"><?= money($tot['sold']) ?></dd>
          <dt>এ পর্যন্ত আদায়</dt><dd class="money t-green"><?= money($tot['paid']) ?></dd>
          <dt>বাকি আছে</dt><dd class="money"><?= money($tot['balance']) ?></dd>
        </dl>
        <div class="meter" style="margin:12px 0 4px"><i style="width:<?= $pct ?>%"></i></div>
        <p class="t-muted" style="font-size:13px"><?= $pct ?>% টাকা আদায় হয়েছে</p>
        <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
        <dl class="kv" style="grid-template-columns:1fr auto;row-gap:10px">
          <dt>মোট ক্রেতা</dt><dd><?= (int)$cust['n'] ?> জন</dd>
          <dt>চলমান কিস্তি</dt><dd><?= (int)$tot['active'] ?> টি</dd>
          <dt>স্টকের মূল্য (ক্রয়মূল্যে)</dt><dd class="money"><?= money($stock['value']) ?></dd>
          <dt>স্টকে পণ্য</dt><dd><?= (int)$stock['units'] ?> টি<?= $stock['low'] ? ' · <a href="' . url('products', ['f' => 'low']) . '" class="t-red">' . (int)$stock['low'] . 'টি প্রায় শেষ</a>' : '' ?></dd>
          <dt class="fw" style="color:var(--ink)">নিট (পাওনা − দেনা)</dt><dd class="money fw t-green"><?= money($tot['balance'] - $supplierDue) ?></dd>
        </dl>
      </div>
    </section>

    <section class="card">
      <div class="card-h"><h2>আগামী ৭ দিনের কিস্তি</h2></div>
      <?php if (!$upcoming): ?>
        <p class="empty-row">আগামী ৭ দিনে কোনো কিস্তি নেই।</p>
      <?php else: ?>
      <ul class="rows">
        <?php foreach ($upcoming as $s): ?>
          <li><a class="row-link" href="<?= url('sale', ['id' => $s['id']]) ?>">
            <span class="grow"><b><?= e($s['customer_name']) ?></b><small><?= e($s['product_name']) ?></small></span>
            <span class="end"><b class="money"><?= money($s['installment_amount']) ?></b><small><?= $s['next_due'] === $t ? 'আজ' : bn_date($s['next_due']) ?></small></span>
          </a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-h"><h2>সাম্প্রতিক আদায়</h2></div>
      <?php if (!$recent): ?>
        <p class="empty-row">এখনো কোনো কিস্তি আদায় হয়নি।</p>
      <?php else: ?>
      <ul class="rows">
        <?php foreach ($recent as $p): ?>
          <li><a class="row-link" href="<?= url('sale', ['id' => $p['sale_id']]) ?>">
            <span class="grow"><b><?= e($p['customer_name']) ?></b><small><?= bn_date($p['pay_date']) ?> · <?= e($p['method']) ?></small></span>
            <span class="end"><b class="money t-green">+<?= money($p['amount'] + $p['fine']) ?></b></span>
          </a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  </div>
</div>

<?php layout_end();
