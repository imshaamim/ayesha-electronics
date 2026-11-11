<?php
defined('APP_ROOT') || exit;

$error = '';
if (is_post()) {
    csrf_check();
    if (post('form') === 'shop') {
        foreach (['shop_name', 'shop_name_en', 'shop_address', 'shop_phone', 'owner_name'] as $k) set_setting($k, post($k));
        set_setting('late_fine', (string)max(0, (int)num('late_fine')));
        flash('সেটিংস সেভ হয়েছে।');
        redirect('settings');
    }
    if (post('form') === 'password') {
        $new = post('new_password');
        if (!password_verify(post('current_password'), setting('password_hash'))) $error = 'বর্তমান পাসওয়ার্ড ভুল।';
        elseif (mb_strlen($new) < 4) $error = 'নতুন পাসওয়ার্ড অন্তত ৪ অক্ষরের দিন।';
        elseif ($new !== post('confirm_password')) $error = 'নতুন পাসওয়ার্ড দুইবার একই হয়নি।';
        else {
            set_setting('password_hash', password_hash($new, PASSWORD_DEFAULT));
            flash('পাসওয়ার্ড বদলানো হয়েছে।');
            redirect('settings');
        }
    }
}

$backups = glob(DATA_DIR . '/backups/data-*.db') ?: [];
rsort($backups);
$dbSize = is_file(DATA_DIR . '/data.db') ? filesize(DATA_DIR . '/data.db') : 0;
$upSize = 0; $upCount = 0;
foreach (glob(DATA_DIR . '/uploads/*') ?: [] as $f) { $upSize += filesize($f); $upCount++; }
$mb = fn($b) => number_format($b / 1048576, 1) . ' MB';
$v = fn($k, $d = '') => e(setting($k, $d));

layout_start('সেটিংস', 'settings');
page_head('সেটিংস', 'দোকানের তথ্য, পাসওয়ার্ড ও ব্যাকআপ');
?>
<?php if ($error): ?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="grid g2">
  <div class="stack">
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="form" value="shop">
      <div class="card-h"><h2>দোকানের তথ্য</h2></div>
      <div class="card-b form-grid">
        <div class="f"><label for="shop_name">দোকানের নাম (বাংলা)</label><input id="shop_name" name="shop_name" value="<?= $v('shop_name') ?>"></div>
        <div class="f"><label for="shop_name_en">দোকানের নাম (English)</label><input id="shop_name_en" name="shop_name_en" value="<?= $v('shop_name_en') ?>"></div>
        <div class="f full"><label for="shop_address">ঠিকানা</label><input id="shop_address" name="shop_address" value="<?= $v('shop_address') ?>"></div>
        <div class="f"><label for="shop_phone">ফোন</label><input id="shop_phone" name="shop_phone" type="tel" value="<?= $v('shop_phone') ?>"></div>
        <div class="f"><label for="owner_name">মালিকের নাম</label><input id="owner_name" name="owner_name" value="<?= $v('owner_name') ?>"></div>
        <div class="f"><label for="late_fine">দেরির জরিমানা (৳)</label><input id="late_fine" name="late_fine" type="number" min="0" value="<?= $v('late_fine', '0') ?>"><p class="hint">বকেয়া থাকলে কিস্তি জমার সময় আগে থেকে বসবে।</p></div>
      </div>
      <div class="card-b form-actions" style="border-top:1px solid var(--line);margin:0"><button class="btn" type="submit">সেভ করুন</button></div>
    </form>

    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="form" value="password">
      <div class="card-h"><h2>পাসওয়ার্ড বদলান</h2></div>
      <div class="card-b form-grid">
        <div class="f full"><label for="current_password">বর্তমান পাসওয়ার্ড</label><input id="current_password" name="current_password" type="password" required></div>
        <div class="f"><label for="new_password">নতুন পাসওয়ার্ড</label><input id="new_password" name="new_password" type="password" required></div>
        <div class="f"><label for="confirm_password">আবার লিখুন</label><input id="confirm_password" name="confirm_password" type="password" required></div>
      </div>
      <div class="card-b form-actions" style="border-top:1px solid var(--line);margin:0"><button class="btn dark" type="submit">পাসওয়ার্ড বদলান</button></div>
    </form>
  </div>

  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>ব্যাকআপ ও ডেটা</h2></div>
      <div class="card-b">
        <p>প্রতি সপ্তাহে একবার ব্যাকআপ ডাউনলোড করে কম্পিউটার বা Google Drive-এ রেখে দিন। এতে সব হিসাব থাকে (ক্রেতা, কিস্তি, পরিশোধ, স্টক, সাপ্লায়ার), <b>ছবি থাকে না</b>, তাই ফাইল ছোট থাকে।</p>
        <p class="t-muted" style="font-size:13.5px;margin-top:6px">শেষ ডাউনলোড: <b><?= setting('last_backup_at') ? e(date('d M Y, h:i A', strtotime(setting('last_backup_at')))) : 'এখনো হয়নি' ?></b></p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin:14px 0">
          <a class="btn" href="<?= url('backup', ['type' => 'zip']) ?>">সাপ্তাহিক ব্যাকআপ ডাউনলোড</a>
          <a class="btn ghost" href="<?= url('backup', ['type' => 'csv']) ?>">ক্রেতার তালিকা (Excel)</a>
        </div>
        <p style="font-size:13px"><a href="<?= url('backup', ['type' => 'zip', 'photos' => 1]) ?>">ছবিসহ পুরো ব্যাকআপ</a> <span class="t-muted">(বড় ফাইল, হোস্টিং বদলানোর সময় লাগবে)</span></p>
        <dl class="kv" style="grid-template-columns:1fr auto">
          <dt>ডেটাবেসের আকার</dt><dd><?= $mb($dbSize) ?></dd>
          <dt>ছবি</dt><dd><?= $upCount ?>টি · <?= $mb($upSize) ?></dd>
          <dt>স্বয়ংক্রিয় দৈনিক ব্যাকআপ</dt><dd><?= count($backups) ?>টি (শেষ ৩০ দিন)</dd>
          <dt>সর্বশেষ</dt><dd><?= $backups ? bn_date(substr(basename($backups[0]), 5, 10)) : '—' ?></dd>
        </dl>
      </div>
    </section>
    <section class="card">
      <div class="card-h"><h2>সিস্টেম</h2></div>
      <div class="card-b">
        <dl class="kv" style="grid-template-columns:1fr auto">
          <dt>ভার্সন</dt><dd><?= APP_VERSION ?></dd>
          <dt>PHP</dt><dd><?= PHP_VERSION ?></dd>
          <dt>ডেটা ফোল্ডার</dt><dd style="font-size:12.5px"><?= e(DATA_DIR) ?></dd>
        </dl>
      </div>
    </section>
  </div>
</div>
<?php layout_end();
