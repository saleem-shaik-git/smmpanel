ALTER TABLE orders ADD COLUMN refill_status VARCHAR(50) NULL AFTER refill_id;

CREATE TABLE refund_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(80) NOT NULL,
    amount DECIMAL(18,4) NOT NULL,
    reference VARCHAR(190) NOT NULL UNIQUE,
    status ENUM('pending','completed','failed') NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_refund_order_reason (order_id, reason),
    INDEX idx_refund_events_user (user_id),
    CONSTRAINT fk_refund_events_order FOREIGN KEY (order_id) REFERENCES orders(id),
    CONSTRAINT fk_refund_events_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    actor_type ENUM('system','admin','user') NOT NULL DEFAULT 'system',
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_audit_order (order_id),
    CONSTRAINT fk_order_audit_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE refill_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    provider_refill_id VARCHAR(120) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    provider_raw JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_refill_provider_id (provider_refill_id),
    INDEX idx_refill_order (order_id),
    CONSTRAINT fk_refill_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
