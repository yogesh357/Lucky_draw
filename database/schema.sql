CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('CLIENT','IB','MIB','ADMIN') NOT NULL,
    parent_ib_id BIGINT UNSIGNED NULL,
    parent_mib_id BIGINT UNSIGNED NULL,
    status ENUM('ACTIVE','HOLD','EXCLUDED') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_parent_ib FOREIGN KEY (parent_ib_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_parent_mib FOREIGN KEY (parent_mib_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS trades (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(100) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NOT NULL,
    lots DECIMAL(10,2) NOT NULL,
    trade_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_trades_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_trades_user_date (user_id, trade_date)
);

CREATE TABLE IF NOT EXISTS spin_ledger (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    type ENUM('CREDIT','DEBIT','EXPIRY','ADJUSTMENT') NOT NULL,
    source ENUM('TRADE','SPIN_USE','FREE_SPIN_USE','EXPIRY_JOB','ADMIN') NOT NULL,
    amount INT NOT NULL,
    usd_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reference_id VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_spin_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_spin_ledger_user_created (user_id, created_at),
    INDEX idx_spin_ledger_reference (reference_id)
);

CREATE TABLE IF NOT EXISTS lambo_ledger (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    type ENUM('BASE','EXPIRED_REALLOC','ADJUSTMENT','PAYOUT') NOT NULL,
    amount_usd DECIMAL(10,2) NOT NULL,
    source VARCHAR(50) NOT NULL,
    reference_id VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lambo_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_lambo_ledger_user_created (user_id, created_at),
    INDEX idx_lambo_ledger_reference (reference_id)
);

CREATE TABLE IF NOT EXISTS surewin_ledger (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    points INT NOT NULL,
    type ENUM('CREDIT','MILESTONE') NOT NULL,
    reference_id VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_surewin_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_surewin_ledger_user_created (user_id, created_at),
    INDEX idx_surewin_ledger_reference (reference_id)
);

CREATE TABLE IF NOT EXISTS spin_results (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    spins_used INT NOT NULL,
    reward_type VARCHAR(100) NOT NULL,
    reward_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    surewin_points INT NOT NULL DEFAULT 0,
    seed_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_spin_results_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_spin_results_user_created (user_id, created_at)
);

CREATE TABLE IF NOT EXISTS fraud_flags (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('OPEN','CLEARED','EXCLUDED') NOT NULL DEFAULT 'OPEN',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fraud_flags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_fraud_flags_status_created (status, created_at)
);

CREATE TABLE IF NOT EXISTS config (
    key_name VARCHAR(100) PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS idempotency_keys (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    scope_name VARCHAR(100) NOT NULL,
    idempotency_key VARCHAR(255) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    response_code SMALLINT NOT NULL,
    response_body MEDIUMTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_idempotency_scope_key (scope_name, idempotency_key)
);

CREATE TABLE IF NOT EXISTS sync_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    method ENUM('CSV','MANUAL','SYSTEM') NOT NULL,
    trades_processed INT NOT NULL DEFAULT 0,
    total_lots DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    duplicates_count INT NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL,
    summary TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reward_catalog (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    reward_key VARCHAR(100) NOT NULL UNIQUE,
    reward_name VARCHAR(100) NOT NULL,
    reward_type ENUM('NONE','CASH','SUREWIN_POINTS','ITEM') NOT NULL DEFAULT 'NONE',
    reward_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    surewin_points INT NOT NULL DEFAULT 0,
    weight INT NOT NULL DEFAULT 1,
    stock_limit INT NULL,
    stock_used INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS surewin_milestones (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(100) NOT NULL,
    points_required INT NOT NULL,
    reward_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS milestone_claims (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    milestone_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_milestone (user_id, milestone_id),
    CONSTRAINT fk_milestone_claims_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_milestone_claims_milestone FOREIGN KEY (milestone_id) REFERENCES surewin_milestones(id) ON DELETE RESTRICT
);
