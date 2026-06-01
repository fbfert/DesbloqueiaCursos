-- Polo Rainbow - integração AbacatePay
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE pedidos
    ADD COLUMN payment_gateway VARCHAR(30) NULL AFTER aprovado_em,
    ADD COLUMN payment_external_id VARCHAR(80) NULL AFTER payment_gateway,
    ADD COLUMN payment_provider_checkout_id VARCHAR(80) NULL AFTER payment_external_id,
    ADD COLUMN payment_provider_product_id VARCHAR(80) NULL AFTER payment_provider_checkout_id,
    ADD COLUMN payment_provider_product_external_id VARCHAR(120) NULL AFTER payment_provider_product_id,
    ADD COLUMN payment_provider_payment_url VARCHAR(255) NULL AFTER payment_provider_product_external_id,
    ADD COLUMN payment_provider_receipt_url VARCHAR(255) NULL AFTER payment_provider_payment_url,
    ADD COLUMN payment_provider_status VARCHAR(40) NULL AFTER payment_provider_receipt_url,
    ADD COLUMN payment_provider_amount DECIMAL(10,2) NULL AFTER payment_provider_status,
    ADD COLUMN payment_provider_paid_amount DECIMAL(10,2) NULL AFTER payment_provider_amount,
    ADD COLUMN payment_provider_method VARCHAR(20) NULL AFTER payment_provider_paid_amount,
    ADD COLUMN payment_provider_payload LONGTEXT NULL AFTER payment_provider_method,
    ADD COLUMN payment_provider_updated_at DATETIME NULL AFTER payment_provider_payload,
    ADD KEY idx_pedidos_payment_gateway (payment_gateway),
    ADD KEY idx_pedidos_payment_external_id (payment_external_id),
    ADD KEY idx_pedidos_payment_checkout_id (payment_provider_checkout_id);

CREATE TABLE IF NOT EXISTS pagamentos_gateway_transacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NULL,
    gateway VARCHAR(30) NOT NULL,
    external_id VARCHAR(120) NULL,
    provider_id VARCHAR(120) NULL,
    event_id VARCHAR(120) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    status VARCHAR(40) NOT NULL,
    amount DECIMAL(10,2) NULL,
    paid_amount DECIMAL(10,2) NULL,
    payment_method VARCHAR(20) NULL,
    receipt_url VARCHAR(255) NULL,
    raw_payload LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pagamentos_gateway_transacoes_event (gateway, event_id),
    KEY idx_pagamentos_gateway_transacoes_pedido (pedido_id),
    KEY idx_pagamentos_gateway_transacoes_gateway (gateway),
    KEY idx_pagamentos_gateway_transacoes_external (external_id),
    KEY idx_pagamentos_gateway_transacoes_event_type (event_type),
    KEY idx_pagamentos_gateway_transacoes_status (status),
    CONSTRAINT fk_pagamentos_gateway_transacoes_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos_gateway_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gateway VARCHAR(30) NOT NULL,
    pedido_id BIGINT UNSIGNED NULL,
    event_id VARCHAR(120) NULL,
    event_type VARCHAR(80) NULL,
    payload LONGTEXT NULL,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_pagamentos_gateway_logs_gateway (gateway),
    KEY idx_pagamentos_gateway_logs_pedido (pedido_id),
    KEY idx_pagamentos_gateway_logs_event (event_id),
    KEY idx_pagamentos_gateway_logs_event_type (event_type),
    KEY idx_pagamentos_gateway_logs_processed (processed),
    CONSTRAINT fk_pagamentos_gateway_logs_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
