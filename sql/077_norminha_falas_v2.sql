-- 077 — A Norminha nas paginas publicas da V2
--
-- O PROBLEMA
--
-- O site que o visitante navega e a V2 (HOME_VERSION=v2). O roteador de
-- contexto ja reconhece as rotas /v2/* desde 22/08/2026, e os toggles estao
-- todos ligados. Mesmo assim a Norminha nao aparecia em nenhuma pagina publica
-- da V2 — so nas areas de estudo.
--
-- A causa nao esta no codigo, esta nos dados. As seis falas cadastradas estao
-- todas presas a rotas da V1 (/, /como-funciona, /cadastro, /curso/*,
-- /aluno/cursos, /aluno/curso/*). Quando nenhuma casa pela rota, o ultimo
-- recurso de TutorFala::buscarAtiva() e buscarPorContexto(), que exige
-- explicitamente `rota IS NULL OR rota = ""` — ou seja, uma fala que valha para
-- o contexto inteiro. Nenhuma das seis tinha isso. Sem fala, sem componente.
--
-- As areas de estudo escapavam porque a Etapa 6 criou uma saudacao padrao para
-- os contextos area_aluno, aula, avaliacao e curso. As paginas publicas nao
-- tem esse resgate, e e por isso que a ausencia so aparecia la.
--
-- A CORRECAO
--
-- Usar o mecanismo que ja existe: fala com rota nula vale para o contexto todo.
-- Nada de codigo novo, e o administrador continua podendo sobrescrever qualquer
-- rota especifica depois — rota exata tem precedencia sobre rota nula.
--
--   * home           — a fala 1 deixa de ser exclusiva de "/" e passa a valer
--                      para "/" e "/v2/". Uma linha, sem texto duplicado.
--   * institucional  — uma fala geral para as paginas que nao tinham nenhuma
--                      (quem-somos, contato, sobre, onde-estamos, termos,
--                      politica de privacidade, validacao de certificado), mais
--                      duas especificas que espelham na V2 o que ja existia na
--                      V1 para "como funciona" e "cadastro". A de "como
--                      funciona" aponta para /v2/como-funciona-a-sala-virtual e
--                      nao para /v2/como-funciona: esta ultima e apenas um 301
--                      para aquela, entao uma fala presa a ela nunca renderiza.
--                      (Em 23/08/2026 as duas dao 404, porque a pagina de
--                      destino foi apagada no admin em 20/07/2026. A fala fica
--                      pronta para quando a pagina voltar.)
--   * cursos         — nunca teve fala nenhuma, em V1 ou V2.
--   * checkout       — idem. O toggle tutor_checkout estava ligado desde
--                      22/08/2026 sem efeito nenhum, por falta de fala.
--
-- Para visitante deslogado o componente e o que sempre foi: avatar e recado,
-- sem campo de pergunta (ver tests/Unit/norminha_visitante.php). O chat so
-- existe para quem tem sessao.
--
-- Idempotente: cada INSERT so ocorre se ainda nao houver fala equivalente, e o
-- UPDATE so age enquanto a fala 1 estiver presa a "/".

-- home: passa a valer para "/" e "/v2/"
UPDATE tutor_falas
   SET rota = NULL,
       atualizado_em = NOW()
 WHERE contexto = 'home'
   AND rota = '/';

-- institucional: fala geral para as paginas sem fala propria
INSERT INTO tutor_falas (titulo, contexto, rota, texto, estado_avatar, ativo, criado_em)
SELECT 'Sobre o Desbloqueia Cursos', 'institucional', NULL,
       'Quer entender melhor como tudo funciona por aqui? Me pergunte sobre os cursos, os certificados ou o que precisar. Estou por perto.',
       'speaking', 1, NOW()
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM tutor_falas
        WHERE contexto = 'institucional' AND (rota IS NULL OR rota = '')
 );

-- institucional: espelha na V2 as duas falas especificas que ja existiam na V1
INSERT INTO tutor_falas (titulo, contexto, rota, texto, estado_avatar, ativo, criado_em)
SELECT 'Como funciona', 'institucional', '/v2/como-funciona-a-sala-virtual',
       'Aqui no Desbloqueia Cursos, você escolhe um curso, faz sua inscrição e acessa os conteúdos de forma simples. Avance no seu ritmo e conclua as etapas para finalizar sua formação.',
       'speaking', 1, NOW()
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM tutor_falas WHERE contexto = 'institucional' AND rota = '/v2/como-funciona-a-sala-virtual'
 );

INSERT INTO tutor_falas (titulo, contexto, rota, texto, estado_avatar, ativo, criado_em)
SELECT 'Cadastro', 'institucional', '/v2/cadastro',
       'Vamos nos cadastrar?<br>\nPreencha com atenção todas as informações, e use uma senha fácil de lembrar.',
       'attention', 1, NOW()
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM tutor_falas WHERE contexto = 'institucional' AND rota = '/v2/cadastro'
 );

-- cursos: o catalogo nunca teve fala
INSERT INTO tutor_falas (titulo, contexto, rota, texto, estado_avatar, ativo, criado_em)
SELECT 'Escolhendo um curso', 'cursos', NULL,
       'Dê uma olhada nos cursos e veja qual combina com o que você procura. Repare na carga horária e no conteúdo de cada um antes de decidir.',
       'speaking', 1, NOW()
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM tutor_falas WHERE contexto = 'cursos' AND (rota IS NULL OR rota = '')
 );

-- checkout: o toggle estava ligado desde 22/08/2026 sem fala que o sustentasse
INSERT INTO tutor_falas (titulo, contexto, rota, texto, estado_avatar, ativo, criado_em)
SELECT 'Finalizando sua inscrição', 'checkout', NULL,
       'Confira os dados da sua inscrição antes de concluir. Se ficou alguma dúvida sobre o curso ou sobre o pagamento, é só me perguntar.',
       'attention', 1, NOW()
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM tutor_falas WHERE contexto = 'checkout' AND (rota IS NULL OR rota = '')
 );
