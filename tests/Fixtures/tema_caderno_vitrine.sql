-- =============================================================================
-- Fixture LOCAL da vitrine do tema caderno.
--
--   *** NÃO aplicar em produção. ***  Só para o banco de desenvolvimento local
--   (docker/local), que não tem cursos, categorias com cursos nem capas.
--
-- O que cria (idempotente: pode rodar de novo; atualiza pelo slug/código):
--   - 6 categorias (Prova Nacional Docente, Cursos Formativos, Direito na
--     Prática, Neurociência, Inteligência Artificial, Extensão Curricular);
--   - 12 cursos ativos espelhando o catálogo real (títulos, preços,
--     valor_promocional, em_promocao em 2 deles, carga horária, modalidade);
--   - turmas ABERTAS para 10 cursos. Casos de borda:
--       * "PND na prática · Educação Física": nenhuma turma (não aparece no
--         catálogo, que exige turma aberta; a página do curso mostra o estado
--         sem inscrição);
--       * "Oficinas de ACE": só uma turma encerrada (mesmo efeito, outro motivo);
--       * "IA na prática": sem conteúdo programático;
--     os demais têm conteúdo programático com 4 a 6 módulos;
--   - páginas institucionais /quem-somos e /onde-estamos publicadas.
--
-- Capas: o CursoService só aceita thumbnail em /assets/uploads/thumbnails/ (e o
-- arquivo precisa existir). As imagens de exemplo ficam versionadas em
-- assets/caderno/exemplos/; copie-as antes ou depois de aplicar o SQL:
--
--   mkdir -p assets/uploads/thumbnails
--   for n in 1 2 3; do cp assets/caderno/exemplos/capa-$n.png assets/uploads/thumbnails/caderno-capa-$n.png; done
--   docker exec -i desbloqueia-db-1 mysql -uroot desbloqueia_local < tests/Fixtures/tema_caderno_vitrine.sql
--
-- (assets/uploads/ é ignorado pelo git; as cópias não vão para commit.)
-- =============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Categorias
-- ---------------------------------------------------------------------------
INSERT INTO categorias (nome, slug, descricao, thumbnail, parent_id, ordem, status, created_at, updated_at, deleted_at) VALUES
  ('Prova Nacional Docente', 'prova-nacional-docente', 'Preparação para a Prova Nacional Docente por área de licenciatura.', NULL, NULL, 10, 'ativo', NOW(), NOW(), NULL),
  ('Cursos Formativos', 'cursos-formativos', 'Formação prática para a vida acadêmica: projetos, TCC e extensão.', NULL, NULL, 20, 'ativo', NOW(), NOW(), NULL),
  ('Direito na Prática', 'direito-na-pratica', 'Preparação para o Exame de Ordem e temas do dia a dia jurídico.', NULL, NULL, 30, 'ativo', NOW(), NOW(), NULL),
  ('Neurociência', 'neurociencia', 'Neurociência aplicada à educação e ao desenvolvimento infantil.', NULL, NULL, 40, 'ativo', NOW(), NOW(), NULL),
  ('Inteligência Artificial', 'inteligencia-artificial', 'Ferramentas de inteligência artificial para estudar e trabalhar melhor.', NULL, NULL, 50, 'ativo', NOW(), NOW(), NULL),
  ('Extensão Curricular', 'extensao-curricular', 'Atividades de extensão curricular presenciais e on-line.', NULL, NULL, 60, 'ativo', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), ordem = VALUES(ordem), status = 'ativo', deleted_at = NULL, updated_at = NOW();

SET @cat_pnd   = (SELECT id FROM categorias WHERE slug = 'prova-nacional-docente');
SET @cat_form  = (SELECT id FROM categorias WHERE slug = 'cursos-formativos');
SET @cat_dir   = (SELECT id FROM categorias WHERE slug = 'direito-na-pratica');
SET @cat_neuro = (SELECT id FROM categorias WHERE slug = 'neurociencia');
SET @cat_ia    = (SELECT id FROM categorias WHERE slug = 'inteligencia-artificial');
SET @cat_ext   = (SELECT id FROM categorias WHERE slug = 'extensao-curricular');

SET @capa1 = '/assets/uploads/thumbnails/caderno-capa-1.png';
SET @capa2 = '/assets/uploads/thumbnails/caderno-capa-2.png';
SET @capa3 = '/assets/uploads/thumbnails/caderno-capa-3.png';

