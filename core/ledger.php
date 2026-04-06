<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

const CAP_PER_LOT = 2.00;
const SPIN_USD_PER_LOT = 1.00;
const LAMBO_USD_PER_LOT = 1.00;
const SUREWIN_POINTS_PER_LOT = 10;

function exposureCapPerLot(): float
{
    return (float) getConfigValue('exposure_cap_per_lot', (string) CAP_PER_LOT);
}

function spinUsdPerLot(): float
{
    return (float) getConfigValue('spin_usd_per_lot', (string) SPIN_USD_PER_LOT);
}

function lamboUsdPerLot(): float
{
    return (float) getConfigValue('lambo_usd_per_lot', (string) LAMBO_USD_PER_LOT);
}

function sureWinPointsPerLot(): int
{
    return (int) getConfigValue('surewin_points_per_lot', (string) SUREWIN_POINTS_PER_LOT);
}

function validateFinancialCap(): void
{
    $total = spinUsdPerLot() + lamboUsdPerLot();
    if ($total - exposureCapPerLot() > 0.00001) {
        throw new RuntimeException('Configured allocations exceed exposure cap');
    }
}

function spinCreditsForLots(float $lots): int
{
    return (int) floor($lots);
}

function getUserHierarchy(int $userId): array
{
    $user = findUserById($userId);
    if (!$user) {
        throw new RuntimeException('User not found');
    }

    $ib = !empty($user['parent_ib_id']) ? findUserById((int) $user['parent_ib_id']) : null;
    $mib = !empty($user['parent_mib_id']) ? findUserById((int) $user['parent_mib_id']) : null;

    return [$user, $ib, $mib];
}

function getSpinBalance(int $userId): int
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM spin_ledger WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);

    return (int) $stmt->fetchColumn();
}

function getSpinBalanceBefore(int $userId, string $cutoffDate): int
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM spin_ledger WHERE user_id = :user_id AND created_at < :cutoff_date');
    $stmt->execute([
        'user_id' => $userId,
        'cutoff_date' => $cutoffDate,
    ]);

    return max(0, (int) $stmt->fetchColumn());
}

function getLamboBalance(int $userId): float
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(amount_usd), 0) FROM lambo_ledger WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);

    return (float) $stmt->fetchColumn();
}

function getSureWinPoints(int $userId): int
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(points), 0) FROM surewin_ledger WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);

    return (int) $stmt->fetchColumn();
}

function getMonthlyLots(int $userId, string $monthStart): float
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(lots), 0) FROM trades WHERE user_id = :user_id AND trade_date >= :month_start');
    $stmt->execute([
        'user_id' => $userId,
        'month_start' => $monthStart,
    ]);

    return (float) $stmt->fetchColumn();
}

function getMonthlySureWinPoints(int $userId, string $monthStart): int
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(points), 0) FROM surewin_ledger WHERE user_id = :user_id AND created_at >= :month_start');
    $stmt->execute([
        'user_id' => $userId,
        'month_start' => $monthStart,
    ]);

    return (int) $stmt->fetchColumn();
}

function getSpinPoolUsd(): float
{
    $stmt = db()->query("SELECT COALESCE(SUM(usd_value), 0) FROM spin_ledger WHERE type = 'CREDIT'");
    return (float) $stmt->fetchColumn();
}

function getExpiredSpinUsd(): float
{
    $stmt = db()->query("SELECT ABS(COALESCE(SUM(usd_value), 0)) FROM spin_ledger WHERE type = 'EXPIRY'");
    return (float) $stmt->fetchColumn();
}

function getPaidRewardUsd(): float
{
    $stmt = db()->query('SELECT COALESCE(SUM(reward_value), 0) FROM spin_results');
    return (float) $stmt->fetchColumn();
}

function getExposureSnapshot(): array
{
    $spinPool = getSpinPoolUsd();
    $paidRewards = getPaidRewardUsd();
    $expiredUsd = getExpiredSpinUsd();
    $pending = max(0, $spinPool - $paidRewards - $expiredUsd);
    $ratio = $spinPool > 0 ? round(($pending / $spinPool) * 100, 2) : 0.0;

    return [
        'spin_pool' => round($spinPool, 2),
        'paid_rewards' => round($paidRewards, 2),
        'expired_reallocated' => round($expiredUsd, 2),
        'pending_liability' => round($pending, 2),
        'liability_ratio' => $ratio,
        'liability_status' => $ratio >= 85 ? 'CRITICAL' : ($ratio >= 70 ? 'WARNING' : 'SAFE'),
        'lambo_fund' => round((float) db()->query('SELECT COALESCE(SUM(amount_usd), 0) FROM lambo_ledger')->fetchColumn(), 2),
    ];
}

