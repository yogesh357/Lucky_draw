<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requireGet();
requireRole('ADMIN');

$flagsStmt = db()->query(
    'SELECT ff.id, ff.user_id, u.email, u.role, u.status AS user_status, ff.reason, ff.status, ff.created_at
     FROM fraud_flags ff
     INNER JOIN users u ON u.id = ff.user_id
     ORDER BY ff.id DESC
     LIMIT 100'
);

$spikeStmt = db()->query(
    'SELECT t.user_id, u.email, MAX(t.lots) AS max_lots, AVG(t.lots) AS avg_lots
     FROM trades t
     INNER JOIN users u ON u.id = t.user_id
     GROUP BY t.user_id, u.email
     HAVING max_lots > (AVG(t.lots) * 5) AND AVG(t.lots) > 0'
);

$excludedStmt = db()->query(
    "SELECT id, email, role, status
     FROM users
     WHERE status = 'EXCLUDED'
     ORDER BY id DESC"
);

$todaySpikeStmt = db()->query(
    "SELECT t.user_id, u.email, SUM(CASE WHEN t.trade_date = UTC_DATE() THEN t.lots ELSE 0 END) AS lots_today,
            AVG(t.lots) AS avg_lots
     FROM trades t
     INNER JOIN users u ON u.id = t.user_id
     GROUP BY t.user_id, u.email
     HAVING lots_today > (AVG(t.lots) * 5) AND AVG(t.lots) > 0"
);

success([
    'data' => [
        'flags' => $flagsStmt->fetchAll(),
        'lot_spikes' => $spikeStmt->fetchAll(),
        'today_spikes' => $todaySpikeStmt->fetchAll(),
        'excluded_accounts' => $excludedStmt->fetchAll(),
    ],
]);
