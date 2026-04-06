<?php
// php/api/spin.php
// POST /api/spin
// Body: { "spin_type": "x1"|"x10", "is_free": false }
// Returns: prize won (or null) + updated wallet balance

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/LedgerService.php';

$user = require_auth();
$body = require_json_post();

$spinType = in_array($body['spin_type'] ?? '', ['x1', 'x10']) ? $body['spin_type'] : 'x1';
$isFree   = !empty($body['is_free']);

// --- Prize selection via weighted RNG ---
$policy   = $isFree ? 1 : 0;
$prizes   = DB::fetchAll(
    "SELECT * FROM prizes
     WHERE is_active = 1 AND is_free_spin = ?
       AND (stock_qty IS NULL OR stock_claimed < stock_qty)
     ORDER BY probability DESC",
    [$policy]
);

// Weighted random draw
$selectedPrize = null;
$roll = (float)(random_int(0, PHP_INT_MAX) / PHP_INT_MAX); // 0..1
$cumulative = 0.0;
foreach ($prizes as $p) {
    $cumulative += (float)$p['probability'];
    if ($roll <= $cumulative) {
        $selectedPrize = $p;
        break;
    }
}

try {
    $result = LedgerService::recordSpin(
        $user['id'],
        $spinType,
        $isFree,
        $selectedPrize ? (int)$selectedPrize['id'] : 0
    );

    // Fetch updated wallet
    $wallet = DB::fetch("SELECT balance, free_balance FROM spin_wallets WHERE user_id = ?", [$user['id']]);

    json_ok([
        'spin_id'      => $result['spin_id'],
        'prize'        => $result['prize']
            ? ['name' => $result['prize']['name'], 'value' => $result['prize']['real_cost_usd']]
            : null,
        'wallet'       => $wallet,
        'rng_seed'     => $result['rng_seed'], // expose for transparency/audit
    ]);
} catch (\Throwable $e) {
    json_err($e->getMessage(), 422);
}