function findTradeByOrderId(string $orderId): ?array
{
    $stmt = db()->prepare('SELECT * FROM trades WHERE order_id = :order_id LIMIT 1');
    $stmt->execute(['order_id' => $orderId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function insertSpinLedger(
    int $userId,
    string $type,
    string $source,
    int $amount,
    float $usdValue,
    string $referenceId
): void {
    $stmt = db()->prepare(
        'INSERT INTO spin_ledger (user_id, type, source, amount, usd_value, reference_id, created_at)
         VALUES (:user_id, :type, :source, :amount, :usd_value, :reference_id, NOW())'
    );
    $stmt->execute([
        'user_id' => $userId,
        'type' => $type,
        'source' => $source,
        'amount' => $amount,
        'usd_value' => toDecimal($usdValue),
        'reference_id' => $referenceId,
    ]);
}

function insertLamboLedger(int $userId, string $type, float $amountUsd, string $source, string $referenceId): void
{
    if ($userId <= 0 || abs($amountUsd) < 0.00001) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO lambo_ledger (user_id, type, amount_usd, source, reference_id, created_at)
         VALUES (:user_id, :type, :amount_usd, :source, :reference_id, NOW())'
    );
    $stmt->execute([
        'user_id' => $userId,
        'type' => $type,
        'amount_usd' => toDecimal($amountUsd),
        'source' => $source,
        'reference_id' => $referenceId,
    ]);
}

function insertSureWinLedger(int $userId, int $points, string $type, string $referenceId): void
{
    if ($points === 0) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO surewin_ledger (user_id, points, type, reference_id, created_at)
         VALUES (:user_id, :points, :type, :reference_id, NOW())'
    );
    $stmt->execute([
        'user_id' => $userId,
        'points' => $points,
        'type' => $type,
        'reference_id' => $referenceId,
    ]);
}

function distributeLamboBase(int $userId, float $lots, string $referenceId): void
{
    [$client, $ib, $mib] = getUserHierarchy($userId);
    $amount = $lots * lamboUsdPerLot();

    if ($client['role'] === 'CLIENT') {
        insertLamboLedger((int) $client['id'], 'BASE', $amount * 0.75, 'TRADE', $referenceId);
        if ($ib) {
            insertLamboLedger((int) $ib['id'], 'BASE', $amount * 0.15, 'TRADE', $referenceId);
        }
        if ($mib) {
            insertLamboLedger((int) $mib['id'], 'BASE', $amount * 0.10, 'TRADE', $referenceId);
        }
        return;
    }

    if ($client['role'] === 'IB') {
        insertLamboLedger((int) $client['id'], 'BASE', $amount * 0.85, 'TRADE', $referenceId);
        if ($mib) {
            insertLamboLedger((int) $mib['id'], 'BASE', $amount * 0.15, 'TRADE', $referenceId);
        }
        return;
    }

    insertLamboLedger((int) $client['id'], 'BASE', $amount, 'TRADE', $referenceId);
}

function distributeExpiredLambo(int $userId, float $amount, string $referenceId): void
{
    [$client, $ib, $mib] = getUserHierarchy($userId);

    if ($client['role'] === 'CLIENT') {
        if ($ib) {
            insertLamboLedger((int) $ib['id'], 'EXPIRED_REALLOC', $amount * 0.70, 'EXPIRY', $referenceId);
        }
        if ($mib) {
            insertLamboLedger((int) $mib['id'], 'EXPIRED_REALLOC', $amount * 0.30, 'EXPIRY', $referenceId);
        }
        return;
    }

    if ($client['role'] === 'IB' && $mib) {
        insertLamboLedger((int) $mib['id'], 'EXPIRED_REALLOC', $amount, 'EXPIRY', $referenceId);
    }
}

function processTradeCredit(PDO $pdo, array $user, string $orderId, float $lots, string $tradeDate, string $sourceMethod = 'MANUAL'): array
{
    validateFinancialCap();

    $existingTrade = findTradeByOrderId($orderId);
    if ($existingTrade) {
        if ((int) $existingTrade['user_id'] !== (int) $user['id']) {
            addFraudFlag((int) $user['id'], sprintf('Duplicate order_id %s attempted across users', $orderId));
            addFraudFlag((int) $existingTrade['user_id'], sprintf('Duplicate order_id %s detected against another user', $orderId));
        }

        return [
            'ok' => true,
            'message' => 'Duplicate trade ignored',
            'duplicate' => true,
            'order_id' => $orderId,
        ];
    }

    $tradeStmt = $pdo->prepare(
        'INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
         VALUES (:order_id, :user_id, :lots, :trade_date, NOW())'
    );
    $tradeStmt->execute([
        'order_id' => $orderId,
        'user_id' => (int) $user['id'],
        'lots' => toDecimal($lots),
        'trade_date' => $tradeDate,
    ]);

    $spinCredits = spinCreditsForLots($lots);
    $spinUsd = $lots * spinUsdPerLot();
    $sureWinPoints = (int) round($lots * sureWinPointsPerLot());

    insertSpinLedger((int) $user['id'], 'CREDIT', 'TRADE', $spinCredits, $spinUsd, $orderId);
    distributeLamboBase((int) $user['id'], $lots, $orderId);
    insertSureWinLedger((int) $user['id'], $sureWinPoints, 'CREDIT', $orderId);

    $avgStmt = $pdo->prepare(
        'SELECT COALESCE(AVG(lots), 0)
         FROM trades
         WHERE user_id = :user_id AND trade_date >= DATE_SUB(:trade_date, INTERVAL 30 DAY) AND order_id <> :order_id'
    );
    $avgStmt->execute([
        'user_id' => (int) $user['id'],
        'trade_date' => $tradeDate,
        'order_id' => $orderId,
    ]);
    $avgLots = (float) $avgStmt->fetchColumn();

    if ($avgLots > 0 && $lots > ($avgLots * 5)) {
        addFraudFlag((int) $user['id'], sprintf('Lot spike detected for order %s: %.2f lots vs %.2f avg', $orderId, $lots, $avgLots));
    }

    return [
        'ok' => true,
        'message' => 'Trade processed successfully',
        'duplicate' => false,
        'order_id' => $orderId,
        'lots' => $lots,
        'spin_credits' => $spinCredits,
        'spin_usd' => round($spinUsd, 2),
        'surewin_points' => $sureWinPoints,
        'lambo_allocated' => round($lots * lamboUsdPerLot(), 2),
        'source_method' => $sourceMethod,
    ];
}

function addFraudFlag(int $userId, string $reason, string $status = 'OPEN'): void
{
    $stmt = db()->prepare(
        'INSERT INTO fraud_flags (user_id, reason, status, created_at)
         VALUES (:user_id, :reason, :status, NOW())'
    );
    $stmt->execute([
        'user_id' => $userId,
        'reason' => $reason,
        'status' => $status,
    ]);
}

function getWeightedReward(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT reward_key, reward_name, reward_type, reward_value, surewin_points, weight, stock_limit, stock_used
         FROM reward_catalog
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC'
    );
    $rewards = $stmt->fetchAll();
    if (!$rewards) {
        return [
            'reward_key' => 'NEAR_MISS',
            'reward_name' => 'Near miss',
            'reward_type' => 'NONE',
            'reward_value' => 0,
            'surewin_points' => 0,
            'weight' => 1,
            'stock_limit' => null,
            'stock_used' => 0,
        ];
    }

    $available = array_values(array_filter($rewards, static function (array $reward): bool {
        return $reward['stock_limit'] === null || (int) $reward['stock_used'] < (int) $reward['stock_limit'];
    }));

    if (!$available) {
        $available = $rewards;
    }

    $totalWeight = array_sum(array_map(static fn(array $reward): int => (int) $reward['weight'], $available));
    $roll = random_int(1, max(1, $totalWeight));
    $cursor = 0;

    foreach ($available as $reward) {
        $cursor += (int) $reward['weight'];
        if ($roll <= $cursor) {
            return $reward;
        }
    }

    return $available[array_key_last($available)];
}

