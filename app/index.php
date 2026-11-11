<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

$routes = [
    'login', 'logout', 'dashboard',
    'customers', 'customer', 'customer_form',
    'products', 'product_form',
    'sale_form', 'sale', 'receipt',
    'suppliers', 'supplier', 'supplier_form',
    'reports', 'trash', 'settings', 'backup',
    'file', 'search', 'action',
];
$r = get('r', 'dashboard');
if (!in_array($r, $routes, true)) $r = 'dashboard';
if ($r !== 'login' && !is_logged_in()) redirect('login');

require __DIR__ . '/views/' . $r . '.php';
