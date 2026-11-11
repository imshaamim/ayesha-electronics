<?php
defined('APP_ROOT') || exit;

$qs = get('q');
$f = get('f', 'all');
$where = ['1=1'];
$p = [];
if ($qs !== '') {
    $like = '%' . $qs . '%';
    $where[] = '(name LIKE ? OR phone LIKE ? OR nid LIKE ? OR address LIKE ? OR CAST(id AS TEXT) = ?)';
    array_push($p, $like, $like, $like, $like, ltrim($qs, '#'));
}
$filters = [
    'all'    => ['সবাই', ''],
    'active' => ['বাকি আছে', 'balance > 0.5'],
    'late'   => ['বকেয়া', 'overdue > 0.5'],
    'clear'  => ['বাকি নেই', 'balance <= 0.5'],
];
if (!isset($filters[$f])) $f = 'all';
$counts = one('SELECT COUNT(*) AS a, COALESCE(SUM(balance > 0.5),0) AS active, COALESCE(SUM(overdue > 0.5),0) AS late, COALESCE(SUM(balance <= 0.5),0) AS clear FROM customer_balance');
$counts['all'] = $counts['a'];
if ($filters[$f][1]) $where[] = $filters[$f][1];

$order = $f === 'late' ? 'overdue DESC' : 'id DESC';
$rows = all('SELECT * FROM customer_balance WHERE ' . implode(' AND ', $where) . " ORDER BY $order LIMIT 500", $p);
$sum = array_sum(array_column($rows, 'balance'));

layout_start('ক্রেতা', 'customers');
page_head(
    $qs !== '' ? '“' . $qs . '” খোঁজার ফলাফল' : 'ক্রেতা',
    count($rows) . ' জন · মোট বাকি <b class="money">' . money($sum) . '</b>',
    '<a class="btn sm" href="' . url('customer_form') . '">+ নতুন ক্রেতা</a>'
);
?>
<nav class="tabs" aria-label="ছাঁকনি">
  <?php foreach ($filters as $k => [$label]): ?>
    <a href="<?= url('customers', ['f' => $k] + ($qs !== '' ? ['q' => $qs] : [])) ?>" class="<?= $f === $k ? 'on' : '' ?>"><?= $label ?><span class="c"><?= (int)$counts[$k] ?></span></a>
  <?php endforeach; ?>
</nav>

<section class="card">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>ক্রেতা</th><th class="hide-md">ঠিকানা</th><th class="r">মোট কিস্তি</th><th class="r">বাকি</th><th class="r">বকেয়া</th><th class="hide-md">পরের কিস্তি</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="empty-row">
          <?= $qs !== '' ? 'এই নামে বা নম্বরে কোনো ক্রেতা পাওয়া যায়নি।' : 'এখনো কোনো ক্রেতা যোগ করা হয়নি।' ?>
          <br><a class="btn sm" style="margin-top:12px" href="<?= url('customer_form') ?>">+ নতুন ক্রেতা যোগ করুন</a>
        </td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $c): ?>
        <tr data-href="<?= url('customer', ['id' => $c['id']]) ?>">
          <td><div class="who"><?= avatar($c['photo'], $c['name']) ?><div><a href="<?= url('customer', ['id' => $c['id']]) ?>"><b><?= e($c['name']) ?></b></a><small>#<?= (int)$c['id'] ?> · <?= e($c['phone'] ?: 'ফোন নেই') ?></small></div></div></td>
          <td class="hide-md t-muted" style="max-width:240px"><?= e(mb_strimwidth((string)$c['address'], 0, 48, '…')) ?></td>
          <td class="r money"><?= money($c['total_payable']) ?></td>
          <td class="r money fw"><?= $c['balance'] > 0.5 ? money($c['balance']) : '<span class="badge ok">বাকি নেই</span>' ?></td>
          <td class="r money <?= $c['overdue'] > 0.5 ? 't-red fw' : 't-muted' ?>"><?= $c['overdue'] > 0.5 ? money($c['overdue']) : '—' ?></td>
          <td class="hide-md"><?= $c['next_due'] ? bn_date($c['next_due']) : '<span class="t-muted">—</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php layout_end();
