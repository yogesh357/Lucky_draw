<?php
// php/lib/LedgerService.php
// All financial movements go through this class.
// Every method is append-only and uses DB transactions.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

class LedgerService
{
    // ------------------------------------------------------------------
    // TRADE CREDIT  (called after a new trade is synced)
    // 1 lot → +1 spin credit (trade wallet) + +1 USD lambo (distributed)
    // ------------------------------------------------------------------
    public static function processTrade(int $tradeId): void
    {
        $trade = DB::fetch("SELECT * FROM trades WHERE id = ?", [$tradeId]);
        if (!$trade) throw new \RuntimeException("Trade $tradeId not found");

        $user  = DB::fetch("SELECT * FROM users WHERE id = ?", [$trade['user_id']]);
        $lots  = (float)$trade['lots'];
        $month = substr($trade['trade_date'], 0, 7); // '2026-02'

        DB::beginTransaction();
        try {
            // ---- 1. Spin Credit ----
            $spinPerLot = (float)DB::config('spin_per_lot_usd'); // default 1.00
            $spinCredits = $lots * $spinPerLot;

            self::_spinCredit($user['id'], $spinCredits, $tradeId, false);

            // Track batch for expiry
            DB::query(
                "INSERT INTO spin_credit_batches
                    (user_id, trade_id, credit_month, credits, status)
                 VALUES (?, ?, ?, ?, 'active')
                 ON DUPLICATE KEY UPDATE credits = credits",
                [$user['id'], $tradeId, $month, $spinCredits]
            );

            // ---- 2. Lambo Base Distribution ----
            $lamboBase = $lots * (float)DB::config('lambo_per_lot_usd'); // default 1.00
            self::_lamboBaseDistribution($user, $lamboBase, $tradeId);

            // ---- 3. Sure Win Points ----
            if (DB::config('sure_win_enabled') === '1') {
                $pts = $lots * (float)DB::config('points_per_lot'); // default 10
                self::_sureWinCredit($user['id'], $pts, $tradeId);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // LAMBO BASE DISTRIBUTION (Section 6 rules)
    // ------------------------------------------------------------------
    private static function _lamboBaseDistribution(array $user, float $amount, int $tradeId): void
    {
        $role = $user['role'];

        if ($role === 'client') {
            // Client 75%, IB 15%, MIB 10%
            self::_lamboCredit($user['id'],         $amount * 0.75, 'base_trade', $tradeId);
            self::_lamboCredit($user['parent_ib_id'],  $amount * 0.15, 'base_trade', $tradeId);
            self::_lamboCredit($user['parent_mib_id'], $amount * 0.10, 'base_trade', $tradeId);

        } elseif ($role === 'ib') {
            // IB 75%, MIB 15%, Company 10%
            self::_lamboCredit($user['id'],           $amount * 0.75, 'base_trade', $tradeId);
            self::_lamboCredit($user['parent_mib_id'], $amount * 0.15, 'base_trade', $tradeId);
            self::_lamboCompanyReserve($amount * 0.10, 'base_trade', $tradeId);

        } elseif ($role === 'mib') {
            // MIB 75%, Company 25%
            self::_lamboCredit($user['id'], $amount * 0.75, 'base_trade', $tradeId);
            self::_lamboCompanyReserve($amount * 0.25, 'base_trade', $tradeId);
        }
    }

    // ------------------------------------------------------------------
    // SPIN EXPIRY (Section 7 — Harvey Style)
    // Called by monthly expiry job for each user batch
    // ------------------------------------------------------------------
    public static function expireSpinBatch(int $batchId, int $jobId): void
    {
        $batch = DB::fetch("SELECT * FROM spin_credit_batches WHERE id = ?", [$batchId]);
        if (!$batch || $batch['status'] === 'expired') return;

        $remaining = (float)$batch['credits'] - (float)$batch['used'] - (float)$batch['expired'];
        if ($remaining <= 0) return;

        $user  = DB::fetch("SELECT * FROM users WHERE id = ?", [$batch['user_id']]);
        $trade = DB::fetch("SELECT * FROM trades WHERE id = ?", [$batch['trade_id']]);
        $role  = $user['role'];

        DB::beginTransaction();
        try {
            // Debit spin wallet
            self::_spinDebit($user['id'], $remaining, $batch['trade_id'], $jobId, 'expiry');

            // Mark batch expired
            DB::query(
                "UPDATE spin_credit_batches SET expired = ?, status = 'expired', expired_at = NOW() WHERE id = ?",
                [$remaining, $batchId]
            );

            // Reallocate 1.00 USD per expired credit to Lambo (Section 7)
            $expiredUsd = $remaining * 1.00;

            if ($role === 'client') {
                // 70% → IB, 30% → MIB, 0% → client
                self::_lamboCredit($user['parent_ib_id'],  $expiredUsd * 0.70, 'expiry_realloc', $trade['id']);
                self::_lamboCredit($user['parent_mib_id'], $expiredUsd * 0.30, 'expiry_realloc', $trade['id']);

            } elseif ($role === 'ib') {
                // 100% → MIB
                self::_lamboCredit($user['parent_mib_id'], $expiredUsd * 1.00, 'expiry_realloc', $trade['id']);

            } elseif ($role === 'mib') {
                // 100% → Company Reserve
                self::_lamboCompanyReserve($expiredUsd * 1.00, 'expiry_realloc', $trade['id']);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // SPIN (user executes x1 or x10)
    // ------------------------------------------------------------------
    public static function recordSpin(int $userId, string $spinType, bool $isFree, int $prizeId = 0): array
    {
        $prize = $prizeId ? DB::fetch("SELECT * FROM prizes WHERE id = ? AND is_active = 1", [$prizeId]) : null;

        // Credits consumed
        $creditsUsed = $spinType === 'x10'
            ? (float)DB::config('spin_x10_multiplier')
            : 1.0;

        // Check wallet
        $wallet = DB::fetch("SELECT * FROM spin_wallets WHERE user_id = ?", [$userId]);
        $walletField = $isFree ? 'free_balance' : 'balance';
        if (!$wallet || (float)$wallet[$walletField] < $creditsUsed) {
            throw new \RuntimeException('Insufficient spin credits');
        }

        // RNG seed for audit
        $rngSeed = bin2hex(random_bytes(16));

        DB::beginTransaction();
        try {
            // Debit wallet
            DB::query(
                "UPDATE spin_wallets SET $walletField = $walletField - ?, total_spent = total_spent + ? WHERE user_id = ?",
                [$creditsUsed, $creditsUsed, $userId]
            );

            // Insert spin event
            $spinId = DB::insert(
                "INSERT INTO spin_events (user_id, spin_type, is_free, credits_used, prize_id, prize_won, prize_value, rng_seed)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId, $spinType, $isFree ? 1 : 0, $creditsUsed,
                    $prize ? $prize['id'] : null,
                    $prize ? $prize['name'] : null,
                    $prize ? $prize['real_cost_usd'] : null,
                    $rngSeed
                ]
            );

            // Spin ledger entry
            DB::query(
                "INSERT INTO spin_ledger (user_id, type, amount, is_free, ref_spin_id)
                 VALUES (?, ?, ?, ?, ?)",
                [$userId, $isFree ? 'free_debit' : 'spin_debit', -$creditsUsed, $isFree ? 1 : 0, $spinId]
            );

            // If prize won, decrement stock
            if ($prize) {
                DB::query(
                    "UPDATE prizes SET stock_claimed = stock_claimed + 1 WHERE id = ?",
                    [$prize['id']]
                );
            }

            DB::commit();
            return ['spin_id' => $spinId, 'prize' => $prize, 'rng_seed' => $rngSeed];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // SURE WIN MILESTONE CLAIM (idempotent)
    // ------------------------------------------------------------------
    public static function claimMilestone(int $userId, int $milestoneId): array
    {
        $key = idempotency_key('surewin', (string)$userId, (string)$milestoneId);
        $exists = DB::fetch("SELECT id FROM sure_win_claims WHERE idempotency_key = ?", [$key]);
        if ($exists) throw new \RuntimeException('Milestone already claimed');

        $milestone = DB::fetch("SELECT * FROM sure_win_milestones WHERE id = ? AND is_active = 1", [$milestoneId]);
        if (!$milestone) throw new \RuntimeException('Milestone not found');

        $wallet = DB::fetch("SELECT * FROM sure_win_wallets WHERE user_id = ?", [$userId]);
        if (!$wallet || (float)$wallet['points'] < (float)$milestone['points_req']) {
            throw new \RuntimeException('Insufficient Sure Win points');
        }

        DB::beginTransaction();
        try {
            // Deduct points
            DB::query(
                "UPDATE sure_win_wallets SET points = points - ? WHERE user_id = ?",
                [$milestone['points_req'], $userId]
            );

            // Ledger
            DB::query(
                "INSERT INTO sure_win_ledger (user_id, type, amount, ref_milestone_id)
                 VALUES (?, 'milestone_debit', ?, ?)",
                [$userId, -$milestone['points_req'], $milestoneId]
            );

            // Claim record
            $claimId = DB::insert(
                "INSERT INTO sure_win_claims (user_id, milestone_id, idempotency_key) VALUES (?, ?, ?)",
                [$userId, $milestoneId, $key]
            );

            DB::commit();
            return ['claim_id' => $claimId, 'milestone' => $milestone];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // INTERNAL HELPERS
    // ------------------------------------------------------------------

    private static function _spinCredit(int $userId, float $amount, int $tradeId, bool $isFree): void
    {
        $type        = $isFree ? 'free_credit' : 'trade_credit';
        $walletField = $isFree ? 'free_balance' : 'balance';

        DB::query(
            "INSERT INTO spin_wallets (user_id, $walletField, total_earned)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE $walletField = $walletField + VALUES($walletField),
                                     total_earned  = total_earned  + VALUES(total_earned)",
            [$userId, $amount, $amount]
        );

        DB::query(
            "INSERT INTO spin_ledger (user_id, type, amount, is_free, ref_trade_id)
             VALUES (?, ?, ?, ?, ?)",
            [$userId, $type, $amount, $isFree ? 1 : 0, $tradeId]
        );
    }

    private static function _spinDebit(int $userId, float $amount, int $tradeId, int $jobId, string $type): void
    {
        DB::query(
            "UPDATE spin_wallets SET balance = balance - ?, total_expired = total_expired + ? WHERE user_id = ?",
            [$amount, $amount, $userId]
        );

        DB::query(
            "INSERT INTO spin_ledger (user_id, type, amount, ref_trade_id, ref_job_id)
             VALUES (?, ?, ?, ?, ?)",
            [$userId, $type, -$amount, $tradeId, $jobId]
        );
    }

    private static function _lamboCredit(?int $recipientId, float $amount, string $type, int $tradeId): void
    {
        if (!$recipientId || $amount <= 0) return;

        DB::query(
            "INSERT INTO lambo_wallets (user_id, balance, total_credited)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance),
                                     total_credited = total_credited + VALUES(total_credited)",
            [$recipientId, $amount, $amount]
        );

        DB::query(
            "INSERT INTO lambo_ledger (recipient_id, type, amount, source_trade_id)
             VALUES (?, ?, ?, ?)",
            [$recipientId, $type, $amount, $tradeId]
        );
    }

    private static function _lamboCompanyReserve(float $amount, string $type, int $tradeId): void
    {
        if ($amount <= 0) return;

        DB::query("UPDATE company_reserve SET balance = balance + ?, total_credited = total_credited + ? WHERE id = 1",
            [$amount, $amount]);

        DB::query(
            "INSERT INTO lambo_ledger (recipient_id, type, amount, source_trade_id)
             VALUES (NULL, ?, ?, ?)",
            [$type, $amount, $tradeId]
        );
    }

    private static function _sureWinCredit(int $userId, float $pts, int $tradeId): void
    {
        DB::query(
            "INSERT INTO sure_win_wallets (user_id, points, total_earned)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE points = points + VALUES(points),
                                     total_earned = total_earned + VALUES(total_earned)",
            [$userId, $pts, $pts]
        );

        DB::query(
            "INSERT INTO sure_win_ledger (user_id, type, amount, ref_trade_id)
             VALUES (?, 'trade_credit', ?, ?)",
            [$userId, $pts, $tradeId]
        );
    }
}
