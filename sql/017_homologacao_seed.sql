-- Polo Rainbow - seed minimo de homologacao
-- Compatibilidade: MySQL 5.7
-- Objetivo: criar um cenario funcional para validar portal publico, admin, professor e aluno.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO configuracoes_globais
    (nome_fantasia, razao_social, cidade, uf, email_institucional, email_suporte, telefone, created_at, updated_at, deleted_at)
SELECT
    'Desbloqueia Cursos',
    'Polo Rainbow Cursos e Eventos',
    'Sao Paulo',
    'SP',
    'contato@polorainbow.com.br',
    'suporte@polorainbow.com.br',
    '(11) 99999-0000',
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM configuracoes_globais
    WHERE deleted_at IS NULL
);

INSERT INTO configuracoes_frontend
    (template_visual_portal, cor_primaria, cor_secundaria, descricao_home, frontend_card_gap, frontend_section_gap, created_at, updated_at, deleted_at)
SELECT
    'padrao',
    '#0c5b4f',
    '#f08c52',
    'Portal de homologacao para cursos, turmas e inscricoes.',
    'clamp(16px, 2vw, 24px)',
    'clamp(24px, 3vw, 40px)',
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM configuracoes_frontend
    WHERE deleted_at IS NULL
);

INSERT INTO usuarios
    (nome, email, cpf, telefone, senha_hash, status, tentativas_login, bloqueado_ate, token_recuperacao, token_recuperacao_expira_em, ultimo_login_em, created_at, updated_at, deleted_at)
VALUES
    ('Admin Homologacao', 'admin.homologacao@polorainbow.com.br', '11111111111', '(11) 90000-1000', '$2y$12$KiHnS0wBddhHajdnCHk6oeQu.7amd6aR8nVkM55f5ncSrCEvHgIWW', 'ativo', 0, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL),
    ('Professor Homologacao', 'professor.homologacao@polorainbow.com.br', '22222222222', '(11) 90000-2000', '$2y$12$8o89hRtjaYM.W52dBOngDeIA/E2AcRBnC7Ac4HjWyTtR/NmQ4FipG', 'ativo', 0, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL),
    ('Aluno Homologacao', 'aluno.homologacao@polorainbow.com.br', '33333333333', '(11) 90000-3000', '$2y$12$WX.ctvEg3nNoy5sdvu7K2eOtsE8ZwzsY2fH6JnD9xvkldHMh/WR3C', 'ativo', 0, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    telefone = VALUES(telefone),
    senha_hash = VALUES(senha_hash),
    status = 'ativo',
    updated_at = NOW(),
    deleted_at = NULL;

SET @admin_usuario_id := (
    SELECT id FROM usuarios WHERE email = 'admin.homologacao@polorainbow.com.br' LIMIT 1
);
SET @professor_usuario_id := (
    SELECT id FROM usuarios WHERE email = 'professor.homologacao@polorainbow.com.br' LIMIT 1
);
SET @aluno_usuario_id := (
    SELECT id FROM usuarios WHERE email = 'aluno.homologacao@polorainbow.com.br' LIMIT 1
);

INSERT INTO usuario_perfis (usuario_id, perfil_id, created_at)
SELECT @admin_usuario_id, p.id, NOW()
FROM perfis p
WHERE p.slug = 'superadmin'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_perfis up WHERE up.usuario_id = @admin_usuario_id AND up.perfil_id = p.id
  );

INSERT INTO usuario_perfis (usuario_id, perfil_id, created_at)
SELECT @professor_usuario_id, p.id, NOW()
FROM perfis p
WHERE p.slug = 'professor'
  AND NOT EXISTS (
      SELECT 1 FROM usuario_perfis up WHERE up.usuario_id = @professor_usuario_id AND up.perfil_id = p.id
  );

INSERT INTO categorias
    (nome, slug, descricao, parent_id, ordem, status, created_at, updated_at, deleted_at)
