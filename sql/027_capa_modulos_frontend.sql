-- Polo Rainbow - Módulos editáveis da capa
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'chamada_principal_capa',
    'Chamada Principal Capa',
    'Formações com turmas públicas, inscrição guiada e acesso separado por perfil.',
    NULL,
    'O portal público consome o catálogo do backoffice sem expor dados administrativos. Aqui entram apenas cursos ativos, turmas abertas e a porta de entrada da inscrição.',
    'capa_hero',
    'bloco_texto',
    1,
    10,
    0,
    'Módulo exibido na chamada principal da capa. O título vai no H1 e o conteúdo vai no texto de apoio.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'chamada_principal_capa'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'catalogo_publico_capa',
    'Capa - Catálogo Público',
    'Catálogo Público',
    NULL,
    'Lista apenas cursos ativos e publicáveis, sem depender de permissão administrativa.',
    'capa_status',
    'card_texto',
    1,
    20,
    0,
    'Card editável da capa.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'catalogo_publico_capa'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'detalhe_seguro_capa',
    'Capa - Detalhe Seguro',
    'Detalhe Seguro',
    NULL,
    'O detalhe do curso exibe somente professor responsável e turmas abertas para inscrição.',
    'capa_status',
    'card_texto',
    1,
    30,
    0,
    'Card editável da capa.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'detalhe_seguro_capa'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'inscricao_inicial_capa',
    'Capa - Inscrição inicial',
    'Inscrição inicial',
    NULL,
    'O frontend encaminha a inscrição apenas para turma aberta e vinculada ao curso correto.',
    'capa_status',
    'card_texto',
    1,
    40,
    0,
    'Card editável da capa.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'inscricao_inicial_capa'
);
