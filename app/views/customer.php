<?php
defined('APP_ROOT') || exit;

$id = (int)get('id');
$c = one('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$id]);
if (!$c) { flash('ক্রেতা পাওয়া যায়নি।', 'err'); redirect('customers'); }
$bal = one('SELECT * FROM customer_balance WHERE id = ?', [$id]);
$sales = all('SELECT * FROM sale_balance WHERE customer_id = ? ORDER BY sale_date DESC, id DESC', [$id]);
$pays = all('SELECT p.*, s.product_name FROM payments p JOIN sales s ON s.id = p.sale_id
             WHERE p.customer_id = ? AND p.deleted_at IS NULL AND s.deleted_at IS NULL ORDER BY p.pay_date DESC, p.id DESC LIMIT 50', [$id]);

layout_start($c['name'], 'customers');
?>
<p class="crumb"><a href="<?= url('customers') ?>">ক্রেতা</a> / #<?= $id ?></p>
<div class="page-head">
  <div class="who" style="gap:16px">
    <?= avatar($c['photo'], $c['name'], 'lg') ?>
    <div>
      <h1><?= e($c['name']) ?></h1>
      <p class="sub"><?= $c['memo_no'] ? 'মেমো <b>' . e($c['memo_no']) . '</b> · ' : '' ?>ক্রেতা #<?= $id ?> ·<?= e($c['phone'] ?: 'ফোন নেই') ?>
        <?php if ($bal['overdue'] > 0.5): ?> · <span class="badge bad">বকেয়া <?= money($bal['overdue']) ?></span>
        <?php elseif ($bal['balance'] > 0.5): ?> · <span class="badge ok">নিয়মিত</span>
        <?php elseif ($bal['sales_count'] > 0): ?> · <span class="badge dark">সব পরিশোধিত</span><?php endif; ?>
      </p>
    </div>
  </div>
  <div class="head-actions">
    <?php if ($c['phone']): ?><a class="btn ghost sm" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $c['phone'])) ?>">কল করুন</a><?php endif; ?>
    <a class="btn ghost sm" href="<?= url('customer_form', ['id' => $id]) ?>">তথ্য বদলান</a>
    <a class="btn sm" href="<?= url('sale_form', ['customer_id' => $id]) ?>">+ কিস্তিতে বিক্রি</a>
  </div>
</div>

<div class="stats">
  <div class="stat hero"><div class="lbl">মোট বাকি</div><b class="money"><?= money($bal['balance']) ?></b><div class="foot"><?= (int)$bal['active_count'] ?>টি চলমান কিস্তি</div></div>
  <div class="stat <?= $bal['overdue'] > 0.5 ? 'bad' : '' ?>"><div class="lbl"><span class="dot red"></span>বকেয়া</div><b class="money"><?= money($bal['overdue']) ?></b><div class="foot">পরের কিস্তি: <?= bn_date($bal['next_due']) ?></div></div>
  <div class="stat"><div class="lbl"><span class="dot"></span>মোট পরিশোধ</div><b class="money"><?= money($bal['paid']) ?></b><div class="foot">মোট কিস্তি <?= money($bal['total_payable']) ?></div></div>
  <div class="stat"><div class="lbl"><span class="dot ink"></span>ক্রেতা হয়েছেন</div><b style="font-size:20px"><?= bn_date(substr($c['created_at'], 0, 10)) ?></b><div class="foot"><?= (int)$bal['sales_count'] ?>টি কেনাকাটা</div></div>
</div>