VALUES
    ('Cursos de Homologacao', 'cursos-homologacao', 'Categoria tecnica para testes do portal.', NULL, 1, 'ativo', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    status = 'ativo',
    updated_at = NOW(),
    deleted_at = NULL;

SET @categoria_homologacao_id := (
    SELECT id FROM categorias WHERE slug = 'cursos-homologacao' LIMIT 1
);

INSERT INTO cursos_eventos
    (categoria_id, nome, slug, tipo, modalidade, thumbnail, descricao_curta, descricao_completa, carga_horaria, valor, usar_turmas, permite_compra_lote, permite_compra_terceiros, certificado_previsto, em_promocao, destaque, ordem, status, exige_presenca, percentual_minimo_presenca, percentual_minimo_conclusao, exige_avaliacao, nota_minima, progresso_base, created_at, updated_at, deleted_at)
VALUES
    (
        @categoria_homologacao_id,
        'Curso Homologacao Portal',
        'curso-homologacao-portal',
        'curso',
        'online_ao_vivo',
        'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
        'Curso publico para validar listagem, detalhe, inscricao e area do professor.',
        'Curso de homologacao criado para testar menu publico, detalhe de curso, checkout inicial, escopo do professor e indicadores do dashboard administrativo.',
        16,
        197.00,
        1,
        1,
        1,
        1,
        0,
        1,
        1,
        'ativo',
        1,
        75.00,
        75.00,
        1,
        70.00,
        'aulas',
        NOW(),
        NOW(),
        NULL
    ),
    (
        @categoria_homologacao_id,
        'Curso Homologacao Inativo',
        'curso-homologacao-inativo',
        'curso',
        'sob_demanda',
        NULL,
        'Curso inativo para confirmar que o frontend nao publica itens fora de escopo.',
        'Este curso fica fora da vitrine publica e serve apenas para teste de filtragem.',
        8,
        97.00,
        1,
        1,
        1,
        1,
        0,
        0,
        99,
        'inativo',
        0,
        75.00,
        75.00,
        0,
        70.00,
        'aulas',
        NOW(),
        NOW(),
        NULL
    )
ON DUPLICATE KEY UPDATE
    categoria_id = VALUES(categoria_id),
    nome = VALUES(nome),
    tipo = VALUES(tipo),
    modalidade = VALUES(modalidade),
    thumbnail = VALUES(thumbnail),
    descricao_curta = VALUES(descricao_curta),
    descricao_completa = VALUES(descricao_completa),
    carga_horaria = VALUES(carga_horaria),
    valor = VALUES(valor),
    usar_turmas = VALUES(usar_turmas),
    permite_compra_lote = VALUES(permite_compra_lote),
    permite_compra_terceiros = VALUES(permite_compra_terceiros),
    certificado_previsto = VALUES(certificado_previsto),
    em_promocao = VALUES(em_promocao),
    destaque = VALUES(destaque),
    ordem = VALUES(ordem),
    status = VALUES(status),
    exige_presenca = VALUES(exige_presenca),
    percentual_minimo_presenca = VALUES(percentual_minimo_presenca),
    percentual_minimo_conclusao = VALUES(percentual_minimo_conclusao),
    exige_avaliacao = VALUES(exige_avaliacao),
    nota_minima = VALUES(nota_minima),
    progresso_base = VALUES(progresso_base),
    updated_at = NOW(),
    deleted_at = NULL;

SET @curso_publico_id := (
    SELECT id FROM cursos_eventos WHERE slug = 'curso-homologacao-portal' LIMIT 1
);
SET @curso_inativo_id := (
    SELECT id FROM cursos_eventos WHERE slug = 'curso-homologacao-inativo' LIMIT 1
);

INSERT INTO turmas
    (curso_evento_id, nome, slug, codigo, data_inicio, data_fim, hora_inicio, hora_fim, inscricoes_abrem_em, inscricoes_encerram_em, local_nome, local_endereco, link_transmissao, link_gravacao, observacoes_publicas, valor_override, vagas, exige_presenca, percentual_minimo_presenca, percentual_minimo_conclusao, exige_avaliacao, nota_minima, progresso_base, status, created_at, updated_at, deleted_at)
VALUES
    (
        @curso_publico_id,
        'Turma Aberta Homologacao',
        'turma-aberta-homologacao',
        'TH-OPEN-001',
        DATE_ADD(CURDATE(), INTERVAL 7 DAY),
        DATE_ADD(CURDATE(), INTERVAL 21 DAY),
        '19:00:00',
        '21:00:00',
        DATE_SUB(NOW(), INTERVAL 2 DAY),
        DATE_ADD(NOW(), INTERVAL 20 DAY),
        'Sala Virtual Rainbow',
        'https://polorainbow.com.br/aovivo',
        'https://polorainbow.com.br/aovivo',
        NULL,
        'Turma publica aberta para validar o fluxo inicial de inscricao.',
        NULL,
        40,
        1,
        75.00,
        75.00,
        1,
        70.00,
        'aulas',
        'aberta',
        NOW(),
        NOW(),
        NULL
    ),
    (
        @curso_publico_id,
        'Turma Encerrada Homologacao',
        'turma-encerrada-homologacao',
        'TH-CLOSE-001',
        DATE_SUB(CURDATE(), INTERVAL 30 DAY),
        DATE_SUB(CURDATE(), INTERVAL 10 DAY),
        '19:00:00',
        '21:00:00',
        DATE_SUB(NOW(), INTERVAL 60 DAY),
        DATE_SUB(NOW(), INTERVAL 15 DAY),
        'Sala Virtual Rainbow',
        'https://polorainbow.com.br/gravado',
        NULL,
        'https://polorainbow.com.br/gravado',
        'Turma encerrada para validar bloqueio de inscricao fora da janela.',
        NULL,
        40,
        1,
        75.00,
        75.00,
        1,
        70.00,
        'aulas',
        'encerrada',
        NOW(),
        NOW(),
        NULL
    )
ON DUPLICATE KEY UPDATE
    curso_evento_id = VALUES(curso_evento_id),
    nome = VALUES(nome),
    slug = VALUES(slug),
    data_inicio = VALUES(data_inicio),
    data_fim = VALUES(data_fim),
    hora_inicio = VALUES(hora_inicio),
    hora_fim = VALUES(hora_fim),
    inscricoes_abrem_em = VALUES(inscricoes_abrem_em),
    inscricoes_encerram_em = VALUES(inscricoes_encerram_em),
    local_nome = VALUES(local_nome),
    local_endereco = VALUES(local_endereco),
    link_transmissao = VALUES(link_transmissao),
    link_gravacao = VALUES(link_gravacao),
    observacoes_publicas = VALUES(observacoes_publicas),
    valor_override = VALUES(valor_override),
    vagas = VALUES(vagas),
    exige_presenca = VALUES(exige_presenca),
    percentual_minimo_presenca = VALUES(percentual_minimo_presenca),
    percentual_minimo_conclusao = VALUES(percentual_minimo_conclusao),
    exige_avaliacao = VALUES(exige_avaliacao),
    nota_minima = VALUES(nota_minima),
    progresso_base = VALUES(progresso_base),
    status = VALUES(status),
    updated_at = NOW(),
    deleted_at = NULL;

SET @turma_aberta_id := (
    SELECT id FROM turmas WHERE codigo = 'TH-OPEN-001' LIMIT 1
);
SET @turma_encerrada_id := (
    SELECT id FROM turmas WHERE codigo = 'TH-CLOSE-001' LIMIT 1
);

INSERT INTO curso_pessoas_vinculadas
    (curso_evento_id, usuario_id, nome, tipo_pessoa, ordem, status, created_at, updated_at, deleted_at)
SELECT
    @curso_publico_id,
    @professor_usuario_id,
    'Professor Homologacao',
    'professor',
    1,
    'ativo',
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM curso_pessoas_vinculadas
    WHERE curso_evento_id = @curso_publico_id
      AND usuario_id = @professor_usuario_id
      AND tipo_pessoa = 'professor'
      AND deleted_at IS NULL
);

