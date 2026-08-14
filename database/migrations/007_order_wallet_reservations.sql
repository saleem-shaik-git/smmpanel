CREATE TABLE order_wallet_reservations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(18,4) NOT NULL,
    status ENUM('reserved','captured','released') NOT NULL DEFAULT 'reserved',
    reserve_reference VARCHAR(190) NOT NULL UNIQUE,
    release_reference VARCHAR(190) NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL,
    UNIQUE KEY uq_order_reservation (order_id),
    CONSTRAINT fk_reservation_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
