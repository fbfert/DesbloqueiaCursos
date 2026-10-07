-- =============================================================================
-- Fixture da API do app — SOMENTE no banco de teste do container
-- (desbloqueia_app_teste). Aplicada por tests/Api/montar_banco.php DEPOIS de
-- tests/Fixtures/tema_caderno_aluno.sql (aluna 9001 com todos os tipos de
-- conteúdo, quizzes, avaliações, pedidos e certificado).
--
-- Idempotente: apaga e recria as linhas 9101-9199 e os tokens/dispositivos/
-- notificações/limites do app.
--
-- Usuários (senha de todos: Local@12345)
--   9001 aluno.caderno@teste.local   (da fixture do tema)  — aluna principal dos testes
--   9101 aluno.b@teste.local         — outro aluno: inscrição 9101 e certificado APPFIX-2026-0101
--   9102 inativo@teste.local         — status inativo (403 conta_inativa)
--   9103 bloqueio@teste.local        — usado no teste de bloqueio por tentativas (423)
-- =============================================================================

SET NAMES utf8mb4;

SELECT IF(DATABASE() = 'desbloqueia_app_teste', 'fixture app: banco de teste ok', (SELECT 'ABORTADO: banco errado' UNION SELECT 'use desbloqueia_app_teste')) AS guarda_fixture;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM app_tokens;
DELETE FROM app_dispositivos;
DELETE FROM app_notificacoes;
DELETE FROM app_limites_taxa;

DELETE FROM certificados WHERE id BETWEEN 9101 AND 9199;
DELETE FROM inscricoes WHERE id BETWEEN 9101 AND 9199;
DELETE FROM participantes_pedido WHERE pedido_id BETWEEN 9101 AND 9199;
DELETE FROM pedido_itens WHERE pedido_id BETWEEN 9101 AND 9199;
DELETE FROM pedidos WHERE id BETWEEN 9101 AND 9199;
DELETE FROM turmas WHERE id BETWEEN 9101 AND 9199;
DELETE FROM cursos_eventos WHERE id BETWEEN 9101 AND 9199;
DELETE FROM usuario_perfis WHERE usuario_id BETWEEN 9101 AND 9199;
DELETE FROM usuarios WHERE id BETWEEN 9101 AND 9199 OR email LIKE 'app.cadastro.%@teste.local';

-- Usuários
INSERT INTO usuarios (id, nome, email, cpf, telefone, cidade, estado, senha_hash, status, cadastro_status, cadastro_origem, tentativas_login, created_at, updated_at)
VALUES
    (9101, 'ALUNO B DO APP', 'aluno.b@teste.local', '52998224725', '(11) 97777-6655', 'Campinas', 'SP', '$2y$10$so3dNPtguylmlbF7xgeFKOTXpVXYf9gA8ghtweClLmnkcnpibh2bi', 'ativo', 'completo', 'fixture_app', 0, NOW(), NOW()),
    (9102, 'ALUNO INATIVO', 'inativo@teste.local', '11144477735', NULL, NULL, NULL, '$2y$10$so3dNPtguylmlbF7xgeFKOTXpVXYf9gA8ghtweClLmnkcnpibh2bi', 'inativo', 'completo', 'fixture_app', 0, NOW(), NOW()),
    (9103, 'ALUNO BLOQUEIO', 'bloqueio@teste.local', '39053344705', NULL, NULL, NULL, '$2y$10$so3dNPtguylmlbF7xgeFKOTXpVXYf9gA8ghtweClLmnkcnpibh2bi', 'ativo', 'completo', 'fixture_app', 0, NOW(), NOW());
INSERT INTO usuario_perfis (usuario_id, perfil_id, created_at) VALUES (9101, 7, NOW()), (9102, 7, NOW()), (9103, 7, NOW());

-- Curso do aluno B (pago), com certificado emitido
INSERT INTO cursos_eventos (id, nome, slug, tipo, modalidade, descricao_curta, descricao_completa, carga_horaria, valor, usar_turmas, certificado_previsto, status, created_at, updated_at)
VALUES (9101, 'Curso do aluno B (fixture app)', 'fixture-app-curso-b', 'curso', 'sob_demanda', 'Curso usado para provar o isolamento entre alunos.', '<p>Descrição <strong>completa</strong> do curso B.</p><script>alert(1)</script>', 12, 49.9, 1, 1, 'ativo', NOW(), NOW());
INSERT INTO turmas (id, curso_evento_id, nome, slug, codigo, data_inicio, data_fim, vagas, status, created_at, updated_at)
VALUES (9101, 9101, 'Turma B (fixture app)', 'fixture-app-turma-b', 'APPFIX-T9101', '2026-10-01', '2027-03-31', 30, 'aberta', NOW(), NOW());

