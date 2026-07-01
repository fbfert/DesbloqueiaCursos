SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'turmas'
              AND COLUMN_NAME = 'local'
        ),
        'SELECT ''A coluna turmas.local já existe; nenhuma alteração necessária.'' AS mensagem;',
        'ALTER TABLE `turmas` ADD COLUMN `local` VARCHAR(255) NULL;'
    )
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;