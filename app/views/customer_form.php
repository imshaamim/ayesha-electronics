<?php
defined('APP_ROOT') || exit;

$id = (int)get('id');
$c = $id ? one('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
if ($id && !$c) { flash('ক্রেতা পাওয়া যায়নি।', 'err'); redirect('customers'); }

$fields = ['memo_no', 'name', 'phone', 'alt_phone', 'nid', 'father_name', 'occupation', 'address',
           'g_name', 'g_phone', 'g_nid', 'g_relation', 'g_address', 'notes'];
$photos = ['photo' => 'ক্রেতার ছবি', 'nid_front' => 'NID ছবি', 'product_photo' => 'পণ্যের ছবি', 'g_photo' => 'জামিনদারের ছবি'];
$error = '';

if (is_post()) {
    csrf_check();
    $data = [];
    foreach ($fields as $k) $data[$k] = post($k);
    if ($data['name'] === '') {
        $error = 'ক্রেতার নাম লিখুন।';
    } else {
        foreach ($photos as $k => $label) {
            $saved = save_upload($k, $label);
            if ($saved) $data[$k] = $saved;
        }
        if ($data['memo_no'] !== '' && val('SELECT id FROM customers WHERE memo_no = ? AND id <> ? AND deleted_at IS NULL', [$data['memo_no'], $id])) {
            $GLOBALS['upload_errors'][] = 'সতর্কতা: মেমো নম্বর ' . $data['memo_no'] . ' আরেকজন ক্রেতার সাথেও আছে।';
        }
        if ($c) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
            q("UPDATE customers SET $set WHERE id = :id", $data + ['id' => $id]);
            flash('ক্রেতার তথ্য আপডেট হয়েছে।');
        } else {
            $cols = implode(', ', array_keys($data));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
            q("INSERT INTO customers ($cols) VALUES ($ph)", $data);
            $id = (int)db()->lastInsertId();
            flash('নতুন ক্রেতা যোগ হয়েছে। এবার কিস্তিতে পণ্য বিক্রি যোগ করুন।');
        }
        if (upload_errors()) flash(implode(' ', upload_errors()), 'err');
        redirect('customer', ['id' => $id]);
    }
    $c = array_merge($c ?? [], $data);
}

