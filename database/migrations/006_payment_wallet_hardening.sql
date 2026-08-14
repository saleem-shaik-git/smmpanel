CREATE TABLE payment_intents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    reference VARCHAR(190) NOT NULL UNIQUE,
    provider_reference VARCHAR(190) NULL,
    amount DECIMAL(18,4) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
    status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    provider_raw JSON NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_intents_user (user_id),
    INDEX idx_payment_intents_status (status),
    CONSTRAINT fk_payment_intents_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wallet_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    transaction_id BIGINT UNSIGNED NULL,
    direction ENUM('credit','debit') NOT NULL,
    amount DECIMAL(18,4) NOT NULL,
    balance_before DECIMAL(18,4) NOT NULL,
    balance_after DECIMAL(18,4) NOT NULL,
    reference VARCHAR(190) NOT NULL UNIQUE,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wallet_ledger_user_created (user_id, created_at),
    CONSTRAINT fk_wallet_ledger_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_wallet_ledger_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payment_webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(40) NOT NULL,
    event_id VARCHAR(190) NOT NULL,
    event_type VARCHAR(100) NULL,
    signature_valid TINYINT(1) NOT NULL DEFAULT 0,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    payload JSON NOT NULL,
    error_message VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    UNIQUE KEY uq_payment_webhook_event (provider, event_id),
    INDEX idx_payment_webhook_processed (processed, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
