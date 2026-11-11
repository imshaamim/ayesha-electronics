<?php
defined('APP_ROOT') || exit;

if (is_logged_in()) redirect('dashboard');

$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$key = 'login_fail_' . md5($ip);
[$fails, $since] = array_pad(explode('|', setting($key, '0|0')), 2, '0');
$fails = (int)$fails; $since = (int)$since;
if (time() - $since > 900) { $fails = 0; }
$locked = $fails >= 10;
$error = '';

if (is_post()) {
    csrf_check();
    if ($locked) {
        $error = 'অনেকবার ভুল পাসওয়ার্ড দেওয়া হয়েছে। ১৫ মিনিট পরে চেষ্টা করুন।';
    } elseif (password_verify(post('password'), setting('password_hash'))) {
        session_regenerate_id(true);
        $_SESSION['uid'] = 1;
        q('DELETE FROM settings WHERE key = ?', [$key]);
        redirect('dashboard');
    } else {
        usleep(700000);
        set_setting($key, ($fails + 1) . '|' . ($fails === 0 ? time() : $since));
        $error = 'পাসওয়ার্ড ভুল হয়েছে। আবার চেষ্টা করুন।';
    }
}

head_tags('লগইন');
$shopBn = setting('shop_name', 'আয়েশা ইলেকট্রনিক্স');
?>
<body>
<div class="login-wrap">
  <section class="login-art" aria-hidden="true">
    <div class="brand" style="padding:0"><?= logo_svg(40) ?><span><b><?= e(setting('shop_name_en', 'Ayesha Electronics')) ?></b><small>কিস্তি ম্যানেজার</small></span></div>
    <h2>খাতার সব হিসাব, এখন <em>এক জায়গায়।</em></h2>
    <div class="facts">
      <div><b>ক্রেতা ও কিস্তি</b>কে কত বাকি, এক নজরে</div>
      <div><b>স্টক ও সাপ্লায়ার</b>কার কাছে কত দেনা</div>
    </div>
  </section>
  <section class="login-form">
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <?= logo_svg(48) ?>
      <h1><?= e($shopBn) ?></h1>
      <p class="sub">চালু করতে পাসওয়ার্ড দিন।</p>
      <?php if ($error): ?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>
      <div class="f">
        <label for="password">পাসওয়ার্ড</label>
        <input class="pin" type="password" id="password" name="password" inputmode="numeric" autofocus required>
      </div>
      <button class="btn" style="width:100%;margin-top:16px;height:46px" type="submit">লগইন করুন</button>
    </form>
  </section>
</div>
</body>
</html>
