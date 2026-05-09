-- Polo Rainbow - limite de destaques na capa
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

SET @coluna_existe := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'configuracoes_frontend'
      AND COLUMN_NAME = 'home_destaques_limite'
);

SET @sql := IF(
    @coluna_existe = 0,
    'ALTER TABLE configuracoes_frontend ADD COLUMN home_destaques_limite INT NOT NULL DEFAULT 6 AFTER descricao_home',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
