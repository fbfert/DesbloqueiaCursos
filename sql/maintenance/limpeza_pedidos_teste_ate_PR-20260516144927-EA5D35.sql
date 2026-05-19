-- Desbloqueia Cursos - Manutenção (pedidos de teste + comprovantes PIX)
-- Corte: PR-20260516144927-EA5D35 (inclusive) e todos os pedidos anteriores
-- Compatibilidade: MySQL 5.7
--
-- ATENÇÃO:
-- - Leia e execute primeiro APENAS o bloco de diagnóstico.
-- - Este script cria backups lógicos (CREATE TABLE AS SELECT) SEM índices.
-- - O COMMIT está comentado por segurança. Revise as contagens antes de executar.
-- - Não remove usuários/alunos, cursos, turmas ou configurações.
-- - Não remove arquivos físicos de comprovantes; apenas lista caminhos.

SET NAMES utf8mb4;
-- Evita erro #1267 (mix de collations) ao comparar com pedidos.codigo
SET @codigo_corte := CONVERT('PR-20260516144927-EA5D35' USING utf8mb4) COLLATE utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- A) Identificação do pedido de corte
-- ------------------------------------------------------------

SELECT
    p.id AS pedido_corte_id,
    p.codigo AS pedido_corte_codigo,
    p.created_at AS pedido_corte_created_at,
    p.status AS pedido_corte_status,
    p.total AS pedido_corte_total
FROM pedidos p
WHERE p.codigo COLLATE utf8mb4_unicode_ci = @codigo_corte
  AND p.deleted_at IS NULL
LIMIT 1;

SELECT
    p.id,
    p.created_at
INTO
    @pedido_corte_id,
    @data_corte
FROM pedidos p
WHERE p.codigo COLLATE utf8mb4_unicode_ci = @codigo_corte
  AND p.deleted_at IS NULL
LIMIT 1;

-- Fallback: se não encontrou o pedido, use o timestamp embutido no código.
-- (2026-05-16 14:49:27) => '2026-05-16 14:49:27'
SET @data_corte = IFNULL(@data_corte, STR_TO_DATE('2026-05-16 14:49:27', '%Y-%m-%d %H:%i:%s'));

-- Segurança: confirme que existe pelo menos 1 pedido posterior ao corte
SELECT COUNT(*) AS pedidos_posteriores_ao_corte
FROM pedidos
WHERE deleted_at IS NULL
  AND created_at > @data_corte;

-- ------------------------------------------------------------
-- B) Diagnóstico: prévia do que seria removido
-- ------------------------------------------------------------

-- Lista pedidos afetados (teste)
SELECT
    p.id,
    p.codigo,
    p.status,
    p.total,
    p.created_at,
    p.pagador_nome,
    p.pagador_email
FROM pedidos p
WHERE p.deleted_at IS NULL
  AND p.created_at <= @data_corte
ORDER BY p.created_at ASC, p.id ASC;

-- Contagens (pedidos e dependências)
SELECT COUNT(*) AS total_pedidos_teste
FROM pedidos p
WHERE p.deleted_at IS NULL
  AND p.created_at <= @data_corte;

SELECT COUNT(*) AS total_itens_pedido_teste
FROM pedido_itens pi
INNER JOIN pedidos p ON p.id = pi.pedido_id
WHERE p.deleted_at IS NULL
  AND p.created_at <= @data_corte
  AND pi.deleted_at IS NULL;

SELECT COUNT(*) AS total_participantes_pedido_teste
FROM participantes_pedido pp
INNER JOIN pedidos p ON p.id = pp.pedido_id
WHERE p.deleted_at IS NULL
  AND p.created_at <= @data_corte
  AND pp.deleted_at IS NULL;

SELECT COUNT(*) AS total_inscricoes_teste
FROM inscricoes i
INNER JOIN pedidos p ON p.id = i.pedido_id
WHERE p.deleted_at IS NULL
  AND p.created_at <= @data_corte
  AND i.deleted_at IS NULL;

SELECT COUNT(*) AS total_status_pedidos_historico_teste
FROM status_pedidos_historico h
INNER JOIN pedidos p ON p.id = h.pedido_id
WHERE p.deleted_at IS NULL
  AND p.created_at <= @data_corte;

SELECT COUNT(*) AS total_status_inscricoes_historico_teste
FROM status_inscricoes_historico h
INNER JOIN inscricoes i ON i.id = h.inscricao_id
INNER JOIN pedidos p ON p.id = i.pedido_id
WHERE p.deleted_at IS NULL
  AND p.created_at <= @data_corte;

-- Comprovantes PIX afetados + caminhos de arquivo (para limpeza manual, se desejado)
SELECT
    cp.id AS comprovante_id,
    cp.pedido_id,
    p.codigo AS pedido_codigo,
    cp.status AS comprovante_status,
    cp.is_atual,
    cp.versao,
    cp.enviado_em,
    cp.arquivo_caminho,
    cp.arquivo_nome_original,
    cp.usuario_id
FROM comprovantes_pix cp
INNER JOIN pedidos p ON p.id = cp.pedido_id
WHERE p.deleted_at IS NULL
  AND p.created_at <= @data_corte
  AND cp.deleted_at IS NULL
