-- Desbloqueia Cursos - módulo de presentes/cortesias promocionais
-- Compatibilidade: MySQL 5.7
-- Objetivo: permitir campanhas de presentes com rastreabilidade, acesso com expiração e exclusão explícita de financeiro/rateio.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS presentes_campanhas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(191) NOT NULL,
    justificativa TEXT NOT NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    acesso_tipo ENUM('sem_prazo', 'dias', 'data') NOT NULL DEFAULT 'sem_prazo',
    acesso_dias INT UNSIGNED NULL,
    acesso_expira_em DATETIME NULL,
    email_modelo_evento VARCHAR(120) NULL,
    email_assunto VARCHAR(255) NOT NULL,
    email_corpo LONGTEXT NOT NULL,
    status ENUM('ativo', 'parcialmente_cancelado', 'cancelado', 'expirado') NOT NULL DEFAULT 'ativo',
    criado_por_usuario_id BIGINT UNSIGNED NULL,
    atualizado_por_usuario_id BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_presentes_campanhas_curso (curso_evento_id),
    KEY idx_presentes_campanhas_turma (turma_id),
    KEY idx_presentes_campanhas_status (status),
    KEY idx_presentes_campanhas_criado_por (criado_por_usuario_id),
    KEY idx_presentes_campanhas_deleted_at (deleted_at),
    CONSTRAINT fk_presentes_campanhas_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_presentes_campanhas_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_presentes_campanhas_criado_por
        FOREIGN KEY (criado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_presentes_campanhas_atualizado_por
        FOREIGN KEY (atualizado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS presentes_beneficiarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campanha_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    pedido_id BIGINT UNSIGNED NOT NULL,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    acesso_expira_em DATETIME NULL,
    status ENUM('ativo', 'cancelado', 'expirado') NOT NULL DEFAULT 'ativo',
    cancelado_por_usuario_id BIGINT UNSIGNED NULL,
    cancelado_em DATETIME NULL,
    cancelamento_tipo VARCHAR(40) NULL,
    cancelamento_justificativa VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_presentes_beneficiarios_campanha_usuario (campanha_id, usuario_id),
    KEY idx_presentes_beneficiarios_campanha (campanha_id),
    KEY idx_presentes_beneficiarios_usuario (usuario_id),
    KEY idx_presentes_beneficiarios_pedido (pedido_id),
    KEY idx_presentes_beneficiarios_inscricao (inscricao_id),
    KEY idx_presentes_beneficiarios_status (status),
    KEY idx_presentes_beneficiarios_deleted_at (deleted_at),
    CONSTRAINT fk_presentes_beneficiarios_campanha
        FOREIGN KEY (campanha_id) REFERENCES presentes_campanhas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_presentes_beneficiarios_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_presentes_beneficiarios_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_presentes_beneficiarios_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_presentes_beneficiarios_cancelado_por
        FOREIGN KEY (cancelado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'pedidos'
              AND COLUMN_NAME = 'is_presente'
        ),
        'SELECT "pedidos.is_presente já existe"',
        'ALTER TABLE pedidos ADD COLUMN is_presente TINYINT(1) NOT NULL DEFAULT 0 AFTER canal_origem'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'pedidos'
              AND COLUMN_NAME = 'presente_campanha_id'
        ),
        'SELECT "pedidos.presente_campanha_id já existe"',
        'ALTER TABLE pedidos ADD COLUMN presente_campanha_id BIGINT UNSIGNED NULL AFTER is_presente'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'pedidos'
              AND COLUMN_NAME = 'presente_titulo'
        ),
        'SELECT "pedidos.presente_titulo já existe"',
        'ALTER TABLE pedidos ADD COLUMN presente_titulo VARCHAR(191) NULL AFTER presente_campanha_id'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'pedidos'
              AND COLUMN_NAME = 'presente_justificativa'
        ),
        'SELECT "pedidos.presente_justificativa já existe"',
        'ALTER TABLE pedidos ADD COLUMN presente_justificativa VARCHAR(500) NULL AFTER presente_titulo'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'pedidos'
              AND COLUMN_NAME = 'presente_concedido_em'
        ),
        'SELECT "pedidos.presente_concedido_em já existe"',
        'ALTER TABLE pedidos ADD COLUMN presente_concedido_em DATETIME NULL AFTER presente_justificativa'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'pedidos'
              AND COLUMN_NAME = 'is_presente'
        ),
        'SELECT "pedidos.idx_is_presente já existe"',
        'ALTER TABLE pedidos ADD KEY idx_pedidos_is_presente (is_presente)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'pedidos'
              AND COLUMN_NAME = 'presente_campanha_id'
        ),
        'SELECT "pedidos.idx_presente_campanha_id já existe"',
        'ALTER TABLE pedidos ADD KEY idx_pedidos_presente_campanha (presente_campanha_id)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'pedidos'
              AND CONSTRAINT_NAME = 'fk_pedidos_presente_campanha'
        ),
        'SELECT "fk_pedidos_presente_campanha já existe"',
        'ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_presente_campanha FOREIGN KEY (presente_campanha_id) REFERENCES presentes_campanhas (id) ON DELETE SET NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'inscricoes'
              AND COLUMN_NAME = 'is_presente'
        ),
        'SELECT "inscricoes.is_presente já existe"',
        'ALTER TABLE inscricoes ADD COLUMN is_presente TINYINT(1) NOT NULL DEFAULT 0 AFTER status'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'inscricoes'
              AND COLUMN_NAME = 'presente_campanha_id'
        ),
        'SELECT "inscricoes.presente_campanha_id já existe"',
        'ALTER TABLE inscricoes ADD COLUMN presente_campanha_id BIGINT UNSIGNED NULL AFTER is_presente'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'inscricoes'
              AND COLUMN_NAME = 'acesso_expira_em'
        ),
        'SELECT "inscricoes.acesso_expira_em já existe"',
        'ALTER TABLE inscricoes ADD COLUMN acesso_expira_em DATETIME NULL AFTER confirmado_em'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'inscricoes'
              AND COLUMN_NAME = 'presente_campanha_id'
        ),
        'SELECT "inscricoes.idx_presente_campanha_id já existe"',
        'ALTER TABLE inscricoes ADD KEY idx_inscricoes_presente_campanha (presente_campanha_id)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'inscricoes'
              AND COLUMN_NAME = 'is_presente'
        ),
        'SELECT "inscricoes.idx_is_presente já existe"',
        'ALTER TABLE inscricoes ADD KEY idx_inscricoes_is_presente (is_presente)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'inscricoes'
              AND CONSTRAINT_NAME = 'fk_inscricoes_presente_campanha'
        ),
        'SELECT "fk_inscricoes_presente_campanha já existe"',
        'ALTER TABLE inscricoes ADD CONSTRAINT fk_inscricoes_presente_campanha FOREIGN KEY (presente_campanha_id) REFERENCES presentes_campanhas (id) ON DELETE SET NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO emails_modelos
    (evento, template, nome, assunto, corpo_html, gatilho_descricao, variaveis_json, ativo, editavel, created_at, updated_at, deleted_at)
