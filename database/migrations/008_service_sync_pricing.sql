ALTER TABLE services
    ADD COLUMN markup_percent DECIMAL(8,2) NULL AFTER selling_rate,
    ADD INDEX idx_services_status_category (status, category_id);