-- Módulos no formato JSON gravado pelo admin: [{"titulo": ..., "itens": [...]}]
SET @mod_pnd = '[{"titulo":"Módulo 1 — Como a prova é organizada","itens":["Estrutura e pesos da Prova Nacional Docente","Leitura do edital sem susto"]},{"titulo":"Módulo 2 — Fundamentos da educação","itens":["Didática e planejamento","Avaliação da aprendizagem"]},{"titulo":"Módulo 3 — Conhecimentos específicos","itens":["Conteúdos mais cobrados da área","Questões comentadas"]},{"titulo":"Módulo 4 — Legislação educacional","itens":["LDB e BNCC na prática","Base legal da docência"]},{"titulo":"Módulo 5 — Simulado oficial","itens":["Simulado no formato da prova","Correção e plano de revisão"]}]';

-- ---------------------------------------------------------------------------
-- Cursos (12)
-- ---------------------------------------------------------------------------
INSERT INTO cursos_eventos
  (categoria_id, nome, slug, tipo, modalidade, thumbnail, descricao_curta, descricao_completa, carga_horaria,
   valor, valor_promocional, usar_turmas, permite_compra_lote, permite_compra_terceiros, certificado_previsto,
   em_promocao, destaque, ordem, status, exige_presenca, percentual_minimo_presenca, percentual_minimo_conclusao,
   exige_avaliacao, nota_minima, progresso_base, created_at, updated_at, deleted_at,
   objetivo_geral, publico_alvo, metodologia, avaliacao, conteudo_programatico_tipo, conteudo_programatico_modulos)