<div class="grid g-main">
  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>কিস্তির কেনাকাটা</h2><a class="link" href="<?= url('sale_form', ['customer_id' => $id]) ?>">+ নতুন</a></div>
      <div class="table-wrap"><table>
        <thead><tr><th>পণ্য</th><th class="r">মোট</th><th class="r">পরিশোধ</th><th class="r">বাকি</th><th>অবস্থা</th></tr></thead>
        <tbody>
        <?php if (!$sales): ?><tr><td colspan="5" class="empty-row">এখনো কোনো কিস্তির বিক্রি নেই।<br><a class="btn sm" style="margin-top:10px" href="<?= url('sale_form', ['customer_id' => $id]) ?>">+ কিস্তিতে বিক্রি যোগ করুন</a></td></tr><?php endif; ?>
        <?php foreach ($sales as $s): ?>
          <tr data-href="<?= url('sale', ['id' => $s['id']]) ?>">
            <td><a href="<?= url('sale', ['id' => $s['id']]) ?>"><b style="font-weight:500"><?= e($s['product_name']) ?></b></a><br><small class="t-muted"><?= $s['memo_no'] ? 'মেমো ' . e($s['memo_no']) . ' · ' : '' ?><?= bn_date($s['sale_date']) ?> ·<?= (int)$s['months'] ?> মাস × <?= money($s['installment_amount']) ?></small></td>
            <td class="r money"><?= money($s['total_payable']) ?></td>
            <td class="r money t-green"><?= money($s['paid']) ?></td>
            <td class="r money fw"><?= money($s['balance']) ?></td>
            <td><?= $s['balance'] <= 0.5 ? '<span class="badge dark">শেষ</span>' : ($s['overdue'] > 0.5 ? '<span class="badge bad">বকেয়া ' . money($s['overdue']) . '</span>' : '<span class="badge ok">নিয়মিত</span>') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>

    <section class="card">
      <div class="card-h"><h2>পরিশোধের ইতিহাস</h2></div>
      <?php if (!$pays): ?><p class="empty-row">এখনো কোনো পরিশোধ নেই।</p><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>তারিখ</th><th>পণ্য</th><th>মাধ্যম</th><th class="r">টাকা</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pays as $p): ?>
          <tr>
            <td><?= bn_date($p['pay_date']) ?></td>
            <td><?= e($p['product_name']) ?><?= $p['note'] ? '<br><small class="t-muted">' . e($p['note']) . '</small>' : '' ?></td>
            <td><?= e($p['method']) ?></td>
            <td class="r money fw t-green"><?= money($p['amount']) ?><?= $p['fine'] > 0 ? '<br><small class="t-muted">+ জরিমানা ' . money($p['fine']) . '</small>' : '' ?></td>
            <td class="r"><a class="btn ghost xs" href="<?= url('receipt', ['id' => $p['id']]) ?>">রসিদ</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
  </div>

  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>ব্যক্তিগত তথ্য</h2></div>
      <div class="card-b">
        <dl class="kv">
          <dt>পিতা/স্বামী</dt><dd><?= e($c['father_name'] ?: '—') ?></dd>
          <dt>মোবাইল</dt><dd><?= e($c['phone'] ?: '—') ?><?= $c['alt_phone'] ? ', ' . e($c['alt_phone']) : '' ?></dd>
          <dt>NID</dt><dd><?= e($c['nid'] ?: '—') ?></dd>
          <dt>পেশা</dt><dd><?= e($c['occupation'] ?: '—') ?></dd>
          <dt>ঠিকানা</dt><dd><?= nl2br(e($c['address'] ?: '—')) ?></dd>
          <?php if ($c['notes']): ?><dt>নোট</dt><dd><?= nl2br(e($c['notes'])) ?></dd><?php endif; ?>
        </dl>
        <?php $docs = array_filter(['ক্রেতার ছবি' => $c['photo'], 'NID সামনে' => $c['nid_front'], 'পণ্যের ছবি' => $c['product_photo']]); ?>
        <?php if ($docs): ?>
          <div class="docs" style="margin-top:16px">
            <?php foreach ($docs as $label => $f): ?><a href="<?= e(file_url($f)) ?>" target="_blank"><img src="<?= e(file_url($f)) ?>" alt="<?= e($label) ?>"><span><?= e($label) ?></span></a><?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="card">
      <div class="card-h"><h2>জামিনদার</h2></div>
      <div class="card-b">
        <?php if (!$c['g_name']): ?><p class="t-muted">জামিনদারের তথ্য দেওয়া হয়নি। <a href="<?= url('customer_form', ['id' => $id]) ?>">যোগ করুন</a></p>
        <?php else: ?>
        <div class="who" style="margin-bottom:12px"><?= avatar($c['g_photo'], $c['g_name']) ?><div><b><?= e($c['g_name']) ?></b><small><?= e($c['g_relation'] ?: '') ?></small></div></div>
        <dl class="kv">
          <dt>মোবাইল</dt><dd><?= e($c['g_phone'] ?: '—') ?></dd>
          <dt>NID</dt><dd><?= e($c['g_nid'] ?: '—') ?></dd>
          <dt>ঠিকানা</dt><dd><?= nl2br(e($c['g_address'] ?: '—')) ?></dd>
        </dl>
        <?php endif; ?>
      </div>
    </section>

    <form method="post" action="<?= url('action') ?>" data-confirm="এই ক্রেতাকে ট্র্যাশে পাঠাবেন? পরে ট্র্যাশ থেকে ফিরিয়ে আনা যাবে।">
      <?= csrf_field() ?><input type="hidden" name="do" value="customer_delete"><input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn danger sm" type="submit">ক্রেতাকে ট্র্যাশে পাঠান</button>
    </form>
  </div>
</div>
<?php layout_end();
