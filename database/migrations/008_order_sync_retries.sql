CREATE TABLE IF NOT EXISTS order_sync_retries (
    order_id BIGINT UNSIGNED PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at TIMESTAMP NULL,
    last_error VARCHAR(1000) NULL,
    last_attempt_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_sync_retry_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_sync_retry_due (next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
