-- Polo Rainbow - configuracoes de pagamento AbacatePay
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS pagamento_gateway_configuracoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gateway VARCHAR(50) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 0,
    ambiente VARCHAR(30) NOT NULL DEFAULT 'sandbox',
    api_key_encrypted TEXT NULL,
    webhook_hmac_secret_encrypted TEXT NULL,
    webhook_url_secret_encrypted TEXT NULL,
    webhook_url_publica VARCHAR(255) NULL,
    ultimo_teste_status VARCHAR(30) NULL,
    ultimo_teste_mensagem TEXT NULL,
    ultimo_teste_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    atualizado_por INT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gateway (gateway),
    KEY idx_pagamento_gateway_configuracoes_ativo (ativo),
    KEY idx_pagamento_gateway_configuracoes_ambiente (ambiente),
    KEY idx_pagamento_gateway_configuracoes_ultimo_teste_em (ultimo_teste_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
