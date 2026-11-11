<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . DATA_DIR . '/data.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    migrate($pdo);
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function one(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

function setting(string $k, string $default = ''): string
{
    $v = val('SELECT value FROM settings WHERE key = ?', [$k]);
    return $v === null ? $default : (string)$v;
}
function set_setting(string $k, string $v): void
{
    q('INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$k, $v]);
}

function migrate(PDO $pdo): void
{
    $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v >= 1) return;

    $pdo->exec(<<<SQL
CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT);

CREATE TABLE customers (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  phone TEXT, alt_phone TEXT, nid TEXT, father_name TEXT, occupation TEXT, address TEXT,
  photo TEXT, nid_front TEXT, nid_back TEXT,
  g_name TEXT, g_phone TEXT, g_nid TEXT, g_relation TEXT, g_address TEXT, g_photo TEXT,
  notes TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now', '+6 hours')),
  deleted_at TEXT
);

CREATE TABLE products (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL, brand TEXT, model TEXT, category TEXT,
  cost_price REAL NOT NULL DEFAULT 0, sale_price REAL NOT NULL DEFAULT 0,
  stock INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now', '+6 hours')),
  deleted_at TEXT
);

CREATE TABLE sales (
  id INTEGER PRIMARY KEY,
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  product_id INTEGER REFERENCES products(id),
  product_name TEXT NOT NULL,
  qty INTEGER NOT NULL DEFAULT 1,
  price REAL NOT NULL,
  down_payment REAL NOT NULL DEFAULT 0,
  profit_rate REAL NOT NULL DEFAULT 0,
  months INTEGER NOT NULL,
  profit REAL NOT NULL DEFAULT 0,
  total_payable REAL NOT NULL,
  installment_amount REAL NOT NULL,
  sale_date TEXT NOT NULL,
  first_due TEXT NOT NULL,
  note TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now', '+6 hours')),
  deleted_at TEXT
);

CREATE TABLE installments (
  id INTEGER PRIMARY KEY,
  sale_id INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
  no INTEGER NOT NULL,
  due_date TEXT NOT NULL,
  amount REAL NOT NULL
);
CREATE INDEX idx_inst_sale ON installments(sale_id, no);

CREATE TABLE payments (
  id INTEGER PRIMARY KEY,
  sale_id INTEGER NOT NULL REFERENCES sales(id),
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  amount REAL NOT NULL DEFAULT 0,
  fine REAL NOT NULL DEFAULT 0,
  pay_date TEXT NOT NULL,
  method TEXT NOT NULL DEFAULT 'নগদ',
  note TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now', '+6 hours')),
  deleted_at TEXT
);
CREATE INDEX idx_pay_sale ON payments(sale_id);
CREATE INDEX idx_pay_date ON payments(pay_date);

CREATE TABLE suppliers (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL, company TEXT, phone TEXT, address TEXT, notes TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now', '+6 hours')),
  deleted_at TEXT
);

CREATE TABLE supplier_txns (
  id INTEGER PRIMARY KEY,
  supplier_id INTEGER NOT NULL REFERENCES suppliers(id),
  type TEXT NOT NULL CHECK (type IN ('bill', 'pay')),
  amount REAL NOT NULL,
  txn_date TEXT NOT NULL,
  note TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now', '+6 hours')),
  deleted_at TEXT
);

-- Paid / due-so-far per live sale (sale and customer not in trash).
CREATE VIEW sale_paid AS
SELECT s.*, c.name AS customer_name, c.phone AS customer_phone,
  COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.sale_id = s.id AND p.deleted_at IS NULL), 0) AS paid,
  COALESCE((SELECT SUM(i.amount) FROM installments i WHERE i.sale_id = s.id AND i.due_date < date('now', '+6 hours')), 0) AS due_now
FROM sales s JOIN customers c ON c.id = s.customer_id
WHERE s.deleted_at IS NULL AND c.deleted_at IS NULL;

CREATE VIEW sale_balance AS
SELECT sp.*,
  MAX(sp.total_payable - sp.paid, 0) AS balance,
  MAX(sp.due_now - sp.paid, 0) AS overdue,
  (SELECT MIN(i.due_date) FROM installments i WHERE i.sale_id = sp.id
     AND (SELECT SUM(j.amount) FROM installments j WHERE j.sale_id = sp.id AND j.no <= i.no) > sp.paid + 0.5) AS next_due
FROM sale_paid sp;

CREATE VIEW customer_balance AS
SELECT c.id, c.name, c.phone, c.address, c.nid, c.photo, c.created_at,
  COUNT(sb.id) AS sales_count,
  COALESCE(SUM(CASE WHEN sb.balance > 0.5 THEN 1 ELSE 0 END), 0) AS active_count,
  COALESCE(SUM(sb.total_payable), 0) AS total_payable,
  COALESCE(SUM(sb.paid), 0) AS paid,
  COALESCE(SUM(sb.balance), 0) AS balance,
  COALESCE(SUM(sb.overdue), 0) AS overdue,
  MIN(sb.next_due) AS next_due
FROM customers c LEFT JOIN sale_balance sb ON sb.customer_id = c.id
WHERE c.deleted_at IS NULL
GROUP BY c.id;

PRAGMA user_version = 1;
SQL);

    $st = $pdo->prepare('INSERT INTO settings(key, value) VALUES(?, ?)');
    foreach ([
        'shop_name'     => 'আয়েশা ইলেকট্রনিক্স',
        'shop_name_en'  => 'Ayesha Electronics',
        'shop_address'  => 'রওশন মার্কেট, ৬ নং ওয়ার্ড, কাশিমপুর, গাজীপুর',
        'shop_phone'    => '',
        'owner_name'    => '',
        'late_fine'     => '100',
        'default_rate'  => '2',
        'password_hash' => password_hash('12345', PASSWORD_DEFAULT),
    ] as $k => $v) {
        $st->execute([$k, $v]);
    }
}
