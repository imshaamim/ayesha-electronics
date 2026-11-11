<?php
defined('APP_ROOT') || exit;

$id = (int)get('id');
$pr = $id ? one('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
if ($id && !$pr) { flash('পণ্য পাওয়া যায়নি।', 'err'); redirect('products'); }
$error = '';

if (is_post()) {
    csrf_check();
    $d = [
        'name' => post('name'), 'brand' => post('brand'), 'model' => post('model'), 'category' => post('category'),
        'cost_price' => num('cost_price'), 'sale_price' => num('sale_price'), 'stock' => (int)num('stock'),
    ];
    if ($d['name'] === '') {
        $error = 'পণ্যের নাম লিখুন।';
    } else {
        if ($pr) {
            q('UPDATE products SET name=:name, brand=:brand, model=:model, category=:category, cost_price=:cost_price, sale_price=:sale_price, stock=:stock WHERE id=:id', $d + ['id' => $id]);
            flash('পণ্যের তথ্য আপডেট হয়েছে।');
        } else {
            q('INSERT INTO products (name, brand, model, category, cost_price, sale_price, stock) VALUES (:name,:brand,:model,:category,:cost_price,:sale_price,:stock)', $d);
            flash('নতুন পণ্য যোগ হয়েছে।');
        }
        redirect('products');
    }
    $pr = array_merge($pr ?? [], $d);
}
$v = fn($k) => e($pr[$k] ?? '');
$cats = array_column(all("SELECT DISTINCT category FROM products WHERE category <> '' AND deleted_at IS NULL ORDER BY category"), 'category');
$cats = array_unique(array_merge($cats, ['মোবাইল', 'টিভি', 'ফ্রিজ', 'এসি', 'ওয়াশিং মেশিন', 'ফ্যান', 'ল্যাপটপ']));

layout_start($id ? 'পণ্য বদলান' : 'নতুন পণ্য', 'products');
?>
<p class="crumb"><a href="<?= url('products') ?>">পণ্য ও স্টক</a></p>
<?php page_head($id ? 'পণ্যের তথ্য বদলান' : 'নতুন পণ্য যোগ করুন'); ?>
<?php if ($error): ?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="card" style="max-width:760px">
  <?= csrf_field() ?>
  <div class="card-b form-grid">
    <div class="f full"><label for="name">পণ্যের নাম *</label><input id="name" name="name" value="<?= $v('name') ?>" placeholder="যেমন: Walton ফ্রিজ 252L" required autofocus></div>
    <div class="f"><label for="brand">ব্র্যান্ড</label><input id="brand" name="brand" value="<?= $v('brand') ?>"></div>
    <div class="f"><label for="model">মডেল</label><input id="model" name="model" value="<?= $v('model') ?>"></div>
    <div class="f"><label for="category">ধরন</label><input id="category" name="category" list="cats" value="<?= $v('category') ?>"><datalist id="cats"><?php foreach ($cats as $ct): ?><option value="<?= e($ct) ?>"><?php endforeach; ?></datalist></div>
    <div class="f"><label for="stock">স্টকে কতটি আছে</label><input id="stock" name="stock" type="number" min="0" value="<?= $v('stock') ?: '0' ?>"></div>
    <div class="f"><label for="cost_price">ক্রয়মূল্য (৳)</label><input id="cost_price" name="cost_price" type="number" min="0" step="1" value="<?= $v('cost_price') ?>"></div>
    <div class="f"><label for="sale_price">নগদ বিক্রয়মূল্য (৳)</label><input id="sale_price" name="sale_price" type="number" min="0" step="1" value="<?= $v('sale_price') ?>"><p class="hint">কিস্তিতে বিক্রির সময় এই দাম থেকে হিসাব শুরু হবে।</p></div>
  </div>
  <div class="card-b" style="border-top:1px solid var(--line);display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
    <span></span>
    <div style="display:flex;gap:10px">
      <a class="btn ghost" href="<?= url('products') ?>">বাতিল</a>
      <button class="btn" type="submit"><?= $id ? 'সেভ করুন' : 'পণ্য যোগ করুন' ?></button>
    </div>
  </div>
</form>
<?php if ($id): ?>
<form method="post" action="<?= url('action') ?>" data-confirm="এই পণ্যটি ট্র্যাশে পাঠাবেন?" style="margin-top:14px">
  <?= csrf_field() ?><input type="hidden" name="do" value="product_delete"><input type="hidden" name="id" value="<?= $id ?>">
  <button class="btn danger sm" type="submit">পণ্যটি ট্র্যাশে পাঠান</button>
</form>
<?php endif; ?>
<?php layout_end();
