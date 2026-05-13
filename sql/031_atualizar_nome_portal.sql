-- Desbloqueia Cursos - atualização do nome institucional e do topo público
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

UPDATE configuracoes_globais
SET nome_fantasia = 'Desbloqueia Cursos',
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND (nome_fantasia = 'Polo Rainbow' OR nome_fantasia IS NULL OR nome_fantasia = '');

UPDATE frontend_modulos
SET titulo = 'Desbloqueia Cursos',
    imagem_alt = 'Desbloqueia Cursos',
    updated_at = NOW()
WHERE codigo = 'topo_site'
  AND deleted_at IS NULL;

UPDATE frontend_modulos
SET titulo = 'Desbloqueia Cursos',
    subtitulo = 'Portal público para cursos, turmas e inscrições iniciais.',
    updated_at = NOW()
WHERE codigo = 'antes_rodape'
  AND deleted_at IS NULL;

UPDATE frontend_modulos
SET conteudo = '{ano} Desbloqueia Cursos.',
    updated_at = NOW()
WHERE codigo = 'rodape'
  AND deleted_at IS NULL;

UPDATE paginas
SET resumo = REPLACE(resumo, 'Polo Rainbow', 'Desbloqueia Cursos'),
    conteudo_html = REPLACE(conteudo_html, 'Polo Rainbow', 'Desbloqueia Cursos'),
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND slug IN ('termos-de-uso', 'politica-de-privacidade');
