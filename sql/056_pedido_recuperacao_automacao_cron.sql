-- Desbloqueia Cursos - automacao por Cron para recuperacao de pedidos
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE configuracoes_seguranca
    ADD COLUMN recuperacao_pedidos_automatica_ativa TINYINT(1) NOT NULL DEFAULT 0 AFTER tempo_bloqueio_login_minutos,
    ADD COLUMN recuperacao_pedidos_processamento_limite INT UNSIGNED NOT NULL DEFAULT 50 AFTER recuperacao_pedidos_automatica_ativa;

ALTER TABLE pedido_recuperacao_logs
    ADD COLUMN etapa VARCHAR(20) NULL AFTER tipo_envio,
    ADD COLUMN execucao_id BIGINT UNSIGNED NULL AFTER etapa,
    ADD KEY idx_pedido_recuperacao_logs_execucao (execucao_id);

CREATE TABLE IF NOT EXISTS pedido_recuperacao_execucoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    status VARCHAR(20) NOT NULL DEFAULT 'running',
    modo VARCHAR(20) NOT NULL DEFAULT 'cron',
    dry_run TINYINT(1) NOT NULL DEFAULT 0,
    limite_processamento INT UNSIGNED NOT NULL DEFAULT 50,
    total_analisados INT UNSIGNED NOT NULL DEFAULT 0,
    total_processados INT UNSIGNED NOT NULL DEFAULT 0,
    total_enviados INT UNSIGNED NOT NULL DEFAULT 0,
    total_ignorados INT UNSIGNED NOT NULL DEFAULT 0,
    total_bloqueados INT UNSIGNED NOT NULL DEFAULT 0,
    total_erros INT UNSIGNED NOT NULL DEFAULT 0,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pedido_recuperacao_execucoes_status (status),
    KEY idx_pedido_recuperacao_execucoes_started_at (started_at),
    KEY idx_pedido_recuperacao_execucoes_finished_at (finished_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pedido_recuperacao_logs
    ADD CONSTRAINT fk_pedido_recuperacao_logs_execucao
    FOREIGN KEY (execucao_id) REFERENCES pedido_recuperacao_execucoes (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
