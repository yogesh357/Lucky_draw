<?php
// php/jobs/monthly_expiry.php
// Runs via cron on Day 1 of each month (or triggered manually by admin)
// Idempotent: safe to run multiple times; uses job table to prevent double processing

// Usage: php monthly_expiry.php [YYYY-MM]
// Example: php monthly_expiry.php 2026-02

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/LedgerService.php';

// Allow CLI or API call
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../lib/helpers.php';
    require_admin();
}

$targetMonth = $argv[1] ?? ($_GET['month'] ?? date('Y-m', strtotime('last month')));

if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
    die("Invalid month format. Use YYYY-MM\n");
}

// Idempotency check
$jobKey = idempotency_key('monthly_expiry', $targetMonth);
$existing = DB::fetch("SELECT id, status FROM jobs WHERE idempotency_key = ?", [$jobKey]);

if ($existing && $existing['status'] === 'done') {
    $msg = "Expiry for $targetMonth already completed (job #{$existing['id']}). Skipping.";
    echo $msg . "\n";
    if (PHP_SAPI !== 'cli') json_ok(['message' => $msg, 'job_id' => $existing['id']]);
    exit;
}

// Create or retrieve job
if ($existing) {
    $jobId = $existing['id'];
    DB::query("UPDATE jobs SET status='running', started_at=NOW() WHERE id=?", [$jobId]);
} else {
    $jobId = DB::insert(
        "INSERT INTO jobs (type, status, ref_month, idempotency_key, started_at)
         VALUES ('monthly_expiry', 'running', ?, ?, NOW())",
        [$targetMonth, $jobKey]
    );
}

echo "Starting monthly expiry for $targetMonth (job #$jobId)\n";

// Find all active batches for target month
$batches = DB::fetchAll(
    "SELECT id FROM spin_credit_batches
     WHERE credit_month = ? AND status IN ('active','partially_used')
     AND (credits - used - expired) > 0",
    [$targetMonth]
);

$processed = 0;
$errors    = [];

foreach ($batches as $batch) {
    try {
        LedgerService::expireSpinBatch((int)$batch['id'], (int)$jobId);
        $processed++;
        echo "  ✓ Batch #{$batch['id']} expired\n";
    } catch (\Throwable $e) {
        $errors[] = "Batch #{$batch['id']}: " . $e->getMessage();
        echo "  ✗ Batch #{$batch['id']}: " . $e->getMessage() . "\n";
    }
}

$status = empty($errors) ? 'done' : 'failed';
DB::query(
    "UPDATE jobs SET status=?, records_processed=?, finished_at=NOW(), error_msg=? WHERE id=?",
    [$status, $processed, implode('; ', $errors) ?: null, $jobId]
);

echo "Expiry complete. Processed: $processed, Errors: " . count($errors) . "\n";
if (PHP_SAPI !== 'cli') {
    json_ok(['job_id' => $jobId, 'month' => $targetMonth, 'processed' => $processed, 'errors' => $errors]);
}
