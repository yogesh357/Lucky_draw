<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requireGet();
$user = authUser();

$totalFundStmt = db()->query('SELECT COALESCE(SUM(amount_usd), 0) FROM lambo_ledger');
$targetAmount = (float) getConfigValue('lambo_target_amount', '600000');
$userBalance = getLamboBalance((int) $user['id']);
$fundTotal = (float) $totalFundStmt->fetchColumn();

success([
    'data' => [
        'user_balance' => round($userBalance, 2),
        'fund_total' => round($fundTotal, 2),
        'target_amount' => round($targetAmount, 2),
        'progress_percent' => $targetAmount > 0 ? round(($fundTotal / $targetAmount) * 100, 2) : 0,
        'fund_share_percent' => $fundTotal > 0 ? round(($userBalance / $fundTotal) * 100, 2) : 0,
    ],
]);
