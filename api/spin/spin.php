<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/idempotency.php';
require_once __DIR__ . '/../../core/ledger.php';

requirePost();
$user = requireRole('CLIENT', 'IB', 'MIB');

$data = requestData();
$spins = (int) ($data['spins'] ?? 1);
$useFreeSpin = (bool) ($data['use_free_spin'] ?? false);

if ($spins <= 0) {
    errorResponse('spins must be at least 1', 422);
}

$requestPayload = [
    'user_id' => (int) $user['id'],
    'spins' => $spins,
    'use_free_spin' => $useFreeSpin,
];

$idempotencyKey = requestIdempotencyKey();
if ($idempotencyKey) {
    replayIdempotentResponse('spin.play', $idempotencyKey, $requestPayload);
}

try {
    $result = withTransaction(function (PDO $pdo) use ($user, $spins, $useFreeSpin): array {
        $balance = getSpinBalance((int) $user['id']);
        if (!$useFreeSpin && $balance < $spins) {
            errorResponse('Insufficient spin balance', 422, ['balance' => $balance]);
        }

        if (!$useFreeSpin) {
            insertSpinLedger((int) $user['id'], 'DEBIT', 'SPIN_USE', -$spins, 0, 'SPIN-' . uniqid('', true));
        }

        $reward = getWeightedReward($pdo);
        $seedHash = seedHashForSpin((int) $user['id'], $spins);

        $stmt = $pdo->prepare(
            'INSERT INTO spin_results (user_id, spins_used, reward_type, reward_value, surewin_points, seed_hash, created_at)
             VALUES (:user_id, :spins_used, :reward_type, :reward_value, :surewin_points, :seed_hash, NOW())'
        );
        $stmt->execute([
            'user_id' => (int) $user['id'],
            'spins_used' => $spins,
            'reward_type' => $reward['reward_name'],
            'reward_value' => toDecimal((float) $reward['reward_value']),
            'surewin_points' => (int) $reward['surewin_points'],
            'seed_hash' => $seedHash,
        ]);

        $spinResultId = (string) $pdo->lastInsertId();
        processSpinReward($pdo, (int) $user['id'], $reward, 'SPIN_RESULT:' . $spinResultId);

        return [
            'ok' => true,
            'message' => 'Spin completed',
            'data' => [
                'spin_result_id' => (int) $spinResultId,
                'reward_type' => $reward['reward_name'],
                'reward_value' => round((float) $reward['reward_value'], 2),
                'surewin_points' => (int) $reward['surewin_points'],
                'seed_hash' => $seedHash,
                'remaining_spins' => $useFreeSpin ? $balance : ($balance - $spins),
            ],
        ];
    });
} catch (RuntimeException $exception) {
    errorResponse($exception->getMessage(), 422);
}

if ($idempotencyKey) {
    rememberIdempotentResponse('spin.play', $idempotencyKey, $result);
}

jsonResponse($result);
