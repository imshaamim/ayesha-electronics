<?php
defined('APP_ROOT') || exit;

$rows = all("SELECT s.*,
  COALESCE(SUM(CASE WHEN t.type='bill' THEN t.amount END),0) AS bills,
  COALESCE(SUM(CASE WHEN t.type='pay' THEN t.amount END),0) AS paid,
  MAX(t.txn_date) AS last_txn
  FROM suppliers s LEFT JOIN supplier_txns t ON t.supplier_id = s.id AND t.deleted_at IS NULL
  WHERE s.deleted_at IS NULL GROUP BY s.id ORDER BY (bills - paid) DESC, s.name");
$due = array_sum(array_map(fn($r) => max($r['bills'] - $r['paid'], 0), $rows));

layout_start('সাপ্লায়ার', 'suppliers');
page_head('সাপ্লায়ার', 'আপনি সাপ্লায়ারদের মোট দেবেন <b class="money t-red">' . money($due) . '</b>', '<a class="btn sm" href="' . url('supplier_form') . '">+ নতুন সাপ্লায়ার</a>');
?>
<section class="card">
  <div class="table-wrap"><table>
    <thead><tr><th>সাপ্লায়ার</th><th class="r">মোট মাল কেনা</th><th class="r">পরিশোধ</th><th class="r">দেনা</th><th class="hide-md">শেষ লেনদেন</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="5" class="empty-row">কোনো সাপ্লায়ার নেই।<br><a class="btn sm" style="margin-top:10px" href="<?= url('supplier_form') ?>">+ সাপ্লায়ার যোগ করুন</a></td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $d = $r['bills'] - $r['paid']; ?>
      <tr data-href="<?= url('supplier', ['id' => $r['id']]) ?>">
        <td><a href="<?= url('supplier', ['id' => $r['id']]) ?>"><b style="font-weight:500"><?= e($r['name']) ?></b></a><br><small class="t-muted"><?= e($r['company'] ?: '') ?><?= $r['company'] && $r['phone'] ? ' · ' : '' ?><?= e($r['phone'] ?: '') ?></small></td>
        <td class="r money"><?= money($r['bills']) ?></td>
        <td class="r money t-green"><?= money($r['paid']) ?></td>
        <td class="r money fw <?= $d > 0.5 ? 't-red' : '' ?>"><?= $d > 0.5 ? money($d) : ($d < -0.5 ? 'অগ্রিম ' . money(-$d) : '<span class="badge ok">দেনা নেই</span>') ?></td>
        <td class="hide-md t-muted"><?= bn_date($r['last_txn']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php layout_end();
