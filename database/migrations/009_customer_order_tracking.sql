ALTER TABLE orders
    ADD COLUMN last_synced_at TIMESTAMP NULL AFTER provider_raw,
    ADD COLUMN status_updated_at TIMESTAMP NULL AFTER last_synced_at;

CREATE TABLE order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL,
    start_count BIGINT NULL,
    remains BIGINT NULL,
    provider_raw JSON NULL,
    source ENUM('provider_sync','customer_refresh','admin') NOT NULL DEFAULT 'provider_sync',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_status_history_order_created (order_id, created_at),
    CONSTRAINT fk_order_status_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
