<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requireGet();
$user = authUser();

$monthStart = (new DateTimeImmutable('first day of this month 00:00:00', new DateTimeZone('UTC')))->format('Y-m-d');
$rewards = db()->query(
    'SELECT reward_key, reward_name, reward_type, reward_value, surewin_points, weight
     FROM reward_catalog
     WHERE is_active = 1
     ORDER BY sort_order ASC, id ASC'
)->fetchAll();

success([
    'data' => [
        'balance' => getSpinBalance((int) $user['id']),
        'expiring_spins' => getSpinBalanceBefore((int) $user['id'], $monthStart),
        'spin_pool' => getExposureSnapshot()['spin_pool'],
        'rewards' => $rewards,
        'recent_results' => recentSpinResults((int) $user['id']),
    ],
]);
