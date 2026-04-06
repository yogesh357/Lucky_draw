<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requirePost();
requireRole('ADMIN');

$data = requestData();
$userId = (int) ($data['user_id'] ?? 0);
$points = (int) ($data['points'] ?? 0);
$referenceId = trim((string) ($data['reference_id'] ?? ('ADMIN:' . uniqid())));

if ($userId <= 0 || $points === 0) {
    errorResponse('user_id and non-zero points are required', 422);
}

if (!findUserById($userId)) {
    errorResponse('User not found', 404);
}

insertSureWinLedger($userId, $points, 'CREDIT', $referenceId);

success([
    'message' => 'Points added',
    'data' => [
        'user_id' => $userId,
        'points' => $points,
        'total_points' => getSureWinPoints($userId),
    ],
]);
