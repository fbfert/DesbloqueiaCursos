-- Polo Rainbow - Campos pedagógicos/comerciais do catálogo (cursos_eventos)
-- Compatibilidade: MySQL 5.7
-- Data: 2026-05-10
-- Objetivo:
-- - Adicionar campos opcionais para exibição pública e administrativa
-- - Manter compatibilidade com dados existentes
-- Observação:
-- - MySQL 5.7 não suporta "ADD COLUMN IF NOT EXISTS". Por isso, usamos INFORMATION_SCHEMA + SQL dinâmico.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- valor_promocional
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'cursos_eventos'
              AND COLUMN_NAME = 'valor_promocional'
        ),
        'SELECT \"cursos_eventos.valor_promocional já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN valor_promocional DECIMAL(10,2) NULL AFTER valor'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- objetivo_geral
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'objetivo_geral'
        ),
        'SELECT \"cursos_eventos.objetivo_geral já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN objetivo_geral LONGTEXT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- objetivos_especificos
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'objetivos_especificos'
        ),
        'SELECT \"cursos_eventos.objetivos_especificos já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN objetivos_especificos LONGTEXT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- publico_alvo
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'publico_alvo'
        ),
        'SELECT \"cursos_eventos.publico_alvo já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN publico_alvo LONGTEXT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pre_requisitos_texto
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'pre_requisitos_texto'
        ),
        'SELECT \"cursos_eventos.pre_requisitos_texto já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN pre_requisitos_texto LONGTEXT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pre_requisitos_itens
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'pre_requisitos_itens'
        ),
        'SELECT \"cursos_eventos.pre_requisitos_itens já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN pre_requisitos_itens LONGTEXT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ementa
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'ementa'
        ),
        'SELECT \"cursos_eventos.ementa já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN ementa LONGTEXT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- conteudo_programatico_tipo
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'conteudo_programatico_tipo'
        ),
        'SELECT \"cursos_eventos.conteudo_programatico_tipo já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN conteudo_programatico_tipo VARCHAR(20) NOT NULL DEFAULT ''texto'''
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- conteudo_programatico_texto
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'conteudo_programatico_texto'
        ),
        'SELECT \"cursos_eventos.conteudo_programatico_texto já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN conteudo_programatico_texto LONGTEXT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- conteudo_programatico_modulos
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'conteudo_programatico_modulos'
        ),
        'SELECT \"cursos_eventos.conteudo_programatico_modulos já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN conteudo_programatico_modulos LONGTEXT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- metodologia
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'metodologia'
        ),
        'SELECT \"cursos_eventos.metodologia já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN metodologia LONGTEXT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- produto_final
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'produto_final'
        ),
        'SELECT \"cursos_eventos.produto_final já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN produto_final LONGTEXT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- avaliacao
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos_eventos' AND COLUMN_NAME = 'avaliacao'
        ),
        'SELECT \"cursos_eventos.avaliacao já existe\"',
        'ALTER TABLE cursos_eventos ADD COLUMN avaliacao LONGTEXT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

