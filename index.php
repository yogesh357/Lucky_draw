<?php

declare(strict_types=1);

require_once __DIR__ . '/core/auth.php';

ensureSessionStarted();

$role = (string) ($_SESSION['role'] ?? '');

$destinations = [
    'CLIENT' => 'client-dashboard.html',
    'IB' => 'ib-dashboard.html',
    'MIB' => 'mib-dashboard.html',
    'ADMIN' => 'admin-overview.html',
];

$target = $destinations[$role] ?? 'login.html';

header('Location: ' . $target, true, 302);
exit;
