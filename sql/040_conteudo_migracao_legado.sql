CREATE TABLE IF NOT EXISTS conteudo_migracao_legado (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    origem_tabela VARCHAR(80) NOT NULL,
    origem_id BIGINT UNSIGNED NOT NULL,
    destino_tabela VARCHAR(80) NOT NULL,
    destino_id BIGINT UNSIGNED NOT NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    tipo_destino VARCHAR(80) NULL,
    status ENUM('migrado','ignorado','erro') NOT NULL DEFAULT 'migrado',
    observacao TEXT NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_conteudo_migracao_origem_destino (origem_tabela, origem_id, destino_tabela),
    KEY idx_conteudo_migracao_curso (curso_evento_id),
    KEY idx_conteudo_migracao_status (status),
    KEY idx_conteudo_migracao_turma (turma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

