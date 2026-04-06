<?php
// php/admin/admin_actions.php
// GET  /admin/actions?action=audit_log|jobs|winners
// POST /admin/actions?action=pick_winners|run_expiry|fulfill_claim

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';

$admin  = require_admin();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ---- AUDIT LOG ----
if ($action === 'audit_log' && $method === 'GET') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $logs = DB::fetchAll(
        "SELECT al.*,
                COALESCE(NULLIF(u.name, ''), u.email) AS username
         FROM audit_log al
         LEFT JOIN users u ON u.id = al.actor_id
         ORDER BY al.created_at DESC LIMIT ? OFFSET ?",
        [$limit, $offset]
    );

    $total = DB::fetch("SELECT COUNT(*) AS c FROM audit_log")['c'];
    json_ok(['logs' => $logs, 'total' => $total, 'page' => $page]);
}

// ---- JOBS LIST ----
if ($action === 'jobs' && $method === 'GET') {
    $jobs = DB::fetchAll("SELECT * FROM jobs ORDER BY created_at DESC LIMIT 100");
    json_ok(['jobs' => $jobs]);
}

// ---- PICK WINNERS (Lambo / Lucky Draw) ----
if ($action === 'pick_winners' && $method === 'POST') {
    $body = require_json_post();
    v_required($body, ['month', 'num_winners']);

    $month      = $body['month'];   // 'YYYY-MM'
    $numWinners = (int)$body['num_winners'];
    if ($numWinners < 1 || $numWinners > 100) json_err('num_winners must be 1-100');

    // Get all clients with lambo balance that month
    $pool = DB::fetchAll(
        "SELECT ll.recipient_id, SUM(ll.amount) AS total_contribution
         FROM lambo_ledger ll
         JOIN users u ON u.id = ll.recipient_id
         WHERE u.role = 'client'
           AND DATE_FORMAT(ll.created_at, '%Y-%m') = ?
           AND ll.amount > 0
         GROUP BY ll.recipient_id
         HAVING total_contribution > 0
         ORDER BY total_contribution DESC",
        [$month]
    );

    if (empty($pool)) json_err("No eligible participants for $month");

    // Crypto-secure RNG weighted draw
    $rngSeed = bin2hex(random_bytes(32));
    $winners = [];
    $usedIds = [];
    $totalContrib = array_sum(array_column($pool, 'total_contribution'));

    $attempts = 0;
    while (count($winners) < $numWinners && $attempts < 10000) {
        $roll = (float)(random_int(0, PHP_INT_MAX) / PHP_INT_MAX) * $totalContrib;
        $cumul = 0.0;
        foreach ($pool as $p) {
            $cumul += (float)$p['total_contribution'];
            if ($roll <= $cumul && !in_array($p['recipient_id'], $usedIds)) {
                $user = DB::fetch("SELECT id, COALESCE(NULLIF(name, ''), email) AS username FROM users WHERE id = ?", [$p['recipient_id']]);
                $winners[] = ['user' => $user, 'contribution' => $p['total_contribution']];
                $usedIds[] = $p['recipient_id'];
                break;
            }
        }
        $attempts++;
    }

    // Log job
    $jobKey = idempotency_key('pick_winners', $month, $rngSeed);
    $jobId  = DB::insert(
        "INSERT INTO jobs (type, status, ref_month, idempotency_key, records_processed, finished_at)
         VALUES ('winner_select', 'done', ?, ?, ?, NOW())",
        [$month, $jobKey, count($winners)]
    );

    DB::query("INSERT INTO audit_log (actor_id, action, meta) VALUES (?, 'pick_winners', ?)",
        [$admin['id'], json_encode(['job_id' => $jobId, 'month' => $month, 'seed' => $rngSeed, 'winners' => count($winners)])]);

    json_ok(['job_id' => $jobId, 'rng_seed' => $rngSeed, 'winners' => $winners]);
}

// ---- RUN EXPIRY ----
if ($action === 'run_expiry' && $method === 'POST') {
    $body  = require_json_post();
    $month = $body['month'] ?? date('Y-m', strtotime('last month'));

    // Delegate to CLI job
    $output = shell_exec("php " . escapeshellarg(__DIR__ . '/../jobs/monthly_expiry.php') . ' ' . escapeshellarg($month) . ' 2>&1');
    json_ok(['output' => $output, 'month' => $month]);
}

// ---- FULFILL CLAIM ----
if ($action === 'fulfill_claim' && $method === 'POST') {
    $body = require_json_post();
    v_required($body, ['claim_id']);

    $claim = DB::fetch("SELECT * FROM sure_win_claims WHERE id = ?", [$body['claim_id']]);
    if (!$claim) json_err('Claim not found', 404);
    if ($claim['status'] !== 'approved') json_err('Claim must be in approved state');

    DB::query(
        "UPDATE sure_win_claims SET status='fulfilled', fulfilled_at=NOW() WHERE id=?",
        [$body['claim_id']]
    );

    DB::query("INSERT INTO audit_log (actor_id, action, target_type, target_id) VALUES (?, 'fulfill_claim', 'claim', ?)",
        [$admin['id'], $body['claim_id']]);

    json_ok(['claim_id' => $body['claim_id'], 'status' => 'fulfilled']);
}

json_err('Unknown action', 404);
