-- Polo Rainbow - Emails transacionais
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS configuracoes_email (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    host VARCHAR(191) NOT NULL,
    porta INT UNSIGNED NOT NULL DEFAULT 587,
    usuario VARCHAR(191) NULL,
    senha VARCHAR(255) NULL,
    criptografia VARCHAR(20) NOT NULL DEFAULT 'tls',
    from_email VARCHAR(191) NOT NULL,
    from_name VARCHAR(191) NOT NULL,
    reply_to_email VARCHAR(191) NULL,
    fila_ativa TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_configuracoes_email_ativo (ativo),
    KEY idx_configuracoes_email_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS emails_envios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    entidade_tipo VARCHAR(120) NULL,
    entidade_id BIGINT UNSIGNED NULL,
    evento VARCHAR(120) NOT NULL,
    template VARCHAR(120) NOT NULL,
    destinatario_email VARCHAR(191) NOT NULL,
    destinatario_nome VARCHAR(191) NULL,
    assunto VARCHAR(255) NOT NULL,
    contexto_json LONGTEXT NULL,
    status ENUM('pendente', 'enviado', 'falhou') NOT NULL DEFAULT 'pendente',
    tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_erro TEXT NULL,
    resposta_smtp LONGTEXT NULL,
    enviado_em DATETIME NULL,
    falhou_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_emails_envios_status (status),
    KEY idx_emails_envios_evento (evento),
    KEY idx_emails_envios_destinatario (destinatario_email),
    KEY idx_emails_envios_entidade (entidade_tipo, entidade_id),
    KEY idx_emails_envios_usuario (usuario_id),
    KEY idx_emails_envios_created_at (created_at),
    CONSTRAINT fk_emails_envios_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('emails', 'ver', 'emails.ver', 'Ver emails', 'Consultar configuracoes e fila de emails', NOW(), NOW(), NULL),
    ('emails', 'gerenciar', 'emails.gerenciar', 'Gerenciar emails', 'Atualizar configuracao SMTP e fila de emails', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

SET FOREIGN_KEY_CHECKS = 1;
