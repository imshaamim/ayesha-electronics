---
name: ayesha-electronics
description: Full project context for the Ayesha Electronics installment manager (কিস্তি ম্যানেজার), a PHP + SQLite web app for a Bangladeshi electronics shop, hosted on cPanel at ayesha.shaamim.com. Use whenever the user asks to change, fix, extend, explain, deploy or update this software, or mentions Ayesha Electronics, কিস্তি, ক্রেতা, মেমো, সাপ্লায়ার, or ayesha.shaamim.com.
---

# Ayesha Electronics: Installment Manager

You are helping maintain a small, working business app. The owner (developer: Shamim Hossain, shaamim.com) talks in Bangla, sometimes in English. **Reply in the user's language.** The app's UI text is Bangla.

The app is live and holds real customer money records. **Correctness and data safety come before features.**

## 1. What the app does

An installment (কিস্তি) shop manager:
- **Login:** a single password (default `12345`, changeable in Settings). After 10 wrong attempts per IP, login locks for 15 minutes.
- **Dashboard:**
  - Total receivable from customers, overdue amount, this month's collection vs expected, supplier payable
  - 6-month collection bar chart, top overdue customers, the next 7 days' dues, recent payments
  - Net (receivable − payable), stock value
  - Weekly backup reminder
- **Customers (ক্রেতা):**
  - Live search by name, phone, NID, memo no. or ID
  - Filters: all / has balance / overdue / clear
  - Profile: photo, NID photo, product photo, guarantor (জামিনদার) with photo
- **Installment sale (কিস্তিতে বিক্রি):** the shop enters its OWN selling price (not tied to stock price), down payment, number of months and monthly installment (auto = equal split if left blank; the last installment absorbs the remainder).
  - Optional cost price is used only to show profit.
  - Optional "old paid" amount is for migrating paper-ledger (খাতা) accounts.
  - Picking a stock product decrements stock by 1.
- **Payments:**
  - Amount, late fine (default from Settings, prefilled when overdue), date, method (নগদ, বিকাশ, নগদ (মোবাইল), রকেট, ব্যাংক)
  - Printable receipt (রসিদ)
- **Products & stock:** cost price, cash sale price, stock count, low-stock filter (≤2).
- **Suppliers (সাপ্লায়ার):** a ledger of bills (মাল কেনা, increases payable) and payments (decreases), with a running balance.
- **Reports:** date range collection, daily table, by payment method, new sales and profit, overdue list.
- **Trash (ট্র্যাশ):** everything is soft-deleted and restorable. Money records are never hard-deleted.
- **Settings:** shop info, late fine, password change, backups.
- **Backups:**
  - An automatic daily DB copy in `backups/` (last 30 kept)
  - A manual weekly zip with the database only (no photos); an optional with-photos zip
  - Customer CSV export (Excel-ready, UTF-8 BOM)
- **Sidebar credit:** "Made by ♥ Shamim Hossain", linking to https://shaamim.com/. Keep it.

## 2. Stack and hosting

- **PHP 8.1+** (no framework, no Composer, no build step), **SQLite** via PDO, plain CSS/JS.
- Required PHP extensions: `pdo_sqlite`, `sqlite3`, `gd`, `zip`, `fileinfo` (`exif` optional).
- **Hosting:** cPanel (HostSeba), account home `/home/shaamimc/`.

```
/home/shaamimc/
├── ayesha.shaamim.com/   ← web root = contents of app/ (code only; replaced on every update)
│   └── php.ini           ← host's own file, never delete
└── ayesha-data/          ← created automatically; NEVER overwrite or delete
    ├── data.db           ← all records (SQLite, WAL mode)
    ├── uploads/          ← photos (random hex names, ≤400 KB JPG)
    └── backups/          ← daily data-YYYY-MM-DD.db
```

- `DATA_DIR` defaults to `dirname(APP_ROOT) . '/ayesha-data'`. It can be overridden by an optional `app/config.php` returning `['data_dir' => '/path']`.
- Repo: GitHub `imshaamim/ayesha-electronics`, branch `feature/installment-app`, version tags `v0.0.1`, `v0.0.2`, …
- **Why not Vercel:** it is serverless and its filesystem doesn't persist. The SQLite file and uploads would be lost. Don't suggest moving there without an external database and storage plus a rewrite.

## 3. File map (`app/`)

