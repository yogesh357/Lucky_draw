# Trading Incentive Ecosystem — Backend Architecture

> Implements the Final Agreed Rules: $2 USD hard cap per lot, ledger-first design, idempotent monthly expiry.

---

## Directory Structure

```
trading_incentive/
├── sql/
│   └── 001_schema.sql          ← Full MySQL schema (run once)
├── php/
│   ├── lib/
│   │   ├── db.php              ← PDO singleton
│   │   ├── helpers.php         ← Auth, JSON response, validation
│   │   └── LedgerService.php   ← ALL financial movements (append-only)
│   ├── api/
│   │   ├── auth.php            ← POST login / logout
│   │   ├── dashboard.php       ← GET all user data
│   │   ├── spin.php            ← POST execute spin
│   │   ├── sync_trades.php     ← POST broker trade import (admin)
│   │   └── sure_win_claim.php  ← POST claim milestone
│   ├── admin/
│   │   ├── exposure.php        ← GET/POST exposure monitoring + config
│   │   └── admin_actions.php   ← audit log, jobs, winners, expiry
│   └── jobs/
│       └── monthly_expiry.php  ← CLI/cron idempotent expiry job
└── ajax/
    └── trading-incentive-ajax.js  ← Drop into Lucky_draw.html
```

---

## Database Tables

| Table | Purpose |
|---|---|
| `users` | User accounts with role (client/ib/mib/admin) and hierarchy |
| `trades` | Immutable broker trade records, deduped by `order_id` |
| `spin_wallets` | Running spin balance per user (trade + free separate) |
| `spin_ledger` | **Append-only** every spin credit/debit/expiry |
| `spin_credit_batches` | Per-trade credit groups for month-based expiry tracking |
| `lambo_wallets` | Lambo fund balance per user |
| `lambo_ledger` | **Append-only** every lambo allocation/payout |
| `company_reserve` | Company reserve bucket |
| `spin_events` | Every spin attempt with RNG seed for audit |
| `prizes` | Prize pool with 2x pricing, stock control |
| `sure_win_wallets` | Sure Win points per user |
| `sure_win_ledger` | Points history |
| `sure_win_milestones` | Milestone config |
| `sure_win_claims` | Idempotent claims |
| `jobs` | Job tracker (sync, expiry, winner selection) |
| `exposure_snapshots` | Admin monitoring snapshots |
| `audit_log` | Full admin action trail |
| `config` | Runtime-editable system config |

---

## Financial Rules Implemented

### Per Lot (Section 2)
```
+1 Spin Credit  → spin_wallets.balance
+1 USD Lambo    → distributed by role hierarchy
```

### Lambo Distribution (Section 6)
| Trade role | Client | IB | MIB | Company |
|---|---|---|---|---|
| Client trade | 75% | 15% | 10% | — |
| IB trade | — | 75% | 15% | 10% |
| MIB trade | — | — | 75% | 25% |

### Expiry Reallocation (Section 7 — Harvey Style)
| Expired from | IB gets | MIB gets | Company gets |
|---|---|---|---|
| Client trade | 70% | 30% | — |
| IB trade | — | 100% | — |
| MIB trade | — | — | 100% |

### Prize Pricing (Section 4.2)
```
Spin credits required = real_cost_USD × 2
```

---

## Setup

### 1. Database
```sql
CREATE DATABASE trading_incentive CHARACTER SET utf8mb4;
CREATE USER 'ti_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
GRANT ALL ON trading_incentive.* TO 'ti_user'@'localhost';
```
```bash
mysql -u ti_user -p trading_incentive < sql/001_schema.sql
```

### 2. Environment
```bash
cp .env.example .env
# Edit .env with your DB credentials
```

Load .env in PHP entry point:
```php
$lines = file('.env');
foreach ($lines as $line) {
    if (strpos($line, '=') !== false && $line[0] !== '#') {
        [$k, $v] = explode('=', trim($line), 2);
        $_ENV[$k] = $v;
    }
}
```

### 3. Cron (monthly expiry)
```cron
# Run on Day 1 of each month at 00:05 UTC
5 0 1 * * php /var/www/trading_incentive/php/jobs/monthly_expiry.php >> /var/log/ti_expiry.log 2>&1
```

### 4. Add AJAX to Frontend
Add before `</body>` in `Lucky_draw.html`:
```html
<script src="/ajax/trading-incentive-ajax.js"></script>
```
The script auto-inits and hooks into the existing prototype buttons.

---

## API Reference

| Endpoint | Method | Auth | Description |
|---|---|---|---|
| `/api/auth.php?action=login` | POST | — | Login, sets session |
| `/api/auth.php?action=logout` | GET | User | Logout |
| `/api/dashboard.php` | GET | User | All wallet + leaderboard data |
| `/api/spin.php` | POST | User | Execute spin |
| `/api/sure_win_claim.php` | POST | User | Claim milestone |
| `/api/sync_trades.php` | POST | Admin | Import broker trades |
| `/admin/exposure.php` | GET | Admin | Exposure snapshot |
| `/admin/exposure.php` | POST | Admin | Update config |
| `/admin/admin_actions.php?action=audit_log` | GET | Admin | Audit trail |
| `/admin/admin_actions.php?action=run_expiry` | POST | Admin | Trigger expiry |
| `/admin/admin_actions.php?action=pick_winners` | POST | Admin | Pick lucky draw winners |
| `/admin/admin_actions.php?action=fulfill_claim` | POST | Admin | Fulfill Sure Win claim |

---

## Key Design Decisions

- **Ledger-first**: Balances are always derived from append-only ledger tables. Wallets are cached sums.
- **Idempotent jobs**: All batch jobs use `idempotency_key` to prevent double processing.
- **Trade deduplication**: `order_id` unique constraint prevents double crediting.
- **2 USD hard cap**: Enforced mathematically: spin_per_lot (1.00) + lambo_per_lot (1.00) = 2.00.
- **Separate wallets**: Free spin credits stored separately from trade-based credits.
- **Crypto-secure RNG**: `random_bytes()` for prize draws and winner selection, seed logged for audit.
- **Decimal precision**: All money stored as `DECIMAL(18,6)` to avoid float drift.