VALUES
  (@cat_dir, 'OAB 1ª Fase Completo: curso preparatório para o Exame de Ordem', 'caderno-oab-1-fase-completo', 'curso', 'sob_demanda', @capa1,
   'Preparação completa para a 1ª fase do Exame de Ordem, com videoaulas, questões comentadas e simulados.',
   'Um curso para quem quer chegar à 1ª fase do Exame de Ordem com método: todas as disciplinas cobradas, resumos objetivos e treino constante com questões no formato da prova.',
   80, 400.00, 199.00, 1, 1, 1, 1, 1, 1, 10, 'ativo', 0, 0.00, 75.00, 1, 70.00, 'aulas', NOW(), NOW(), NULL,
   'Preparar o aluno para a aprovação na 1ª fase do Exame de Ordem.',
   'Estudantes dos últimos períodos de Direito e bacharéis que vão prestar o Exame de Ordem.',
   'Videoaulas sob demanda, resumos para revisão e listas de questões comentadas por disciplina.',
   'Simulados no formato oficial, com mais de uma tentativa.',
   'modulos', '[{"titulo":"Módulo 1 — Ética profissional","itens":["Estatuto da Advocacia","Código de Ética e Disciplina"]},{"titulo":"Módulo 2 — Direito Constitucional","itens":["Direitos fundamentais","Organização do Estado"]},{"titulo":"Módulo 3 — Direito Civil e Processo Civil","itens":["Parte geral e contratos","Recursos e execução"]},{"titulo":"Módulo 4 — Direito Penal e Processo Penal","itens":["Teoria do crime","Prisões e recursos"]},{"titulo":"Módulo 5 — Direito do Trabalho e Tributário","itens":["Contrato de trabalho","Tributos em espécie"]},{"titulo":"Módulo 6 — Simulados","itens":["Simulado completo","Revisão final"]}]'),

  (@cat_pnd, 'PND na prática · Pedagogia', 'caderno-pnd-pedagogia', 'curso', 'online_ao_vivo', @capa2,
   'Aulas ao vivo para a Prova Nacional Docente na área de Pedagogia.',
   'Preparação direta ao ponto para a Prova Nacional Docente em Pedagogia: aulas ao vivo, material de apoio e simulado no formato oficial.',
   30, 150.00, 100.00, 1, 1, 1, 1, 0, 1, 20, 'ativo', 1, 75.00, 75.00, 1, 70.00, 'aulas', NOW(), NOW(), NULL,
   'Preparar professores de Pedagogia para a Prova Nacional Docente.',
   'Licenciados e licenciandos em Pedagogia.',
   'Aulas on-line ao vivo, com gravação disponível e exercícios entre os encontros.',
   'Simulado no formato oficial ao final do curso.',
   'modulos', @mod_pnd),

  (@cat_neuro, 'O que prejudica o cérebro infantil em formação? A neurociência explica!', 'caderno-cerebro-infantil-neurociencia', 'curso', 'online_ao_vivo', @capa3,
   'O que a neurociência sabe sobre telas, sono, estresse e aprendizagem na infância.',
   'Um encontro ao vivo para pais e educadores entenderem o que favorece e o que atrapalha o cérebro infantil em formação, com exemplos do dia a dia da escola e de casa.',
   8, 90.00, 45.00, 1, 1, 1, 1, 1, 1, 30, 'ativo', 1, 75.00, 75.00, 0, 0.00, 'aulas', NOW(), NOW(), NULL,
   'Apresentar, em linguagem simples, os fatores que prejudicam o desenvolvimento do cérebro infantil.',
   'Professores da educação infantil e dos anos iniciais, pais e responsáveis.',
   NULL, NULL,
   'modulos', '[{"titulo":"Módulo 1 — Como o cérebro se forma","itens":["Janelas de desenvolvimento","Plasticidade"]},{"titulo":"Módulo 2 — Telas e atenção","itens":["O que dizem os estudos","Combinados possíveis"]},{"titulo":"Módulo 3 — Sono, estresse e alimentação","itens":["Rotina e aprendizagem","Sinais de alerta"]},{"titulo":"Módulo 4 — O que fazer na escola","itens":["Estratégias em sala","Conversa com as famílias"]}]'),

  (@cat_ia, 'IA na prática, como usar nos estudos!', 'caderno-ia-na-pratica-estudos', 'curso', 'online_ao_vivo', @capa1,
   'Como usar ferramentas de inteligência artificial para estudar melhor, sem atalhos que atrapalham.',
   'Um curso curto e prático para usar inteligência artificial como apoio aos estudos: resumir, revisar, praticar e organizar a rotina, com cuidado ético.',
   8, 90.00, 45.00, 1, 1, 1, 1, 0, 1, 40, 'ativo', 1, 75.00, 75.00, 0, 0.00, 'aulas', NOW(), NOW(), NULL,
   NULL, NULL, NULL, NULL,
   'texto', NULL),

  (@cat_pnd, 'PND na prática · Geografia', 'caderno-pnd-geografia', 'curso', 'online_ao_vivo', @capa2,
   'Aulas ao vivo para a Prova Nacional Docente na área de Geografia.',
   'Preparação para a Prova Nacional Docente em Geografia, com aulas ao vivo e simulado no formato oficial.',
   30, 150.00, 100.00, 1, 1, 1, 1, 0, 1, 50, 'ativo', 1, 75.00, 75.00, 1, 70.00, 'aulas', NOW(), NOW(), NULL,
   NULL, NULL, NULL, NULL, 'modulos', @mod_pnd),

  (@cat_pnd, 'PND na prática · Letras Português', 'caderno-pnd-letras-portugues', 'curso', 'online_ao_vivo', @capa3,
   'Aulas ao vivo para a Prova Nacional Docente na área de Letras Português.',
   'Preparação para a Prova Nacional Docente em Letras Português, com aulas ao vivo e simulado no formato oficial.',
   30, 150.00, 100.00, 1, 1, 1, 1, 0, 0, 60, 'ativo', 1, 75.00, 75.00, 1, 70.00, 'aulas', NOW(), NOW(), NULL,
   NULL, NULL, NULL, NULL, 'modulos', @mod_pnd),

  (@cat_pnd, 'PND na prática · Matemática', 'caderno-pnd-matematica', 'curso', 'online_ao_vivo', @capa1,
   'Aulas ao vivo para a Prova Nacional Docente na área de Matemática.',
   'Preparação para a Prova Nacional Docente em Matemática, com aulas ao vivo e simulado no formato oficial.',
   30, 150.00, 100.00, 1, 1, 1, 1, 0, 1, 70, 'ativo', 1, 75.00, 75.00, 1, 70.00, 'aulas', NOW(), NOW(), NULL,
   NULL, NULL, NULL, NULL, 'modulos', @mod_pnd),

  (@cat_pnd, 'PND na prática · História', 'caderno-pnd-historia', 'curso', 'online_ao_vivo', @capa2,
   'Aulas ao vivo para a Prova Nacional Docente na área de História.',
   'Preparação para a Prova Nacional Docente em História, com aulas ao vivo e simulado no formato oficial.',
   30, 150.00, 100.00, 1, 1, 1, 1, 0, 0, 80, 'ativo', 1, 75.00, 75.00, 1, 70.00, 'aulas', NOW(), NOW(), NULL,
   NULL, NULL, NULL, NULL, 'modulos', @mod_pnd),

  (@cat_pnd, 'PND na prática · Educação Física', 'caderno-pnd-educacao-fisica', 'curso', 'online_ao_vivo', @capa3,
   'Aulas ao vivo para a Prova Nacional Docente na área de Educação Física.',
   'Preparação para a Prova Nacional Docente em Educação Física. Novas turmas em breve.',
   30, 150.00, 100.00, 1, 1, 1, 1, 0, 0, 90, 'ativo', 1, 75.00, 75.00, 1, 70.00, 'aulas', NOW(), NOW(), NULL,
   NULL, NULL, NULL, NULL, 'modulos', @mod_pnd),

  (@cat_form, 'Elaboração de Projeto de ACE', 'caderno-elaboracao-projeto-ace', 'curso', 'sob_demanda', @capa1,
   'Passo a passo para elaborar o projeto de Atividade Curricular de Extensão.',
   'Do problema da comunidade ao cronograma: um roteiro sob demanda para escrever o projeto de ACE exigido pela sua instituição.',
   8, 150.00, 15.00, 1, 1, 1, 1, 0, 0, 100, 'ativo', 0, 0.00, 75.00, 0, 0.00, 'aulas', NOW(), NOW(), NULL,
   NULL, NULL, NULL, NULL,
   'modulos', '[{"titulo":"Módulo 1 — O que é extensão curricular","itens":["Regras gerais","Exemplos de projetos"]},{"titulo":"Módulo 2 — Diagnóstico da comunidade","itens":["Escolha do público","Levantamento de necessidades"]},{"titulo":"Módulo 3 — Escrita do projeto","itens":["Objetivos e justificativa","Metodologia e cronograma"]},{"titulo":"Módulo 4 — Entrega e relatório","itens":["Modelo de relatório","Evidências e registros"]}]'),

  (@cat_form, 'TCC sem Medo', 'caderno-tcc-sem-medo', 'curso', 'online_ao_vivo', @capa2,
   'Um encontro ao vivo para destravar o trabalho de conclusão de curso.',
   'Como escolher o tema, montar o sumário e organizar a escrita do TCC sem travar, com exemplos reais e um plano de estudo semanal.',
   2, 150.00, 35.00, 1, 1, 1, 1, 0, 0, 110, 'ativo', 1, 75.00, 75.00, 0, 0.00, 'aulas', NOW(), NOW(), NULL,
   NULL, NULL, NULL, NULL,
   'modulos', '[{"titulo":"Módulo 1 — Tema e pergunta","itens":["Como recortar o tema","Pergunta de pesquisa"]},{"titulo":"Módulo 2 — Estrutura do trabalho","itens":["Sumário que funciona","Normas sem drama"]},{"titulo":"Módulo 3 — Escrita","itens":["Rotina semanal","Revisão com a orientação"]},{"titulo":"Módulo 4 — Defesa","itens":["Slides","Ensaio da apresentação"]}]'),

  (@cat_ext, 'Oficinas de ACE', 'caderno-oficinas-de-ace', 'curso', 'presencial', @capa3,
   'Oficinas presenciais de Atividade Curricular de Extensão.',
   'Encontros presenciais para planejar e executar atividades de extensão com acompanhamento.',
   20, 100.00, NULL, 1, 1, 1, 1, 0, 0, 120, 'ativo', 1, 75.00, 75.00, 0, 0.00, 'aulas', NOW(), NOW(), NULL,
   NULL, NULL, NULL, NULL,
   'modulos', '[{"titulo":"Oficina 1 — Planejamento","itens":["Público e objetivo","Plano de ação"]},{"titulo":"Oficina 2 — Execução","itens":["Organização da atividade","Registro fotográfico"]},{"titulo":"Oficina 3 — Avaliação","itens":["Retorno da comunidade","Indicadores simples"]},{"titulo":"Oficina 4 — Relatório","itens":["Escrita do relatório","Entrega"]}]')
