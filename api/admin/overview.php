<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requireGet();
requireRole('ADMIN');

$totalUsers = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$monthlyLots = (float) db()->query('SELECT COALESCE(SUM(lots), 0) FROM trades WHERE trade_date >= DATE_FORMAT(UTC_DATE(), "%Y-%m-01")')->fetchColumn();
$exposure = getExposureSnapshot();
$openFlags = (int) db()->query("SELECT COUNT(*) FROM fraud_flags WHERE status = 'OPEN'")->fetchColumn();

success([
    'data' => [
        'total_users' => $totalUsers,
        'monthly_lots' => round($monthlyLots, 2),
        'spin_pool' => $exposure['spin_pool'],
        'paid_rewards' => $exposure['paid_rewards'],
        'pending_liability' => $exposure['pending_liability'],
        'liability_ratio' => $exposure['liability_ratio'],
        'lambo_fund' => $exposure['lambo_fund'],
        'open_flags' => $openFlags,
        'system_health' => $exposure['liability_status'] === 'SAFE' ? 'Healthy' : ucfirst(strtolower($exposure['liability_status'])),
    ],
]);
