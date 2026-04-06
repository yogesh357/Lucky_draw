<?php
// php/admin/exposure.php
// GET  /admin/exposure        — exposure snapshot + alert
// POST /admin/config          — update a config key

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';

$admin = require_admin();
$method = $_SERVER['REQUEST_METHOD'];

// ---- UPDATE CONFIG ----
if ($method === 'POST') {
    $body = require_json_post();
    v_required($body, ['key', 'value']);

    $allowed = [
        'spin_per_lot_usd','lambo_per_lot_usd','points_per_lot',
        'expiry_day','liability_alert_pct','spin_x10_multiplier',
        'sure_win_enabled','free_spin_prize_policy','max_notifications_per_day'
    ];

    if (!in_array($body['key'], $allowed)) json_err('Config key not allowed');

    DB::query("UPDATE config SET value = ? WHERE `key` = ?", [$body['value'], $body['key']]);
    DB::query("INSERT INTO audit_log (actor_id, action, meta) VALUES (?, 'config_update', ?)",
        [$admin['id'], json_encode(['key' => $body['key'], 'value' => $body['value']])]);

    json_ok(['key' => $body['key'], 'value' => $body['value']]);
}

// ---- GET EXPOSURE ----
// Spin pool
$spinStats = DB::fetch(
    "SELECT
        SUM(CASE WHEN type='trade_credit' THEN amount ELSE 0 END) AS allocated,
        ABS(SUM(CASE WHEN type='spin_debit' THEN amount ELSE 0 END)) AS paid,
        SUM(CASE WHEN type='trade_credit' THEN amount ELSE 0 END)
            - ABS(SUM(CASE WHEN type='spin_debit' THEN amount ELSE 0 END))
            - ABS(SUM(CASE WHEN type='expiry' THEN amount ELSE 0 END)) AS pending_liability
     FROM spin_ledger WHERE is_free = 0"
);

// Lambo fund
$lamboStats = DB::fetch(
    "SELECT
        SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) AS total_fund,
        ABS(SUM(CASE WHEN type='payout' THEN amount ELSE 0 END)) AS paid_out
     FROM lambo_ledger"
);

// Company reserve
$reserve = DB::fetch("SELECT balance FROM company_reserve WHERE id = 1");

// Total lots
$lotsRow = DB::fetch("SELECT COALESCE(SUM(lots), 0) AS total FROM trades");
$totalLots = (float)$lotsRow['total'];

// Liability ratio
$allocated = (float)($spinStats['allocated'] ?? 0);
$pending   = (float)($spinStats['pending_liability'] ?? 0);
$ratio = $allocated > 0 ? round(($pending / $allocated) * 100, 2) : 0;
$alertPct = (float)(DB::config('liability_alert_pct') ?? 70);
$alert = $ratio >= $alertPct;

// Snapshot
DB::query(
    "INSERT INTO exposure_snapshots
        (total_lots, spin_pool_allocated, spin_rewards_paid, spin_pending_liability,
         lambo_total_fund, lambo_paid_out, company_reserve_bal, liability_ratio, alert_triggered)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [
        $totalLots,
        $spinStats['allocated'],
        $spinStats['paid'],
        $pending,
        $lamboStats['total_fund'],
        $lamboStats['paid_out'],
        $reserve['balance'],
        $ratio,
        $alert ? 1 : 0,
    ]
);

// Config
$config = DB::fetchAll("SELECT `key`, value, note FROM config ORDER BY `key`");
$configMap = array_column($config, 'value', 'key');

json_ok([
    'total_lots'     => $totalLots,
    'spin'           => $spinStats,
    'lambo'          => $lamboStats,
    'company_reserve'=> $reserve['balance'],
    'liability_ratio'=> $ratio,
    'alert'          => $alert,
    'alert_threshold'=> $alertPct,
    'config'         => $configMap,
]);
