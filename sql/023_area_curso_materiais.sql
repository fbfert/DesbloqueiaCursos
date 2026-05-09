-- Polo Rainbow - Materiais protegidos da area interna do curso
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SET @schema_name := DATABASE();

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'materiais'
      AND COLUMN_NAME = 'tipo_material'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE materiais ADD COLUMN tipo_material VARCHAR(40) NOT NULL DEFAULT ''arquivo_protegido'' AFTER aula_id',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'materiais'
      AND COLUMN_NAME = 'url'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE materiais ADD COLUMN url VARCHAR(500) NULL AFTER tipo_arquivo',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'materiais'
      AND COLUMN_NAME = 'status'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE materiais ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''publicado'' AFTER visivel',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @status_missing := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'materiais'
      AND COLUMN_NAME = 'status'
);
SET @sql := IF(
    @status_missing = 1 AND @column_exists = 0,
    'UPDATE materiais SET status = CASE WHEN visivel = 1 THEN ''publicado'' ELSE ''oculto'' END WHERE deleted_at IS NULL',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'materiais'
      AND COLUMN_NAME = 'criado_por'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE materiais ADD COLUMN criado_por BIGINT UNSIGNED NULL AFTER status',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'materiais'
      AND COLUMN_NAME = 'atualizado_por'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE materiais ADD COLUMN atualizado_por BIGINT UNSIGNED NULL AFTER criado_por',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @needs_modify := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'materiais'
      AND COLUMN_NAME = 'arquivo_caminho'
      AND IS_NULLABLE = 'NO'
);
SET @sql := IF(
    @needs_modify > 0,
    'ALTER TABLE materiais MODIFY arquivo_caminho VARCHAR(255) NULL',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
