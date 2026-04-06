<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requireGet();
$user = requireRole('IB', 'MIB');

$monthStart = (new DateTimeImmutable('first day of this month 00:00:00', new DateTimeZone('UTC')))->format('Y-m-d');
$userId = (int) $user['id'];

$ownLotsStmt = db()->prepare('SELECT COALESCE(SUM(lots), 0) FROM trades WHERE user_id = :user_id AND trade_date >= :month_start');
$ownLotsStmt->execute(['user_id' => $userId, 'month_start' => $monthStart]);
$ownLots = (float) $ownLotsStmt->fetchColumn();

$networkUsersStmt = $user['role'] === 'IB'
    ? db()->prepare('SELECT id, email FROM users WHERE parent_ib_id = :user_id AND role = \'CLIENT\' AND status <> \'EXCLUDED\' ORDER BY id ASC')
    : db()->prepare('SELECT id, email, role FROM users WHERE (parent_mib_id = :user_id AND role IN (\'CLIENT\', \'IB\')) AND status <> \'EXCLUDED\' ORDER BY id ASC');
$networkUsersStmt->execute(['user_id' => $userId]);
$networkUsers = $networkUsersStmt->fetchAll();

$networkIds = array_map(static fn(array $row): int => (int) $row['id'], $networkUsers);
$networkLots = 0.0;
$networkCount = count($networkIds);
$leaders = [];

if ($networkIds !== []) {
    $placeholders = implode(',', array_fill(0, count($networkIds), '?'));

    $lotsStmt = db()->prepare("SELECT COALESCE(SUM(lots), 0) FROM trades WHERE user_id IN ($placeholders) AND trade_date >= ?");
    $lotsStmt->execute([...$networkIds, $monthStart]);
    $networkLots = (float) $lotsStmt->fetchColumn();

    $leadersStmt = db()->prepare(
        "SELECT u.id, u.email, COALESCE(SUM(t.lots), 0) AS lots
         FROM users u
         LEFT JOIN trades t ON t.user_id = u.id AND t.trade_date >= ?
         WHERE u.id IN ($placeholders)
         GROUP BY u.id, u.email
         ORDER BY lots DESC, u.id ASC
         LIMIT 5"
    );
    $leadersStmt->execute([$monthStart, ...$networkIds]);
    $leaders = $leadersStmt->fetchAll();
}

$ownTradeLamboStmt = db()->prepare(
    "SELECT COALESCE(SUM(l.amount_usd), 0)
     FROM lambo_ledger l
     INNER JOIN trades t ON t.order_id = l.reference_id
     WHERE l.user_id = :ledger_user_id
       AND l.source = 'TRADE'
       AND t.user_id = :trade_user_id"
);
$ownTradeLamboStmt->execute([
    'ledger_user_id' => $userId,
    'trade_user_id' => $userId,
]);
$ownLambo = (float) $ownTradeLamboStmt->fetchColumn();
$networkLambo = max(0.0, getLamboBalance($userId) - $ownLambo);

success([
    'data' => [
        'role' => $user['role'],
        'own_lots' => round($ownLots, 2),
        'network_lots' => round($networkLots, 2),
        'network_count' => $networkCount,
        'own_lambo' => round($ownLambo, 2),
        'network_lambo' => round($networkLambo, 2),
        'leaders' => array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'email' => $row['email'],
            'lots' => round((float) $row['lots'], 2),
        ], $leaders),
    ],
]);