INSERT INTO usuario_cursos
    (usuario_id, curso_evento_id, tipo_vinculo, status, created_at, updated_at, deleted_at)
SELECT
    @professor_usuario_id,
    @curso_publico_id,
    'professor',
    'ativo',
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM usuario_cursos
    WHERE usuario_id = @professor_usuario_id
      AND curso_evento_id = @curso_publico_id
      AND tipo_vinculo = 'professor'
      AND deleted_at IS NULL
);

INSERT INTO usuario_turmas
    (usuario_id, turma_id, tipo_vinculo, status, created_at, updated_at, deleted_at)
SELECT
    @professor_usuario_id,
    @turma_aberta_id,
    'professor',
    'ativo',
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM usuario_turmas
    WHERE usuario_id = @professor_usuario_id
      AND turma_id = @turma_aberta_id
      AND tipo_vinculo = 'professor'
      AND deleted_at IS NULL
);

INSERT INTO modulos
    (curso_evento_id, turma_id, titulo, descricao, visivel, ordem, created_at, updated_at, deleted_at)
SELECT
    @curso_publico_id,
    @turma_aberta_id,
    'Modulo Inicial Homologacao',
    'Modulo inicial para testar area interna do curso.',
    1,
    1,
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM modulos
    WHERE curso_evento_id = @curso_publico_id
      AND turma_id = @turma_aberta_id
      AND titulo = 'Modulo Inicial Homologacao'
      AND deleted_at IS NULL
);