VALUES
    ('email.presente_concedido', 'presente_concedido', 'Curso recebido como presente', 'Você ganhou acesso a um curso na Desbloqueia Cursos', '', 'Enviado quando um presente promocional é concedido a um usuário.', '["{nome_usuario}","{nome_curso}","{nome_turma}","{prazo_acesso}","{link_meus_cursos}","{nome_plataforma}"]', 1, 1, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    assunto = VALUES(assunto),
    gatilho_descricao = VALUES(gatilho_descricao),
    variaveis_json = VALUES(variaveis_json),
    ativo = VALUES(ativo),
    editavel = VALUES(editavel),
    updated_at = NOW();

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('promocionais', 'presentes.ver', 'promocionais.presentes.ver', 'Ver presentes', 'Listar campanhas e presentes promocionais', NOW(), NOW(), NULL),
    ('promocionais', 'presentes.gerenciar', 'promocionais.presentes.gerenciar', 'Gerenciar presentes', 'Criar campanhas de presentes promocionais', NOW(), NOW(), NULL),
    ('promocionais', 'presentes.cancelar', 'promocionais.presentes.cancelar', 'Cancelar presentes', 'Revogar presentes concedidos', NOW(), NOW(), NULL),
    ('promocionais', 'presentes.cancelar_lote', 'promocionais.presentes.cancelar_lote', 'Cancelar presentes em lote', 'Revogar presentes selecionados em massa', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('promocionais.presentes.ver', 'promocionais.presentes.gerenciar', 'promocionais.presentes.cancelar', 'promocionais.presentes.cancelar_lote')
WHERE p.slug = 'superadmin'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('promocionais.presentes.ver', 'promocionais.presentes.gerenciar', 'promocionais.presentes.cancelar', 'promocionais.presentes.cancelar_lote')
WHERE p.slug = 'marketing'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

SET FOREIGN_KEY_CHECKS = 1;
