<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requireGet();
$user = authUser();

$milestonesStmt = db()->prepare(
    'SELECT m.id, m.code, m.title, m.points_required, m.reward_value,
            EXISTS(
                SELECT 1 FROM milestone_claims mc
                WHERE mc.user_id = :user_id AND mc.milestone_id = m.id
            ) AS claimed
     FROM surewin_milestones m
     WHERE m.is_active = 1
     ORDER BY m.points_required ASC'
);
$milestonesStmt->execute(['user_id' => (int) $user['id']]);

$spinRewardsStmt = db()->query(
    'SELECT reward_key, reward_name, reward_type, reward_value, surewin_points, weight
     FROM reward_catalog
     WHERE is_active = 1
     ORDER BY sort_order ASC, id ASC'
);

$historyStmt = db()->prepare(
    "(SELECT mc.created_at AS created_at, 'SUREWIN' AS reward_channel, m.title AS reward_name,
             'CLAIMED' AS reward_status,
             m.reward_value
      FROM surewin_milestones m
      INNER JOIN milestone_claims mc ON mc.milestone_id = m.id AND mc.user_id = :milestone_user_id)
     UNION ALL
     (SELECT created_at, 'SPIN' AS reward_channel, reward_type AS reward_name,
             CASE WHEN reward_value > 0 OR surewin_points > 0 THEN 'WON' ELSE 'NO_PRIZE' END AS reward_status,
             reward_value
      FROM spin_results
      WHERE user_id = :spin_user_id)
     ORDER BY created_at DESC
     LIMIT 30"
);
$historyStmt->execute([
    'milestone_user_id' => (int) $user['id'],
    'spin_user_id' => (int) $user['id'],
]);

success([
    'data' => [
        'points' => getSureWinPoints((int) $user['id']),
        'milestones' => $milestonesStmt->fetchAll(),
        'spin_rewards' => $spinRewardsStmt->fetchAll(),
        'history' => $historyStmt->fetchAll(),
    ],
]);
