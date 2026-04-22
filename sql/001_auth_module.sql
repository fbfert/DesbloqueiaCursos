-- Polo Rainbow - modulo inicial de autenticacao
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    telefone VARCHAR(30) NULL,
    senha_hash VARCHAR(255) NOT NULL,
    status ENUM('ativo', 'inativo', 'bloqueado') NOT NULL DEFAULT 'ativo',
    tentativas_login INT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    token_recuperacao VARCHAR(100) NULL,
    token_recuperacao_expira_em DATETIME NULL,
    ultimo_login_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_email (email),
    UNIQUE KEY uk_usuarios_cpf (cpf),
    KEY idx_usuarios_status (status),
    KEY idx_usuarios_bloqueado_ate (bloqueado_ate),
    KEY idx_usuarios_token_recuperacao (token_recuperacao),
    KEY idx_usuarios_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_consentimentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    versao VARCHAR(20) NOT NULL,
    obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
    consentido TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    revogado_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_usuario_consentimentos_usuario (usuario_id),
    KEY idx_usuario_consentimentos_tipo (tipo),
    KEY idx_usuario_consentimentos_revogado_em (revogado_em),
    CONSTRAINT fk_usuario_consentimentos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acessos_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    evento VARCHAR(80) NOT NULL,
    resultado VARCHAR(80) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    metadados TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_acessos_logs_usuario (usuario_id),
    KEY idx_acessos_logs_evento_resultado (evento, resultado),
    KEY idx_acessos_logs_created_at (created_at),
    CONSTRAINT fk_acessos_logs_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
