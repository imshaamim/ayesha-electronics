<?php
defined('APP_ROOT') || exit;
$_SESSION = [];
session_destroy();
header('Location: index.php?r=login');
exit;
