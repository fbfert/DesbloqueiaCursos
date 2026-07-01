-- Polo Rainbow - favicon das configuracoes globais
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'configuracoes_globais'
              AND COLUMN_NAME = 'favicon_caminho'
        ),
        'SELECT 1',
        'ALTER TABLE configuracoes_globais ADD COLUMN favicon_caminho VARCHAR(255) NULL AFTER logo_caminho'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
