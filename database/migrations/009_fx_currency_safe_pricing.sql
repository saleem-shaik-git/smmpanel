ALTER TABLE services
    ADD COLUMN provider_currency CHAR(3) NOT NULL DEFAULT 'USD' AFTER provider_rate,
    ADD COLUMN customer_currency CHAR(3) NOT NULL DEFAULT 'NGN' AFTER selling_rate,
    ADD COLUMN fx_rate DECIMAL(18,8) NOT NULL DEFAULT 1.00000000 AFTER customer_currency;

ALTER TABLE orders
    ADD COLUMN provider_currency CHAR(3) NOT NULL DEFAULT 'USD' AFTER provider_cost,
    ADD COLUMN customer_currency CHAR(3) NOT NULL DEFAULT 'NGN' AFTER provider_currency,
    ADD COLUMN fx_rate DECIMAL(18,8) NOT NULL DEFAULT 1.00000000 AFTER customer_currency;
