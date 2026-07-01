-- Desbloqueia Cursos - Conteúdo Unificado (hardening)
-- Compatibilidade: MySQL 5.7
--
-- Objetivo:
-- - Garantir conteudo_progresso_aluno.inscricao_id como NOT NULL (evita duplicidade em UNIQUE com NULL)
-- - Ajustar FK para ON DELETE CASCADE (inscrição é obrigatória no progresso)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SET @schema_name := DATABASE();

SET @inscricao_nullable := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE CONVERT(TABLE_SCHEMA USING utf8mb4) = @schema_name
      AND CONVERT(TABLE_NAME USING utf8mb4) = 'conteudo_progresso_aluno'
      AND CONVERT(COLUMN_NAME USING utf8mb4) = 'inscricao_id'
      AND CONVERT(IS_NULLABLE USING utf8mb4) = 'YES'
);

SET @sql := IF(
    @inscricao_nullable = 0,
    'DO 1',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @null_count := (
    SELECT COUNT(*)
    FROM conteudo_progresso_aluno
    WHERE inscricao_id IS NULL
);

-- Se houver dados inválidos, mostra orientação e bloqueia a alteração.
SET @sql := IF(
    @inscricao_nullable = 1 AND @null_count > 0,
    'SELECT inscricao_id_nao_nula FROM conteudo_progresso_aluno LIMIT 1',
    'DO 1'
);

-- Mensagem explícita antes de bloquear.
SET @msg := IF(
    @inscricao_nullable = 1 AND @null_count > 0,
    CONCAT('ERRO: existem ', @null_count, ' registros em conteudo_progresso_aluno com inscricao_id NULL. Corrija os dados (definindo a inscricao_id correta por registro) e reexecute esta migration.'),
    NULL
);

SET @sql_msg := IF(@msg IS NULL, 'DO 1', CONCAT('SELECT ', QUOTE(@msg), ' AS erro'));
PREPARE stmt_msg FROM @sql_msg;
EXECUTE stmt_msg;
DEALLOCATE PREPARE stmt_msg;

PREPARE stmt_guard FROM @sql;
EXECUTE stmt_guard;
DEALLOCATE PREPARE stmt_guard;

-- Drop FK (se existir) para permitir o MODIFY e evitar ON DELETE SET NULL com coluna NOT NULL.
SET @fk_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONVERT(CONSTRAINT_SCHEMA USING utf8mb4) = @schema_name
      AND CONVERT(TABLE_NAME USING utf8mb4) = 'conteudo_progresso_aluno'
      AND CONVERT(CONSTRAINT_NAME USING utf8mb4) = 'fk_conteudo_progresso_aluno_inscricao'
);

SET @sql := IF(
    @inscricao_nullable = 1 AND @fk_exists > 0,
    'ALTER TABLE conteudo_progresso_aluno DROP FOREIGN KEY fk_conteudo_progresso_aluno_inscricao',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agora garante NOT NULL.
SET @sql := IF(
    @inscricao_nullable = 1,
    'ALTER TABLE conteudo_progresso_aluno MODIFY inscricao_id BIGINT UNSIGNED NOT NULL',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recria FK com ON DELETE CASCADE (se ainda não existir).
SET @fk_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONVERT(CONSTRAINT_SCHEMA USING utf8mb4) = @schema_name
      AND CONVERT(TABLE_NAME USING utf8mb4) = 'conteudo_progresso_aluno'
      AND CONVERT(CONSTRAINT_NAME USING utf8mb4) = 'fk_conteudo_progresso_aluno_inscricao'
);

SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE conteudo_progresso_aluno ADD CONSTRAINT fk_conteudo_progresso_aluno_inscricao FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id) ON DELETE CASCADE',
    'DO 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

