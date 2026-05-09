-- Polo Rainbow - criterios de conclusao do mini-LMS
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS lms_criterios_conclusao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    exigir_progresso TINYINT(1) NOT NULL DEFAULT 1,
    progresso_minimo DECIMAL(5,2) NOT NULL DEFAULT 75.00,
    exigir_atividades TINYINT(1) NOT NULL DEFAULT 0,
    criterio_atividades VARCHAR(30) NOT NULL DEFAULT 'nenhuma',
    nota_minima_atividades DECIMAL(5,2) NULL,
    exigir_presenca TINYINT(1) NOT NULL DEFAULT 0,
    presenca_minima DECIMAL(5,2) NOT NULL DEFAULT 75.00,
    exigir_avaliacao TINYINT(1) NOT NULL DEFAULT 0,
    nota_minima_avaliacao DECIMAL(5,2) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_lms_criterios_conclusao_contexto (curso_id, turma_id, ativo),
    KEY idx_lms_criterios_conclusao_curso (curso_id),
    KEY idx_lms_criterios_conclusao_turma (turma_id),
    KEY idx_lms_criterios_conclusao_ativo (ativo),
    KEY idx_lms_criterios_conclusao_deleted_at (deleted_at),
    CONSTRAINT fk_lms_criterios_conclusao_curso
        FOREIGN KEY (curso_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lms_criterios_conclusao_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lms_criterios_conclusao_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_lms_criterios_conclusao_atualizado_por
        FOREIGN KEY (atualizado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
