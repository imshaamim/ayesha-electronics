<?php
declare(strict_types=1);

function e($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

function money($n): string
{
    $n = (float)$n;
    return '৳' . number_format(round($n), 0, '.', ',');
}

function today(): string { return date('Y-m-d'); }

function bn_date(?string $d): string
{
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date('d M Y', $t) : e($d);
}

function url(string $r, array $p = []): string
{
    return 'index.php?' . http_build_query(['r' => $r] + $p);
}

function redirect(string $r, array $p = []): void
{
    header('Location: ' . url($r, $p));
    exit;
}

function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) { $_SESSION['flash'] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function post(string $k, string $default = ''): string { return trim((string)($_POST[$k] ?? $default)); }
function num(string $k): float { return (float)str_replace(',', '', post($k, '0')); }
function get(string $k, string $default = ''): string { return trim((string)($_GET[$k] ?? $default)); }

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_t" value="' . csrf_token() . '">'; }
function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['_t'] ?? ''))) {
        http_response_code(400);
        exit('ফর্মের মেয়াদ শেষ হয়েছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
    }
}

function is_logged_in(): bool { return !empty($_SESSION['uid']); }

function valid_date(string $d): bool
{
    $t = DateTime::createFromFormat('Y-m-d', $d);
    return $t && $t->format('Y-m-d') === $d;
}

function add_months(string $date, int $n): string
{
    $d = new DateTime($date);
    $day = (int)$d->format('d');
    $d->modify('first day of this month')->modify("+$n month");
    $last = (int)$d->format('t');
    $d->setDate((int)$d->format('Y'), (int)$d->format('m'), min($day, $last));
    return $d->format('Y-m-d');
}

/**
 * The shop sets its own selling price, down payment and monthly installment.
 * Installments cover (price - down); the last one takes whatever is left.
 * Profit is only for reports: selling price - purchase price (if known).
 */
function calc_sale(float $price, float $down, int $months, float $installment = 0, float $cost = 0): array
{
    $total = max($price - $down, 0);
    $inst  = $installment > 0 ? round($installment) : ($months > 0 ? round($total / $months) : $total);
    $last  = $months > 0 ? $total - $inst * ($months - 1) : $total;
    return [
        'total'       => $total,
        'installment' => $inst,
        'last'        => $last,
        'profit'      => $cost > 0 ? $price - $cost : 0,
    ];
}

/** Amount of installment no. $i (1-based) out of $months. */
function installment_amount(array $calc, int $i, int $months): float
{
    return $i < $months ? $calc['installment'] : $calc['last'];
}

/** Installment rows for one sale with how much of each is paid. */
function sale_schedule(int $sale_id, float $paid): array
{
    $rows = all('SELECT * FROM installments WHERE sale_id = ? ORDER BY no', [$sale_id]);
    $left = $paid;
    $t = today();
    foreach ($rows as &$r) {
        $r['paid'] = min($r['amount'], max($left, 0));
        $left -= $r['amount'];
        if ($r['paid'] >= $r['amount'] - 0.5)      $r['state'] = 'paid';
        elseif ($r['due_date'] < $t)              $r['state'] = $r['paid'] > 0 ? 'partial-late' : 'late';
        elseif ($r['paid'] > 0)                   $r['state'] = 'partial';
        else                                      $r['state'] = 'upcoming';
    }
    return $rows;
}

function state_badge(string $s): string
{
    $map = [
        'paid'         => ['ok', 'পরিশোধিত'],
        'partial'      => ['warn', 'আংশিক'],
        'partial-late' => ['bad', 'আংশিক, বকেয়া'],
        'late'         => ['bad', 'বকেয়া'],
        'upcoming'     => ['muted', 'আসন্ন'],
    ];
    [$c, $t] = $map[$s] ?? ['muted', $s];
    return '<span class="badge ' . $c . '">' . $t . '</span>';
}

const IMG_MAX_BYTES = 400 * 1024;  // every stored photo stays under 400 KB
const IMG_MAX_SIDE  = 1280;        // enough to read an NID, small enough for backups

/**
 * Save an uploaded photo, shrunk and re-compressed until it is under 400 KB.
 * Returns the stored filename, or null (with a message in $GLOBALS['upload_errors']).
 */
function save_upload(string $field, string $label = 'ছবি'): ?string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $fail = function (string $why) use ($label) { $GLOBALS['upload_errors'][] = "$label: $why"; return null; };

    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return $fail('ফাইলটি অনেক বড়, আপলোড হয়নি।');
    if ($f['error'] !== UPLOAD_ERR_OK) return $fail('আপলোড হয়নি, আবার চেষ্টা করুন।');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return $fail('শুধু JPG, PNG বা WebP ছবি দেওয়া যাবে।');

    $name = bin2hex(random_bytes(12)) . '.jpg';
    $dest = DATA_DIR . '/uploads/' . $name;

    if (!function_exists('imagecreatefromstring')) {
        // No image library on the server: accept only files that are already small.
        if ($f['size'] > IMG_MAX_BYTES || $mime !== 'image/jpeg') return $fail('৪০০ KB-এর কম JPG ছবি দিন।');
        move_uploaded_file($f['tmp_name'], $dest);
        return $name;
    }

    $img = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
    if (!$img) return $fail('ছবিটি পড়া যায়নি।');
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($f['tmp_name'])['Orientation'] ?? 1);
        if ($o === 3) $img = imagerotate($img, 180, 0);
        if ($o === 6) $img = imagerotate($img, -90, 0);
        if ($o === 8) $img = imagerotate($img, 90, 0);
    }

    $side = IMG_MAX_SIDE;
    for ($try = 0; $try < 8; $try++) {
        $w = imagesx($img); $h = imagesy($img);
        $s = min(1, $side / max($w, $h));
        $tw = max(1, (int)round($w * $s)); $th = max(1, (int)round($h * $s));
        $out = imagecreatetruecolor($tw, $th);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
        foreach ([80, 70, 60, 50] as $quality) {
            ob_start();
            imagejpeg($out, null, $quality);
            $bytes = (string)ob_get_clean();
            if (strlen($bytes) <= IMG_MAX_BYTES) {
                file_put_contents($dest, $bytes);
                return $name;
            }
        }
        $side = (int)round($side * 0.8);
    }
    return $fail('ছবিটি ৪০০ KB-এর মধ্যে আনা যায়নি।');
}

function upload_errors(): array { return $GLOBALS['upload_errors'] ?? []; }

function file_url(?string $f): string { return $f ? url('file', ['f' => $f]) : ''; }

/** Once a day, keep a consistent copy of the database in backups/ (last 30 kept). */
function daily_backup(): void
{
    if (!is_file(DATA_DIR . '/data.db')) return;
    $file = DATA_DIR . '/backups/data-' . today() . '.db';
    if (is_file($file)) return;
    try {
        db()->exec('VACUUM INTO ' . db()->quote($file));
        $old = glob(DATA_DIR . '/backups/data-*.db') ?: [];
        sort($old);
        foreach (array_slice($old, 0, max(count($old) - 30, 0)) as $o) @unlink($o);
    } catch (Throwable $e) {
        // Backups must never break the app.
    }
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= mb_substr($p, 0, 1);
    return $out ?: '?';
}

function avatar(?string $photo, string $name, string $cls = ''): string
{
    if ($photo) return '<img class="avatar ' . $cls . '" src="' . e(file_url($photo)) . '" alt="">';
    return '<span class="avatar ' . $cls . '" aria-hidden="true">' . e(initials($name)) . '</span>';
}
