-- Desbloqueia Cursos - recuperação de pedidos incompletos v1
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS pedido_recuperacao_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    aluno_id BIGINT UNSIGNED NULL,
    curso_id BIGINT UNSIGNED NULL,
    admin_user_id BIGINT UNSIGNED NULL,
    canal VARCHAR(20) NOT NULL DEFAULT 'email',
    modelo_chave VARCHAR(120) NOT NULL,
    tipo_envio ENUM('manual', 'automatico') NOT NULL DEFAULT 'manual',
    status ENUM('enviado', 'erro', 'ignorado', 'bloqueado') NOT NULL DEFAULT 'bloqueado',
    email_destino VARCHAR(191) NULL,
    valor_pendente DECIMAL(10,2) NULL,
    cupom_codigo VARCHAR(80) NULL,
    motivo_bloqueio VARCHAR(255) NULL,
    erro TEXT NULL,
    email_envio_id BIGINT UNSIGNED NULL,
    enviado_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pedido_recuperacao_logs_pedido (pedido_id),
    KEY idx_pedido_recuperacao_logs_aluno (aluno_id),
    KEY idx_pedido_recuperacao_logs_curso (curso_id),
    KEY idx_pedido_recuperacao_logs_admin (admin_user_id),
    KEY idx_pedido_recuperacao_logs_status (status),
    KEY idx_pedido_recuperacao_logs_tipo_envio (tipo_envio),
    KEY idx_pedido_recuperacao_logs_modelo (modelo_chave),
    KEY idx_pedido_recuperacao_logs_enviado_em (enviado_em),
    CONSTRAINT fk_pedido_recuperacao_logs_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pedido_recuperacao_logs_aluno
        FOREIGN KEY (aluno_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pedido_recuperacao_logs_curso
        FOREIGN KEY (curso_id) REFERENCES cursos_eventos (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pedido_recuperacao_logs_admin
        FOREIGN KEY (admin_user_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_optouts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aluno_id BIGINT UNSIGNED NULL,
    email VARCHAR(191) NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    optout_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_email_optouts_token_hash (token_hash),
    KEY idx_email_optouts_aluno_tipo (aluno_id, tipo),
    KEY idx_email_optouts_email_tipo (email, tipo),
    KEY idx_email_optouts_tipo (tipo),
    KEY idx_email_optouts_optout_em (optout_em),
    CONSTRAINT fk_email_optouts_aluno
        FOREIGN KEY (aluno_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO emails_modelos
    (evento, template, nome, assunto, corpo_html, gatilho_descricao, variaveis_json, ativo, editavel, created_at, updated_at, deleted_at)
VALUES
    (
        'pedido_recuperacao_primeiro_lembrete',
        'pedido_recuperacao_primeiro_lembrete',
        'Recuperação de pedido - 1º lembrete',
        'Norminha aqui: sua inscrição ficou quase pronta',
        '',
        'Modelo padrão para o primeiro lembrete manual de recuperação de pedido.',
        '["{{aluno_nome}}","{{aluno_email}}","{{pedido_codigo}}","{{curso_nome}}","{{valor_total}}","{{valor_pago}}","{{valor_pendente}}","{{link_pagamento}}","{{link_pedido}}","{{data_pedido}}","{{data_expiracao}}","{{cupom_codigo}}","{{whatsapp_atendimento}}","{{link_descadastro_recuperacao}}"]',
        1,
        1,
        NOW(),
        NOW(),
        NULL
    ),
    (
        'pedido_recuperacao_segundo_lembrete',
        'pedido_recuperacao_segundo_lembrete',
        'Recuperação de pedido - 2º lembrete',
        'Seu pedido ainda está esperando por você',
        '',
        'Modelo padrão para o segundo lembrete manual de recuperação de pedido.',
        '["{{aluno_nome}}","{{aluno_email}}","{{pedido_codigo}}","{{curso_nome}}","{{valor_total}}","{{valor_pago}}","{{valor_pendente}}","{{link_pagamento}}","{{link_pedido}}","{{data_pedido}}","{{data_expiracao}}","{{cupom_codigo}}","{{whatsapp_atendimento}}","{{link_descadastro_recuperacao}}"]',
        1,
        1,
        NOW(),
        NOW(),
        NULL
    ),
    (
        'pedido_recuperacao_terceiro_lembrete',
        'pedido_recuperacao_terceiro_lembrete',
        'Recuperação de pedido - 3º lembrete',
        'Norminha passando para te lembrar do seu curso',
        '',
        'Modelo padrão para o terceiro lembrete manual de recuperação de pedido.',
        '["{{aluno_nome}}","{{aluno_email}}","{{pedido_codigo}}","{{curso_nome}}","{{valor_total}}","{{valor_pago}}","{{valor_pendente}}","{{link_pagamento}}","{{link_pedido}}","{{data_pedido}}","{{data_expiracao}}","{{cupom_codigo}}","{{whatsapp_atendimento}}","{{link_descadastro_recuperacao}}"]',
        1,
        1,
        NOW(),
        NOW(),
        NULL
    ),
    (
        'pedido_recuperacao_ultimo_lembrete',
        'pedido_recuperacao_ultimo_lembrete',
        'Recuperação de pedido - último lembrete',
        'Último lembrete sobre seu pedido no Desbloqueia Cursos',
        '',
        'Modelo padrão para o último lembrete manual de recuperação de pedido.',
        '["{{aluno_nome}}","{{aluno_email}}","{{pedido_codigo}}","{{curso_nome}}","{{valor_total}}","{{valor_pago}}","{{valor_pendente}}","{{link_pagamento}}","{{link_pedido}}","{{data_pedido}}","{{data_expiracao}}","{{cupom_codigo}}","{{whatsapp_atendimento}}","{{link_descadastro_recuperacao}}"]',
        1,
        1,
        NOW(),
        NOW(),
        NULL
    ),
    (
        'pedido_recuperacao_quase_expirando',
        'pedido_recuperacao_quase_expirando',
        'Recuperação de pedido - quase expirando',
        'Seu pedido pode expirar em breve',
        '',
        'Modelo padrão para mensagens de recuperação quando o pedido está perto do prazo limite.',
        '["{{aluno_nome}}","{{aluno_email}}","{{pedido_codigo}}","{{curso_nome}}","{{valor_total}}","{{valor_pago}}","{{valor_pendente}}","{{link_pagamento}}","{{link_pedido}}","{{data_pedido}}","{{data_expiracao}}","{{cupom_codigo}}","{{whatsapp_atendimento}}","{{link_descadastro_recuperacao}}"]',
        1,
        1,
        NOW(),
        NOW(),
        NULL
    ),
    (
        'pedido_recuperacao_com_cupom',
        'pedido_recuperacao_com_cupom',
        'Recuperação de pedido - com cupom',
        'A Norminha trouxe uma ajudinha para você concluir seu curso',
        '',
        'Modelo padrão para recuperação manual com cupom informado pelo admin.',
        '["{{aluno_nome}}","{{aluno_email}}","{{pedido_codigo}}","{{curso_nome}}","{{valor_total}}","{{valor_pago}}","{{valor_pendente}}","{{link_pagamento}}","{{link_pedido}}","{{data_pedido}}","{{data_expiracao}}","{{cupom_codigo}}","{{whatsapp_atendimento}}","{{link_descadastro_recuperacao}}"]',
        1,
        1,
        NOW(),
        NOW(),
        NULL
    )
ON DUPLICATE KEY UPDATE
    id = id;

SET FOREIGN_KEY_CHECKS = 1;
