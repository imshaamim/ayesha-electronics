<?php
defined('APP_ROOT') || exit;

$id = (int)get('id');
$s = one('SELECT * FROM sale_balance WHERE id = ?', [$id]);
if (!$s) { flash('কিস্তির হিসাব পাওয়া যায়নি।', 'err'); redirect('customers'); }
$sched = sale_schedule($id, (float)$s['paid']);
$pays = all('SELECT * FROM payments WHERE sale_id = ? AND deleted_at IS NULL ORDER BY pay_date DESC, id DESC', [$id]);
$paidCount = count(array_filter($sched, fn($r) => $r['state'] === 'paid'));
$lateCount = count(array_filter($sched, fn($r) => in_array($r['state'], ['late', 'partial-late'], true)));
$suggest = $s['overdue'] > 0.5 ? $s['overdue'] : min($s['installment_amount'], $s['balance']);
$pct = $s['total_payable'] > 0 ? min(100, round($s['paid'] / $s['total_payable'] * 100)) : 100;

layout_start($s['product_name'], 'customers');
?>
<p class="crumb"><a href="<?= url('customers') ?>">ক্রেতা</a> / <a href="<?= url('customer', ['id' => $s['customer_id']]) ?>"><?= e($s['customer_name']) ?></a> / কিস্তি #<?= $id ?></p>
<?php page_head(
    $s['product_name'],
    ($s['memo_no'] ? 'মেমো <b>' . e($s['memo_no']) . '</b> · ' : '') . '<a href="' . url('customer', ['id' => $s['customer_id']]) . '">' . e($s['customer_name']) . '</a> · বিক্রি ' . bn_date($s['sale_date']) . ' · ' . (int)$s['months'] . ' মাসের কিস্তি'
    . ($s['balance'] <= 0.5 ? ' · <span class="badge dark">সব পরিশোধিত</span>' : ($s['overdue'] > 0.5 ? ' · <span class="badge bad">' . $lateCount . 'টি কিস্তি বকেয়া</span>' : ' · <span class="badge ok">নিয়মিত</span>'))
); ?>

<div class="stats">
  <div class="stat hero"><div class="lbl">বাকি আছে</div><b class="money"><?= money($s['balance']) ?></b><div class="foot"><?= $paidCount ?>/<?= (int)$s['months'] ?> কিস্তি পরিশোধিত</div></div>
  <div class="stat <?= $s['overdue'] > 0.5 ? 'bad' : '' ?>"><div class="lbl"><span class="dot red"></span>বকেয়া</div><b class="money"><?= money($s['overdue']) ?></b><div class="foot">পরের কিস্তি: <?= bn_date($s['next_due']) ?></div></div>
  <div class="stat"><div class="lbl"><span class="dot"></span>পরিশোধ</div><b class="money"><?= money($s['paid']) ?></b><div class="meter" style="margin-top:8px"><i style="width:<?= $pct ?>%"></i></div></div>
  <div class="stat"><div class="lbl"><span class="dot ink"></span>মাসিক কিস্তি</div><b class="money"><?= money($s['installment_amount']) ?></b><div class="foot">প্রতি মাসের <?= (int)date('j', strtotime($s['first_due'])) ?> তারিখে</div></div>
</div>

