-- Desbloqueia Cursos - escopo de cupons por cursos
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS cupom_cursos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cupom_id BIGINT UNSIGNED NOT NULL,
    curso_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cupom_cursos (cupom_id, curso_id),
    KEY idx_cupom_cursos_cupom (cupom_id),
    KEY idx_cupom_cursos_curso (curso_id),
    CONSTRAINT fk_cupom_cursos_cupom
        FOREIGN KEY (cupom_id) REFERENCES cupons (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cupom_cursos_curso
        FOREIGN KEY (curso_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO cupom_cursos (cupom_id, curso_id, created_at)
SELECT DISTINCT cr.cupom_id, CAST(cr.valor_relacao AS UNSIGNED), NOW()
FROM cupons_relacoes cr
INNER JOIN cupons c ON c.id = cr.cupom_id
WHERE cr.tipo_relacao = 'curso_evento'
  AND cr.valor_relacao REGEXP '^[0-9]+$'
  AND c.deleted_at IS NULL;

-- A coluna cupons.escopo não era criada por nenhuma migração (existia só em
-- produção); num banco novo este UPDATE falhava. Criada aqui de forma condicional
-- (07/10/2026) — no-op onde já existe. A 081 repete a garantia para bancos em que
-- esta migração já rodou e falhou.
SET @has_cupons_escopo_033 := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cupons' AND COLUMN_NAME = 'escopo'
);
SET @sql_cupons_escopo_033 := IF(
    @has_cupons_escopo_033 = 0,
    'ALTER TABLE cupons ADD COLUMN escopo VARCHAR(40) NOT NULL DEFAULT ''todo_site'' AFTER descricao',
    'SELECT 1'
);
PREPARE stmt_cupons_escopo_033 FROM @sql_cupons_escopo_033;
EXECUTE stmt_cupons_escopo_033;
DEALLOCATE PREPARE stmt_cupons_escopo_033;

UPDATE cupons c
INNER JOIN (
    SELECT DISTINCT cupom_id
    FROM cupom_cursos
) cc ON cc.cupom_id = c.id
SET c.escopo = 'cursos_especificos',
    c.updated_at = NOW()
WHERE c.deleted_at IS NULL;

SET FOREIGN_KEY_CHECKS = 1;
