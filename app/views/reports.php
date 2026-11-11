<?php
defined('APP_ROOT') || exit;

$from = get('from', date('Y-m-01'));
$to = get('to', today());
if (!valid_date($from)) $from = date('Y-m-01');
if (!valid_date($to)) $to = today();
if ($from > $to) [$from, $to] = [$to, $from];

$base = 'FROM payments p JOIN sale_paid s ON s.id = p.sale_id WHERE p.deleted_at IS NULL AND p.pay_date BETWEEN ? AND ?';
$tot = one("SELECT COALESCE(SUM(p.amount),0) AS amt, COALESCE(SUM(p.fine),0) AS fine, COUNT(*) AS n $base", [$from, $to]);
$daily = all("SELECT p.pay_date AS d, SUM(p.amount) AS amt, SUM(p.fine) AS fine, COUNT(*) AS n $base GROUP BY p.pay_date ORDER BY p.pay_date DESC", [$from, $to]);
$byMethod = all("SELECT p.method, SUM(p.amount + p.fine) AS amt, COUNT(*) AS n $base GROUP BY p.method ORDER BY amt DESC", [$from, $to]);
$sales = one('SELECT COUNT(*) AS n, COALESCE(SUM(price),0) AS price, COALESCE(SUM(down_payment),0) AS down, COALESCE(SUM(profit),0) AS profit FROM sale_paid WHERE sale_date BETWEEN ? AND ?', [$from, $to]);
$expected = (float)val('SELECT COALESCE(SUM(i.amount),0) FROM installments i JOIN sale_paid s ON s.id = i.sale_id WHERE i.due_date BETWEEN ? AND ?', [$from, $to]);
$late = all('SELECT * FROM customer_balance WHERE overdue > 0.5 ORDER BY overdue DESC LIMIT 100');

$presets = [
    'আজ' => [today(), today()],
    'এ মাস' => [date('Y-m-01'), today()],
    'গত মাস' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
    'এ বছর' => [date('Y-01-01'), today()],
];

layout_start('রিপোর্ট', 'reports');
page_head('রিপোর্ট', bn_date($from) . ' থেকে ' . bn_date($to), '<button class="btn ghost sm no-print" type="button" onclick="window.print()">প্রিন্ট</button>');
?>
<div class="card no-print" style="margin-bottom:16px">
  <form method="get" class="card-b" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="r" value="reports">
    <div class="f"><label for="from">শুরু</label><input id="from" type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="f"><label for="to">শেষ</label><input id="to" type="date" name="to" value="<?= e($to) ?>"></div>
    <button class="btn sm" type="submit">দেখুন</button>
    <nav class="tabs" style="margin:0 0 0 auto">
      <?php foreach ($presets as $label => [$a, $b]): ?><a href="<?= url('reports', ['from' => $a, 'to' => $b]) ?>" class="<?= $a === $from && $b === $to ? 'on' : '' ?>"><?= $label ?></a><?php endforeach; ?>
    </nav>
  </form>
</div>

<div class="stats">
  <div class="stat hero"><div class="lbl">মোট আদায়</div><b class="money"><?= money($tot['amt'] + $tot['fine']) ?></b><div class="foot"><?= (int)$tot['n'] ?>টি পরিশোধ</div></div>
  <div class="stat"><div class="lbl"><span class="dot"></span>কিস্তি আদায়</div><b class="money"><?= money($tot['amt']) ?></b><div class="foot">আদায়যোগ্য ছিল <?= money($expected) ?></div></div>
  <div class="stat"><div class="lbl"><span class="dot amber"></span>জরিমানা</div><b class="money"><?= money($tot['fine']) ?></b></div>
  <div class="stat"><div class="lbl"><span class="dot ink"></span>নতুন কিস্তি বিক্রি</div><b><?= (int)$sales['n'] ?>টি</b><div class="foot">দাম <?= money($sales['price']) ?> · লাভ <?= money($sales['profit']) ?></div></div>
</div>

<div class="grid g-main">
  <section class="card">
    <div class="card-h"><h2>দৈনিক আদায়</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>তারিখ</th><th class="r">পরিশোধ</th><th class="r">কিস্তি</th><th class="r">জরিমানা</th><th class="r">মোট</th></tr></thead>
      <tbody>
      <?php if (!$daily): ?><tr><td colspan="5" class="empty-row">এই সময়ে কোনো আদায় নেই।</td></tr><?php endif; ?>
      <?php foreach ($daily as $d): ?>
        <tr><td><?= bn_date($d['d']) ?></td><td class="r"><?= (int)$d['n'] ?>টি</td><td class="r money"><?= money($d['amt']) ?></td><td class="r money"><?= $d['fine'] > 0 ? money($d['fine']) : '—' ?></td><td class="r money fw"><?= money($d['amt'] + $d['fine']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>মাধ্যম অনুযায়ী</h2></div>
      <?php if (!$byMethod): ?><p class="empty-row">কোনো তথ্য নেই।</p><?php else: ?>
      <ul class="rows"><?php foreach ($byMethod as $m): ?><li><span class="grow"><b><?= e($m['method']) ?></b><small><?= (int)$m['n'] ?>টি পরিশোধ</small></span><span class="end"><b class="money"><?= money($m['amt']) ?></b></span></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
    <section class="card">
      <div class="card-h"><h2>বকেয়া তালিকা (আজ পর্যন্ত)</h2><span class="badge bad"><?= count($late) ?> জন</span></div>
      <?php if (!$late): ?><p class="empty-row">কোনো বকেয়া নেই।</p><?php else: ?>
      <ul class="rows"><?php foreach ($late as $c): ?><li><a class="row-link" href="<?= url('customer', ['id' => $c['id']]) ?>"><span class="grow"><b><?= e($c['name']) ?></b><small><?= e($c['phone'] ?: '') ?></small></span><span class="end"><b class="money t-red"><?= money($c['overdue']) ?></b></span></a></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php layout_end();
