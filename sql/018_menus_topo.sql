-- Polo Rainbow - menus superiores do site público
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

INSERT INTO frontend_menus
    (codigo, nome_admin, posicao, ativo, ordem, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'menu_topo_publico',
    'Menu principal superior público',
    'topo_publico',
    1,
    10,
    'Menu superior padrão para visitantes.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM frontend_menus
    WHERE codigo = 'menu_topo_publico'
      AND deleted_at IS NULL
);

INSERT INTO frontend_menus
    (codigo, nome_admin, posicao, ativo, ordem, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'menu_topo_logado',
    'Menu principal superior logado',
    'topo_logado',
    1,
    20,
    'Menu superior padrão para usuários autenticados.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM frontend_menus
    WHERE codigo = 'menu_topo_logado'
      AND deleted_at IS NULL
);

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Início', '/', '_self', NULL, 1, 10, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_publico'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Início' AND i.url = '/' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Cursos', '/cursos', '_self', NULL, 1, 20, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_publico'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Cursos' AND i.url = '/cursos' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Como funciona', '/como-funciona', '_self', NULL, 1, 30, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_publico'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Como funciona' AND i.url = '/como-funciona' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Sobre', '/sobre', '_self', NULL, 1, 40, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_publico'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Sobre' AND i.url = '/sobre' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Contato', '/contato', '_self', NULL, 1, 50, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_publico'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Contato' AND i.url = '/contato' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Validar certificado', '/certificados/validar', '_self', NULL, 1, 60, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_publico'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Validar certificado' AND i.url = '/certificados/validar' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Entrar', '/login', '_self', NULL, 1, 70, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_publico'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Entrar' AND i.url = '/login' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Criar conta', '/cadastro', '_self', NULL, 1, 80, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_publico'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Criar conta' AND i.url = '/cadastro' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Minha Página', '/minha-pagina', '_self', NULL, 1, 10, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_logado'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Minha Página' AND i.url = '/minha-pagina' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Meus Cursos', '/area-curso', '_self', NULL, 1, 20, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_logado'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Meus Cursos' AND i.url = '/area-curso' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Certificados', '/certificados', '_self', NULL, 1, 30, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_logado'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Certificados' AND i.url = '/certificados' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Pedidos', '/pedidos', '_self', NULL, 1, 40, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_logado'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Pedidos' AND i.url = '/pedidos' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Cursos', '/cursos', '_self', NULL, 1, 50, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_logado'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Cursos' AND i.url = '/cursos' AND i.deleted_at IS NULL
  );

INSERT INTO frontend_menu_itens
    (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT fm.id, 'Sair', '/logout', '_self', NULL, 1, 90, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL
FROM frontend_menus fm
WHERE fm.codigo = 'menu_topo_logado'
  AND fm.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM frontend_menu_itens i
      WHERE i.menu_id = fm.id AND i.rotulo = 'Sair' AND i.url = '/logout' AND i.deleted_at IS NULL
  );
