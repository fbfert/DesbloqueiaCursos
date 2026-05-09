-- Polo Rainbow - LMS da area interna do curso
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SET @schema_name := DATABASE();

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'modulos'
      AND COLUMN_NAME = 'status'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE modulos ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''publicado'' AFTER visivel',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'modulos'
      AND COLUMN_NAME = 'criado_por'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE modulos ADD COLUMN criado_por BIGINT UNSIGNED NULL AFTER status',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'modulos'
      AND COLUMN_NAME = 'atualizado_por'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE modulos ADD COLUMN atualizado_por BIGINT UNSIGNED NULL AFTER criado_por',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'aulas'
      AND COLUMN_NAME = 'status'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE aulas ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''publicado'' AFTER visivel',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'aulas'
      AND COLUMN_NAME = 'criado_por'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE aulas ADD COLUMN criado_por BIGINT UNSIGNED NULL AFTER status',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'aulas'
      AND COLUMN_NAME = 'atualizado_por'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE aulas ADD COLUMN atualizado_por BIGINT UNSIGNED NULL AFTER criado_por',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
