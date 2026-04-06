<?php
// php/api/dashboard.php
// GET /api/dashboard
// Returns wallet balances, recent spins, leaderboard, milestones for current user

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';

$user = require_auth();
$uid  = $user['id'];

// Spin wallet
$spinWallet = DB::fetch("SELECT * FROM spin_wallets WHERE user_id = ?", [$uid])
    ?? ['balance' => 0, 'free_balance' => 0, 'total_earned' => 0, 'total_spent' => 0];

// Lambo wallet
$lamboWallet = DB::fetch("SELECT * FROM lambo_wallets WHERE user_id = ?", [$uid])
    ?? ['balance' => 0, 'total_credited' => 0];

// Sure Win wallet + milestones
$swWallet = DB::fetch("SELECT * FROM sure_win_wallets WHERE user_id = ?", [$uid])
    ?? ['points' => 0];

$milestones = DB::fetchAll(
    "SELECT m.*, c.status AS claim_status
     FROM sure_win_milestones m
     LEFT JOIN sure_win_claims c ON c.milestone_id = m.id AND c.user_id = ?
     WHERE m.is_active = 1
     ORDER BY m.sort_order ASC",
    [$uid]
);

// Recent spin events
$recentSpins = DB::fetchAll(
    "SELECT se.id, se.spin_type, se.is_free, se.credits_used, se.prize_won, se.prize_value, se.created_at
     FROM spin_events se
     WHERE se.user_id = ?
     ORDER BY se.created_at DESC LIMIT 10",
    [$uid]
);

// Monthly lots traded
$monthlyLots = DB::fetch(
    "SELECT COALESCE(SUM(lots), 0) AS total
     FROM trades
     WHERE user_id = ? AND trade_date >= DATE_FORMAT(NOW(), '%Y-%m-01')",
    [$uid]
);

// Current user info
$userInfo = DB::fetch(
    "SELECT id,
            COALESCE(NULLIF(name, ''), email) AS username,
            name,
            email,
            role,
            tier
     FROM users
     WHERE id = ?",
    [$uid]
);

// Leaderboard (top 10 by lots this month)
$leaderboard = DB::fetchAll(
    "SELECT COALESCE(NULLIF(u.name, ''), u.email) AS username,
            u.tier,
            COALESCE(SUM(t.lots),0) AS total_lots
     FROM users u
     LEFT JOIN trades t ON t.user_id = u.id AND t.trade_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
     GROUP BY u.id
     ORDER BY total_lots DESC LIMIT 10"
);

// Top IB/MIB by network lots
$ibLeaderboard = DB::fetchAll(
    "SELECT COALESCE(NULLIF(u.name, ''), u.email) AS username,
            u.role,
            COALESCE(SUM(t.lots),0) AS network_lots
     FROM users u
     JOIN trades t ON t.user_id IN (
        SELECT id FROM users WHERE parent_ib_id = u.id OR parent_mib_id = u.id
     )
     WHERE u.role IN ('ib','mib')
       AND t.trade_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
     GROUP BY u.id
     ORDER BY network_lots DESC LIMIT 10"
);

json_ok([
    'user'           => $userInfo,
    'spin_wallet'    => $spinWallet,
    'lambo_wallet'   => $lamboWallet,
    'sure_win'       => ['wallet' => $swWallet, 'milestones' => $milestones],
    'recent_spins'   => $recentSpins,
    'monthly_lots'   => (float)($monthlyLots['total'] ?? 0),
    'leaderboard'    => $leaderboard,
    'ib_leaderboard' => $ibLeaderboard,
]);
