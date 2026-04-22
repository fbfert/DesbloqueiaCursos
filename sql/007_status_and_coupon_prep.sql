-- Polo Rainbow - ajuste de status operacionais e preparacao de cupons
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE pedidos
    MODIFY COLUMN status ENUM(
        'rascunho',
        'pendencia',
        'aguardando_pagamento',
        'aguardando_reenvio',
        'comprovante_enviado',
        'em_analise',
        'aprovado',
        'pago',
        'cancelado',
        'reembolsado',
        'expirado'
    ) NOT NULL DEFAULT 'rascunho';

ALTER TABLE inscricoes
    MODIFY COLUMN status ENUM(
        'pendente',
        'com_pendencia',
        'ativa',
        'em_andamento',
        'cancelada',
        'reprovada',
        'concluida',
        'concluida_sem_certificado',
        'certificado_emitido'
    ) NOT NULL DEFAULT 'pendente';

CREATE TABLE IF NOT EXISTS pedidos_cupons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    cupom_codigo VARCHAR(80) NOT NULL,
    valor_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_pedidos_cupons_pedido (pedido_id),
    KEY idx_pedidos_cupons_codigo (cupom_codigo),
    CONSTRAINT fk_pedidos_cupons_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
