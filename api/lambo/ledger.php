<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requireGet();
$user = authUser();

$stmt = db()->prepare(
    'SELECT created_at, source, type, reference_id, amount_usd
     FROM lambo_ledger
     WHERE user_id = :user_id
     ORDER BY id DESC
     LIMIT 50'
);
$stmt->execute(['user_id' => (int) $user['id']]);

success([
    'data' => [
        'balance' => round(getLamboBalance((int) $user['id']), 2),
        'entries' => $stmt->fetchAll(),
    ],
]);
