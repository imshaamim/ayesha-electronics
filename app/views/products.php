<?php
defined('APP_ROOT') || exit;

$qs = get('q');
$f = get('f');
$where = ['deleted_at IS NULL'];
$p = [];
if ($qs !== '') {
    $like = '%' . $qs . '%';
    $where[] = '(name LIKE ? OR brand LIKE ? OR model LIKE ? OR category LIKE ?)';
    array_push($p, $like, $like, $like, $like);
}
if ($f === 'low') $where[] = 'stock <= 2';
$rows = all('SELECT p.*, (SELECT COUNT(*) FROM sales s WHERE s.product_id = p.id AND s.deleted_at IS NULL) AS sold
             FROM products p WHERE ' . implode(' AND ', $where) . ' ORDER BY name', $p);
$tot = one('SELECT COALESCE(SUM(stock),0) AS units, COALESCE(SUM(stock*cost_price),0) AS cost, COALESCE(SUM(stock*sale_price),0) AS sale FROM products WHERE deleted_at IS NULL');

layout_start('পণ্য ও স্টক', 'products');
page_head('পণ্য ও স্টক', 'স্টকে ' . (int)$tot['units'] . 'টি পণ্য · ক্রয়মূল্যে ' . money($tot['cost']), '<a class="btn sm" href="' . url('product_form') . '">+ নতুন পণ্য</a>');
?>
<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
  <nav class="tabs" style="margin:0">
    <a href="<?= url('products') ?>" class="<?= $f !== 'low' ? 'on' : '' ?>">সব পণ্য</a>
    <a href="<?= url('products', ['f' => 'low']) ?>" class="<?= $f === 'low' ? 'on' : '' ?>">স্টক প্রায় শেষ</a>
  </nav>
  <form method="get" class="f" style="margin-left:auto;min-width:240px">
    <input type="hidden" name="r" value="products">
    <input type="search" name="q" value="<?= e($qs) ?>" placeholder="পণ্য খুঁজুন" aria-label="পণ্য খুঁজুন">
  </form>
</div>
<section class="card">
  <div class="table-wrap"><table>
    <thead><tr><th>পণ্য</th><th class="hide-md">ধরন</th><th class="r">ক্রয়মূল্য</th><th class="r">বিক্রয়মূল্য</th><th class="r">স্টক</th><th class="r hide-md">বিক্রি</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="empty-row">কোনো পণ্য নেই।<br><a class="btn sm" style="margin-top:10px" href="<?= url('product_form') ?>">+ পণ্য যোগ করুন</a></td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr data-href="<?= url('product_form', ['id' => $r['id']]) ?>">
        <td><b style="font-weight:500"><?= e($r['name']) ?></b><br><small class="t-muted"><?= e(trim($r['brand'] . ' ' . $r['model']) ?: '—') ?></small></td>
        <td class="hide-md"><?= e($r['category'] ?: '—') ?></td>
        <td class="r money"><?= money($r['cost_price']) ?></td>
        <td class="r money"><?= money($r['sale_price']) ?></td>
        <td class="r"><?= $r['stock'] <= 0 ? '<span class="badge bad">শেষ</span>' : ($r['stock'] <= 2 ? '<span class="badge warn">' . (int)$r['stock'] . 'টি</span>' : (int)$r['stock'] . 'টি') ?></td>
        <td class="r hide-md t-muted"><?= (int)$r['sold'] ?>বার</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php layout_end();
