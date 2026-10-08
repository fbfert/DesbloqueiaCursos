-- 082 — App do aluno (API /api/app/v1): tokens, dispositivos, notificações e limite de taxa
-- Compatibilidade: MySQL 5.7 / MariaDB 10.5. Aditiva: só CREATE TABLE IF NOT EXISTS.
-- Pode rodar mais de uma vez. Rollback: DROP TABLE das quatro tabelas (o site não as usa).
--
-- TOKENS NUNCA FICAM EM CLARO
--
-- app_tokens guarda o SHA-256 do token opaco (32 bytes aleatórios). Um dump desta
-- tabela não permite usar nenhuma sessão do app.
--
-- ROTAÇÃO DO REFRESH E DETECÇÃO DE REUSO
--
-- Cada login abre uma "família" (familia, 32 hex). Todo refresh trocado vira
-- consumido (consumido_em) e aponta para o sucessor (substituido_por_id). Se um
-- refresh já consumido for apresentado de novo, alguém copiou o token: a API revoga
-- todos os tokens do dispositivo (device_id) e responde 401 sessao_revogada.
--
-- HORÁRIOS
--
-- Os horários destas tabelas são gravados e comparados pelo PHP no fuso da aplicação
-- (APP_TIMEZONE, padrão America/Sao_Paulo) — nunca com NOW() do banco. O PHP e o
-- MySQL da VPS não estão no mesmo fuso; misturar os dois já desligou um bloqueio de
-- login neste projeto (ver NorminhaPublicoLimiteService).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS app_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    device_name VARCHAR(150) NULL DEFAULT NULL,
    tipo ENUM('access','refresh') NOT NULL,
    token_hash CHAR(64) NOT NULL COMMENT 'sha256 do token opaco; nunca o token em claro',
    familia CHAR(32) NOT NULL COMMENT 'sessão do login; todos os tokens rotacionados a partir dele',
    parent_id BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'refresh que gerou este token',
    substituido_por_id BIGINT UNSIGNED NULL DEFAULT NULL,
    expira_em DATETIME NOT NULL,
    consumido_em DATETIME NULL DEFAULT NULL COMMENT 'refresh já trocado por um novo par',
    revogado_em DATETIME NULL DEFAULT NULL,
    revogado_motivo VARCHAR(40) NULL DEFAULT NULL,
    ultimo_uso_em DATETIME NULL DEFAULT NULL,
    ip VARCHAR(45) NULL DEFAULT NULL,
    user_agent VARCHAR(255) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_app_tokens_hash (token_hash),
    KEY idx_app_tokens_usuario_device (usuario_id, device_id),
    KEY idx_app_tokens_device (device_id),
    KEY idx_app_tokens_familia (familia),
    KEY idx_app_tokens_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_dispositivos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    fcm_token VARCHAR(512) NULL DEFAULT NULL,
    plataforma VARCHAR(20) NOT NULL DEFAULT 'android',
    app_versao VARCHAR(40) NULL DEFAULT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    desativado_motivo VARCHAR(60) NULL DEFAULT NULL,
    ultimo_envio_em DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_app_dispositivos_device (device_id),
    KEY idx_app_dispositivos_usuario_ativo (usuario_id, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_notificacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    corpo VARCHAR(500) NOT NULL,
    dados TEXT NULL COMMENT 'JSON com as chaves de contexto (inscricao_id, item_id, pedido_id...)',
    lida_em DATETIME NULL DEFAULT NULL,
    envio_status ENUM('pendente','enviado','falhou','sem_dispositivo') NOT NULL DEFAULT 'pendente',
    tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_erro VARCHAR(500) NULL DEFAULT NULL,
    enviado_em DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_app_notificacoes_usuario (usuario_id, id),
    KEY idx_app_notificacoes_envio (envio_status, tentativas, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limite de taxa de login/cadastro/recuperação do app. A chave é um HASH
-- (sha256 com a APP_KEY como sal) de escopo + IP ou login: conta tentativas sem
-- guardar o endereço nem o e-mail/CPF em claro.
CREATE TABLE IF NOT EXISTS app_limites_taxa (
    chave_hash CHAR(64) NOT NULL,
    escopo VARCHAR(40) NOT NULL,
    janela_inicio DATETIME NOT NULL,
    contagem INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (chave_hash),
    KEY idx_app_limites_taxa_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
