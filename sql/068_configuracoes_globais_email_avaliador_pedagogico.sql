-- Desbloqueia Cursos - novo e-mail institucional "avaliador pedagogico"
-- (usado para notificar avaliacoes textuais pendentes de correcao)
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'configuracoes_globais'
              AND COLUMN_NAME = 'email_avaliador_pedagogico'
        ),
        'SELECT 1',
        'ALTER TABLE configuracoes_globais ADD COLUMN email_avaliador_pedagogico VARCHAR(191) NULL AFTER email_certificados'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