ON DUPLICATE KEY UPDATE
  categoria_id = VALUES(categoria_id), nome = VALUES(nome), modalidade = VALUES(modalidade), thumbnail = VALUES(thumbnail),
  descricao_curta = VALUES(descricao_curta), descricao_completa = VALUES(descricao_completa), carga_horaria = VALUES(carga_horaria),
  valor = VALUES(valor), valor_promocional = VALUES(valor_promocional), em_promocao = VALUES(em_promocao), destaque = VALUES(destaque),
  ordem = VALUES(ordem), status = 'ativo', deleted_at = NULL, updated_at = NOW(),
  objetivo_geral = VALUES(objetivo_geral), publico_alvo = VALUES(publico_alvo), metodologia = VALUES(metodologia), avaliacao = VALUES(avaliacao),
  conteudo_programatico_tipo = VALUES(conteudo_programatico_tipo), conteudo_programatico_modulos = VALUES(conteudo_programatico_modulos);

-- ---------------------------------------------------------------------------
-- Turmas: abertas para 10 cursos; "Oficinas de ACE" só com turma encerrada;
-- "PND · Educação Física" sem turma. Datas relativas a hoje, para a fixture
-- continuar válida quando for reaplicada.
-- ---------------------------------------------------------------------------
INSERT INTO turmas
  (curso_evento_id, nome, slug, codigo, data_inicio, data_fim, hora_inicio, hora_fim, inscricoes_abrem_em, inscricoes_encerram_em,
   local_nome, local_endereco, observacoes_publicas, valor_override, vagas, status, created_at, updated_at, deleted_at)
