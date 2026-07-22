-- Desbloqueia Cursos - Novo tipo de conteudo "video_incorporado"
-- Compatibilidade: MySQL 5.7
-- Todas as operacoes sao idempotentes (IF NOT EXISTS / SET @sql...)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Adicionar tipo 'video_incorporado' ao ENUM de conteudo_itens
-- Recria o ENUM preservando todos os valores existentes
SET @sql = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'conteudo_itens'
              AND COLUMN_NAME  = 'tipo'
              AND COLUMN_TYPE LIKE '%video_incorporado%'
        ),
        'SELECT ''conteudo_itens.tipo ja tem video_incorporado'' AS mensagem',
        'ALTER TABLE conteudo_itens MODIFY tipo ENUM(''etiqueta'',''texto'',''arquivo'',''link'',''avaliacao_textual'',''video'',''quiz'',''html'',''video_incorporado'') NOT NULL'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Tabela satelite do tipo video_incorporado (detalhe do item)
-- Conteudo gravado sem sanitizacao (codigo de embed original preservado,
-- inclui <iframe>); exibido ao aluno dentro de iframe sandboxed 16:9
-- (ver App\Support\HtmlEmbedRenderer e resources/views/aluno/curso/conteudo.php).
CREATE TABLE IF NOT EXISTS conteudo_videos_incorporados (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id    BIGINT UNSIGNED NOT NULL,
    conteudo   LONGTEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_conteudo_videos_incorporados_item (item_id),
    CONSTRAINT fk_conteudo_videos_incorporados_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
