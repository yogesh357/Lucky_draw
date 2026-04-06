# Lucky Draw Application Guide

## 1. Introduction

Lucky Draw is a trading-based rewards application. It turns user trading activity into a structured reward journey that combines gameplay, milestone rewards, and hierarchy-based incentive sharing.

The application is built around three connected ideas:

- trading generates reward value,
- users can use that value inside a spin-based experience,
- the business can control and audit the full reward economy.

This document is intentionally written as an application guide rather than a file-by-file code inventory so it can be converted cleanly into PDF.

## 2. What The Application Is

Lucky Draw is not just a random prize spinner. It is a reward system built on top of trading volume.

When a user trades, the system can reward that activity in three ways:

- by giving spin credits,
- by adding value into the Lambo reward structure,
- by adding Sure Win points for milestone rewards.

This means the product mixes:

- engagement,
- incentive design,
- financial tracking,
- hierarchical reward sharing.

At the user level, it feels like a game-driven rewards dashboard. At the system level, it is a controlled incentive engine.

## 3. Main Purpose Of The Application

The main purpose of Lucky Draw is to make trading activity visible, rewarding, and repeatable.

Instead of offering only a simple rebate or passive bonus, the system creates a loop:

1. User trades.
2. User earns credits and points.
3. User sees progress on the dashboard.
4. User uses spins for a chance to win.
5. User progresses toward guaranteed rewards.
6. User returns as new trading activity is synced.

This makes the application motivational as well as transactional.

## 4. Core Reward Systems

The application uses three reward layers.

### 4.1 Spin Credits

Spin credits are the direct play currency of the application.

When a user trades, they earn spin credits. Those credits can be spent on:

- single spins,
- or multi-spins such as `x10`.

There are also free promotional spins, which are kept separate from normal earned spins. This separation is important because promotional credit should not behave exactly like trade-earned credit.

### 4.2 Lambo Reward Structure

The Lambo structure is the hierarchy-based reward component.

Whenever a trade is processed, value is distributed through the user hierarchy depending on who made the trade. This allows the platform to reward both direct activity and network relationships.

This is less about instant gameplay and more about structured accumulation.

### 4.3 Sure Win Milestones

Sure Win is the guaranteed reward layer.

Instead of relying only on luck, the user also accumulates points. When enough points are collected, the user can claim a milestone reward.

This makes the reward system more balanced because it contains both:

- chance-based rewards,
- certainty-based rewards.

## 5. User Roles

The application is built around a hierarchy of roles.

### 5.1 Client

The client is the main player in the system.

A client can:

- trade,
- receive spin credits,
- receive Sure Win points,
- play spins,
- appear in rankings,
- contribute to the Lambo reward system.

### 5.2 IB

IB stands for Introducing Broker.

This role participates in the hierarchy and receives value from linked client activity as defined by the reward rules.

### 5.3 MIB

MIB is a higher-level network role above IB.

This role receives value further up the chain and becomes important in both normal distribution and expired credit reallocation.

### 5.4 Admin

The admin role operates the system.

An admin can:

- sync trades,
- monitor exposure,
- update reward configuration,
- run monthly expiry,
- manage claims,
- calculate winners.

This matters because the application is not only a user-facing game. It is also an admin-managed reward economy.

## 6. How A User Plays

From the user perspective, the journey is simple.

### Step 1: Login

The user enters the application and logs in.

### Step 2: Trade

The user performs trading activity. The trade data is then brought into the system.

### Step 3: Receive Rewards

After trade sync, the user’s dashboard can show:

- new spin credits,
- free spins if available,
- Sure Win points,
- monthly lot count,
- Lambo progress,
- recent reward activity.

### Step 4: Use Spins

The user can spend earned credits on available spin actions.

### Step 5: Try To Win

Each spin checks whether the user has won a prize based on the configured prize probabilities.

### Step 6: Track Progress

Even if the user does not win a spin prize immediately, they continue building toward Sure Win milestones.

### Step 7: Claim Milestones

When the user reaches the required points threshold, they can claim milestone rewards.

## 7. The Core Rules Of The Application

The application works because a fixed set of reward rules is enforced in code.

### 7.1 Trade Creates Multiple Rewards

One trade can feed multiple systems at once.

The core idea is visible in the processing logic:

```php
$spinCredits = $lots * $spinPerLot;
$lamboBase   = $lots * (float)DB::config('lambo_per_lot_usd');
$pts         = $lots * (float)DB::config('points_per_lot');
```

This is one of the most important pieces of logic in the whole application. It shows that the system is not using trading activity for just one reward. It creates:

- gameplay credit,
- long-term reward allocation,
- milestone progression.

### 7.2 Spin Usage Rules

The user can perform at least two spin types:

- `x1`
- `x10`

The system treats `x1` as one credit and `x10` as a configured multi-credit action. That gives flexibility to the reward economy because the business can tune how costly a larger spin should be.

### 7.3 Free Spins Stay Separate

Promotional spins are stored separately from trade-earned spins.

This is a strong design choice because it keeps marketing rewards from directly mixing with core economic balances.

### 7.4 Sure Win Claim Rules

A milestone can only be claimed when:

- the milestone is active,
- the user has enough points,
- the same user has not already claimed that milestone in a duplicate way.

### 7.5 Monthly Expiry Rules

Trade-based spin credits can expire monthly.

This helps the platform control long-term liability and keeps the system active rather than allowing users to hold credits forever.

## 8. Lambo Distribution Rules

The Lambo reward structure depends on user role.

### If the trade belongs to a Client

- 75% goes to the client,
- 15% goes to the parent IB,
- 10% goes to the parent MIB.

### If the trade belongs to an IB

- 75% goes to the IB,
- 15% goes to the parent MIB,
- 10% goes to company reserve.

