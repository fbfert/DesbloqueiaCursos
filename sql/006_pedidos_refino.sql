-- Polo Rainbow - refino incremental de pedidos e inscricoes
-- Compatibilidade: MySQL 5.7
-- Objetivo: adicionar controle de aprovacao, progresso e versionamento do comprovante PIX.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE pedidos
    ADD COLUMN aprovado_por_usuario_id BIGINT UNSIGNED NULL AFTER status,
    ADD COLUMN aprovado_em DATETIME NULL AFTER aprovado_por_usuario_id,
    ADD COLUMN data_expiracao_pagamento DATETIME NULL AFTER aprovado_em,
    ADD COLUMN cupom_codigo VARCHAR(80) NULL AFTER data_expiracao_pagamento,
    ADD KEY idx_pedidos_aprovado_por (aprovado_por_usuario_id),
    ADD KEY idx_pedidos_data_expiracao_pagamento (data_expiracao_pagamento),
    ADD KEY idx_pedidos_cupom_codigo (cupom_codigo),
    ADD CONSTRAINT fk_pedidos_aprovado_por
        FOREIGN KEY (aprovado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL;

ALTER TABLE inscricoes
    ADD COLUMN percentual_progresso DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER status,
    ADD COLUMN presenca_percentual DECIMAL(5,2) NULL AFTER percentual_progresso,
    ADD COLUMN nota_final DECIMAL(5,2) NULL AFTER presenca_percentual,
    ADD COLUMN apto_certificado TINYINT(1) NOT NULL DEFAULT 0 AFTER nota_final,
    ADD COLUMN concluida_em DATETIME NULL AFTER apto_certificado,
    ADD KEY idx_inscricoes_percentual_progresso (percentual_progresso),
    ADD KEY idx_inscricoes_apto_certificado (apto_certificado),
    ADD KEY idx_inscricoes_concluida_em (concluida_em);

ALTER TABLE comprovantes_pix
    DROP INDEX uk_comprovantes_pix_pedido,
    ADD COLUMN versao INT UNSIGNED NOT NULL DEFAULT 1 AFTER valor_informado,
    ADD COLUMN is_atual TINYINT(1) NOT NULL DEFAULT 1 AFTER versao,
    ADD COLUMN motivo_reenvio VARCHAR(500) NULL AFTER is_atual,
    ADD UNIQUE KEY uk_comprovantes_pix_pedido_versao (pedido_id, versao),
    ADD KEY idx_comprovantes_pix_pedido_atual (pedido_id, is_atual);

SET FOREIGN_KEY_CHECKS = 1;
