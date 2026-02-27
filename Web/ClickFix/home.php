<?php
$_GET = array_merge($_GET, ['public' => '1', 'page' => 'home']);
require __DIR__ . '/dashboard.php';