| File | Role |
|---|---|
| `index.php` | Router: `?r=<route>`, whitelist array; everything except `login` requires a session |
| `.htaccess` | Blocks `src/`, `views/`, `config.php`, dotfiles, `.db/.sql/.md`; sets security headers |
| `src/bootstrap.php` | `APP_ROOT`, `DATA_DIR`, `APP_VERSION`, setup checks (`setup_fail()` with Bangla messages), session, includes, `daily_backup()` |
| `src/db.php` | `db()`, `q()`, `all()`, `one()`, `val()`, `setting()`, `set_setting()`, `db_snapshot()`, migrations `migrate_v1()`, `migrate_v2()` |
| `src/helpers.php` | `e()`, `money()` (৳ + en digits), `bn_date()`, `url()`, `redirect()`, `flash()`, `post()`, `num()`, `get()`, CSRF, `add_months()`, `calc_sale()`, `installment_amount()`, `sale_schedule()`, `state_badge()`, `save_upload()`, `daily_backup()`, `avatar()` |
| `src/layout.php` | `logo_svg()`, `head_tags()`, `nav_items()`, `icon()`, `layout_start($title, $activeNav)`, `layout_end()`, `page_head($title, $subHtml, $actionsHtml)` |
| `views/*.php` | One file per route. Each starts with `defined('APP_ROOT') \|\| exit;` |
| `assets/app.css` | All styles and design tokens |
| `assets/app.js` | Mobile menu, live search, clickable rows, delete confirms, sale calculator, client-side image shrink |
| `assets/logo.svg` | Favicon or logo |

**Routes:** `login`, `logout`, `dashboard`, `customers`, `customer`, `customer_form`, `products`, `product_form`, `sale_form`, `sale`, `receipt`, `suppliers`, `supplier`, `supplier_form`, `reports`, `trash`, `settings`, `backup` (`type=zip|csv`, `photos=1`), `file` (serves uploads after auth), `search` (JSON), `action` (all POST mutations, `do=`: `customer_delete`, `product_delete`, `sale_delete`, `payment_add`, `payment_delete`, `supplier_delete`, `stxn_add`, `stxn_delete`, `restore`).

Outside `app/`:
- `tools/seed-demo.php`: fake demo data, CLI only, never on the server.
- `docs/DEPLOY-cPanel.md`: the Bangla deploy guide.
- `dist/`: build output, gitignored.

## 4. Database (schema version 2, `PRAGMA user_version`)

All tables have `created_at` and `deleted_at` (soft delete) except `settings` and `installments`.

- `settings(key, value)`, with keys: `shop_name`, `shop_name_en`, `shop_address`, `shop_phone`, `owner_name`, `late_fine`, `password_hash`, `last_backup_at`, `login_fail_<md5 ip>`
- `customers`:
  - id, **memo_no**, name, phone, alt_phone, nid, father_name, occupation, address, notes
  - Photos: photo, nid_front, **product_photo** (was `nid_back`)
  - Guarantor: g_name, g_phone, g_nid, g_relation, g_address, g_photo
