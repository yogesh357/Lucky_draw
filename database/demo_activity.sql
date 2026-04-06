SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM milestone_claims
WHERE user_id IN (SELECT id FROM users WHERE email IN (
  'client@demo.com','traderx@demo.com','neofx@demo.com','alphapips@demo.com',
  'ib@demo.com','ibflow@demo.com','orbitclient@demo.com','mib@demo.com'
));

DELETE FROM fraud_flags WHERE reason LIKE 'Demo %';
DELETE FROM sync_logs WHERE summary LIKE 'Demo %';
DELETE FROM spin_results WHERE seed_hash LIKE 'demo-%';
DELETE FROM surewin_ledger WHERE reference_id LIKE 'DEMO-%';
DELETE FROM lambo_ledger WHERE reference_id LIKE 'DEMO-%';
DELETE FROM spin_ledger WHERE reference_id LIKE 'DEMO-%';
DELETE FROM trades WHERE order_id LIKE 'DEMO-%';

DELETE FROM users WHERE email IN (
  'traderx@demo.com','neofx@demo.com','alphapips@demo.com','ibflow@demo.com','orbitclient@demo.com'
);

UPDATE users SET status = 'ACTIVE' WHERE email IN ('admin@demo.com','client@demo.com','ib@demo.com','mib@demo.com');

INSERT INTO users (email, password_hash, role, parent_ib_id, parent_mib_id, status)
SELECT 'traderx@demo.com', '$2y$10$.bqQYBkygPUrAgJZowAiMuRNfNMsHvdXysyucOITWMksQZDDF4bXC', 'CLIENT',
       (SELECT id FROM users WHERE email = 'ib@demo.com'),
       (SELECT id FROM users WHERE email = 'mib@demo.com'),
       'ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'traderx@demo.com');

INSERT INTO users (email, password_hash, role, parent_ib_id, parent_mib_id, status)
SELECT 'neofx@demo.com', '$2y$10$.bqQYBkygPUrAgJZowAiMuRNfNMsHvdXysyucOITWMksQZDDF4bXC', 'CLIENT',
       (SELECT id FROM users WHERE email = 'ib@demo.com'),
       (SELECT id FROM users WHERE email = 'mib@demo.com'),
       'ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'neofx@demo.com');

INSERT INTO users (email, password_hash, role, parent_ib_id, parent_mib_id, status)
SELECT 'alphapips@demo.com', '$2y$10$.bqQYBkygPUrAgJZowAiMuRNfNMsHvdXysyucOITWMksQZDDF4bXC', 'CLIENT',
       (SELECT id FROM users WHERE email = 'ib@demo.com'),
       (SELECT id FROM users WHERE email = 'mib@demo.com'),
       'EXCLUDED'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'alphapips@demo.com');

INSERT INTO users (email, password_hash, role, parent_ib_id, parent_mib_id, status)
SELECT 'ibflow@demo.com', '$2y$10$.bqQYBkygPUrAgJZowAiMuRNfNMsHvdXysyucOITWMksQZDDF4bXC', 'IB',
       NULL,
       (SELECT id FROM users WHERE email = 'mib@demo.com'),
       'ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'ibflow@demo.com');

INSERT INTO users (email, password_hash, role, parent_ib_id, parent_mib_id, status)
SELECT 'orbitclient@demo.com', '$2y$10$.bqQYBkygPUrAgJZowAiMuRNfNMsHvdXysyucOITWMksQZDDF4bXC', 'CLIENT',
       (SELECT id FROM users WHERE email = 'ibflow@demo.com'),
       (SELECT id FROM users WHERE email = 'mib@demo.com'),
       'ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'orbitclient@demo.com');

INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-CL-20260305', id, 18.20, '2026-03-05', '2026-03-05 09:00:00' FROM users WHERE email = 'client@demo.com';
INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-CL-20260312', id, 12.40, '2026-03-12', '2026-03-12 10:00:00' FROM users WHERE email = 'client@demo.com';
INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-CL-20260322', id, 14.70, '2026-03-22', '2026-03-22 11:00:00' FROM users WHERE email = 'client@demo.com';
INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-CL-20260401', id, 9.80, '2026-04-01', '2026-04-01 09:15:00' FROM users WHERE email = 'client@demo.com';
INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-CL-20260402A', id, 15.60, '2026-04-02', '2026-04-02 10:10:00' FROM users WHERE email = 'client@demo.com';
INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-CL-20260402B', id, 11.30, '2026-04-02', '2026-04-02 13:40:00' FROM users WHERE email = 'client@demo.com';

INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-TX-20260401', id, 7.40, '2026-04-01', '2026-04-01 10:20:00' FROM users WHERE email = 'traderx@demo.com';
INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-TX-20260402', id, 10.10, '2026-04-02', '2026-04-02 12:00:00' FROM users WHERE email = 'traderx@demo.com';
INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-NF-20260402', id, 16.80, '2026-04-02', '2026-04-02 12:20:00' FROM users WHERE email = 'neofx@demo.com';
INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-AP-20260401', id, 8.60, '2026-04-01', '2026-04-01 11:15:00' FROM users WHERE email = 'alphapips@demo.com';

INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-IB-20260315', id, 9.20, '2026-03-15', '2026-03-15 14:00:00' FROM users WHERE email = 'ib@demo.com';
INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-IB-20260402', id, 22.50, '2026-04-02', '2026-04-02 09:45:00' FROM users WHERE email = 'ib@demo.com';
INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-IBF-20260402', id, 17.40, '2026-04-02', '2026-04-02 15:10:00' FROM users WHERE email = 'ibflow@demo.com';

INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-OC-20260402', id, 13.50, '2026-04-02', '2026-04-02 15:30:00' FROM users WHERE email = 'orbitclient@demo.com';
INSERT INTO trades (order_id, user_id, lots, trade_date, created_at)
SELECT 'DEMO-MIB-20260402', id, 11.40, '2026-04-02', '2026-04-02 16:00:00' FROM users WHERE email = 'mib@demo.com';

INSERT INTO spin_ledger (user_id, type, source, amount, usd_value, reference_id, created_at)
SELECT user_id, 'CREDIT', 'TRADE', FLOOR(lots), lots, order_id, created_at
FROM trades
WHERE order_id LIKE 'DEMO-%';

INSERT INTO surewin_ledger (user_id, points, type, reference_id, created_at)
SELECT user_id, ROUND(lots * 10), 'CREDIT', order_id, created_at
FROM trades
WHERE order_id LIKE 'DEMO-%';

INSERT INTO lambo_ledger (user_id, type, amount_usd, source, reference_id, created_at)
SELECT t.user_id, 'BASE', ROUND(t.lots * 0.75, 2), 'TRADE', t.order_id, t.created_at
FROM trades t
INNER JOIN users u ON u.id = t.user_id
WHERE t.order_id LIKE 'DEMO-%' AND u.role = 'CLIENT';

INSERT INTO lambo_ledger (user_id, type, amount_usd, source, reference_id, created_at)
SELECT u.parent_ib_id, 'BASE', ROUND(t.lots * 0.15, 2), 'TRADE', t.order_id, t.created_at
FROM trades t
INNER JOIN users u ON u.id = t.user_id
WHERE t.order_id LIKE 'DEMO-%' AND u.role = 'CLIENT' AND u.parent_ib_id IS NOT NULL;

INSERT INTO lambo_ledger (user_id, type, amount_usd, source, reference_id, created_at)
SELECT u.parent_mib_id, 'BASE', ROUND(t.lots * 0.10, 2), 'TRADE', t.order_id, t.created_at
FROM trades t
INNER JOIN users u ON u.id = t.user_id
WHERE t.order_id LIKE 'DEMO-%' AND u.role = 'CLIENT' AND u.parent_mib_id IS NOT NULL;

INSERT INTO lambo_ledger (user_id, type, amount_usd, source, reference_id, created_at)
SELECT t.user_id, 'BASE', ROUND(t.lots * 0.85, 2), 'TRADE', t.order_id, t.created_at
FROM trades t
INNER JOIN users u ON u.id = t.user_id
WHERE t.order_id LIKE 'DEMO-%' AND u.role = 'IB';

INSERT INTO lambo_ledger (user_id, type, amount_usd, source, reference_id, created_at)
SELECT u.parent_mib_id, 'BASE', ROUND(t.lots * 0.15, 2), 'TRADE', t.order_id, t.created_at
FROM trades t
INNER JOIN users u ON u.id = t.user_id
WHERE t.order_id LIKE 'DEMO-%' AND u.role = 'IB' AND u.parent_mib_id IS NOT NULL;