SET @modulo_homologacao_id := (
    SELECT id
    FROM modulos
    WHERE curso_evento_id = @curso_publico_id
      AND turma_id = @turma_aberta_id
      AND titulo = 'Modulo Inicial Homologacao'
      AND deleted_at IS NULL
    LIMIT 1
);

INSERT INTO aulas
    (modulo_id, curso_evento_id, turma_id, titulo, conteudo, tipo, url_video, duracao_minutos, visivel, obrigatoria, ordem, created_at, updated_at, deleted_at)
SELECT
    @modulo_homologacao_id,
    @curso_publico_id,
    @turma_aberta_id,
    'Aula de Boas-vindas',
    'Aula inicial para validar fluxo do aluno e do professor.',
    'video',
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    25,
    1,
    1,
    1,
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM aulas
    WHERE modulo_id = @modulo_homologacao_id
      AND titulo = 'Aula de Boas-vindas'
      AND deleted_at IS NULL
);

SET @aula_homologacao_id := (
    SELECT id
    FROM aulas
    WHERE modulo_id = @modulo_homologacao_id
      AND titulo = 'Aula de Boas-vindas'
      AND deleted_at IS NULL
    LIMIT 1
);

INSERT INTO materiais
    (curso_evento_id, turma_id, modulo_id, aula_id, titulo, descricao, arquivo_caminho, arquivo_nome_original, arquivo_mime_type, arquivo_tamanho_bytes, tipo_arquivo, visivel, ordem, created_at, updated_at, deleted_at)
SELECT
    @curso_publico_id,
    @turma_aberta_id,
    @modulo_homologacao_id,
    @aula_homologacao_id,
    'Apostila Homologacao',
    'Material de apoio para o teste da area do curso.',
    'storage/homologacao/apostila-homologacao.pdf',
    'apostila-homologacao.pdf',
    'application/pdf',
    102400,
    'pdf',
    1,
    1,
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM materiais
    WHERE aula_id = @aula_homologacao_id
      AND titulo = 'Apostila Homologacao'
      AND deleted_at IS NULL
);

INSERT INTO links_externos
    (curso_evento_id, turma_id, modulo_id, aula_id, titulo, url, tipo_link, visivel, ordem, created_at, updated_at, deleted_at)