### If the trade belongs to an MIB

- 75% goes to the MIB,
- 25% goes to company reserve.

This makes the system hierarchy-aware and suitable for a brokerage-style network structure.

## 9. Expiry Reallocation Rules

The application does not simply delete expired value. It reallocates it.

### If expired credits came from a Client

- 70% goes to IB,
- 30% goes to MIB.

### If expired credits came from an IB

- 100% goes to MIB.

### If expired credits came from an MIB

- 100% goes to company reserve.

This is a very important business rule because it shows that expiry is not only a reduction in user balance. It is also a redistribution event inside the reward system.

## 10. How Prize Winning Works

Prize selection is based on weighted probability.

That means the prizes do not all have equal chance. Each prize has a probability value, and that value affects how likely it is to be selected during a spin.

The essential logic is:

```php
$selectedPrize = null;
$roll = (float)(random_int(0, PHP_INT_MAX) / PHP_INT_MAX);
$cumulative = 0.0;

foreach ($prizes as $p) {
    $cumulative += (float)$p['probability'];
    if ($roll <= $cumulative) {
        $selectedPrize = $p;
        break;
    }
}
```

### What This Means

- the system loads active prizes,
- removes prizes that are not available,
- generates a random value,
- walks through probabilities cumulatively,
- selects the prize whose range contains the random roll.

This model is important because it keeps the spin system controlled. Prize chances are not hardcoded only by type; they are weighted by configuration.

## 11. How Monthly Winners Are Calculated

The monthly winner calculation is one of the most important mechanics in the application.

It is not a pure equal-luck draw. It is a weighted draw based on contribution.

### Basic Principle

The system gathers eligible client contribution totals for a month and uses those totals as weights.

This means:

- larger contributors have a higher chance,
- selection is still random,
- duplicate winners are prevented within the same draw.

The core selection logic is:

```php
$totalContrib = array_sum(array_column($pool, 'total_contribution'));

while (count($winners) < $numWinners && $attempts < 10000) {
    $roll = (float)(random_int(0, PHP_INT_MAX) / PHP_INT_MAX) * $totalContrib;
    $cumul = 0.0;

    foreach ($pool as $p) {
        $cumul += (float)$p['total_contribution'];
        if ($roll <= $cumul && !in_array($p['recipient_id'], $usedIds)) {
            $winners[] = $p;
            $usedIds[] = $p['recipient_id'];
            break;
        }
    }

    $attempts++;
}
```

### Example

If the contribution pool is:

- User A: 100
- User B: 300
- User C: 600

Then the total is 1000, and their weighted chance is:

- User A: 10%
- User B: 30%
- User C: 60%

So User C has the strongest chance, but the outcome remains random rather than guaranteed.

### Why This Matters

This method creates a middle ground between fairness and excitement:

- it rewards stronger participation,
- it still feels like a draw,
- it avoids repeated winners in one run,
- it can be audited later because the selection process records random seed information.

## 12. Why The System Is Trustworthy Internally

There are a few internal design ideas that matter more than file names.

### 12.1 Ledger-First Reward Accounting

The system does not rely only on current balances. It also keeps historical financial movement records.

That is important because reward systems need traceability. If a user asks:

- where credits came from,
- why balance changed,
- whether a claim already happened,

the system should be able to explain it.

### 12.2 Transactions For Critical Actions

Major actions such as trade processing, spin deductions, and claim handling are grouped inside database transactions.

That reduces the risk of partial failures causing broken reward balances.

### 12.3 Duplicate Protection

Important operations are protected against being processed twice.

This matters especially for:

- trade imports,
- milestone claims,
- monthly expiry runs.

Without this protection, the system could over-credit or over-process rewards.

## 13. What The Dashboard Represents

The dashboard is the user’s main understanding layer.

It translates backend reward logic into visible status:

- how much the user has traded,
- how many spins are available,
- how many credits may expire soon,
- how many Sure Win points have been earned,
- what reward is next,
- how much Lambo progress exists,
- how the user compares with others.

This is important because the real backend logic is complex, but the dashboard makes it understandable and motivating.

## 14. Strengths Of The Application

The application has several strong design qualities.

### 14.1 It combines luck and certainty

Users are motivated by:

- random reward excitement through spins,
- guaranteed progress through milestones.

### 14.2 It supports hierarchy-based incentives

The Lambo structure allows the system to support network reward logic instead of only direct-user rewards.

### 14.3 It is built for operational control

The admin side is designed to monitor the reward economy rather than letting it run blindly.

### 14.4 It is built to be auditable

Weighted prize selection, tracked jobs, reward history, and recorded seeds all point toward a system that tries to remain explainable.

## 15. Current Limitations

Based on the current repository state, a few practical limitations are visible:

- the referenced schema file is missing,
- some naming appears to come from different iterations of the project,
- parts of the frontend still behave like a prototype,
- some integration details look partially transitional.

These do not change the main application idea, but they do affect maintainability and production readiness.

## 16. Final Summary

Lucky Draw is a trading reward application that transforms trading volume into a layered engagement system.

It gives users:

- spin-based gameplay,
- milestone-based guaranteed rewards,
- visible progress and competition.

It gives the business:

- role-based reward distribution,
- financial control,
- expiry handling,
- auditability,
- weighted winner calculation.

The most important concepts in the application are:

- one trade can feed multiple reward paths,
- spins are controlled by weighted probabilities,
- monthly winners are chosen through contribution-weighted randomness,
- expired value is redistributed through role-based rules,
- the whole reward economy is designed to be tracked rather than guessed.

In short, Lucky Draw is a reward ecosystem built on top of trading activity. The user sees a game-driven interface, but underneath it is a structured and rule-based incentive engine.
