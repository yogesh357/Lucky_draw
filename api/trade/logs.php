<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/auth.php';

requireGet();
requireRole('ADMIN');

$stmt = db()->query(
    'SELECT method, trades_processed, total_lots, duplicates_count, status, summary, created_at
     FROM sync_logs
     ORDER BY id DESC
     LIMIT 20'
);

success([
    'data' => [
        'logs' => $stmt->fetchAll(),
    ],
]);