SELECT
    @curso_publico_id,
    @turma_aberta_id,
    @modulo_homologacao_id,
    @aula_homologacao_id,
    'Link complementar',
    'https://polorainbow.com.br/homologacao/link-complementar',
    'apoio',
    1,
    1,
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM links_externos
    WHERE aula_id = @aula_homologacao_id
      AND titulo = 'Link complementar'
      AND deleted_at IS NULL
);

INSERT INTO avaliacoes
    (curso_evento_id, turma_id, titulo, descricao, tipo, visivel, obrigatoria, percentual_minimo, nota_minima, ordem, created_at, updated_at, deleted_at)
SELECT
    @curso_publico_id,
    @turma_aberta_id,
    'Avaliacao Final Homologacao',
    'Avaliacao minima para testar o modulo academico.',
    'avaliacao',
    1,
    1,
    70.00,
    70.00,
    1,
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM avaliacoes
    WHERE curso_evento_id = @curso_publico_id
      AND turma_id = @turma_aberta_id
      AND titulo = 'Avaliacao Final Homologacao'
      AND deleted_at IS NULL
);

SET @avaliacao_homologacao_id := (
    SELECT id
    FROM avaliacoes
    WHERE curso_evento_id = @curso_publico_id
      AND turma_id = @turma_aberta_id
      AND titulo = 'Avaliacao Final Homologacao'
      AND deleted_at IS NULL
    LIMIT 1
);

INSERT INTO avaliacao_perguntas
    (avaliacao_id, enunciado, tipo_resposta, opcoes_json, obrigatoria, ordem, created_at, updated_at, deleted_at)
SELECT
    @avaliacao_homologacao_id,
    'Qual e o objetivo principal deste seed de homologacao?',
    'dissertativa',
    NULL,
    1,
    1,
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM avaliacao_perguntas
    WHERE avaliacao_id = @avaliacao_homologacao_id
      AND ordem = 1
      AND deleted_at IS NULL
);

INSERT INTO pedidos
    (codigo, comprador_usuario_id, pagador_usuario_id, pagador_nome, pagador_cpf, pagador_email, pagador_telefone, tipo_pedido, status, aprovado_por_usuario_id, aprovado_em, data_expiracao_pagamento, subtotal, desconto_total, acrescimo_total, total, observacoes_internas, observacoes_publicas, canal_origem, created_at, updated_at, deleted_at)
SELECT
    'PED-HOM-001',
    @aluno_usuario_id,
    @aluno_usuario_id,
    'Aluno Homologacao',
    '33333333333',
    'aluno.homologacao@polorainbow.com.br',
    '(11) 90000-3000',
    'propria',
    'pago',
    @admin_usuario_id,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 2 DAY),
    197.00,
    0.00,
    0.00,
    197.00,
    'Pedido homologado para preencher dashboard e area do aluno.',
    'Pedido de teste aprovado.',
    'seed_homologacao',
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM pedidos WHERE codigo = 'PED-HOM-001' AND deleted_at IS NULL
);

INSERT INTO pedidos
    (codigo, comprador_usuario_id, pagador_usuario_id, pagador_nome, pagador_cpf, pagador_email, pagador_telefone, tipo_pedido, status, aprovado_por_usuario_id, aprovado_em, data_expiracao_pagamento, subtotal, desconto_total, acrescimo_total, total, observacoes_internas, observacoes_publicas, canal_origem, created_at, updated_at, deleted_at)
SELECT
    'PED-HOM-002',
    @aluno_usuario_id,
    @aluno_usuario_id,
    'Aluno Homologacao',
    '33333333333',
    'aluno.homologacao@polorainbow.com.br',
    '(11) 90000-3000',
    'propria',
    'comprovante_enviado',
    NULL,
    NULL,
    DATE_ADD(NOW(), INTERVAL 2 DAY),
    197.00,
    0.00,
    0.00,
    197.00,
    'Pedido com comprovante pendente para analise do admin.',
    'Pedido pendente para teste.',
    'seed_homologacao',
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM pedidos WHERE codigo = 'PED-HOM-002' AND deleted_at IS NULL
);

