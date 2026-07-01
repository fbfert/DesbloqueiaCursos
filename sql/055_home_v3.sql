-- Desbloqueia Cursos - Home v3 (Commit 1): documenta 'v3' como valor aceito
-- para configuracoes_frontend.template_visual_portal
-- Compatibilidade: MySQL 5.7
--
-- CONTEXTO:
--   A coluna configuracoes_frontend.template_visual_portal e do tipo
--   VARCHAR(80) NOT NULL DEFAULT 'padrao' (ver sql/013_configuracoes_globais.sql).
--   Nao existe ENUM nem CHECK CONSTRAINT sobre os valores aceitos hoje
--   ('padrao', 'v1', 'v2') -- a validacao e feita em PHP, em
--   App\Services\ConfiguracaoGlobalService::normalizeFrontendTemplate()
--   e ::validateFrontend(). Como o tipo ja e VARCHAR(80), o valor 'v3'
--   (2 caracteres) cabe sem qualquer alteracao de tamanho ou tipo de
--   coluna. Esta migration apenas atualiza o COMMENT da coluna para
--   documentar, no proprio schema, que 'v3' passa a ser uma opcao
--   aceita pela aplicacao a partir deste commit. Nenhuma linha
--   existente e alterada.
--
-- ROLLBACK:
--   ALTER TABLE configuracoes_frontend
--       MODIFY COLUMN template_visual_portal VARCHAR(80) NOT NULL DEFAULT 'padrao'
--       COMMENT 'Valores aceitos: padrao, v1, v2';

SET NAMES utf8mb4;

SET @comentario_atual := (
    SELECT COLUMN_COMMENT
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'configuracoes_frontend'
      AND COLUMN_NAME = 'template_visual_portal'
);

SET @sql := IF(
    @comentario_atual IS NULL OR @comentario_atual NOT LIKE '%v3%',
    'ALTER TABLE configuracoes_frontend MODIFY COLUMN template_visual_portal VARCHAR(80) NOT NULL DEFAULT \'padrao\' COMMENT \'Valores aceitos: padrao, v1, v2, v3\'',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
