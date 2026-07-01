-- Polo Rainbow - Módulos da Home v3 (Commit 6)
-- Compatibilidade: MySQL 5.7
-- Objetivo:
-- - Inserir o bloco institucional "Quem somos" (posicao: quem_somos_v3)
-- - Inserir as 4 perguntas frequentes da Home v3 (posicao: faq_v3_item)
-- Idempotente: cada INSERT só ocorre se o codigo ainda não existir.

SET NAMES utf8mb4;

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'quem_somos_v3',
    'Home v3 - Quem somos',
    'Quem somos',
    NULL,
    'Somos a Desbloqueia Cursos, parte do Polo Rainbow, dedicada a oferecer formações práticas e acessíveis para impulsionar sua carreira. Nossa missão é conectar pessoas a oportunidades reais de aprendizado, com suporte humano em cada etapa.',
    'quem_somos_v3',
    'institucional',
    1,
    10,
    0,
    'Bloco institucional "Quem somos" da Home v3. Preencha imagem_caminho/imagem_alt pelo admin para exibir a imagem ao lado do texto.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'quem_somos_v3'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'faq_v3_item_1',
    'Home v3 - FAQ - Item 1',
    'Os cursos têm certificado?',
    NULL,
    'Sim, todos os cursos emitem certificado ao concluir.',
    'faq_v3_item',
    'faq',
    1,
    10,
    0,
    'Item 1 da seção "Perguntas frequentes" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'faq_v3_item_1'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'faq_v3_item_2',
    'Home v3 - FAQ - Item 2',
    'Posso fazer o curso online?',
    NULL,
    'Sim, oferecemos modalidades online, presencial e híbrida.',
    'faq_v3_item',
    'faq',
    1,
    20,
    0,
    'Item 2 da seção "Perguntas frequentes" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'faq_v3_item_2'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'faq_v3_item_3',
    'Home v3 - FAQ - Item 3',
    'Como faço minha inscrição?',
    NULL,
    'Escolha o curso, clique em "Ver curso" e siga os passos de inscrição.',
    'faq_v3_item',
    'faq',
    1,
    30,
    0,
    'Item 3 da seção "Perguntas frequentes" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'faq_v3_item_3'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'faq_v3_item_4',
    'Home v3 - FAQ - Item 4',
    'Há suporte durante o curso?',
    NULL,
    'Sim, nossa equipe está disponível para tirar dúvidas durante todo o curso.',
    'faq_v3_item',
    'faq',
    1,
    40,
    0,
    'Item 4 da seção "Perguntas frequentes" da Home v3.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'faq_v3_item_4'
);
