-- Polo Rainbow - Módulo do topo e imagens nos módulos de frontend
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

SET @col_imagem_caminho_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'frontend_modulos'
      AND COLUMN_NAME = 'imagem_caminho'
);
SET @sql := IF(
    @col_imagem_caminho_exists = 0,
    'ALTER TABLE frontend_modulos ADD COLUMN imagem_caminho VARCHAR(255) NULL AFTER conteudo',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_imagem_alt_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'frontend_modulos'
      AND COLUMN_NAME = 'imagem_alt'
);
SET @sql := IF(
    @col_imagem_alt_exists = 0,
    'ALTER TABLE frontend_modulos ADD COLUMN imagem_alt VARCHAR(180) NULL AFTER imagem_caminho',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, imagem_caminho, imagem_alt, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'topo_site',
    'Topo do site',
    'Desbloqueia Cursos',
    NULL,
    NULL,
    NULL,
    'Desbloqueia Cursos',
    'topo_site',
    'identidade_visual',
    1,
    1,
    0,
    'Módulo usado no topo público do site. Se houver imagem, ela substitui o texto do título. Se não houver imagem, o título é exibido como texto.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'topo_site'
);

UPDATE frontend_modulos
SET nome_admin = 'Topo do site',
    posicao = 'topo_site',
    tipo = 'identidade_visual',
    observacoes_admin = COALESCE(observacoes_admin, 'Módulo usado no topo público do site. Se houver imagem, ela substitui o texto do título. Se não houver imagem, o título é exibido como texto.'),
    updated_at = NOW()
WHERE codigo = 'topo_site'
  AND deleted_at IS NULL;