SELECT ce.id, t.nome, t.slug, t.codigo,
       DATE_ADD(CURDATE(), INTERVAL t.inicio DAY), DATE_ADD(CURDATE(), INTERVAL t.inicio + t.duracao DAY),
       t.hora_inicio, t.hora_fim,
       DATE_SUB(NOW(), INTERVAL 7 DAY),
       IF(t.status = 'aberta', DATE_ADD(NOW(), INTERVAL GREATEST(t.inicio - 1, 30) DAY), DATE_ADD(NOW(), INTERVAL t.inicio DAY)),
       t.local_nome, NULL, t.obs, NULL, t.vagas, t.status, NOW(), NOW(), NULL
FROM (
  SELECT 'caderno-oab-1-fase-completo' AS curso, 'Turma contínua 2026' AS nome, 'caderno-oab-turma-continua' AS slug, 'CAD-OAB-01' AS codigo, 0 AS inicio, 120 AS duracao, NULL AS hora_inicio, NULL AS hora_fim, 'Plataforma on-line' AS local_nome, 'Acesso imediato após a confirmação do pagamento.' AS obs, 200 AS vagas, 'aberta' AS status
  UNION ALL SELECT 'caderno-pnd-pedagogia', 'Turma de outubro (noite)', 'caderno-pnd-pedagogia-out', 'CAD-PND-PED-01', 10, 30, '19:00:00', '21:00:00', 'Sala virtual ao vivo', 'Encontros às terças e quintas, com gravação.', 60, 'aberta'
  UNION ALL SELECT 'caderno-cerebro-infantil-neurociencia', 'Encontro ao vivo', 'caderno-neuro-encontro', 'CAD-NEURO-01', 14, 0, '19:30:00', '22:00:00', 'Sala virtual ao vivo', NULL, 100, 'aberta'
  UNION ALL SELECT 'caderno-ia-na-pratica-estudos', 'Turma de outubro', 'caderno-ia-out', 'CAD-IA-01', 12, 7, '19:00:00', '21:00:00', 'Sala virtual ao vivo', NULL, 80, 'aberta'
  UNION ALL SELECT 'caderno-pnd-geografia', 'Turma de outubro (noite)', 'caderno-pnd-geografia-out', 'CAD-PND-GEO-01', 11, 30, '19:00:00', '21:00:00', 'Sala virtual ao vivo', NULL, 60, 'aberta'
  UNION ALL SELECT 'caderno-pnd-letras-portugues', 'Turma de outubro (noite)', 'caderno-pnd-letras-out', 'CAD-PND-LET-01', 11, 30, '19:00:00', '21:00:00', 'Sala virtual ao vivo', NULL, 60, 'aberta'
  UNION ALL SELECT 'caderno-pnd-matematica', 'Turma de outubro (noite)', 'caderno-pnd-matematica-out', 'CAD-PND-MAT-01', 12, 30, '19:00:00', '21:00:00', 'Sala virtual ao vivo', NULL, 6, 'aberta'
  UNION ALL SELECT 'caderno-pnd-historia', 'Turma de novembro (noite)', 'caderno-pnd-historia-nov', 'CAD-PND-HIS-01', 30, 30, '19:00:00', '21:00:00', 'Sala virtual ao vivo', NULL, 60, 'aberta'
  UNION ALL SELECT 'caderno-elaboracao-projeto-ace', 'Turma contínua', 'caderno-ace-projeto-continua', 'CAD-ACE-PROJ-01', 0, 90, NULL, NULL, 'Plataforma on-line', 'Acesso imediato após a confirmação do pagamento.', 500, 'aberta'
  UNION ALL SELECT 'caderno-tcc-sem-medo', 'Encontro ao vivo', 'caderno-tcc-encontro', 'CAD-TCC-01', 9, 0, '20:00:00', '22:00:00', 'Sala virtual ao vivo', NULL, 120, 'aberta'
  UNION ALL SELECT 'caderno-tcc-sem-medo', 'Encontro extra (sábado)', 'caderno-tcc-encontro-sabado', 'CAD-TCC-02', 16, 0, '09:00:00', '11:00:00', 'Sala virtual ao vivo', NULL, 120, 'aberta'
  UNION ALL SELECT 'caderno-oficinas-de-ace', 'Turma do 1º semestre', 'caderno-oficinas-ace-sem1', 'CAD-OFI-01', -120, 30, '14:00:00', '18:00:00', 'Auditório do polo', 'Turma já realizada.', 30, 'encerrada'
) t
INNER JOIN cursos_eventos ce ON ce.slug = t.curso
ON DUPLICATE KEY UPDATE
  curso_evento_id = VALUES(curso_evento_id), nome = VALUES(nome), data_inicio = VALUES(data_inicio), data_fim = VALUES(data_fim),
  hora_inicio = VALUES(hora_inicio), hora_fim = VALUES(hora_fim), inscricoes_abrem_em = VALUES(inscricoes_abrem_em),
  inscricoes_encerram_em = VALUES(inscricoes_encerram_em), local_nome = VALUES(local_nome), observacoes_publicas = VALUES(observacoes_publicas),
  vagas = VALUES(vagas), status = VALUES(status), deleted_at = NULL, updated_at = NOW();

