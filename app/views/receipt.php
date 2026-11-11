<?php
defined('APP_ROOT') || exit;

$id = (int)get('id');
$p = one('SELECT p.*, s.product_name, s.memo_no, s.total_payable, s.customer_id, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address
          FROM payments p JOIN sales s ON s.id = p.sale_id JOIN customers c ON c.id = p.customer_id
          WHERE p.id = ? AND p.deleted_at IS NULL', [$id]);
if (!$p) { flash('রসিদ পাওয়া যায়নি।', 'err'); redirect('dashboard'); }
$paidUntil = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE sale_id = ? AND deleted_at IS NULL AND (pay_date < ? OR (pay_date = ? AND id <= ?))',
    [$p['sale_id'], $p['pay_date'], $p['pay_date'], $id]);
$left = max($p['total_payable'] - $paidUntil, 0);

layout_start('রসিদ #' . $id, 'customers');
?>
<div class="no-print" style="display:flex;gap:10px;justify-content:center;margin-bottom:8px">
  <a class="btn ghost sm" href="<?= url('sale', ['id' => $p['sale_id']]) ?>">← ফিরে যান</a>
  <button class="btn sm" type="button" onclick="window.print()">প্রিন্ট করুন</button>
</div>
<article class="receipt">
  <div class="rh">
    <?= logo_svg(44) ?>
    <div><b><?= e(setting('shop_name')) ?></b><small><?= e(setting('shop_address')) ?></small><?php if (setting('shop_phone')): ?><small>ফোন: <?= e(setting('shop_phone')) ?></small><?php endif; ?></div>
  </div>
  <div style="display:flex;justify-content:space-between;margin:14px 0 6px">
    <b>কিস্তি আদায়ের রসিদ</b><span class="t-muted">#<?= str_pad((string)$id, 5, '0', STR_PAD_LEFT) ?></span>
  </div>
  <table>
    <?php if ($p['memo_no']): ?><tr><td class="t-muted">মেমো নম্বর</td><td class="r"><?= e($p['memo_no']) ?></td></tr><?php endif; ?>
    <tr><td class="t-muted">তারিখ</td><td class="r"><?= bn_date($p['pay_date']) ?></td></tr>
    <tr><td class="t-muted">ক্রেতা</td><td class="r"><?= e($p['customer_name']) ?> (#<?= (int)$p['customer_id'] ?>)</td></tr>
    <?php if ($p['customer_phone']): ?><tr><td class="t-muted">মোবাইল</td><td class="r"><?= e($p['customer_phone']) ?></td></tr><?php endif; ?>
    <tr><td class="t-muted">পণ্য</td><td class="r"><?= e($p['product_name']) ?></td></tr>
    <tr><td class="t-muted">মাধ্যম</td><td class="r"><?= e($p['method']) ?></td></tr>
    <tr><td>কিস্তি জমা</td><td class="r money"><?= money($p['amount']) ?></td></tr>
    <?php if ($p['fine'] > 0): ?><tr><td>জরিমানা</td><td class="r money"><?= money($p['fine']) ?></td></tr><?php endif; ?>
    <tr class="tot"><td>মোট গ্রহণ</td><td class="r money"><?= money($p['amount'] + $p['fine']) ?></td></tr>
    <tr><td class="t-muted">এরপর বাকি থাকল</td><td class="r money fw"><?= money($left) ?></td></tr>
  </table>
  <div class="sig"><span>ক্রেতার স্বাক্ষর</span><span>দোকানের স্বাক্ষর</span></div>
  <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:16px">ধন্যবাদ। রসিদটি যত্ন করে রাখুন।</p>
</article>
<?php layout_end();
