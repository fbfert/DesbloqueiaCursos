-- Polo Rainbow - Módulos e menus de frontend
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS frontend_modulos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(80) NOT NULL,
    nome_admin VARCHAR(120) NOT NULL,
    titulo VARCHAR(180) NULL,
    subtitulo VARCHAR(255) NULL,
    conteudo TEXT NULL,
    posicao VARCHAR(80) NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'bloco_texto',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    permite_html TINYINT(1) NOT NULL DEFAULT 0,
    observacoes_admin TEXT NULL,
    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    excluido_por BIGINT UNSIGNED NULL,
    justificativa_exclusao TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_frontend_modulos_codigo (codigo),
    KEY idx_frontend_modulos_codigo (codigo),
    KEY idx_frontend_modulos_posicao (posicao),
    KEY idx_frontend_modulos_ativo (ativo),
    KEY idx_frontend_modulos_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS frontend_menus (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(80) NOT NULL,
    nome_admin VARCHAR(120) NOT NULL,
    posicao VARCHAR(80) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    observacoes_admin TEXT NULL,
    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    excluido_por BIGINT UNSIGNED NULL,
    justificativa_exclusao TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_frontend_menus_codigo (codigo),
    KEY idx_frontend_menus_codigo (codigo),
    KEY idx_frontend_menus_posicao (posicao),
    KEY idx_frontend_menus_ativo (ativo),
    KEY idx_frontend_menus_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS frontend_menu_itens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    menu_id INT UNSIGNED NOT NULL,
    rotulo VARCHAR(120) NOT NULL,
    url VARCHAR(255) NOT NULL,
    target VARCHAR(20) NOT NULL DEFAULT '_self',
    rel VARCHAR(120) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    excluido_por BIGINT UNSIGNED NULL,
    justificativa_exclusao TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_frontend_menu_itens_menu_id (menu_id),
    KEY idx_frontend_menu_itens_ativo (ativo),
    KEY idx_frontend_menu_itens_ordem (ordem),
    KEY idx_frontend_menu_itens_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_frontend_menu_itens_menu'
);
SET @fk_sql = IF(
    @fk_exists = 0,
    'ALTER TABLE frontend_menu_itens ADD CONSTRAINT fk_frontend_menu_itens_menu FOREIGN KEY (menu_id) REFERENCES frontend_menus(id) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk FROM @fk_sql;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'antes_rodape',
    'Antes do Rodapé',
    'Polo Rainbow',
    'Portal público para cursos, turmas e inscrições iniciais.',
    NULL,
    'antes_rodape',
    'bloco_texto',
    1,
    10,
    0,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'antes_rodape'
);

INSERT INTO frontend_menus
    (codigo, nome_admin, posicao, ativo, ordem, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'menu_antes_rodape',
    'Menu antes do rodapé',
    'antes_rodape',
    1,
    20,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_menus WHERE codigo = 'menu_antes_rodape'
);

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    fm.id, 'Cursos', '/cursos', '_self', NULL, 1, 10, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_antes_rodape'
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i WHERE i.menu_id = fm.id AND i.rotulo = 'Cursos' AND i.url = '/cursos'
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    fm.id, 'Como funciona', '/como-funciona', '_self', NULL, 1, 20, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_antes_rodape'
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i WHERE i.menu_id = fm.id AND i.rotulo = 'Como funciona' AND i.url = '/como-funciona'
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    fm.id, 'Sobre', '/sobre', '_self', NULL, 1, 30, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_antes_rodape'
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i WHERE i.menu_id = fm.id AND i.rotulo = 'Sobre' AND i.url = '/sobre'
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    fm.id, 'Contato', '/contato', '_self', NULL, 1, 40, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_antes_rodape'
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i WHERE i.menu_id = fm.id AND i.rotulo = 'Contato' AND i.url = '/contato'
  );

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'rodape',
    'Rodapé',
    NULL,
    NULL,
    '{ano} Polo Rainbow.',
    'rodape',
    'rodape',
    1,
    30,
    0,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'rodape'
);

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
SELECT 'frontend', 'modulos.ver', 'frontend.modulos.ver', 'Ver módulos de frontend', 'Listar módulos de frontend', NOW(), NOW(), NULL
WHERE NOT EXISTS (SELECT 1 FROM permissoes WHERE slug = 'frontend.modulos.ver');

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
SELECT 'frontend', 'modulos.gerenciar', 'frontend.modulos.gerenciar', 'Gerenciar módulos de frontend', 'Criar e editar módulos de frontend', NOW(), NOW(), NULL
WHERE NOT EXISTS (SELECT 1 FROM permissoes WHERE slug = 'frontend.modulos.gerenciar');

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
SELECT 'frontend', 'menus.ver', 'frontend.menus.ver', 'Ver menus de frontend', 'Listar menus de frontend', NOW(), NOW(), NULL
WHERE NOT EXISTS (SELECT 1 FROM permissoes WHERE slug = 'frontend.menus.ver');

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
SELECT 'frontend', 'menus.gerenciar', 'frontend.menus.gerenciar', 'Gerenciar menus de frontend', 'Criar e editar menus de frontend', NOW(), NOW(), NULL
WHERE NOT EXISTS (SELECT 1 FROM permissoes WHERE slug = 'frontend.menus.gerenciar');

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('frontend.modulos.ver', 'frontend.modulos.gerenciar', 'frontend.menus.ver', 'frontend.menus.gerenciar')
WHERE p.slug = 'superadmin'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('frontend.modulos.ver', 'frontend.modulos.gerenciar', 'frontend.menus.ver', 'frontend.menus.gerenciar')
WHERE p.slug IN ('conteudo', 'marketing')
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);