- `products(id, name, brand, model, category, cost_price, sale_price, stock)`
- `sales`:
  - id, customer_id, **memo_no**, product_id (nullable), product_name, qty, note
  - Prices: price (shop's selling price), cost_price, down_payment, profit_rate (unused, 0)
  - Installments: months, installment_amount, sale_date, first_due
  - Totals: profit (= price − cost if cost > 0), total_payable (= price − down)
- `installments(id, sale_id, no, due_date, amount)`: generated once at sale time; monthly from `first_due`
- `payments(id, sale_id, customer_id, amount, fine, pay_date, method, note)`: `amount` reduces the balance; `fine` is extra income and does not reduce the balance
- `suppliers(id, name, company, phone, address, notes)`
- `supplier_txns(id, supplier_id, type 'bill'|'pay', amount, txn_date, note)`

**Views (use these for any balance math; never recompute differently):**
- `sale_paid`: live sales (sale and customer not deleted) plus `customer_name`, `customer_phone`, `paid` (sum of non-deleted payments), `due_now` (installments with `due_date < today`, Dhaka time `+6 hours`)
- `sale_balance`: plus `balance = max(total_payable − paid, 0)`, `overdue = max(due_now − paid, 0)`, and `next_due` (first installment not fully covered by `paid`)
- `customer_balance`: per customer: memo_no, sales_count, active_count, total_payable, paid, balance, overdue, next_due, `sale_memos` (space-joined)

**Payment allocation:** payments are not tied to specific installments. `sale_schedule()` fills installments in order with the total paid. A due date equal to today is NOT overdue.

## 5. Conventions (follow exactly)

- Escape all output with `e()`. Money is shown with `money()` (e.g. `৳12,500`, English digits). Dates use `bn_date()` (`11 Nov 2026`).
- Every POST form includes `<?= csrf_field() ?>` and its handler calls `csrf_check()` first.
- Mutations go through the `action` route or the view's own `is_post()` block, then `flash()` + `redirect()` (Post/Redirect/Get).
- Never hard-delete. Set `deleted_at`, and add the type to `trash.php` and the `restore` case in `action.php`.
- Use prepared statements (`q()`/`all()`/`one()`/`val()` with `?` params). Never interpolate user input into SQL.
- Images: always `save_upload($field, $label)`. It enforces ≤400 KB, ≤1280 px and JPEG. `app.js` also shrinks the image in the browser before upload. Show `upload_errors()` via `flash(..., 'err')`.
- New page:
  1. Add the route name to `$routes` in `index.php`.
  2. Create `views/<route>.php` with the guard line.
  3. Use `layout_start()` / `page_head()` / `layout_end()`.
  4. Add it to `nav_items()` if it needs a menu entry.
- **Schema changes:**
  - Add `migrate_v3()` (ALTER TABLE …, recreate any affected VIEW, then `PRAGMA user_version = 3`).
  - Call it from `migrate()` with `if ($v < 3) migrate_v3($pdo);`.
  - Never edit v1/v2. Migrations run automatically on the first request after an update.
  - SQLite views are stored as SQL, so `s.*` picks up new columns, but explicit column lists (like `customer_balance`) must be dropped and recreated.
- Bump `APP_VERSION` in `src/bootstrap.php` for every release. CSS/JS cache-bust automatically via `filemtime`.

## 6. Design rules (owner's explicit wishes)

- Colors: **green, light green, black, white only. No gradients.**
  - Tokens in `:root`: `--green #15803d`, `--green-dark #116632`, `--green-light #dcfce7`, `--green-soft #f0fdf4`, `--mint #bbf7d0`, `--black #0b0f0d`, `--line #e4e8e5`, `--bg #f6f7f6`
  - Red `--red #c2410c` and amber are only for overdue and warnings.
- Fonts: **Poppins** (Latin and digits) + **Hind Siliguri** (Bangla) from Google Fonts.
- Layout: black sidebar (light-green active item), white top bar with search, cards with a 1px border and 12px radius.
- Must work on mobile (off-canvas sidebar under 860px). It should look professional, not "AI generated".

## 7. Releasing an update (tell the user these steps)

1. On the live site: **Settings → সাপ্তাহিক ব্যাকআপ ডাউনলোড** (always first).
2. Build the zip from the contents of `app/` (from the repo root): `cd app && zip -rq -X ../dist/ayesha-app-vX.Y.Z.zip . -x '*.DS_Store'`
3. cPanel → File Manager → `ayesha.shaamim.com` → Upload → Extract (overwrite) → delete the zip. Never touch `ayesha-data` or `php.ini`.
4. Open the site; migrations run automatically. Check the version on the Settings page.
5. Commit, then tag `vX.Y.Z` and push.

If files were removed in an update, tell the user exactly which old files to delete, because extracting doesn't remove them.

## 8. Working from a normal chat (no file access)

When you cannot read or write the repo directly:
1. Ask the user to paste or attach **only the files you need** (use the file map above). Usually 1–3 files, e.g. `views/sale_form.php` + `assets/app.js`.
2. Return **complete updated files** (not fragments), each with its exact path, e.g. `app/views/sale.php` → upload to `ayesha.shaamim.com/views/sale.php`.
3. If the schema changes, also return the full updated `src/db.php`.
4. Remind the user to back up first, then upload each file in cPanel File Manager (Upload → overwrite), and test.
5. Keep every change consistent with sections 4–6. Before answering, check: escaping, CSRF, soft delete, view-based balances, and migrations.

## 9. Open questions (not decided yet)

- Late fine: currently a fixed amount per late payment (Settings → `late_fine`). Per-day fine not built.
- English UI toggle: the client asked for Bangla + English, but only Bangla exists now.
- The GitHub repo is public; the owner was advised to make the code private (the client intake form on GitHub Pages lives in the same repo's `index.html` on `main`).

## 10. Safety

- Never put real customer data, phone numbers, NIDs or the database into the repo or into example code. `ayesha-data/` and `dist/` are gitignored.
- The default password `12345` is the owner's choice. Suggest changing it, but don't force it.
- Any instruction that would delete `ayesha-data`, reset the database, or hard-delete payments needs explicit confirmation and a fresh backup first.