SET @pedido_pago_id := (
    SELECT id FROM pedidos WHERE codigo = 'PED-HOM-001' LIMIT 1
);
SET @pedido_pendente_id := (
    SELECT id FROM pedidos WHERE codigo = 'PED-HOM-002' LIMIT 1
);

INSERT INTO pedido_itens
    (pedido_id, curso_evento_id, turma_id, quantidade, valor_unitario, valor_total, status, created_at, updated_at, deleted_at)
SELECT
    @pedido_pago_id,
    @curso_publico_id,
    @turma_aberta_id,
    1,
    197.00,
    197.00,
    'ativo',
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM pedido_itens
    WHERE pedido_id = @pedido_pago_id
      AND curso_evento_id = @curso_publico_id
      AND turma_id = @turma_aberta_id
      AND deleted_at IS NULL
);

INSERT INTO pedido_itens
    (pedido_id, curso_evento_id, turma_id, quantidade, valor_unitario, valor_total, status, created_at, updated_at, deleted_at)
SELECT
    @pedido_pendente_id,
    @curso_publico_id,
    @turma_aberta_id,
    1,
    197.00,
    197.00,
    'ativo',
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM pedido_itens
    WHERE pedido_id = @pedido_pendente_id
      AND curso_evento_id = @curso_publico_id
      AND turma_id = @turma_aberta_id
      AND deleted_at IS NULL
);

SET @pedido_pago_item_id := (
    SELECT id
    FROM pedido_itens
    WHERE pedido_id = @pedido_pago_id
      AND curso_evento_id = @curso_publico_id
      AND turma_id = @turma_aberta_id
      AND deleted_at IS NULL
    LIMIT 1
);
SET @pedido_pendente_item_id := (
    SELECT id
    FROM pedido_itens
    WHERE pedido_id = @pedido_pendente_id
      AND curso_evento_id = @curso_publico_id
      AND turma_id = @turma_aberta_id
      AND deleted_at IS NULL
    LIMIT 1
);

INSERT INTO participantes_pedido
    (pedido_id, pedido_item_id, usuario_id, nome, cpf, email, telefone, ordem, status, created_at, updated_at, deleted_at)
SELECT
    @pedido_pago_id,
    @pedido_pago_item_id,
    @aluno_usuario_id,
    'Aluno Homologacao',
    '33333333333',
    'aluno.homologacao@polorainbow.com.br',
    '(11) 90000-3000',
    1,
    'ativo',
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM participantes_pedido
    WHERE pedido_id = @pedido_pago_id
      AND pedido_item_id = @pedido_pago_item_id
      AND email = 'aluno.homologacao@polorainbow.com.br'
      AND deleted_at IS NULL
);

SET @participante_pago_id := (
    SELECT id
    FROM participantes_pedido
    WHERE pedido_id = @pedido_pago_id
      AND pedido_item_id = @pedido_pago_item_id
      AND email = 'aluno.homologacao@polorainbow.com.br'
      AND deleted_at IS NULL
    LIMIT 1
);

INSERT INTO inscricoes
    (pedido_id, pedido_item_id, participante_pedido_id, usuario_id, curso_evento_id, turma_id, status, percentual_progresso, presenca_percentual, nota_final, apto_certificado, confirmado_em, created_at, updated_at, deleted_at)
SELECT
    @pedido_pago_id,
    @pedido_pago_item_id,
    @participante_pago_id,
    @aluno_usuario_id,
    @curso_publico_id,
    @turma_aberta_id,
    'em_andamento',
    45.00,
    100.00,
    85.00,
    1,
    NOW(),
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM inscricoes
    WHERE pedido_item_id = @pedido_pago_item_id
      AND participante_pedido_id = @participante_pago_id
      AND deleted_at IS NULL
);

SET @inscricao_homologacao_id := (
    SELECT id
    FROM inscricoes
    WHERE pedido_item_id = @pedido_pago_item_id
      AND participante_pedido_id = @participante_pago_id
      AND deleted_at IS NULL
    LIMIT 1
);