ORDER BY cp.pedido_id ASC, cp.versao ASC, cp.id ASC;

SELECT COUNT(*) AS total_comprovantes_pix_teste
FROM comprovantes_pix cp
INNER JOIN pedidos p ON p.id = cp.pedido_id
WHERE p.deleted_at IS NULL
  AND p.created_at <= @data_corte
  AND cp.deleted_at IS NULL;

-- ------------------------------------------------------------
-- C) Backup lógico (tabelas backup sem índices)
-- ------------------------------------------------------------
-- Sufixo do backup: 20260516 (data do corte)
SET @backup_suffix := '20260516';

-- Lista de IDs de pedidos de teste (para reuso)
DROP TEMPORARY TABLE IF EXISTS tmp_pedidos_teste_ids;
CREATE TEMPORARY TABLE tmp_pedidos_teste_ids (
    pedido_id BIGINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=MEMORY;

INSERT IGNORE INTO tmp_pedidos_teste_ids (pedido_id)
SELECT p.id
FROM pedidos p
WHERE p.deleted_at IS NULL
  AND p.created_at <= @data_corte;

-- Backup: pedidos
SET @sql := CONCAT(
    'CREATE TABLE IF NOT EXISTS backup_pedidos_teste_', @backup_suffix, ' AS ',
    'SELECT p.* FROM pedidos p INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = p.id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backup: comprovantes_pix
SET @sql := CONCAT(
    'CREATE TABLE IF NOT EXISTS backup_comprovantes_pix_teste_', @backup_suffix, ' AS ',
    'SELECT cp.* FROM comprovantes_pix cp INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = cp.pedido_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backup: pedido_itens
SET @sql := CONCAT(
    'CREATE TABLE IF NOT EXISTS backup_pedido_itens_teste_', @backup_suffix, ' AS ',
    'SELECT pi.* FROM pedido_itens pi INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = pi.pedido_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backup: participantes_pedido
SET @sql := CONCAT(
    'CREATE TABLE IF NOT EXISTS backup_participantes_pedido_teste_', @backup_suffix, ' AS ',
    'SELECT pp.* FROM participantes_pedido pp INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = pp.pedido_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backup: inscricoes
SET @sql := CONCAT(
    'CREATE TABLE IF NOT EXISTS backup_inscricoes_teste_', @backup_suffix, ' AS ',
    'SELECT i.* FROM inscricoes i INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = i.pedido_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backup: status_pedidos_historico
SET @sql := CONCAT(
    'CREATE TABLE IF NOT EXISTS backup_status_pedidos_historico_teste_', @backup_suffix, ' AS ',
    'SELECT h.* FROM status_pedidos_historico h INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = h.pedido_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backup: status_inscricoes_historico
SET @sql := CONCAT(
    'CREATE TABLE IF NOT EXISTS backup_status_inscricoes_historico_teste_', @backup_suffix, ' AS ',
    'SELECT h.* FROM status_inscricoes_historico h ',
    'INNER JOIN inscricoes i ON i.id = h.inscricao_id ',
    'INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = i.pedido_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- D) Exclusão (em transação) - MODO EXECUÇÃO
-- ------------------------------------------------------------
-- Observação:
-- - Há FKs com ON DELETE CASCADE para pedido_itens/participantes_pedido/inscricoes/históricos/comprovantes_pix.
-- - Mesmo assim, deletamos explicitamente comprovantes_pix e pedido_itens primeiro para manter clareza e permitir conferência.
-- - Não apagamos usuários/alunos (usuarios).

START TRANSACTION;

-- DELETE filhos (explícitos)
DELETE cp
FROM comprovantes_pix cp
INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = cp.pedido_id;

DELETE h
FROM status_pedidos_historico h
INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = h.pedido_id;

DELETE i_h
FROM status_inscricoes_historico i_h
INNER JOIN inscricoes i ON i.id = i_h.inscricao_id
INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = i.pedido_id;

DELETE i
FROM inscricoes i
INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = i.pedido_id;

DELETE pp
FROM participantes_pedido pp
INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = pp.pedido_id;

DELETE pi
FROM pedido_itens pi
INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = pi.pedido_id;

-- DELETE pai
DELETE p
FROM pedidos p
INNER JOIN tmp_pedidos_teste_ids t ON t.pedido_id = p.id;

-- ------------------------------------------------------------
-- E) Conferência pós-delete (antes do COMMIT)
-- ------------------------------------------------------------

SELECT COUNT(*) AS pedidos_teste_restantes
FROM pedidos
WHERE deleted_at IS NULL
  AND created_at <= @data_corte;

SELECT COUNT(*) AS pedidos_posteriores_preservados
FROM pedidos
WHERE deleted_at IS NULL
  AND created_at > @data_corte;

SELECT COUNT(*) AS comprovantes_pix_orfaos
FROM comprovantes_pix cp
LEFT JOIN pedidos p ON p.id = cp.pedido_id
WHERE p.id IS NULL
  AND cp.deleted_at IS NULL;

-- Se tudo estiver correto, descomente o COMMIT e execute.
-- COMMIT;

-- Por segurança, deixe rollback na primeira execução.
ROLLBACK;
