# Onda 0 — relatório de entrega

**Branch:** `feat/norminha-v1` · **Base:** `frontend-v4` · **Concluída em** 23/08/2026

A Norminha determinística: um chat acadêmico que responde com dado real do LMS, **sem nenhuma
chamada a provedor de IA** e sem custo por mensagem. Oito etapas do plano mestre, mais o roteiro de
deploy antecipado.

## Números

| | |
|---|---|
| Commits | 12 |
| Arquivos | 49 alterados · +9.073 −835 linhas |
| Testes | **178** casos (158 unitários + 20 HTTP) |
| Smoke | 34 rotas, com guarda de layout |
| Linhas de teste × código | 3.416 × 3.647 — quase 1:1 |
| Migrations | 073, 074, 075 — todas aditivas e idempotentes |
| Chamadas a provedor de IA | **zero**, provado por contador em teste |

## O que o aluno ganha

Um chat na área do aluno V2 que responde na hora, com dado do próprio LMS:

- **Continuar de onde parei** — leva à aula certa, usando `ultimo_acesso_em` real
- **Ver meu progresso** — o mesmo número que a tela do curso mostra
- **Meu certificado** — situação e motivos vindos do serviço oficial
- **Próximo passo** — o próximo item não concluído, na ordem do curso
- **Tirar dúvida desta aula** — só aparece quando há aula

Pergunta livre recebe uma resposta honesta de indisponibilidade — e fica **registrada**. Esse
registro é o dado que decide se a camada de IA se paga.

## O que o operador ganha

Menu do admin, sob `conteudo.ver`:

- **Norminha · Configurações** — ligar/desligar, onde aparece, limites de mensagens, avatar, textos
- **Norminha · Telemetria** — uso, distribuição de respostas, feedback, bloqueios e **a lista de
  perguntas não resolvidas**
- **Norminha** — falas por contexto, curso, módulo e aula

## Onze correções ao plano mestre

A auditoria da Etapa 0 encontrou pontos em que seguir o plano ao pé da letra produziria defeito.
Todas em [`CONTEXTO-EXECUCAO.md`](CONTEXTO-EXECUCAO.md). As que mais importam:

**C1 — a fonte do progresso estava errada.** O plano mandava usar
`ProgressoService::resumoAluno()`. Esse método lê o modelo legado de `aulas`, vazio desde a migração
para o Conteúdo Unificado: em 12 inscrições reais, retornou **0% em todas**, enquanto a tela mostrava
100%, 45,83%, 25,74%. Implementado como estava, o botão diria "você concluiu 0%" para quem terminou o
curso. A fonte correta é `inscricoes.percentual_progresso`.

**C3 — nenhum middleware devolvia JSON.** Todos redirecionam 302. Um `fetch()` que segue o redirect
recebe 200 com o HTML do login e conclui que deu certo. Exigiu um `auth.api` novo e um retorno
antecipado no CSRF para caminhos `/api/`.

**C12 — a fonte do certificado também.** `apto_certificado` vem de `calcularParaInscricao()`, não da
versão de conteúdo. Usar a errada faria a Norminha discordar da tela em casos reais.

**C5, C6, C8 — a Norminha não existia na V2.** O componente nunca foi montado no layout V2,
`resolverContexto()` não conhecia nenhuma rota `/v2/`, e o cache-busting apontava para o diretório
errado — servindo CSS e JS antigos de cache depois de todo deploy.

## Defeitos encontrados durante a construção

Cada um foi corrigido no commit da própria etapa:

- **A telemetria repetia o bug de fuso do projeto.** PHP roda em UTC, MySQL em UTC−3. Métodos que
  recebiam data como string consultariam o dia errado das 21h à meia-noite — silenciosamente. Meu
  teste passava porque usava janela larga demais para notar.
- **A posse da conversa era verificada tarde demais.** Um aluno sem matrícula saía por retorno
  antecipado antes da checagem. Não vazava nada, mas fazia a segurança depender do conteúdo de outra
  resposta.
- **A ordem dos middlewares invertia o erro.** Anônimo recebia `403 csrf_invalido` em vez de
  `401 nao_autenticado` — sem sessão não existe token válido.
- **URL adulterada gerava contexto mentiroso.** A página ignorava corretamente um item de outro
  curso, mas o DOM anunciava esse item e oferecia "tirar dúvida desta aula" sobre nada.
- **`getCertificateStatus()` rodava a mesma query duas vezes.**
- **Os limites do rate limit não eram editáveis** — o que tornava inexecutável uma instrução do
  próprio roteiro de deploy.

## Como sabemos que funciona

Os testes não afirmam apenas o caminho feliz. As garantias centrais foram verificadas **por
mutação** — quebrando o código de propósito para confirmar que o teste cai:

| Garantia quebrada de propósito | O que o teste acusou |
|---|---|
| Remover a checagem de dono na busca por UUID | `NEGA o mesmo uuid para outro aluno` |
| Remover o escopo de curso na consulta de item | vazou "Aula de Boas-vindas" de curso alheio |
| Introduzir `recalcularInscricao()` | varredura de código **e** `Com_insert 0 → 1` |
| Forçar o rótulo "Você parou em" | mostrou o texto exato que iria ao aluno |
| Fazer um fast-path chamar o gerador | contador de IA: `Esperava 0, recebeu 1` |
| Trocar a janela SQL por `date()` do PHP | contagem vazia: `0 não é maior que 0` |

E a chave de desligamento é testada, não prometida: com `tutor_ativo=0`, nenhuma rota monta o
componente, nada lança exceção, o LMS segue respondendo e as tabelas permanecem intactas.

## Estado

- **Produção intacta.** Branch `frontend-v4`, working tree limpo, zero tabelas `norminha_*` no banco.
- **Ambiente de desenvolvimento** isolado em `norminha-dev`, worktree próprio, banco próprio, e-mail
  e gateway desligados.
- **Backup verificado** anterior ao projeto, mais a tag `pre-norminha-20260822` como ponto de retorno.

## O que vem agora não é código

1. As ações de segurança pendentes do relatório da Etapa 0 (detalhadas em
   [`00-auditoria-preflight.md`](00-auditoria-preflight.md)).
2. Aplicar o [roteiro de deploy](17-deploy.md) — subir com a Norminha desligada, verificar,
   depois ligar.
3. Deixar rodando 10 a 14 dias.
4. Ler a lista de perguntas não resolvidas e classificá-las: **conteúdo** ou **navegação**?

Se forem majoritariamente de navegação, a decisão certa é escrever mais atalhos determinísticos —
mais barato, mais rápido e sem risco de resposta inventada. A Onda 1 só se justifica se forem de
conteúdo.

Essa leitura é a entrega real da Onda 0. Todo o resto existe para torná-la possível.
