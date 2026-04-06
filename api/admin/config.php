<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

$admin = requireRole('ADMIN');
unset($admin);

$keys = [
    'exposure_cap_per_lot',
    'spin_usd_per_lot',
    'lambo_usd_per_lot',
    'surewin_points_per_lot',
    'notifications_max_per_day',
    'expiry_warning_days',
    'lambo_target_amount',
    'campaign_status',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $data = [];
    foreach ($keys as $key) {
        $data[$key] = getConfigValue($key);
    }
    success(['data' => $data]);
}

requirePost();
$payload = requestData();

if (isset($payload['exposure_cap_per_lot']) && (float) $payload['exposure_cap_per_lot'] < CAP_PER_LOT) {
    errorResponse('Exposure cap cannot be reduced below 2.00 per lot', 422);
}

$effectiveCap = isset($payload['exposure_cap_per_lot']) ? (float) $payload['exposure_cap_per_lot'] : exposureCapPerLot();
$effectiveSpin = isset($payload['spin_usd_per_lot']) ? (float) $payload['spin_usd_per_lot'] : spinUsdPerLot();
$effectiveLambo = isset($payload['lambo_usd_per_lot']) ? (float) $payload['lambo_usd_per_lot'] : lamboUsdPerLot();
if (($effectiveSpin + $effectiveLambo) - $effectiveCap > 0.00001) {
    errorResponse('Spin allocation plus Lambo allocation cannot exceed the exposure cap', 422);
}

foreach ($keys as $key) {
    if (array_key_exists($key, $payload)) {
        setConfigValue($key, (string) $payload[$key]);
    }
}

success(['message' => 'Configuration saved']);
