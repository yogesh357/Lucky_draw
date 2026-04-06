<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requireGet();
$user = authUser();

$monthStart = (new DateTimeImmutable('first day of this month 00:00:00', new DateTimeZone('UTC')))->format('Y-m-d');

$traderStmt = db()->prepare(
    "SELECT u.id, u.email, u.role, COALESCE(SUM(t.lots), 0) AS lots
     FROM users u
     LEFT JOIN trades t ON t.user_id = u.id AND t.trade_date >= :month_start
     WHERE u.role = 'CLIENT' AND u.status <> 'EXCLUDED'
     GROUP BY u.id, u.email, u.role
     HAVING lots > 0
     ORDER BY lots DESC, u.id ASC
     LIMIT 20"
);
$traderStmt->execute(['month_start' => $monthStart]);
$traders = $traderStmt->fetchAll();

$leadersStmt = db()->prepare(
    "SELECT u.id, u.email, u.role, COALESCE(SUM(t.lots), 0) AS network_lots
     FROM users u
     LEFT JOIN users c ON (
        (u.role = 'IB' AND c.parent_ib_id = u.id AND c.role = 'CLIENT' AND c.status <> 'EXCLUDED')
        OR
        (u.role = 'MIB' AND c.parent_mib_id = u.id AND c.role IN ('CLIENT','IB') AND c.status <> 'EXCLUDED')
     )
     LEFT JOIN trades t ON t.user_id = c.id AND t.trade_date >= :month_start
     WHERE u.role IN ('IB','MIB') AND u.status <> 'EXCLUDED'
     GROUP BY u.id, u.email, u.role
     HAVING network_lots > 0
     ORDER BY network_lots DESC, u.id ASC
     LIMIT 20"
);
$leadersStmt->execute(['month_start' => $monthStart]);
$leaders = $leadersStmt->fetchAll();

function userTier(float $lots): string
{
    if ($lots >= 1200) {
        return 'Diamond';
    }
    if ($lots >= 900) {
        return 'Gold';
    }
    if ($lots >= 500) {
        return 'Silver';
    }
    return 'Bronze';
}

success([
    'data' => [
        'month' => $monthStart,
        'me' => [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
        'traders' => array_map(static function (array $row) use ($user): array {
            $lots = round((float) $row['lots'], 2);
            return [
                'id' => (int) $row['id'],
                'email' => $row['email'],
                'lots' => $lots,
                'tier' => userTier($lots),
                'is_me' => (int) $row['id'] === (int) $user['id'],
            ];
        }, $traders),
        'leaders' => array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'email' => $row['email'],
                'role' => $row['role'],
                'network_lots' => round((float) $row['network_lots'], 2),
            ];
        }, $leaders),
    ],
]);
