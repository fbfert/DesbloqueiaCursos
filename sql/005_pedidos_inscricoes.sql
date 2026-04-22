-- Polo Rainbow - pedidos, participantes, inscricoes e comprovantes PIX
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS pedidos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(40) NOT NULL,
    comprador_usuario_id BIGINT UNSIGNED NULL,
    pagador_usuario_id BIGINT UNSIGNED NULL,
    pagador_nome VARCHAR(150) NULL,
    pagador_cpf VARCHAR(14) NULL,
    pagador_email VARCHAR(191) NULL,
    pagador_telefone VARCHAR(30) NULL,
    tipo_pedido ENUM('propria', 'terceiros', 'lote') NOT NULL DEFAULT 'propria',
    status ENUM('rascunho', 'aguardando_pagamento', 'comprovante_enviado', 'em_analise', 'pago', 'cancelado', 'expirado') NOT NULL DEFAULT 'rascunho',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    desconto_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    acrescimo_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    observacoes_internas TEXT NULL,
    observacoes_publicas TEXT NULL,
    canal_origem VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pedidos_codigo (codigo),
    KEY idx_pedidos_comprador (comprador_usuario_id),
    KEY idx_pedidos_pagador (pagador_usuario_id),
    KEY idx_pedidos_status (status),
    KEY idx_pedidos_tipo (tipo_pedido),
    KEY idx_pedidos_deleted_at (deleted_at),
    CONSTRAINT fk_pedidos_comprador
        FOREIGN KEY (comprador_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pedidos_pagador
        FOREIGN KEY (pagador_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_itens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    quantidade INT UNSIGNED NOT NULL DEFAULT 1,
    valor_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('ativo', 'cancelado') NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pedido_itens_pedido (pedido_id),
    KEY idx_pedido_itens_curso (curso_evento_id),
    KEY idx_pedido_itens_turma (turma_id),
    KEY idx_pedido_itens_status (status),
    KEY idx_pedido_itens_deleted_at (deleted_at),
    CONSTRAINT fk_pedido_itens_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pedido_itens_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_pedido_itens_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS participantes_pedido (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    pedido_item_id BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NULL,
    nome VARCHAR(150) NOT NULL,
    cpf VARCHAR(14) NULL,
    email VARCHAR(191) NULL,
    telefone VARCHAR(30) NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_participantes_pedido_pedido (pedido_id),
    KEY idx_participantes_pedido_item (pedido_item_id),
    KEY idx_participantes_pedido_usuario (usuario_id),
    KEY idx_participantes_pedido_cpf (cpf),
    KEY idx_participantes_pedido_status (status),
    KEY idx_participantes_pedido_deleted_at (deleted_at),
    CONSTRAINT fk_participantes_pedido_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_participantes_pedido_item
        FOREIGN KEY (pedido_item_id) REFERENCES pedido_itens (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_participantes_pedido_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscricoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    pedido_item_id BIGINT UNSIGNED NOT NULL,
    participante_pedido_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    status ENUM('pendente', 'ativa', 'cancelada', 'reprovada', 'concluida') NOT NULL DEFAULT 'pendente',
    confirmado_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_inscricoes_item_participante (pedido_item_id, participante_pedido_id),
    KEY idx_inscricoes_pedido (pedido_id),
    KEY idx_inscricoes_participante (participante_pedido_id),
    KEY idx_inscricoes_usuario (usuario_id),
    KEY idx_inscricoes_curso (curso_evento_id),
    KEY idx_inscricoes_turma (turma_id),
    KEY idx_inscricoes_status (status),
    KEY idx_inscricoes_deleted_at (deleted_at),
    CONSTRAINT fk_inscricoes_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_inscricoes_pedido_item
        FOREIGN KEY (pedido_item_id) REFERENCES pedido_itens (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_inscricoes_participante
        FOREIGN KEY (participante_pedido_id) REFERENCES participantes_pedido (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_inscricoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_inscricoes_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_inscricoes_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comprovantes_pix (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    arquivo_caminho VARCHAR(255) NOT NULL,
    arquivo_nome_original VARCHAR(255) NULL,
    arquivo_mime_type VARCHAR(120) NULL,
    arquivo_tamanho_bytes BIGINT UNSIGNED NULL,
    valor_informado DECIMAL(10,2) NULL,
    enviado_em DATETIME NOT NULL,
    status ENUM('pendente', 'em_analise', 'aprovado', 'reprovado') NOT NULL DEFAULT 'pendente',
    analise_observacao TEXT NULL,
    analisado_por_usuario_id BIGINT UNSIGNED NULL,
    analisado_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_comprovantes_pix_pedido (pedido_id),
    KEY idx_comprovantes_pix_usuario (usuario_id),
    KEY idx_comprovantes_pix_status (status),
    KEY idx_comprovantes_pix_analisado_por (analisado_por_usuario_id),
    KEY idx_comprovantes_pix_deleted_at (deleted_at),
    CONSTRAINT fk_comprovantes_pix_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_comprovantes_pix_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_comprovantes_pix_analisado_por
        FOREIGN KEY (analisado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS status_pedidos_historico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    status_anterior VARCHAR(40) NULL,
    status_novo VARCHAR(40) NOT NULL,
    observacao VARCHAR(500) NULL,
    alterado_por_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_status_pedidos_historico_pedido (pedido_id),
    KEY idx_status_pedidos_historico_status (status_novo),
    KEY idx_status_pedidos_historico_usuario (alterado_por_usuario_id),
    KEY idx_status_pedidos_historico_created_at (created_at),
    CONSTRAINT fk_status_pedidos_historico_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_status_pedidos_historico_usuario
        FOREIGN KEY (alterado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS status_inscricoes_historico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    status_anterior VARCHAR(40) NULL,
    status_novo VARCHAR(40) NOT NULL,
    observacao VARCHAR(500) NULL,
    alterado_por_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_status_inscricoes_historico_inscricao (inscricao_id),
    KEY idx_status_inscricoes_historico_status (status_novo),
    KEY idx_status_inscricoes_historico_usuario (alterado_por_usuario_id),
    KEY idx_status_inscricoes_historico_created_at (created_at),
    CONSTRAINT fk_status_inscricoes_historico_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_status_inscricoes_historico_usuario
        FOREIGN KEY (alterado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
