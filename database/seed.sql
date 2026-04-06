INSERT INTO config (key_name, value) VALUES
('exposure_cap_per_lot', '2.00'),
('spin_usd_per_lot', '1.00'),
('lambo_usd_per_lot', '1.00'),
('surewin_points_per_lot', '10'),
('notifications_max_per_day', '2'),
('expiry_warning_days', '3'),
('lambo_target_amount', '600000'),
('campaign_status', 'ACTIVE')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO reward_catalog (reward_key, reward_name, reward_type, reward_value, surewin_points, weight, stock_limit, sort_order) VALUES
('NEAR_MISS', 'Near miss', 'NONE', 0.00, 0, 60, NULL, 10),
('SW_20', '+20 Sure Win Points', 'SUREWIN_POINTS', 0.00, 20, 25, NULL, 20),
('GIFT_CARD', 'Gift Card', 'ITEM', 50.00, 0, 10, NULL, 30),
('AIRPODS', 'AirPods Pro', 'ITEM', 250.00, 0, 4, 100, 40),
('IPAD', 'iPad', 'ITEM', 800.00, 0, 1, 50, 50)
ON DUPLICATE KEY UPDATE
reward_name = VALUES(reward_name),
reward_type = VALUES(reward_type),
reward_value = VALUES(reward_value),
surewin_points = VALUES(surewin_points),
weight = VALUES(weight),
stock_limit = VALUES(stock_limit),
sort_order = VALUES(sort_order);

INSERT INTO surewin_milestones (code, title, points_required, reward_value) VALUES
('HOODIE', 'Hoodie', 500, 35.00),
('IPAD', 'iPad', 1000, 800.00),
('IPHONE_16', 'iPhone 16', 1500, 1200.00),
('MACBOOK_AIR', 'MacBook Air', 3000, 2000.00)
ON DUPLICATE KEY UPDATE
title = VALUES(title),
points_required = VALUES(points_required),
reward_value = VALUES(reward_value);
