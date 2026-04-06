INSERT INTO users (email, password_hash, role, parent_ib_id, parent_mib_id, status) VALUES
('admin@demo.com', '$2y$10$.bqQYBkygPUrAgJZowAiMuRNfNMsHvdXysyucOITWMksQZDDF4bXC', 'ADMIN', NULL, NULL, 'ACTIVE'),
('mib@demo.com', '$2y$10$.bqQYBkygPUrAgJZowAiMuRNfNMsHvdXysyucOITWMksQZDDF4bXC', 'MIB', NULL, NULL, 'ACTIVE'),
('ib@demo.com', '$2y$10$.bqQYBkygPUrAgJZowAiMuRNfNMsHvdXysyucOITWMksQZDDF4bXC', 'IB', NULL, 2, 'ACTIVE'),
('client@demo.com', '$2y$10$.bqQYBkygPUrAgJZowAiMuRNfNMsHvdXysyucOITWMksQZDDF4bXC', 'CLIENT', 3, 2, 'ACTIVE')
ON DUPLICATE KEY UPDATE
password_hash = VALUES(password_hash),
role = VALUES(role),
parent_ib_id = VALUES(parent_ib_id),
parent_mib_id = VALUES(parent_mib_id),
status = VALUES(status);