$v = fn($k) => e($c[$k] ?? '');
layout_start($c && $id ? 'ক্রেতার তথ্য বদলান' : 'নতুন ক্রেতা', 'customers');
?>
<p class="crumb"><a href="<?= url('customers') ?>">ক্রেতা</a><?= $id ? ' / <a href="' . url('customer', ['id' => $id]) . '">' . $v('name') . '</a>' : '' ?></p>
<?php page_head($id ? 'ক্রেতার তথ্য বদলান' : 'নতুন ক্রেতা যোগ করুন', 'শুধু নাম দেওয়া বাধ্যতামূলক। বাকি তথ্য পরেও যোগ করা যাবে।'); ?>
<?php if ($error): ?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($error && upload_errors()): ?><div class="flash err" role="alert"><?= e(implode(' ', upload_errors())) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="stack">
  <?= csrf_field() ?>
  <section class="card">
    <div class="card-h"><h2>ক্রেতার তথ্য</h2></div>
    <div class="card-b form-grid">
      <div class="f"><label for="memo_no">মেমো নম্বর</label><input id="memo_no" name="memo_no" value="<?= $v('memo_no') ?>" placeholder="আপনার মেমো বইয়ের নম্বর"><p class="hint">এই নম্বর দিয়েও ক্রেতা খোঁজা যাবে।</p></div>
      <div class="f"><label for="name">পুরো নাম *</label><input id="name" name="name" value="<?= $v('name') ?>" required autofocus></div>
      <div class="f"><label for="father_name">পিতা/স্বামীর নাম <span class="opt">ঐচ্ছিক</span></label><input id="father_name" name="father_name" value="<?= $v('father_name') ?>"></div>
      <div class="f"><label for="phone">মোবাইল নম্বর</label><input id="phone" name="phone" type="tel" inputmode="tel" placeholder="01XXXXXXXXX" value="<?= $v('phone') ?>"></div>
      <div class="f"><label for="alt_phone">বিকল্প নম্বর <span class="opt">ঐচ্ছিক</span></label><input id="alt_phone" name="alt_phone" type="tel" inputmode="tel" value="<?= $v('alt_phone') ?>"></div>
      <div class="f"><label for="nid">NID নম্বর</label><input id="nid" name="nid" inputmode="numeric" value="<?= $v('nid') ?>"></div>
      <div class="f"><label for="occupation">পেশা <span class="opt">ঐচ্ছিক</span></label><input id="occupation" name="occupation" value="<?= $v('occupation') ?>"></div>
      <div class="f full"><label for="address">ঠিকানা</label><textarea id="address" name="address" rows="2"><?= $v('address') ?></textarea></div>
    </div>
  </section>

  <section class="card">
    <div class="card-h"><h2>ছবি ও কাগজপত্র</h2><span class="t-muted" style="font-size:13px">মোবাইলে ক্যামেরা দিয়ে সরাসরি তোলা যাবে</span></div>
    <p class="card-b" style="padding-bottom:0;margin:0;font-size:13.5px;color:var(--ink-2)">প্রতিটি ছবি সর্বোচ্চ <b>৪০০ KB</b>। বড় ছবি দিলেও চিন্তা নেই, আপলোডের সময় নিজে থেকে ছোট হয়ে যাবে।</p>
    <div class="card-b form-grid">
      <?php foreach (['photo' => 'ক্রেতার ছবি', 'nid_front' => 'NID ছবি', 'product_photo' => 'পণ্যের ছবি'] as $k => $label): ?>
        <div class="f">
          <label for="<?= $k ?>"><?= $label ?></label>
          <?php if (!empty($c[$k])): ?><img src="<?= e(file_url($c[$k])) ?>" alt="" style="width:96px;height:66px;object-fit:cover;border-radius:8px;border:1px solid var(--line);display:block;margin-bottom:6px"><?php endif; ?>
          <input type="file" id="<?= $k ?>" name="<?= $k ?>" accept="image/*">
          <?php if (!empty($c[$k])): ?><p class="hint">নতুন ছবি দিলে আগেরটা বদলে যাবে।</p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-h"><h2>জামিনদারের তথ্য</h2><span class="t-muted" style="font-size:13px">ঐচ্ছিক</span></div>
    <div class="card-b form-grid">
      <div class="f"><label for="g_name">জামিনদারের নাম</label><input id="g_name" name="g_name" value="<?= $v('g_name') ?>"></div>
      <div class="f"><label for="g_relation">ক্রেতার সাথে সম্পর্ক</label><input id="g_relation" name="g_relation" placeholder="যেমন: ভাই, প্রতিবেশী" value="<?= $v('g_relation') ?>"></div>
      <div class="f"><label for="g_phone">মোবাইল নম্বর</label><input id="g_phone" name="g_phone" type="tel" inputmode="tel" value="<?= $v('g_phone') ?>"></div>
      <div class="f"><label for="g_nid">NID নম্বর</label><input id="g_nid" name="g_nid" inputmode="numeric" value="<?= $v('g_nid') ?>"></div>
      <div class="f full"><label for="g_address">ঠিকানা</label><textarea id="g_address" name="g_address" rows="2"><?= $v('g_address') ?></textarea></div>
      <div class="f">
        <label for="g_photo">জামিনদারের ছবি</label>
        <?php if (!empty($c['g_photo'])): ?><img src="<?= e(file_url($c['g_photo'])) ?>" alt="" style="width:66px;height:66px;object-fit:cover;border-radius:8px;display:block;margin-bottom:6px"><?php endif; ?>
        <input type="file" id="g_photo" name="g_photo" accept="image/*">
      </div>
      <div class="f"><label for="notes">নোট <span class="opt">ঐচ্ছিক</span></label><textarea id="notes" name="notes" rows="2"><?= $v('notes') ?></textarea></div>
    </div>
  </section>

  <div class="form-actions">
    <a class="btn ghost" href="<?= $id ? url('customer', ['id' => $id]) : url('customers') ?>">বাতিল</a>
    <button class="btn" type="submit"><?= $id ? 'সেভ করুন' : 'ক্রেতা যোগ করুন' ?></button>
  </div>
</form>
<?php layout_end();
