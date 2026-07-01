-- Desbloqueia Cursos - certificados: assinatura por template
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'assinatura_url'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN assinatura_url VARCHAR(500) NULL AFTER logo'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