INSERT INTO lambo_ledger (user_id, type, amount_usd, source, reference_id, created_at)
SELECT t.user_id, 'BASE', ROUND(t.lots, 2), 'TRADE', t.order_id, t.created_at
FROM trades t
INNER JOIN users u ON u.id = t.user_id
WHERE t.order_id LIKE 'DEMO-%' AND u.role = 'MIB';

INSERT INTO spin_ledger (user_id, type, source, amount, usd_value, reference_id, created_at)
SELECT id, 'DEBIT', 'SPIN_USE', -3, 0.00, 'DEMO-SPIN-CL-1', '2026-03-20 18:00:00' FROM users WHERE email = 'client@demo.com';
INSERT INTO spin_results (user_id, spins_used, reward_type, reward_value, surewin_points, seed_hash, created_at)
SELECT id, 3, 'Gift Card', 50.00, 0, 'demo-spin-cl-1', '2026-03-20 18:00:05' FROM users WHERE email = 'client@demo.com';

INSERT INTO spin_ledger (user_id, type, source, amount, usd_value, reference_id, created_at)
SELECT id, 'DEBIT', 'SPIN_USE', -2, 0.00, 'DEMO-SPIN-CL-2', '2026-03-28 19:10:00' FROM users WHERE email = 'client@demo.com';
INSERT INTO spin_results (user_id, spins_used, reward_type, reward_value, surewin_points, seed_hash, created_at)
SELECT id, 2, '+20 Sure Win Points', 0.00, 20, 'demo-spin-cl-2', '2026-03-28 19:10:05' FROM users WHERE email = 'client@demo.com';
INSERT INTO surewin_ledger (user_id, points, type, reference_id, created_at)
SELECT id, 20, 'CREDIT', 'DEMO-SPIN-CL-2', '2026-03-28 19:10:05' FROM users WHERE email = 'client@demo.com';

INSERT INTO spin_ledger (user_id, type, source, amount, usd_value, reference_id, created_at)
SELECT id, 'DEBIT', 'SPIN_USE', -2, 0.00, 'DEMO-SPIN-CL-3', '2026-04-02 17:45:00' FROM users WHERE email = 'client@demo.com';
INSERT INTO spin_results (user_id, spins_used, reward_type, reward_value, surewin_points, seed_hash, created_at)
SELECT id, 2, 'Near miss', 0.00, 0, 'demo-spin-cl-3', '2026-04-02 17:45:05' FROM users WHERE email = 'client@demo.com';

INSERT INTO spin_ledger (user_id, type, source, amount, usd_value, reference_id, created_at)
SELECT id, 'DEBIT', 'SPIN_USE', -1, 0.00, 'DEMO-SPIN-TX-1', '2026-04-02 18:10:00' FROM users WHERE email = 'traderx@demo.com';
INSERT INTO spin_results (user_id, spins_used, reward_type, reward_value, surewin_points, seed_hash, created_at)
SELECT id, 1, 'Gift Card', 50.00, 0, 'demo-spin-tx-1', '2026-04-02 18:10:05' FROM users WHERE email = 'traderx@demo.com';

INSERT INTO fraud_flags (user_id, reason, status, created_at)
SELECT id, 'Demo lot spike anomaly on recent trades', 'OPEN', '2026-04-02 14:20:00' FROM users WHERE email = 'neofx@demo.com';
INSERT INTO fraud_flags (user_id, reason, status, created_at)
SELECT id, 'Demo internal or excluded account review', 'EXCLUDED', '2026-04-01 10:00:00' FROM users WHERE email = 'alphapips@demo.com';

INSERT INTO milestone_claims (user_id, milestone_id, created_at)
SELECT u.id, m.id, '2026-03-29 12:00:00'
FROM users u
INNER JOIN surewin_milestones m ON m.code = 'HOODIE'
WHERE u.email = 'client@demo.com';

INSERT INTO sync_logs (method, trades_processed, total_lots, duplicates_count, status, summary, created_at)
VALUES
('CSV', 11, 160.00, 0, 'SUCCESS', 'Demo CSV sync completed for seeded activity', '2026-04-02 16:30:00'),
('MANUAL', 1, 11.30, 0, 'SUCCESS', 'Demo manual trade added for client@demo.com', '2026-04-02 13:45:00');

SET FOREIGN_KEY_CHECKS = 1;
