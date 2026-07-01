-- Polo Rainbow - configuracoes globais
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS configuracoes_globais (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome_fantasia VARCHAR(191) NOT NULL,
    razao_social VARCHAR(191) NULL,
    cnpj VARCHAR(20) NULL,
    cidade VARCHAR(191) NULL,
    uf CHAR(2) NULL,
    email_institucional VARCHAR(191) NULL,
    email_financeiro VARCHAR(191) NULL,
    email_suporte VARCHAR(191) NULL,
    email_certificados VARCHAR(191) NULL,
    telefone VARCHAR(30) NULL,
    logo_caminho VARCHAR(255) NULL,
    favicon_caminho VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_configuracoes_globais_deleted_at (deleted_at),
    KEY idx_configuracoes_globais_nome_fantasia (nome_fantasia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes_certificados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prefixo_certificado VARCHAR(20) NOT NULL DEFAULT 'PRC',
    titulo_padrao VARCHAR(191) NULL,
    texto_validacao_publica TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_configuracoes_certificados_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes_financeiras (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    data_corte_financeiro DATE NULL,
    percentual_rateio_maximo DECIMAL(5,2) NOT NULL DEFAULT 75.00,
    observacao_repasse TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_configuracoes_financeiras_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes_frontend (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_visual_portal VARCHAR(80) NOT NULL DEFAULT 'padrao',
    cor_primaria VARCHAR(30) NULL,
    cor_secundaria VARCHAR(30) NULL,
    logo_caminho VARCHAR(255) NULL,
    banner_caminho VARCHAR(255) NULL,
    descricao_home TEXT NULL,
    frontend_card_gap VARCHAR(191) NULL,
    frontend_section_gap VARCHAR(191) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_configuracoes_frontend_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes_seguranca (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    politica_login VARCHAR(20) NOT NULL DEFAULT 'email_cpf',
    validade_reset_senha_minutos INT UNSIGNED NOT NULL DEFAULT 60,
    max_tentativas_login INT UNSIGNED NOT NULL DEFAULT 5,
    tempo_bloqueio_login_minutos INT UNSIGNED NOT NULL DEFAULT 15,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_configuracoes_seguranca_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('configuracoes_globais', 'ver', 'configuracoes_globais.ver', 'Ver configuracoes globais', 'Visualizar configuracoes institucionais e operacionais', NOW(), NOW(), NULL),
    ('configuracoes_globais', 'gerenciar', 'configuracoes_globais.gerenciar', 'Gerenciar configuracoes globais', 'Editar configuracoes institucionais e operacionais', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('configuracoes_globais.ver', 'configuracoes_globais.gerenciar')
WHERE p.slug = 'superadmin'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

SET FOREIGN_KEY_CHECKS = 1;