INSERT INTO pedidos (id, codigo, comprador_usuario_id, pagador_usuario_id, pagador_nome, pagador_cpf, pagador_email, tipo_pedido, status, aprovado_em, subtotal, desconto_total, acrescimo_total, total, canal_origem, created_at, updated_at)
VALUES (9101, 'APP-FIX-9101', 9101, 9101, 'ALUNO B DO APP', '52998224725', 'aluno.b@teste.local', 'propria', 'pago', NOW(), 49.9, 0, 0, 49.9, 'fixture_app', NOW(), NOW());
INSERT INTO pedido_itens (id, pedido_id, curso_evento_id, turma_id, quantidade, valor_unitario, valor_total, status, created_at, updated_at)
VALUES (9101, 9101, 9101, 9101, 1, 49.9, 49.9, 'ativo', NOW(), NOW());
INSERT INTO participantes_pedido (id, pedido_id, pedido_item_id, usuario_id, nome, cpf, email, ordem, status, created_at, updated_at)
VALUES (9101, 9101, 9101, 9101, 'ALUNO B DO APP', '52998224725', 'aluno.b@teste.local', 1, 'ativo', NOW(), NOW());
INSERT INTO inscricoes (id, pedido_id, pedido_item_id, participante_pedido_id, usuario_id, curso_evento_id, turma_id, status, is_presente, percentual_progresso, apto_certificado, confirmado_em, created_at, updated_at)
VALUES (9101, 9101, 9101, 9101, 9101, 9101, 9101, 'certificado_emitido', 0, 100, 1, NOW(), NOW(), NOW());
INSERT INTO certificados (id, template_id, curso_evento_id, turma_id, pedido_id, inscricao_id, participante_pedido_id, usuario_id, codigo, versao, nome_participante, cpf_participante, cpf_mascarado, titulo, status, emitido_em, created_at, updated_at)
VALUES (9101, 1, 9101, 9101, 9101, 9101, 9101, 9101, 'APPFIX-2026-0101', 1, 'ALUNO B DO APP', '52998224725', '***.***.***-25', 'Curso do aluno B (fixture app)', 'emitido', NOW(), NOW(), NOW());

-- Logo padrão do certificado. Sem logo configurado, o CertificadoService usa um
-- PNG transparente embutido (logoTransparenteDataUri) cujo CRC está corrompido:
-- o libpng recusa a imagem e o PDF falha (também na rota pública do site). A
-- produção tem logo configurado; aqui configuramos um PNG válido do repositório.
INSERT INTO configuracoes_certificados (id, certificados_logo_padrao, created_at, updated_at)
VALUES (1, '/assets/caderno/exemplos/capa-1.png', NOW(), NOW())
ON DUPLICATE KEY UPDATE certificados_logo_padrao = VALUES(certificados_logo_padrao);

-- Páginas institucionais que existem só no banco de produção: sem elas o smoke
-- anônimo acusa 404 em /v2/quem-somos e /v2/onde-estamos (dado, não código).
DELETE FROM paginas WHERE rota IN ('/quem-somos', '/onde-estamos') AND slug LIKE 'fixture-app-%';
INSERT INTO paginas (titulo, slug, rota, resumo, conteudo_html, status, ordem, publicada_em, created_at, updated_at)
SELECT 'Quem somos', 'fixture-app-quem-somos', '/quem-somos', NULL, '<p>Página de teste (fixture da API do app).</p>', 'publicada', 10, NOW(), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM paginas WHERE rota = '/quem-somos' AND deleted_at IS NULL);
INSERT INTO paginas (titulo, slug, rota, resumo, conteudo_html, status, ordem, publicada_em, created_at, updated_at)
SELECT 'Onde estamos', 'fixture-app-onde-estamos', '/onde-estamos', NULL, '<p>Página de teste (fixture da API do app).</p>', 'publicada', 11, NOW(), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM paginas WHERE rota = '/onde-estamos' AND deleted_at IS NULL);

SET FOREIGN_KEY_CHECKS = 1;
