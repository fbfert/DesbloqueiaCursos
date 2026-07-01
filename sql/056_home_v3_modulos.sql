-- Polo Rainbow - Módulos da Home v3 (Commit 5)
-- Compatibilidade: MySQL 5.7
-- Objetivo:
-- - Inserir os 4 passos da seção "Começar é simples" (posicao: como_funciona_v3_passo)
-- - Inserir os 4 itens da seção "Diferenciais" (posicao: diferenciais_v3_item)
-- Idempotente: cada INSERT só ocorre se o codigo ainda não existir.

SET NAMES utf8mb4;

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'como_funciona_v3_passo_1',
    'Home v3 - Começar é simples - Passo 1',
    'Escolha seu curso',
    NULL,
    'Navegue pelo catálogo e encontre o curso ideal para você ou sua equipe.',
    'como_funciona_v3_passo',
    'passo',
    1,
    10,
    0,
    'Passo 1 da seção "Começar é simples" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'como_funciona_v3_passo_1'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'como_funciona_v3_passo_2',
    'Home v3 - Começar é simples - Passo 2',
    'Faça sua inscrição',
    NULL,
    'Processo simples e rápido, com suporte em cada etapa.',
    'como_funciona_v3_passo',
    'passo',
    1,
    20,
    0,
    'Passo 2 da seção "Começar é simples" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'como_funciona_v3_passo_2'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'como_funciona_v3_passo_3',
    'Home v3 - Começar é simples - Passo 3',
    'Estude no seu ritmo',
    NULL,
    'Conteúdo online ou presencial, com material de apoio incluído.',
    'como_funciona_v3_passo',
    'passo',
    1,
    30,
    0,
    'Passo 3 da seção "Começar é simples" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'como_funciona_v3_passo_3'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'como_funciona_v3_passo_4',
    'Home v3 - Começar é simples - Passo 4',
    'Receba seu certificado',
    NULL,
    'Certificado reconhecido emitido ao concluir o curso.',
    'como_funciona_v3_passo',
    'passo',
    1,
    40,
    0,
    'Passo 4 da seção "Começar é simples" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'como_funciona_v3_passo_4'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'diferenciais_v3_item_1',
    'Home v3 - Diferenciais - Item 1',
    'Online e presencial',
    NULL,
    'Escolha a modalidade que se adapta à sua rotina.',
    'diferenciais_v3_item',
    'diferencial',
    1,
    10,
    0,
    'Item 1 da seção "Diferenciais" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'diferenciais_v3_item_1'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'diferenciais_v3_item_2',
    'Home v3 - Diferenciais - Item 2',
    'Certificado incluso',
    NULL,
    'Todos os cursos emitem certificado ao concluir.',
    'diferenciais_v3_item',
    'diferencial',
    1,
    20,
    0,
    'Item 2 da seção "Diferenciais" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'diferenciais_v3_item_2'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'diferenciais_v3_item_3',
    'Home v3 - Diferenciais - Item 3',
    'Suporte ao aluno',
    NULL,
    'Equipe disponível para tirar dúvidas durante o curso.',
    'diferenciais_v3_item',
    'diferencial',
    1,
    30,
    0,
    'Item 3 da seção "Diferenciais" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'diferenciais_v3_item_3'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'diferenciais_v3_item_4',
    'Home v3 - Diferenciais - Item 4',
    'Cursos práticos',
    NULL,
    'Conteúdo aplicável direto ao mercado de trabalho.',
    'diferenciais_v3_item',
    'diferencial',
    1,
    40,
    0,
    'Item 4 da seção "Diferenciais" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'diferenciais_v3_item_4'
);
