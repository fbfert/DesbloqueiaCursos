-- =====================================================================
-- 074 — Norminha: telemetria (Etapa 8)
-- Docs: docs/norminha/08-telemetria.md
--
-- Duas coisas que o painel de telemetria precisa e a migration 073 não
-- previu.
--
-- 1. CONTAGEM REAL DE BLOQUEIOS. O rate limit registra os 429 no log de
--    arquivo, que não é consultável por período. Sem uma coluna, "quantos
--    alunos bateram o limite" viraria aproximação por mensagens_dia — que
--    não enxerga bloqueio de JANELA, só de dia.
--
-- 2. ÍNDICES POR DATA. Todo painel filtra por período. norminha_mensagens
--    tinha índice em (resolved_by, created_at) e (usuario_id, created_at),
--    mas nenhum em created_at sozinho: contar mensagens de um período
--    varreria a tabela inteira. Idem para conversas.
--
-- Aditiva e idempotente. Compatível com MySQL 5.7.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Contador durável de bloqueios por rate limit
--
--    Incrementado por NorminhaRateLimitService quando a requisição é
--    negada, na mesma linha (usuario_id, dia) que já conta as mensagens.
--    Assim o 429 vira número histórico, e não só linha de log.
-- ---------------------------------------------------------------------

SET @existe := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'norminha_uso'
      AND column_name = 'bloqueios_dia'
);
SET @sql := IF(@existe = 0,
    'ALTER TABLE `norminha_uso`
        ADD COLUMN `bloqueios_dia` INT(11) NOT NULL DEFAULT 0
        COMMENT ''quantas vezes o aluno recebeu 429 neste dia''
        AFTER `mensagens_ia_dia`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 2. Índice de mensagens por data
-- ---------------------------------------------------------------------

SET @existe := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'norminha_mensagens'
      AND index_name = 'idx_norminha_mensagens_data'
);
SET @sql := IF(@existe = 0,
    'ALTER TABLE `norminha_mensagens` ADD INDEX `idx_norminha_mensagens_data` (`created_at`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 3. Índice de conversas por data de criação
-- ---------------------------------------------------------------------

SET @existe := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'norminha_conversas'
      AND index_name = 'idx_norminha_conversas_criacao'
);
SET @sql := IF(@existe = 0,
    'ALTER TABLE `norminha_conversas` ADD INDEX `idx_norminha_conversas_criacao` (`created_at`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 4. Índice de feedback por data
-- ---------------------------------------------------------------------

SET @existe := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'norminha_feedback'
      AND index_name = 'idx_norminha_feedback_data'
);
SET @sql := IF(@existe = 0,
    'ALTER TABLE `norminha_feedback` ADD INDEX `idx_norminha_feedback_data` (`created_at`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
