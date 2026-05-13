-- Polo Rainbow - Módulos pós-destaques da capa
-- Compatibilidade: MySQL 5.7
-- Objetivo:
-- - Inserir módulos editáveis após o botão "Ver todos os cursos"
-- - Preparar Top 5 Cursos, Top 5 Avaliações e Depoimentos
-- - Manter dados reais: Top 5 Cursos usa pedidos aprovados/pagos; avaliações ficam como placeholder até haver base pública

SET NAMES utf8mb4;

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'top_5_cursos_capa',
    'Capa - Top 5 Cursos',
    'Top 5 Cursos',
    NULL,
    'Cursos com mais vendas aprovadas no portal.',
    'capa_pos_destaques',
    'ranking_cursos',
    1,
    60,
    0,
    'Módulo exibido após o botão Ver todos os cursos. O ranking é calculado automaticamente por vendas confirmadas em pedidos aprovados/pagos ou comprovante PIX aprovado.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'top_5_cursos_capa'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'top_5_avaliacoes_capa',
    'Capa - Top 5 Avaliações',
    'Top 5 Avaliações',
    NULL,
    'Em breve, este espaço mostrará os cursos com melhores avaliações dos participantes.',
    'capa_pos_destaques',
    'ranking_avaliacoes',
    1,
    70,
    0,
    'Módulo preparado para uso futuro, quando existir base pública de avaliações dos cursos.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'top_5_avaliacoes_capa'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'depoimentos_capa',
    'Capa - Depoimentos',
    'Depoimentos',
    NULL,
    'Relatos de participantes poderão ser exibidos aqui em formato de carrossel.',
    'capa_pos_destaques',
    'depoimentos_slider',
    1,
    80,
    0,
    'Módulo principal do bloco de depoimentos. Os itens do carrossel devem ser cadastrados como módulos com posição depoimentos_capa_item.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'depoimentos_capa'
);

-- Modelos inativos para orientar o cadastro dos depoimentos sem publicar conteúdo fictício.
INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'depoimento_capa_modelo_1',
    'Capa - Depoimento modelo 1',
    'Nome do participante',
    'Curso ou identificação breve',
    'Texto do depoimento real do participante.',
    'depoimentos_capa_item',
    'depoimento',
    0,
    10,
    0,
    'Modelo inativo. Duplique/edite, preencha com depoimento real e ative para aparecer no carrossel da capa.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'depoimento_capa_modelo_1'
);

INSERT INTO frontend_modulos
    (codigo, nome_admin, titulo, subtitulo, conteudo, posicao, tipo, ativo, ordem, permite_html, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
SELECT
    'depoimento_capa_modelo_2',
    'Capa - Depoimento modelo 2',
    'Nome do participante',
    'Curso ou identificação breve',
    'Texto do depoimento real do participante.',
    'depoimentos_capa_item',
    'depoimento',
    0,
    20,
    0,
    'Modelo inativo. Duplique/edite, preencha com depoimento real e ative para aparecer no carrossel da capa.',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM frontend_modulos WHERE codigo = 'depoimento_capa_modelo_2'
);
