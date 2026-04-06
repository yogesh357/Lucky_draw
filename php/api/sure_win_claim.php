<?php
// php/api/sure_win_claim.php
// POST /api/sure_win_claim
// Body: { "milestone_id": 1 }

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/LedgerService.php';

$user = require_auth();
$body = require_json_post();
v_required($body, ['milestone_id']);

try {
    $result = LedgerService::claimMilestone($user['id'], (int)$body['milestone_id']);

    DB::query(
        "INSERT INTO audit_log (actor_id, action, target_type, target_id, meta)
         VALUES (?, 'surewin_claim', 'milestone', ?, ?)",
        [$user['id'], $body['milestone_id'], json_encode(['claim_id' => $result['claim_id']])]
    );

    json_ok([
        'claim_id'  => $result['claim_id'],
        'milestone' => $result['milestone']['reward_name'],
        'status'    => 'pending',
    ]);
} catch (\Throwable $e) {
    json_err($e->getMessage(), 422);
}
