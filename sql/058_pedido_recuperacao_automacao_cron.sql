-- 058 (duplicata) — automação por Cron para recuperação de pedidos
--
-- Este arquivo era cópia byte a byte de 056_pedido_recuperacao_automacao_cron.sql.
-- Rodado depois da 056, falhava logo no primeiro ALTER ("Duplicate column name
-- 'recuperacao_pedidos_automatica_ativa'") e o cliente mysql parava ali; num
-- banco novo isso aparecia como erro de migração sem ser defeito real.
--
-- Desde 07/10/2026 é um no-op: todo o conteúdo vive na 056. Em bancos onde esta
-- cópia já rodou (ou falhou), nada muda.

SELECT 'sql/058_pedido_recuperacao_automacao_cron.sql: duplicata da 056, nada a fazer' AS aviso;
