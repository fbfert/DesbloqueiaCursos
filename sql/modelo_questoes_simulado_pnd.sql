-- =====================================================================
-- MODELO DE IMPORTAÇÃO — Banco de questões do Simulado oficial PND
-- Curso 118 · item 1537 · quiz 24
--
-- NÃO execute este arquivo como está: ele é um molde.
-- Copie os blocos, troque o conteúdo e rode no phpMyAdmin.
--
-- Depois de importar, rode a verificação:
--   php scripts/verificar_banco_questoes.php --quiz=24
-- =====================================================================

-- IDs FIXOS (já criados em produção — não invente outros):
--   bloco_id = 1  → FGD         (30 objetivas por tentativa)
--   bloco_id = 2  → PEDAGOGIA   (50 objetivas por tentativa)
--   bloco_id = 3  → DISCURSIVA  (1 discursiva por tentativa)
--
-- dificuldade: 'facil' | 'media' | 'dificil'
--   A distribuição configurada é 20% / 60% / 20%. Para o sorteio nunca
--   precisar completar com outra faixa, o banco deve ter pelo menos:
--     FGD (90 itens):        18 fáceis · 54 médias · 18 difíceis
--     PEDAGOGIA (150 itens): 30 fáceis · 90 médias · 30 difíceis
--
-- REGRAS QUE O SISTEMA EXIGE:
--   - exatamente 1 alternativa com correta = 1 por questão objetiva;
--   - no mínimo 2 alternativas (o padrão da PND são 5: A a E);
--   - status = 'ativo' para a questão entrar no sorteio;
--   - tema é opcional, mas alimenta o "desempenho por tema" do aluno.


-- ---------------------------------------------------------------------
-- 1. QUESTÃO OBJETIVA (repita este par de comandos para cada questão)
-- ---------------------------------------------------------------------

INSERT INTO conteudo_quiz_perguntas
    (quiz_id, bloco_id, enunciado, tipo, dificuldade, tema, status,
     explicacao, referencia, peso, obrigatoria, ordem, created_at, updated_at)
VALUES
    (24, 1,
     'Enunciado da questão aqui.',
     'multipla_escolha',
     'media',
     'Políticas educacionais',
     'ativo',
     'Comentário exibido ao aluno depois do envio (opcional).',
     'PND 2024 · adaptada',
     1.00, 1, 1, NOW(), NOW());

SET @pergunta := LAST_INSERT_ID();

INSERT INTO conteudo_quiz_alternativas (pergunta_id, texto, correta, ordem, created_at, updated_at) VALUES
    (@pergunta, 'Texto da alternativa A.', 0, 1, NOW(), NOW()),
    (@pergunta, 'Texto da alternativa B.', 1, 2, NOW(), NOW()),   -- <<< a correta
    (@pergunta, 'Texto da alternativa C.', 0, 3, NOW(), NOW()),
    (@pergunta, 'Texto da alternativa D.', 0, 4, NOW(), NOW()),
    (@pergunta, 'Texto da alternativa E.', 0, 5, NOW(), NOW());


-- ---------------------------------------------------------------------
-- 2. QUESTÃO DISCURSIVA (bloco 3 — sem alternativas)
-- ---------------------------------------------------------------------
-- Cadastre mais de uma: o sistema sorteia 1 por tentativa e, com
-- evitar_repeticao_tentativas ligado, tenta não repetir entre as 3 tentativas.

INSERT INTO conteudo_quiz_perguntas
    (quiz_id, bloco_id, enunciado, tipo, dificuldade, tema, status,
     rubrica, nota_maxima, peso, obrigatoria, ordem, created_at, updated_at)
VALUES
    (24, 3,
     'Enunciado completo da questão discursiva, incluindo o texto de apoio.',
     'discursiva',
     'media',
     'Prática docente',
     'ativo',
     'Critérios de correção: (1) compreensão da situação; (2) fundamentação teórica; (3) proposta de intervenção viável; (4) coesão e norma culta.',
     10.00,
     1.00, 1, 1, NOW(), NOW());


-- ---------------------------------------------------------------------
-- 3. FORMA COMPACTA — várias questões de uma vez
-- ---------------------------------------------------------------------
-- Se preferir, insira todas as perguntas primeiro e depois as alternativas
-- referenciando a pergunta pelo enunciado. Funciona, mas exige que os
-- enunciados sejam únicos. Exemplo para uma questão:

-- INSERT INTO conteudo_quiz_alternativas (pergunta_id, texto, correta, ordem, created_at, updated_at)
-- SELECT id, 'Texto da alternativa A.', 0, 1, NOW(), NOW()
--   FROM conteudo_quiz_perguntas WHERE quiz_id = 24 AND enunciado = 'Enunciado exato da questão.';


-- ---------------------------------------------------------------------
-- 4. CONFERÊNCIAS RÁPIDAS (rode depois de importar)
-- ---------------------------------------------------------------------

-- 4.1 Quantas questões ativas por bloco e dificuldade
SELECT b.codigo, p.dificuldade, COUNT(*) AS total
FROM conteudo_quiz_perguntas p
INNER JOIN conteudo_quiz_blocos b ON b.id = p.bloco_id
WHERE p.quiz_id = 24 AND p.status = 'ativo' AND p.deleted_at IS NULL
GROUP BY b.codigo, p.dificuldade
ORDER BY b.codigo, p.dificuldade;

-- 4.2 Questões objetivas com número errado de alternativas corretas
--     (o resultado TEM que vir vazio)
SELECT p.id, LEFT(p.enunciado, 60) AS enunciado,
       SUM(a.correta) AS corretas, COUNT(a.id) AS alternativas
FROM conteudo_quiz_perguntas p
LEFT JOIN conteudo_quiz_alternativas a ON a.pergunta_id = p.id AND a.deleted_at IS NULL
WHERE p.quiz_id = 24 AND p.tipo = 'multipla_escolha' AND p.deleted_at IS NULL
GROUP BY p.id
HAVING corretas <> 1 OR alternativas < 2;

-- 4.3 Dificuldade inválida (resultado TEM que vir vazio)
SELECT id, dificuldade FROM conteudo_quiz_perguntas
WHERE quiz_id = 24 AND deleted_at IS NULL
  AND dificuldade NOT IN ('facil', 'media', 'dificil');

-- 4.4 Questão sem bloco ou com tipo divergente do bloco
--     (resultado TEM que vir vazio)
SELECT p.id, p.tipo, b.codigo, b.tipo_questao
FROM conteudo_quiz_perguntas p
LEFT JOIN conteudo_quiz_blocos b ON b.id = p.bloco_id
WHERE p.quiz_id = 24 AND p.deleted_at IS NULL
  AND (p.bloco_id IS NULL OR p.tipo <> b.tipo_questao);
