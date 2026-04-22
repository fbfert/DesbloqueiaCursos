-- Polo Rainbow - RBAC e controle de acesso
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS perfis (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    descricao VARCHAR(255) NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    sistema TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_perfis_slug (slug),
    KEY idx_perfis_status (status),
    KEY idx_perfis_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissoes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    modulo VARCHAR(80) NOT NULL,
    acao VARCHAR(80) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    nome VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_permissoes_slug (slug),
    KEY idx_permissoes_modulo_acao (modulo, acao),
    KEY idx_permissoes_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_perfis (
    usuario_id BIGINT UNSIGNED NOT NULL,
    perfil_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (usuario_id, perfil_id),
    KEY idx_usuario_perfis_perfil (perfil_id),
    CONSTRAINT fk_usuario_perfis_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_usuario_perfis_perfil
        FOREIGN KEY (perfil_id) REFERENCES perfis (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS perfil_permissoes (
    perfil_id INT UNSIGNED NOT NULL,
    permissao_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (perfil_id, permissao_id),
    KEY idx_perfil_permissoes_permissao (permissao_id),
    CONSTRAINT fk_perfil_permissoes_perfil
        FOREIGN KEY (perfil_id) REFERENCES perfis (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_perfil_permissoes_permissao
        FOREIGN KEY (permissao_id) REFERENCES permissoes (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    acao VARCHAR(120) NOT NULL,
    entidade_tipo VARCHAR(120) NOT NULL,
    entidade_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    metadados LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_auditoria_usuario (usuario_id),
    KEY idx_auditoria_entidade (entidade_tipo, entidade_id),
    KEY idx_auditoria_acao (acao),
    KEY idx_auditoria_created_at (created_at),
    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lixeira (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entidade_tipo VARCHAR(120) NOT NULL,
    entidade_id BIGINT UNSIGNED NOT NULL,
    justificativa VARCHAR(500) NOT NULL,
    snapshot_dados LONGTEXT NULL,
    excluido_por_usuario_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    restaurado_por_usuario_id BIGINT UNSIGNED NULL,
    restaurado_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_lixeira_entidade (entidade_tipo, entidade_id),
    KEY idx_lixeira_excluido_por (excluido_por_usuario_id),
    KEY idx_lixeira_restaurado_por (restaurado_por_usuario_id),
    KEY idx_lixeira_created_at (created_at),
    CONSTRAINT fk_lixeira_excluido_por
        FOREIGN KEY (excluido_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_lixeira_restaurado_por
        FOREIGN KEY (restaurado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO perfis (nome, slug, descricao, status, sistema, created_at, updated_at, deleted_at)
VALUES
    ('Superadmin', 'superadmin', 'Acesso total ao portal', 'ativo', 1, NOW(), NOW(), NULL),
    ('Atendimento', 'atendimento', 'Atendimento e suporte operacional', 'ativo', 1, NOW(), NOW(), NULL),
    ('Financeiro', 'financeiro', 'Gestao financeira e conciliacao', 'ativo', 1, NOW(), NOW(), NULL),
    ('Conteudo', 'conteudo', 'Gestao de cursos e conteudos', 'ativo', 1, NOW(), NOW(), NULL),
    ('Marketing', 'marketing', 'Operacao de marketing e comunicacao', 'ativo', 1, NOW(), NOW(), NULL),
    ('Professor', 'professor', 'Acesso restrito para professor', 'ativo', 1, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    status = VALUES(status),
    sistema = VALUES(sistema),
    updated_at = NOW();

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('rbac', 'dashboard.ver', 'rbac.dashboard.ver', 'Ver painel RBAC', 'Acessar a tela de controle de acesso', NOW(), NOW(), NULL),
    ('rbac', 'perfis.ver', 'rbac.perfis.ver', 'Ver perfis', 'Listar perfis do sistema', NOW(), NOW(), NULL),
    ('rbac', 'perfis.gerenciar', 'rbac.perfis.gerenciar', 'Gerenciar perfis', 'Atualizar vinculacoes de perfis', NOW(), NOW(), NULL),
    ('rbac', 'permissoes.ver', 'rbac.permissoes.ver', 'Ver permissoes', 'Listar permissoes do sistema', NOW(), NOW(), NULL),
    ('rbac', 'permissoes.gerenciar', 'rbac.permissoes.gerenciar', 'Gerenciar permissoes', 'Atualizar vinculacoes de permissoes', NOW(), NOW(), NULL),
    ('usuarios', 'ver', 'usuarios.ver', 'Ver usuarios', 'Consultar usuarios do sistema', NOW(), NOW(), NULL),
    ('usuarios', 'gerenciar', 'usuarios.gerenciar', 'Gerenciar usuarios', 'Administrar usuarios do sistema', NOW(), NOW(), NULL),
    ('conteudo', 'ver', 'conteudo.ver', 'Ver conteudo', 'Acessar area de conteudo', NOW(), NOW(), NULL),
    ('conteudo', 'gerenciar', 'conteudo.gerenciar', 'Gerenciar conteudo', 'Administrar conteudos', NOW(), NOW(), NULL),
    ('pedidos', 'ver', 'pedidos.ver', 'Ver pedidos', 'Consultar pedidos', NOW(), NOW(), NULL),
    ('pedidos', 'gerenciar', 'pedidos.gerenciar', 'Gerenciar pedidos', 'Administrar pedidos', NOW(), NOW(), NULL),
    ('financeiro', 'ver', 'financeiro.ver', 'Ver financeiro', 'Consultar financeiro', NOW(), NOW(), NULL),
    ('financeiro', 'gerenciar', 'financeiro.gerenciar', 'Gerenciar financeiro', 'Administrar financeiro', NOW(), NOW(), NULL),
    ('marketing', 'ver', 'marketing.ver', 'Ver marketing', 'Consultar marketing', NOW(), NOW(), NULL),
    ('marketing', 'gerenciar', 'marketing.gerenciar', 'Gerenciar marketing', 'Administrar marketing', NOW(), NOW(), NULL),
    ('professor', 'ver', 'professor.ver', 'Ver area do professor', 'Acessar area do professor', NOW(), NOW(), NULL),
    ('professor', 'gerenciar', 'professor.gerenciar', 'Gerenciar area do professor', 'Administrar area do professor', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
CROSS JOIN permissoes per
WHERE p.slug = 'superadmin'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('rbac.dashboard.ver', 'usuarios.ver', 'usuarios.gerenciar', 'pedidos.ver')
WHERE p.slug = 'atendimento'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('financeiro.ver', 'financeiro.gerenciar', 'pedidos.ver')
WHERE p.slug = 'financeiro'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('conteudo.ver', 'conteudo.gerenciar')
WHERE p.slug = 'conteudo'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('marketing.ver', 'marketing.gerenciar')
WHERE p.slug = 'marketing'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('professor.ver')
WHERE p.slug = 'professor'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

SET FOREIGN_KEY_CHECKS = 1;
