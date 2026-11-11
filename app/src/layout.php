<?php
declare(strict_types=1);

function logo_svg(int $size = 36): string
{
    // Mark: a ledger page folded into an "A", with one light-green paid line.
    return '<svg class="logo-mark" width="' . $size . '" height="' . $size . '" viewBox="0 0 40 40" aria-hidden="true">'
        . '<rect width="40" height="40" rx="10" fill="#15803d"/>'
        . '<path d="M11 30 L20 9 L29 30" fill="none" stroke="#fff" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<path d="M14.6 23 H25.4" stroke="#bbf7d0" stroke-width="3.6" stroke-linecap="round"/>'
        . '</svg>';
}

function head_tags(string $title): void
{
    ?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e(setting('shop_name_en', 'Ayesha Electronics')) ?></title>
<link rel="icon" href="assets/logo.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=<?= APP_VERSION ?>">
</head>
    <?php
}

function nav_items(): array
{
    return [
        'dashboard' => ['ড্যাশবোর্ড', '<path d="M4 13h6V4H4zM14 20h6V11h-6zM4 20h6v-4H4zM14 4v4h6V4z"/>'],
        'customers' => ['ক্রেতা', '<path d="M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M21 19v-1a4 4 0 0 0-3-3.9M16 4.1a3.5 3.5 0 0 1 0 6.8"/>'],
        'products'  => ['পণ্য ও স্টক', '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>'],
        'suppliers' => ['সাপ্লায়ার', '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>'],
        'reports'   => ['রিপোর্ট', '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'],
        'settings'  => ['সেটিংস', '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>'],
    ];
}

function icon(string $paths, int $s = 20): string
{
    return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}

function layout_start(string $title, string $active): void
{
    head_tags($title);
    $shop = setting('shop_name', 'আয়েশা ইলেকট্রনিক্স');
    ?>
<body>
<a class="skip" href="#main">মূল অংশে যান</a>
<div class="shell">
  <aside class="side" id="side">
    <a class="brand" href="<?= url('dashboard') ?>">
      <?= logo_svg() ?>
      <span><b><?= e(setting('shop_name_en', 'Ayesha Electronics')) ?></b><small>কিস্তি ম্যানেজার</small></span>
    </a>
    <nav class="nav" aria-label="প্রধান মেনু">
      <?php foreach (nav_items() as $k => [$label, $ic]): ?>
        <a href="<?= url($k) ?>" class="<?= $k === $active ? 'on' : '' ?>" <?= $k === $active ? 'aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= $label ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= url('trash') ?>" class="<?= $active === 'trash' ? 'on' : '' ?>"><?= icon('<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>', 18) ?><span>ট্র্যাশ</span></a>
      <a href="<?= url('logout') ?>"><?= icon('<path d="M15 4h4v16h-4M10 17l5-5-5-5M15 12H3"/>', 18) ?><span>লগআউট</span></a>
    </div>
  </aside>
  <div class="scrim" id="scrim"></div>

  <div class="main-col">
    <header class="top">
      <button class="icon-btn menu-btn" id="menuBtn" aria-label="মেনু খুলুন" aria-controls="side" aria-expanded="false"><?= icon('<path d="M4 6h16M4 12h16M4 18h16"/>', 22) ?></button>
      <form class="search" action="index.php" method="get" role="search">
        <input type="hidden" name="r" value="customers">
        <?= icon('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>', 18) ?>
        <input type="search" name="q" id="gsearch" placeholder="ক্রেতা খুঁজুন: নাম, ফোন, NID বা আইডি" autocomplete="off" value="<?= e($active === 'customers' ? get('q') : '') ?>" aria-label="ক্রেতা খুঁজুন">
        <div class="search-pop" id="searchPop" hidden></div>
      </form>
      <a class="btn sm" href="<?= url('customer_form') ?>"><?= icon('<path d="M12 5v14M5 12h14"/>', 18) ?><span class="hide-sm">নতুন ক্রেতা</span></a>
    </header>
    <main id="main" class="content">
    <?php if ($f = flash()): ?>
      <div class="flash <?= e($f[0]) ?>" role="status"><?= e($f[1]) ?></div>
    <?php endif;
}

function layout_end(): void
{
    ?>
    </main>
  </div>
</div>
<script src="assets/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
    <?php
}

function page_head(string $title, string $sub = '', string $actions = ''): void
{
    echo '<div class="page-head"><div><h1>' . e($title) . '</h1>';
    if ($sub) echo '<p class="sub">' . $sub . '</p>';
    echo '</div>';
    if ($actions) echo '<div class="head-actions">' . $actions . '</div>';
    echo '</div>';
}