function processSpinReward(PDO $pdo, int $userId, array $reward, string $referenceId): void
{
    $snapshot = getExposureSnapshot();
    $rewardValue = (float) $reward['reward_value'];
    if ($rewardValue > 0 && $rewardValue > $snapshot['pending_liability']) {
        throw new RuntimeException('Reward would exceed available spin liability');
    }

    if ((string) $reward['reward_type'] === 'SUREWIN_POINTS') {
        insertSureWinLedger($userId, (int) $reward['surewin_points'], 'CREDIT', $referenceId);
    }

    if ($reward['stock_limit'] !== null) {
        $stmt = $pdo->prepare('UPDATE reward_catalog SET stock_used = stock_used + 1 WHERE reward_key = :reward_key');
        $stmt->execute(['reward_key' => $reward['reward_key']]);
    }
}

function seedHashForSpin(int $userId, int $spins): string
{
    return hash('sha256', $userId . '|' . $spins . '|' . microtime(true) . '|' . bin2hex(random_bytes(16)));
}

function recentSpinResults(int $userId, int $limit = 10): array
{
    $stmt = db()->prepare(
        'SELECT id, spins_used, reward_type, reward_value, surewin_points, created_at
         FROM spin_results
         WHERE user_id = :user_id
         ORDER BY id DESC
         LIMIT :limit_value'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}