<div class="grid g-main">
  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>কিস্তির তালিকা</h2></div>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>তারিখ</th><th class="r">কিস্তি</th><th class="r">পরিশোধ</th><th>অবস্থা</th></tr></thead>
        <tbody>
        <?php foreach ($sched as $r): ?>
          <tr>
            <td class="t-muted"><?= (int)$r['no'] ?></td>
            <td><?= bn_date($r['due_date']) ?></td>
            <td class="r money"><?= money($r['amount']) ?></td>
            <td class="r money <?= $r['paid'] > 0 ? 't-green' : 't-muted' ?>"><?= $r['paid'] > 0 ? money($r['paid']) : '—' ?></td>
            <td><?= state_badge($r['state']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>

    <section class="card">
      <div class="card-h"><h2>পরিশোধের ইতিহাস</h2></div>
      <?php if (!$pays): ?><p class="empty-row">এখনো কোনো কিস্তি পরিশোধ হয়নি।</p><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>তারিখ</th><th>মাধ্যম</th><th class="r">টাকা</th><th class="r">জরিমানা</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pays as $p): ?>
          <tr>
            <td><?= bn_date($p['pay_date']) ?><?= $p['note'] ? '<br><small class="t-muted">' . e($p['note']) . '</small>' : '' ?></td>
            <td><?= e($p['method']) ?></td>
            <td class="r money fw t-green"><?= money($p['amount']) ?></td>
            <td class="r money"><?= $p['fine'] > 0 ? money($p['fine']) : '—' ?></td>
            <td class="r" style="white-space:nowrap">
              <a class="btn ghost xs" href="<?= url('receipt', ['id' => $p['id']]) ?>">রসিদ</a>
              <form method="post" action="<?= url('action') ?>" style="display:inline" data-confirm="এই পরিশোধটি মুছে ট্র্যাশে পাঠাবেন?">
                <?= csrf_field() ?><input type="hidden" name="do" value="payment_delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn danger xs" type="submit" aria-label="পরিশোধ মুছুন">মুছুন</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
  </div>

  <div class="stack">
    <?php if ($s['balance'] > 0.5): ?>
    <section class="card" id="pay">
      <div class="card-h"><h2>কিস্তি জমা নিন</h2></div>
      <form method="post" action="<?= url('action') ?>" class="card-b stack" style="gap:12px">
        <?= csrf_field() ?><input type="hidden" name="do" value="payment_add"><input type="hidden" name="sale_id" value="<?= $id ?>">
        <div class="f"><label for="amount">টাকার পরিমাণ (৳)</label><input id="amount" name="amount" type="number" min="1" step="1" max="<?= (int)ceil($s['balance']) ?>" value="<?= (int)round($suggest) ?>" required></div>
        <div class="f"><label for="fine">দেরির জরিমানা (৳) <span class="opt">ঐচ্ছিক</span></label><input id="fine" name="fine" type="number" min="0" step="1" value="<?= $s['overdue'] > 0.5 ? (int)setting('late_fine', '0') : 0 ?>"></div>
        <div class="f"><label for="pay_date">তারিখ</label><input id="pay_date" name="pay_date" type="date" value="<?= today() ?>"></div>
        <div class="f"><label for="method">মাধ্যম</label>
          <select id="method" name="method"><?php foreach (['নগদ', 'বিকাশ', 'নগদ (মোবাইল)', 'রকেট', 'ব্যাংক'] as $m): ?><option><?= $m ?></option><?php endforeach; ?></select>
        </div>
        <div class="f"><label for="pnote">নোট <span class="opt">ঐচ্ছিক</span></label><input id="pnote" name="note"></div>
        <button class="btn" type="submit">জমা করুন ও রসিদ দেখুন</button>
      </form>
    </section>
    <?php endif; ?>

    <section class="card">
      <div class="card-h"><h2>হিসাবের বিবরণ</h2></div>
      <div class="card-b">
        <dl class="kv" style="grid-template-columns:1fr auto;row-gap:8px">
          <?php if ($s['memo_no']): ?><dt>মেমো নম্বর</dt><dd><?= e($s['memo_no']) ?></dd><?php endif; ?>
          <dt>বিক্রয়মূল্য</dt><dd class="money"><?= money($s['price']) ?></dd>
          <dt>ডাউন পেমেন্ট</dt><dd class="money">− <?= money($s['down_payment']) ?></dd>
          <dt class="fw" style="color:var(--ink)">কিস্তিতে মোট</dt><dd class="money fw"><?= money($s['total_payable']) ?></dd>
          <?php $lastAmt = $sched ? (float)end($sched)['amount'] : 0; $n = (int)$s['months']; $same = $n <= 1 || abs($lastAmt - $s['installment_amount']) < 1; ?>
          <dt>কিস্তি</dt><dd><?= money($s['installment_amount']) ?> × <?= $same ? $n : $n - 1 ?><?= $same ? '' : '<br><small class="t-muted">শেষ কিস্তি ' . money($lastAmt) . '</small>' ?></dd>
        </dl>
        <?php if ($s['cost_price'] > 0): ?>
          <p class="t-muted" style="margin-top:12px;font-size:13.5px">ক্রয়মূল্য <?= money($s['cost_price']) ?> · আপনার লাভ <b class="t-green"><?= money($s['profit']) ?></b></p>
        <?php endif; ?>
        <?php if ($s['note']): ?><p style="margin-top:12px" class="t-muted">নোট: <?= e($s['note']) ?></p><?php endif; ?>
      </div>
    </section>

    <form method="post" action="<?= url('action') ?>" data-confirm="এই পুরো কিস্তির হিসাব ট্র্যাশে পাঠাবেন? স্টক থেকে নেওয়া পণ্য স্টকে ফেরত যাবে।">
      <?= csrf_field() ?><input type="hidden" name="do" value="sale_delete"><input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn danger sm" type="submit">এই কিস্তির হিসাব ট্র্যাশে পাঠান</button>
    </form>
  </div>
</div>
<?php layout_end();
