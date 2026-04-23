-- Polo Rainbow - Certificados
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS certificados_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    descricao VARCHAR(255) NULL,
    corpo_html LONGTEXT NULL,
    cor_fundo VARCHAR(20) NULL,
    cor_texto VARCHAR(20) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    padrao TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_certificados_templates_slug (slug),
    KEY idx_certificados_templates_ativo (ativo),
    KEY idx_certificados_templates_padrao (padrao),
    KEY idx_certificados_templates_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certificados_assinantes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    certificado_template_id INT UNSIGNED NULL,
    nome VARCHAR(191) NOT NULL,
    cargo VARCHAR(120) NOT NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_certificados_assinantes_curso (curso_evento_id),
    KEY idx_certificados_assinantes_template (certificado_template_id),
    KEY idx_certificados_assinantes_status (status),
    KEY idx_certificados_assinantes_deleted_at (deleted_at),
    CONSTRAINT fk_certificados_assinantes_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_certificados_assinantes_template
        FOREIGN KEY (certificado_template_id) REFERENCES certificados_templates (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certificados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    pedido_id BIGINT UNSIGNED NULL,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    participante_pedido_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    codigo VARCHAR(80) NOT NULL,
    versao INT UNSIGNED NOT NULL DEFAULT 1,
    nome_participante VARCHAR(191) NOT NULL,
    cpf_participante VARCHAR(11) NOT NULL,
    cpf_mascarado VARCHAR(20) NOT NULL,
    titulo VARCHAR(191) NOT NULL,
    status ENUM('emitido', 'cancelado', 'revogado', 'substituido') NOT NULL DEFAULT 'emitido',
    pdf_caminho VARCHAR(255) NULL,
    pdf_nome_original VARCHAR(255) NULL,
    emitido_por_usuario_id BIGINT UNSIGNED NULL,
    emitido_em DATETIME NOT NULL,
    cancelado_por_usuario_id BIGINT UNSIGNED NULL,
    cancelado_em DATETIME NULL,
    revogado_por_usuario_id BIGINT UNSIGNED NULL,
    revogado_em DATETIME NULL,
    reemissao_de_certificado_id BIGINT UNSIGNED NULL,
    substituido_por_certificado_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_certificados_codigo (codigo),
    KEY idx_certificados_template (template_id),
    KEY idx_certificados_curso (curso_evento_id),
    KEY idx_certificados_turma (turma_id),
    KEY idx_certificados_pedido (pedido_id),
    KEY idx_certificados_inscricao (inscricao_id),
    KEY idx_certificados_participante (participante_pedido_id),
    KEY idx_certificados_status (status),
    KEY idx_certificados_emitido_por (emitido_por_usuario_id),
    KEY idx_certificados_reemissao_de (reemissao_de_certificado_id),
    KEY idx_certificados_substituido_por (substituido_por_certificado_id),
    KEY idx_certificados_deleted_at (deleted_at),
    CONSTRAINT fk_certificados_template
        FOREIGN KEY (template_id) REFERENCES certificados_templates (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_certificados_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_certificados_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_certificados_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_certificados_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_certificados_participante
        FOREIGN KEY (participante_pedido_id) REFERENCES participantes_pedido (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_certificados_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_certificados_emitido_por
        FOREIGN KEY (emitido_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_certificados_cancelado_por
        FOREIGN KEY (cancelado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_certificados_revogado_por
        FOREIGN KEY (revogado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_certificados_reemissao_de
        FOREIGN KEY (reemissao_de_certificado_id) REFERENCES certificados (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_certificados_substituido_por
        FOREIGN KEY (substituido_por_certificado_id) REFERENCES certificados (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certificados_historico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    certificado_id BIGINT UNSIGNED NOT NULL,
    status_anterior VARCHAR(40) NULL,
    status_novo VARCHAR(40) NOT NULL,
    observacao VARCHAR(500) NULL,
    alterado_por_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_certificados_historico_certificado (certificado_id),
    KEY idx_certificados_historico_usuario (alterado_por_usuario_id),
    CONSTRAINT fk_certificados_historico_certificado
        FOREIGN KEY (certificado_id) REFERENCES certificados (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_certificados_historico_usuario
        FOREIGN KEY (alterado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certificados_validacao_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    certificado_id BIGINT UNSIGNED NULL,
    codigo VARCHAR(80) NOT NULL,
    cpf_informado VARCHAR(11) NULL,
    resultado VARCHAR(40) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_certificados_validacao_logs_certificado (certificado_id),
    KEY idx_certificados_validacao_logs_codigo (codigo),
    KEY idx_certificados_validacao_logs_resultado (resultado),
    KEY idx_certificados_validacao_logs_created_at (created_at),
    CONSTRAINT fk_certificados_validacao_logs_certificado
        FOREIGN KEY (certificado_id) REFERENCES certificados (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('certificados', 'ver', 'certificados.ver', 'Ver certificados', 'Consultar certificados emitidos e aptos', NOW(), NOW(), NULL),
    ('certificados', 'gerenciar', 'certificados.gerenciar', 'Gerenciar certificados', 'Emitir, reemitir, cancelar e revogar certificados', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('certificados.ver', 'certificados.gerenciar')
WHERE p.slug IN ('superadmin', 'conteudo')
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug = 'certificados.ver'
WHERE p.slug = 'atendimento'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO certificados_templates (nome, slug, descricao, corpo_html, cor_fundo, cor_texto, ativo, padrao, created_at, updated_at, deleted_at)
VALUES
    ('Padrao Polo Rainbow', 'padrao', 'Template padrao para certificados do portal', NULL, '#ffffff', '#111827', 1, 1, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    corpo_html = VALUES(corpo_html),
    cor_fundo = VALUES(cor_fundo),
    cor_texto = VALUES(cor_texto),
    ativo = VALUES(ativo),
    padrao = VALUES(padrao),
    updated_at = NOW();

SET FOREIGN_KEY_CHECKS = 1;
