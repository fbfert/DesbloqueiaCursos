-- Desbloqueia Cursos - thumbnail de categorias e limite de categorias na capa
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SET @schema_name := DATABASE();

SET @categorias_thumbnail_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'categorias'
      AND COLUMN_NAME = 'thumbnail'
);

SET @categorias_after_column := (
    SELECT IF(COUNT(*) > 0, 'descricao', 'nome')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'categorias'
      AND COLUMN_NAME = 'descricao'
);

SET @sql := IF(
    @categorias_thumbnail_exists = 0,
    CONCAT('ALTER TABLE categorias ADD COLUMN thumbnail VARCHAR(255) NULL AFTER ', @categorias_after_column),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @home_categorias_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'configuracoes_frontend'
      AND COLUMN_NAME = 'home_categorias_limite'
);

SET @frontend_after_column := (
    SELECT IF(COUNT(*) > 0, 'home_destaques_limite', IF(SUM(COLUMN_NAME = 'descricao_home') > 0, 'descricao_home', 'banner_caminho'))
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'configuracoes_frontend'
      AND COLUMN_NAME IN ('home_destaques_limite', 'descricao_home', 'banner_caminho')
);

SET @sql := IF(
    @home_categorias_exists = 0,
    CONCAT(
        'ALTER TABLE configuracoes_frontend ADD COLUMN home_categorias_limite INT NOT NULL DEFAULT 6 AFTER ',
        @frontend_after_column
    ),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
