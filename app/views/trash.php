<?php
defined('APP_ROOT') || exit;

$groups = [
    'customer' => ['ক্রেতা', all('SELECT id, name AS title, phone AS sub, deleted_at FROM customers WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC')],
    'sale'     => ['কিস্তির হিসাব', all("SELECT s.id, s.product_name AS title, c.name || ' · ' || s.total_payable AS sub, s.deleted_at FROM sales s JOIN customers c ON c.id = s.customer_id WHERE s.deleted_at IS NOT NULL ORDER BY s.deleted_at DESC")],
    'payment'  => ['পরিশোধ', all("SELECT p.id, c.name || ' · ' || p.amount AS title, p.pay_date AS sub, p.deleted_at FROM payments p JOIN customers c ON c.id = p.customer_id WHERE p.deleted_at IS NOT NULL ORDER BY p.deleted_at DESC")],
    'product'  => ['পণ্য', all('SELECT id, name AS title, model AS sub, deleted_at FROM products WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC')],
    'supplier' => ['সাপ্লায়ার', all('SELECT id, name AS title, company AS sub, deleted_at FROM suppliers WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC')],
    'stxn'     => ['সাপ্লায়ার লেনদেন', all("SELECT t.id, s.name || ' · ' || t.amount AS title, t.txn_date AS sub, t.deleted_at FROM supplier_txns t JOIN suppliers s ON s.id = t.supplier_id WHERE t.deleted_at IS NOT NULL ORDER BY t.deleted_at DESC")],
];
$total = array_sum(array_map(fn($g) => count($g[1]), $groups));

layout_start('ট্র্যাশ', 'trash');
page_head('ট্র্যাশ', 'মুছে ফেলা জিনিস এখানে থাকে, যাতে ভুল করে মুছলে ফিরিয়ে আনা যায়। টাকার হিসাব কখনো পুরোপুরি মোছা হয় না।');
?>
<?php if (!$total): ?>
  <section class="card"><p class="empty-row">ট্র্যাশ খালি।</p></section>
<?php endif; ?>
<div class="stack">
<?php foreach ($groups as $type => [$label, $items]): if (!$items) continue; ?>
  <section class="card">
    <div class="card-h"><h2><?= $label ?></h2><span class="badge muted"><?= count($items) ?></span></div>
    <ul class="rows">
      <?php foreach ($items as $it): ?>
        <li>
          <span class="grow"><b><?= e($it['title']) ?></b><small><?= e($it['sub'] ?: '') ?> · মোছা হয়েছে <?= bn_date(substr($it['deleted_at'], 0, 10)) ?></small></span>
          <form method="post" action="<?= url('action') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="restore"><input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button class="btn ghost xs" type="submit">ফিরিয়ে আনুন</button></form>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>
</div>
<?php layout_end();
