<?php
defined('APP_ROOT') || exit;

$id = (int)get('id');
$s = $id ? one('SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
if ($id && !$s) redirect('suppliers');
$error = '';
if (is_post()) {
    csrf_check();
    $d = ['name' => post('name'), 'company' => post('company'), 'phone' => post('phone'), 'address' => post('address'), 'notes' => post('notes')];
    if ($d['name'] === '') $error = 'সাপ্লায়ারের নাম লিখুন।';
    else {
        if ($s) q('UPDATE suppliers SET name=:name, company=:company, phone=:phone, address=:address, notes=:notes WHERE id=:id', $d + ['id' => $id]);
        else { q('INSERT INTO suppliers (name, company, phone, address, notes) VALUES (:name,:company,:phone,:address,:notes)', $d); $id = (int)db()->lastInsertId(); }
        flash('সাপ্লায়ারের তথ্য সেভ হয়েছে।');
        redirect('supplier', ['id' => $id]);
    }
    $s = $d;
}
$v = fn($k) => e($s[$k] ?? '');
layout_start('সাপ্লায়ার', 'suppliers');
?>
<p class="crumb"><a href="<?= url('suppliers') ?>">সাপ্লায়ার</a></p>
<?php page_head($id ? 'সাপ্লায়ারের তথ্য বদলান' : 'নতুন সাপ্লায়ার'); ?>
<?php if ($error): ?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="card" style="max-width:760px">
  <?= csrf_field() ?>
  <div class="card-b form-grid">
    <div class="f"><label for="name">নাম *</label><input id="name" name="name" value="<?= $v('name') ?>" required autofocus></div>
    <div class="f"><label for="company">কোম্পানি / দোকান</label><input id="company" name="company" value="<?= $v('company') ?>"></div>
    <div class="f"><label for="phone">মোবাইল</label><input id="phone" name="phone" type="tel" value="<?= $v('phone') ?>"></div>
    <div class="f"><label for="address">ঠিকানা</label><input id="address" name="address" value="<?= $v('address') ?>"></div>
    <div class="f full"><label for="notes">নোট</label><textarea id="notes" name="notes" rows="2"><?= $v('notes') ?></textarea></div>
  </div>
  <div class="card-b form-actions" style="border-top:1px solid var(--line);margin:0">
    <a class="btn ghost" href="<?= $id ? url('supplier', ['id' => $id]) : url('suppliers') ?>">বাতিল</a>
    <button class="btn" type="submit">সেভ করুন</button>
  </div>
</form>
<?php layout_end();