INSERT INTO progresso_usuario_modulos
    (inscricao_id, usuario_id, curso_evento_id, turma_id, modulo_id, percentual, concluido, concluido_em, created_at, updated_at)
SELECT
    @inscricao_homologacao_id,
    @aluno_usuario_id,
    @curso_publico_id,
    @turma_aberta_id,
    @modulo_homologacao_id,
    45.00,
    0,
    NULL,
    NOW(),
    NOW()
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM progresso_usuario_modulos
    WHERE inscricao_id = @inscricao_homologacao_id
      AND usuario_id = @aluno_usuario_id
      AND modulo_id = @modulo_homologacao_id
);

INSERT INTO progresso_usuario_aulas
    (inscricao_id, usuario_id, curso_evento_id, turma_id, aula_id, percentual, concluido, visualizado_em, concluido_em, created_at, updated_at)
SELECT
    @inscricao_homologacao_id,
    @aluno_usuario_id,
    @curso_publico_id,
    @turma_aberta_id,
    @aula_homologacao_id,
    45.00,
    0,
    NOW(),
    NULL,
    NOW(),
    NOW()
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM progresso_usuario_aulas
    WHERE inscricao_id = @inscricao_homologacao_id
      AND usuario_id = @aluno_usuario_id
      AND aula_id = @aula_homologacao_id
);

INSERT INTO presencas
    (inscricao_id, pedido_id, pedido_item_id, participante_pedido_id, usuario_id, curso_evento_id, turma_id, aula_id, data_presenca, status, observacao, marcado_por_usuario_id, created_at, updated_at, deleted_at)
SELECT
    @inscricao_homologacao_id,
    @pedido_pago_id,
    @pedido_pago_item_id,
    @participante_pago_id,
    @aluno_usuario_id,
    @curso_publico_id,
    @turma_aberta_id,
    @aula_homologacao_id,
    CURDATE(),
    'presente',
    'Presenca seed de homologacao.',
    @professor_usuario_id,
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM presencas
    WHERE inscricao_id = @inscricao_homologacao_id
      AND aula_id = @aula_homologacao_id
      AND data_presenca = CURDATE()
      AND deleted_at IS NULL
);

INSERT INTO notas_avaliacoes
    (avaliacao_id, inscricao_id, usuario_id, nota, percentual, status, observacao, corrigida_por_usuario_id, corrigida_em, created_at, updated_at, deleted_at)
SELECT
    @avaliacao_homologacao_id,
    @inscricao_homologacao_id,
    @aluno_usuario_id,
    85.00,
    85.00,
    'aprovada',
    'Nota seed de homologacao.',
    @professor_usuario_id,
    NOW(),
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM notas_avaliacoes
    WHERE avaliacao_id = @avaliacao_homologacao_id
      AND inscricao_id = @inscricao_homologacao_id
      AND usuario_id = @aluno_usuario_id
      AND deleted_at IS NULL
);

INSERT INTO comprovantes_pix
    (pedido_id, usuario_id, arquivo_caminho, arquivo_nome_original, arquivo_mime_type, arquivo_tamanho_bytes, valor_informado, versao, is_atual, motivo_reenvio, enviado_em, status, analise_observacao, analisado_por_usuario_id, analisado_em, created_at, updated_at, deleted_at)
SELECT
    @pedido_pendente_id,
    @aluno_usuario_id,
    'storage/homologacao/comprovante-pix-homologacao.png',
    'comprovante-pix-homologacao.png',
    'image/png',
    24576,
    197.00,
    1,
    1,
    NULL,
    NOW(),
    'em_analise',
    'Comprovante seed para a fila do admin.',
    @admin_usuario_id,
    NOW(),
    NOW(),
    NOW(),
    NULL
FROM dual
WHERE NOT EXISTS (
    SELECT 1
    FROM comprovantes_pix
    WHERE pedido_id = @pedido_pendente_id
      AND versao = 1
      AND deleted_at IS NULL
);

SET FOREIGN_KEY_CHECKS = 1;
