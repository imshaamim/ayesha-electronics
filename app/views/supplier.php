<?php
defined('APP_ROOT') || exit;

$id = (int)get('id');
$s = one('SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL', [$id]);
if (!$s) redirect('suppliers');
$txns = all('SELECT * FROM supplier_txns WHERE supplier_id = ? AND deleted_at IS NULL ORDER BY txn_date, id', [$id]);
$run = 0; $bills = 0; $paid = 0;
foreach ($txns as &$t) {
    if ($t['type'] === 'bill') { $run += $t['amount']; $bills += $t['amount']; } else { $run -= $t['amount']; $paid += $t['amount']; }
    $t['running'] = $run;
}
unset($t);
$txns = array_reverse($txns);

layout_start($s['name'], 'suppliers');
?>
<p class="crumb"><a href="<?= url('suppliers') ?>">সাপ্লায়ার</a></p>
<?php page_head($s['name'], e(trim(($s['company'] ?: '') . ($s['phone'] ? ' · ' . $s['phone'] : ''), ' ·')), '<a class="btn ghost sm" href="' . url('supplier_form', ['id' => $id]) . '">তথ্য বদলান</a>'); ?>

<div class="stats" style="grid-template-columns:repeat(3,minmax(0,1fr))">
  <div class="stat hero"><div class="lbl">আপনি দেবেন</div><b class="money"><?= money(max($run, 0)) ?></b><div class="foot"><?= $run < -0.5 ? 'অগ্রিম দেওয়া আছে ' . money(-$run) : 'বর্তমান দেনা' ?></div></div>
  <div class="stat"><div class="lbl"><span class="dot ink"></span>মোট মাল কেনা (বাকিতে)</div><b class="money"><?= money($bills) ?></b></div>
  <div class="stat"><div class="lbl"><span class="dot"></span>মোট পরিশোধ</div><b class="money"><?= money($paid) ?></b></div>
</div>

<div class="grid g-main">
  <section class="card">
    <div class="card-h"><h2>লেনদেনের খাতা</h2></div>
    <?php if (!$txns): ?><p class="empty-row">এখনো কোনো লেনদেন নেই।</p><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>তারিখ</th><th>বিবরণ</th><th class="r">মাল কেনা</th><th class="r">পরিশোধ</th><th class="r">দেনা</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($txns as $t): ?>
        <tr>
          <td><?= bn_date($t['txn_date']) ?></td>
          <td><?= $t['type'] === 'bill' ? 'মাল কেনা' : 'পরিশোধ' ?><?= $t['note'] ? '<br><small class="t-muted">' . e($t['note']) . '</small>' : '' ?></td>
          <td class="r money"><?= $t['type'] === 'bill' ? money($t['amount']) : '' ?></td>
          <td class="r money t-green"><?= $t['type'] === 'pay' ? money($t['amount']) : '' ?></td>
          <td class="r money fw"><?= money($t['running']) ?></td>
          <td class="r"><form method="post" action="<?= url('action') ?>" data-confirm="এই লেনদেনটি ট্র্যাশে পাঠাবেন?"><?= csrf_field() ?><input type="hidden" name="do" value="stxn_delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn danger xs" type="submit">মুছুন</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>

  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>নতুন লেনদেন</h2></div>
      <form method="post" action="<?= url('action') ?>" class="card-b stack" style="gap:12px">
        <?= csrf_field() ?><input type="hidden" name="do" value="stxn_add"><input type="hidden" name="supplier_id" value="<?= $id ?>">
        <div class="f"><label for="type">ধরন</label>
          <select id="type" name="type"><option value="bill">মাল কিনলাম (দেনা বাড়বে)</option><option value="pay">টাকা দিলাম (দেনা কমবে)</option></select>
        </div>
        <div class="f"><label for="amount">টাকার পরিমাণ (৳)</label><input id="amount" name="amount" type="number" min="1" step="1" required></div>
        <div class="f"><label for="txn_date">তারিখ</label><input id="txn_date" name="txn_date" type="date" value="<?= today() ?>"></div>
        <div class="f"><label for="note">বিবরণ <span class="opt">ঐচ্ছিক</span></label><input id="note" name="note" placeholder="যেমন: ৫টি ফ্রিজ, মেমো নং ১২৩"></div>
        <button class="btn" type="submit">যোগ করুন</button>
      </form>
    </section>
    <form method="post" action="<?= url('action') ?>" data-confirm="এই সাপ্লায়ারকে ট্র্যাশে পাঠাবেন?">
      <?= csrf_field() ?><input type="hidden" name="do" value="supplier_delete"><input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn danger sm" type="submit">সাপ্লায়ার ট্র্যাশে পাঠান</button>
    </form>
  </div>
</div>
<?php layout_end();
