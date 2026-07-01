-- Polo Rainbow - favicon nas configuracoes globais
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

SET @db_name = DATABASE();

SET @coluna_existe := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'configuracoes_globais'
      AND COLUMN_NAME = 'favicon_caminho'
);

SET @sql := IF(
    @coluna_existe = 0,
    'ALTER TABLE configuracoes_globais ADD COLUMN favicon_caminho VARCHAR(255) NULL AFTER logo_caminho',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
