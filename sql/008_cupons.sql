-- Polo Rainbow - modulo de cupons
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE pedidos
    ADD COLUMN pagador_cidade VARCHAR(120) NULL AFTER pagador_telefone,
    ADD COLUMN pagador_estado CHAR(2) NULL AFTER pagador_cidade,
    ADD COLUMN pagador_empresa_nome VARCHAR(191) NULL AFTER pagador_estado,
    ADD COLUMN pagador_empresa_documento VARCHAR(30) NULL AFTER pagador_empresa_nome,
    ADD KEY idx_pedidos_pagador_cidade (pagador_cidade),
    ADD KEY idx_pedidos_pagador_estado (pagador_estado),
    ADD KEY idx_pedidos_pagador_empresa_documento (pagador_empresa_documento);

CREATE TABLE IF NOT EXISTS cupons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(80) NOT NULL,
    nome VARCHAR(150) NOT NULL,
    descricao VARCHAR(500) NULL,
    escopo VARCHAR(30) NOT NULL DEFAULT 'todo_site',
    tipo ENUM('publico', 'privado', 'usuario', 'empresa') NOT NULL DEFAULT 'publico',
    desconto_tipo ENUM('percentual', 'valor') NOT NULL DEFAULT 'percentual',
    valor_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantidade_minima_vagas INT UNSIGNED NULL,
    limite_total_usos INT UNSIGNED NULL,
    limite_por_usuario INT UNSIGNED NULL,
    data_inicio DATETIME NULL,
    data_fim DATETIME NULL,
    status ENUM('rascunho', 'ativo', 'inativo', 'expirado') NOT NULL DEFAULT 'rascunho',
    link_promocional VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cupons_codigo (codigo),
    KEY idx_cupons_tipo (tipo),
    KEY idx_cupons_escopo (escopo),
    KEY idx_cupons_desconto_tipo (desconto_tipo),
    KEY idx_cupons_status (status),
    KEY idx_cupons_data_inicio (data_inicio),
    KEY idx_cupons_data_fim (data_fim),
    KEY idx_cupons_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupom_cursos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cupom_id BIGINT UNSIGNED NOT NULL,
    curso_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cupom_cursos (cupom_id, curso_id),
    KEY idx_cupom_cursos_cupom (cupom_id),
    KEY idx_cupom_cursos_curso (curso_id),
    CONSTRAINT fk_cupom_cursos_cupom
        FOREIGN KEY (cupom_id) REFERENCES cupons (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cupom_cursos_curso
        FOREIGN KEY (curso_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupons_relacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cupom_id BIGINT UNSIGNED NOT NULL,
    tipo_relacao ENUM('usuario', 'empresa', 'perfil', 'curso_evento', 'tipo_curso', 'cidade', 'estado') NOT NULL,
    valor_relacao VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cupons_relacoes (cupom_id, tipo_relacao, valor_relacao),
    KEY idx_cupons_relacoes_cupom (cupom_id),
    KEY idx_cupons_relacoes_tipo (tipo_relacao),
    KEY idx_cupons_relacoes_valor (valor_relacao),
    CONSTRAINT fk_cupons_relacoes_cupom
        FOREIGN KEY (cupom_id) REFERENCES cupons (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupons_usos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cupom_id BIGINT UNSIGNED NOT NULL,
    pedido_id BIGINT UNSIGNED NOT NULL,
    pedido_cupom_id BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NULL,
    cupom_codigo VARCHAR(80) NOT NULL,
    valor_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cupons_usos_pedido (pedido_id),
    KEY idx_cupons_usos_cupom (cupom_id),
    KEY idx_cupons_usos_pedido_cupom (pedido_cupom_id),
    KEY idx_cupons_usos_usuario (usuario_id),
    KEY idx_cupons_usos_codigo (cupom_codigo),
    KEY idx_cupons_usos_created_at (created_at),
    CONSTRAINT fk_cupons_usos_cupom
        FOREIGN KEY (cupom_id) REFERENCES cupons (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_cupons_usos_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cupons_usos_pedido_cupom
        FOREIGN KEY (pedido_cupom_id) REFERENCES pedidos_cupons (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_cupons_usos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupons_historico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cupom_id BIGINT UNSIGNED NOT NULL,
    acao VARCHAR(120) NOT NULL,
    observacao VARCHAR(500) NULL,
    metadados LONGTEXT NULL,
    alterado_por_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_cupons_historico_cupom (cupom_id),
    KEY idx_cupons_historico_acao (acao),
    KEY idx_cupons_historico_usuario (alterado_por_usuario_id),
    KEY idx_cupons_historico_created_at (created_at),
    CONSTRAINT fk_cupons_historico_cupom
        FOREIGN KEY (cupom_id) REFERENCES cupons (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cupons_historico_usuario
        FOREIGN KEY (alterado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pedidos_cupons
    ADD COLUMN cupom_id BIGINT UNSIGNED NULL AFTER pedido_id,
    ADD COLUMN status ENUM('aplicado', 'cancelado') NOT NULL DEFAULT 'aplicado' AFTER valor_desconto,
    ADD COLUMN observacao VARCHAR(500) NULL AFTER status,
    ADD UNIQUE KEY uk_pedidos_cupons_pedido (pedido_id),
    ADD KEY idx_pedidos_cupons_cupom (cupom_id),
    ADD KEY idx_pedidos_cupons_status (status),
    ADD CONSTRAINT fk_pedidos_cupons_cupom
        FOREIGN KEY (cupom_id) REFERENCES cupons (id)
        ON DELETE SET NULL;

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('cupons', 'ver', 'cupons.ver', 'Ver cupons', 'Listar e consultar cupons do portal', NOW(), NOW(), NULL),
    ('cupons', 'gerenciar', 'cupons.gerenciar', 'Gerenciar cupons', 'Criar, editar e excluir cupons', NOW(), NOW(), NULL),
    ('cupons', 'usos.ver', 'cupons.usos.ver', 'Ver usos de cupons', 'Consultar resumo de usos e aplicacoes', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
CROSS JOIN permissoes per
WHERE p.slug = 'superadmin'
  AND per.slug IN ('cupons.ver', 'cupons.gerenciar', 'cupons.usos.ver')
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('cupons.ver', 'cupons.gerenciar', 'cupons.usos.ver')
WHERE p.slug = 'marketing'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('cupons.ver', 'cupons.usos.ver')
WHERE p.slug = 'financeiro'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

SET FOREIGN_KEY_CHECKS = 1;
