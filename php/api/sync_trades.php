<?php
// php/api/sync_trades.php
// POST /api/sync_trades  (admin only)
// Accepts array of trades from broker, deduplicates by order_id, credits spin & lambo.

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/LedgerService.php';

$admin = require_admin();
$body  = require_json_post();
v_required($body, ['trades']);

if (!is_array($body['trades'])) json_err("'trades' must be an array");

// Create job record
$jobKey = idempotency_key('sync', date('Y-m-d H:i:s'), (string)$admin['id']);
$jobId  = DB::insert(
    "INSERT INTO jobs (type, status, idempotency_key) VALUES ('trade_sync', 'running', ?)",
    [$jobKey]
);

$inserted = 0;
$skipped  = 0;
$errors   = [];

foreach ($body['trades'] as $i => $t) {
    try {
        // Validate
        if (empty($t['order_id']) || empty($t['user_id']) || empty($t['lots']) || empty($t['trade_date'])) {
            $errors[] = "Row $i: missing fields";
            continue;
        }

        // Dedup by order_id
        $exists = DB::fetch("SELECT id FROM trades WHERE order_id = ?", [$t['order_id']]);
        if ($exists) { $skipped++; continue; }

        // Insert trade
        $tradeId = DB::insert(
            "INSERT INTO trades (order_id, user_id, lots, trade_date, symbol)
             VALUES (?, ?, ?, ?, ?)",
            [
                $t['order_id'],
                $t['user_id'],
                v_positive_decimal($t['lots'], 'lots'),
                $t['trade_date'],
                $t['symbol'] ?? null
            ]
        );

        // Process credits via LedgerService
        LedgerService::processTrade((int)$tradeId);

        $inserted++;
    } catch (\Throwable $e) {
        $errors[] = "Row $i [{$t['order_id']}]: " . $e->getMessage();
    }
}

// Finalize job
DB::query(
    "UPDATE jobs SET status = ?, records_processed = ?, finished_at = NOW(), error_msg = ? WHERE id = ?",
    [empty($errors) ? 'done' : 'failed', $inserted, implode('; ', $errors) ?: null, $jobId]
);

// Audit
DB::query(
    "INSERT INTO audit_log (actor_id, action, meta) VALUES (?, 'trade_sync', ?)",
    [$admin['id'], json_encode(['job_id' => $jobId, 'inserted' => $inserted, 'skipped' => $skipped])]
);

json_ok(['job_id' => $jobId, 'inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors]);
