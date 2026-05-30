-- Polo Rainbow - espacamento vertical entre secoes do frontend
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

SET @coluna_existe := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'configuracoes_frontend'
      AND COLUMN_NAME = 'frontend_section_gap'
);

SET @coluna_card_gap_existe := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'configuracoes_frontend'
      AND COLUMN_NAME = 'frontend_card_gap'
);

SET @sql := IF(
    @coluna_existe = 0 AND @coluna_card_gap_existe > 0,
    'ALTER TABLE configuracoes_frontend ADD COLUMN frontend_section_gap VARCHAR(191) NULL AFTER frontend_card_gap',
    IF(
        @coluna_existe = 0,
        'ALTER TABLE configuracoes_frontend ADD COLUMN frontend_section_gap VARCHAR(191) NULL AFTER descricao_home',
        'SELECT 1'
    )
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
