-- Desbloqueia Cursos - segunda pagina editavel dos templates de certificado
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
              AND COLUMN_NAME = 'html_segunda_pagina'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN html_segunda_pagina LONGTEXT NULL AFTER corpo_html'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