-- ---------------------------------------------------------------------------
-- Páginas institucionais (lidas por V2\InstitucionalController pela `rota`,
-- status 'publicada'). O iframe do Google Maps vira o mapa da página.
-- ---------------------------------------------------------------------------
INSERT INTO paginas (titulo, slug, rota, resumo, conteudo_html, status, ordem, publicada_em, created_at, updated_at, deleted_at) VALUES
  ('Quem somos', 'quem-somos', '/quem-somos',
   'Cursos práticos com certificado, turmas ao vivo e acompanhamento.',
   '<h2>Aprender de verdade</h2><p>A Desbloqueia Cursos nasceu para quem precisa de formação prática e reconhecida: professores que vão fazer a Prova Nacional Docente, estudantes que encaram o Exame de Ordem, o TCC ou a extensão curricular.</p><p>Cada curso é uma trilha. Você sabe onde está, o que vem a seguir e o que ganha ao concluir: um certificado com validação pública, que qualquer pessoa confere no site.</p><h2>Como trabalhamos</h2><ul><li>Turmas on-line ao vivo, com gravação.</li><li>Cursos sob demanda, para estudar no seu horário.</li><li>Simulados no formato oficial da prova.</li></ul><p>Dúvidas? Escreva para <a href="mailto:desbloqueiacursos@gmail.com">desbloqueiacursos@gmail.com</a>.</p>',
   'publicada', 10, NOW(), NOW(), NOW(), NULL),
  ('Onde estamos', 'onde-estamos', '/onde-estamos',
   'Atendimento on-line para todo o Brasil e encontros presenciais em Santa Catarina.',
   '<h2>Atendimento</h2><p>As aulas on-line ao vivo e os cursos sob demanda funcionam em qualquer lugar do Brasil. Os encontros presenciais acontecem em Santa Catarina e são informados na página de cada turma.</p><p>WhatsApp: +55 49 991581411<br>E-mail: desbloqueiacursos@gmail.com</p><iframe src="https://www.google.com/maps?q=Santa%20Catarina%2C%20Brasil&amp;output=embed" width="600" height="400" loading="lazy"></iframe>',
   'publicada', 20, NOW(), NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), resumo = VALUES(resumo), conteudo_html = VALUES(conteudo_html),
  status = 'publicada', publicada_em = COALESCE(publicada_em, NOW()), deleted_at = NULL, updated_at = NOW();
