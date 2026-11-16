-- 084 — Correções do financeiro (openspec/changes/financeiro-correcoes)
-- Compatibilidade: MySQL 5.7 / MariaDB 10.5. Aditiva: cada coluna só é criada se ainda não
-- existir (information_schema + PREPARE). Pode rodar mais de uma vez.
--
-- PAGO_EM
--
-- Até aqui o registro de pagamento de repasse gravava a data do pagamento em
-- repasses_professores.documento_validado_em (RepasseProfessor::updateStatus). A data do
-- pagamento passa a ter coluna própria. Repasses já pagos recebem pago_em a partir do último
-- pagamento registrado em pagamentos_professores. O documento_validado_em desses repasses NÃO é
-- corrigido: não há como separar o valor real do sobrescrito (limitação histórica documentada
-- em docs/2026-11-16-financeiro-correcoes.md).
--
-- MOTIVO_REJEICAO
--
-- Documento de repasse rejeitado pelo admin guarda o motivo.
--
-- Rollback: as colunas podem ficar (o código anterior as ignora) ou ser removidas com
-- ALTER TABLE ... DROP COLUMN.

SET NAMES utf8mb4;

SET @col_pago_em := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'repasses_professores'
      AND COLUMN_NAME = 'pago_em'
);
SET @sql_pago_em := IF(
    @col_pago_em = 0,
    'ALTER TABLE repasses_professores ADD COLUMN pago_em DATETIME NULL AFTER documento_validado_em',
    'SELECT 1'
);
PREPARE stmt_pago_em FROM @sql_pago_em;
EXECUTE stmt_pago_em;
DEALLOCATE PREPARE stmt_pago_em;

SET @col_motivo := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'repasses_documentos'
      AND COLUMN_NAME = 'motivo_rejeicao'
);
SET @sql_motivo := IF(
    @col_motivo = 0,
    'ALTER TABLE repasses_documentos ADD COLUMN motivo_rejeicao TEXT NULL AFTER observacao',
    'SELECT 1'
);
PREPARE stmt_motivo FROM @sql_motivo;
EXECUTE stmt_motivo;
DEALLOCATE PREPARE stmt_motivo;

UPDATE repasses_professores rp
INNER JOIN (
    SELECT repasse_professor_id, MAX(created_at) AS ultimo_pagamento
    FROM pagamentos_professores
    WHERE deleted_at IS NULL
      AND status = 'pago'
    GROUP BY repasse_professor_id
) pg ON pg.repasse_professor_id = rp.id
SET rp.pago_em = pg.ultimo_pagamento
WHERE rp.status = 'pago'
  AND rp.pago_em IS NULL;
