<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requireGet();
$user = authUser();

$points = getSureWinPoints((int) $user['id']);
$stmt = db()->query(
    'SELECT m.id, m.code, m.title, m.points_required, m.reward_value,
            EXISTS(
                SELECT 1 FROM milestone_claims mc
                WHERE mc.user_id = ' . (int) $user['id'] . ' AND mc.milestone_id = m.id
            ) AS claimed
     FROM surewin_milestones m
     WHERE m.is_active = 1
     ORDER BY m.points_required ASC'
);
$milestones = [];

foreach ($stmt->fetchAll() as $milestone) {
    $required = (int) $milestone['points_required'];
    $milestones[] = [
        'id' => (int) $milestone['id'],
        'code' => $milestone['code'],
        'title' => $milestone['title'],
        'points_required' => $required,
        'reward_value' => round((float) $milestone['reward_value'], 2),
        'claimed' => (bool) $milestone['claimed'],
        'status' => (bool) $milestone['claimed'] ? 'unlocked' : ($points >= $required ? 'claimable' : 'locked'),
        'progress_percent' => min(100, $required > 0 ? round(($points / $required) * 100, 2) : 0),
    ];
}

success([
    'data' => [
        'points' => $points,
        'milestones' => $milestones,
    ],
]);
