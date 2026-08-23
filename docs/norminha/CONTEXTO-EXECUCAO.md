# CONTEXTO DE EXECUÇÃO — NORMINHA IA V1

> Gerado pela Etapa 0 (auditoria pré-flight) em 22/08/2026, contra o commit `f7ff3ce`.
> Todos os prompts das etapas seguintes devem ler este arquivo integralmente antes de escrever código.
> O relatório completo da auditoria está em `docs/norminha/00-auditoria-preflight.md`.

Repositório `fbfert/DesbloqueiaCursos`, branch `frontend-v4`.

## ANTES DE ALTERAR QUALQUER ARQUIVO

1. Leia integralmente `AGENTS.md` e `CLAUDE.md`.
2. Leia `docs/regras-portugues-interface.md` e `docs/padrao-editorial-ptbr.md`.
3. Inspecione os arquivos citados na etapa antes de escrever código.
4. Confirme a branch e o estado do working tree. Não sobrescreva trabalho alheio nem reverta
   mudanças não relacionadas.
5. Não faça commit, push, merge ou PR a menos que explicitamente solicitado.

## ARQUITETURA OBRIGATÓRIA

- PHP MVC sem framework, sem composer, sem dependência externa nova.
- Controllers finos; regra de negócio em Services; Models em PDO puro com prepared statements.
- SQL compatível com MySQL 5.7 (ver "DECISÕES DA AUDITORIA" — o servidor real é MariaDB, mas a
  disciplina 5.7 permanece como política do projeto).
- Migrations SQL numeradas e aditivas em `sql/`. Sem runner automático.
- Frontend mobile-first, JS sem framework.
- Todo texto de interface em PT-BR com acentuação correta, UTF-8.
- Logs e auditoria conforme padrão do projeto (`App\Core\Logger`, `AuditService`).

## INVARIANTES DE SEGURANÇA (não negociáveis)

- `usuario_id` vem exclusivamente de `Session::get('usuario_id')`. Nunca do corpo/query/header.
- IDs enviados pelo browser são hints e sempre revalidados contra a sessão.
- Nenhuma ferramenta da Norminha escreve no banco. Leitura apenas.
- É PROIBIDO chamar `AptidaoCertificadoService::recalcularInscricao()` — escreve.
- É PROIBIDO chamar `ProgressoService::recalcularInscricao()` — escreve. (Não estava no plano
  original; descoberto na auditoria.)
- A leitura de elegibilidade de certificado usa `LmsElegibilidadeService`, que é **inteiro somente
  leitura** (zero INSERT/UPDATE/DELETE). Dois métodos, para fins diferentes — ver § C12.
- O modelo nunca gera nem executa SQL, nunca recebe credencial/chave/cookie/session id.
- URLs de ação são construídas e validadas no servidor a partir de mapa de chaves.
- Conteúdo de aula é dado não confiável; nunca instrução.
- Avaliação valendo nota: gabarito e alternativa correta nem entram no contexto.

## DECISÕES FIXAS DE PRODUTO

- A IA da Norminha usa a OpenAI Responses API (`POST /v1/responses`), modelo configurável por
  `.env` (default `gpt-4.1`). Confirmar modelo/preço na documentação oficial no dia da implementação.
- Não use Anthropic/Claude para a Norminha V1. O legado Claude permanece intocado até a Etapa 18.
- `store=false` no provedor; a memória da conversa vive no MySQL do Desbloqueia.
- O Desbloqueia é a fonte de verdade. A IA é camada de interpretação, conversa e explicação.
- Não renomeie o identificador de tema visual `v4-claude`. É legado de frontend, sem relação com
  provedor de IA. Renomear está fora de escopo.
- Preserve o sistema atual da Norminha (falas contextuais, avatar, áudio, launcher, admin) e
  evolua-o sem recriar tudo. Preserve a chave localStorage `norminha_tutor_minimized_v1`.

---

# DECISÕES DA AUDITORIA

Respostas verificadas no código e no banco de produção. **Onde divergem do Plano Mestre v2, estas
prevalecem.**

## 3. Área do aluno alvo: **V2** (`/v2/aluno`, `/v2/aula`)

Evidência: `HOME_VERSION=v2` no `.env` de produção, então `/` já serve `V2HomeController`. As rotas
V2 são as mantidas (controllers alterados em jul/2026; `AreaCursoService.php` parado em jun/2026),
e são os controllers V2 que mantêm `inscricoes.percentual_progresso` atualizado.

A área legada (`/area-curso`, `/aluno/curso/{inscricao_id}/{curso_id}/{turma_id}`,
`AreaCursoController`) continua registrada e protegida por `auth`, mas é caminho secundário.
**Integrar apenas na V2.**

⚠️ **Consequência não prevista pelo plano:** `resources/views/v2/layout.php` **não monta** o
componente da Norminha (zero referências). O widget só existe em `resources/views/layout.php`
(legado). Portanto a Etapa 6/7 **cria** o ponto de montagem na V2 — não "preserva o ponto único
existente". Reserve esforço para isso.

## 4. `conteudo_progresso_aluno`: **CONFIRMADO**

Colunas verificadas em produção: `primeiro_acesso_em`, `ultimo_acesso_em`, `concluido_em`,
`status ENUM('nao_iniciado','acessado','em_andamento','concluido','pendente_correcao','reprovado')`,
`percentual DECIMAL(5,2)`, `obrigatorio`, **e `deleted_at`** (o plano não citou — toda query
precisa filtrar `deleted_at IS NULL`).

Índice `uk_conteudo_progresso_aluno_contexto` UNIQUE em `(aluno_id, inscricao_id, item_id)`: existe.

Queries validadas contra dados reais (0,7–0,8 ms cada):

```sql
-- REGRA 1: onde o aluno parou  -> origem = "ultimo_acesso"
SELECT i.id AS item_id, i.titulo, i.tipo, m.id AS modulo_id, m.titulo AS modulo, p.status, p.ultimo_acesso_em
FROM conteudo_progresso_aluno p
INNER JOIN conteudo_itens   i ON i.id = p.item_id   AND i.deleted_at IS NULL AND i.status = 'publicado'
INNER JOIN conteudo_modulos m ON m.id = i.modulo_id AND m.deleted_at IS NULL AND m.status = 'publicado'
WHERE p.aluno_id = :aluno_id AND p.inscricao_id = :inscricao_id AND p.deleted_at IS NULL
  AND p.status <> 'concluido' AND p.ultimo_acesso_em IS NOT NULL
ORDER BY p.ultimo_acesso_em DESC
LIMIT 1;

-- REGRA 2: próximo item obrigatório não concluído  -> origem = "proximo_item"
SELECT i.id AS item_id, i.titulo, i.tipo, i.obrigatorio, m.titulo AS modulo
FROM conteudo_itens i
INNER JOIN conteudo_modulos m ON m.id = i.modulo_id AND m.deleted_at IS NULL AND m.status = 'publicado'
LEFT  JOIN conteudo_progresso_aluno p
       ON p.item_id = i.id AND p.inscricao_id = :inscricao_id AND p.aluno_id = :aluno_id AND p.deleted_at IS NULL
WHERE i.curso_evento_id = :curso_evento_id AND i.deleted_at IS NULL AND i.status = 'publicado'
  AND i.tipo <> 'etiqueta'
  AND (p.id IS NULL OR p.status <> 'concluido')
ORDER BY m.ordem ASC, i.ordem ASC, i.id ASC
LIMIT 1;

-- REGRA 3: nada retornado por 1 nem por 2 -> origem = "curso_concluido"
```

`i.tipo <> 'etiqueta'` é obrigatório: etiqueta é separador visual, não passo de estudo.

## 5. Rate limit: **CONFIRMADO — não existe**

Zero ocorrências de `rate limit`/`throttle`/`RateLimit` em `app/`, `config/`, `routes/`.
Extensões PHP disponíveis: `curl`, `json`, `mbstring`, `pdo_mysql`. **Sem Redis, sem APCu.**
A implementação em MySQL (tabela `norminha_uso`) é a única opção viável. Único precedente de
limitação no projeto: `AuthService` (`MAX_LOGIN_ATTEMPTS`/`LOCK_MINUTES`) para login.

## 6. `LmsElegibilidadeService::calcularElegibilidadeConteudoUnificado()`: **CONFIRMADO**

Assinatura exata: `($cursoEventoId, $turmaId, $inscricaoId, $alunoId)`. **1 query, ~1,7 ms,
payload ~0,4 KB.** Somente leitura (SELECT + agregação em PHP).

Retorno (chaves reais):
`total_itens_publicados`, `total_itens_obrigatorios`, `obrigatorios_concluidos`,
`obrigatorios_pendentes`, `percentual_conteudo_obrigatorio`, `avaliacoes_textuais_obrigatorias`,
`avaliacoes_textuais_aprovadas`, `avaliacoes_textuais_reprovadas`, `avaliacoes_textuais_pendentes`,
**`bloqueios`** (array), **`apto_conteudo`** (bool), **`detalhes_itens_pendentes`** (array).

Os "motivos" pedidos pelo plano são `bloqueios` + `detalhes_itens_pendentes`.

⚠️ `$turmaId` é aceito mas **ignorado** na query (`$clauseTurma` é sempre string vazia,
`LmsElegibilidadeService.php:99`). Não é bug introduzido pela Norminha, mas não presuma filtro por turma.

## 7. Custo do contexto: **NÃO embrulhar `AreaCursoService`**

Medido em produção, inscrição real com 136 itens de progresso:

| Chamada | Queries | Tempo | Payload |
|---|---|---|---|
| `AreaCursoService::carregarAluno()` | **32** | 25,1 ms | 12,8 KB |
| `ProgressoService::resumoAluno()` | 13 | 4,3 ms | 0,2 KB |
| `LmsElegibilidadeService::calcular...()` | **1** | 1,7 ms | 0,4 KB |

`carregarAluno()` monta payload de tela inteira (17 chaves de topo, incluindo `modulos`,
`materiais`, `atividades`). **O `NorminhaContextService` deve consultar os Models diretamente**
(`Inscricao`, `ConteudoModulo`, `ConteudoItem`, `ConteudoProgressoAluno`) com queries enxutas.

## 9. Migrations

Maior número existente: **`072_perfil_revisor_comentarios.sql`**. Próximo livre: **`073`**.
(O Plano Mestre v2 dizia 070 — desatualizado. Reconfira no dia da execução.)

---

# CORREÇÕES AO PLANO MESTRE v2

Pontos em que o plano está factualmente errado. Seguir o plano literalmente aqui produz defeito.

### C1 — 🔴 `ProgressoService::resumoAluno()` NÃO serve para o progresso do aluno

O Prompt 3 manda usar `ProgressoService::resumoAluno()` como fonte de progresso e "não
reimplementar o cálculo". **Esse service lê o modelo LEGADO de `aulas`, que está vazio.** Em
amostra de 12 inscrições reais, retornou `0.00% (0/0 aulas)` em **todas**, enquanto a tela do
aluno mostrava 100%, 45,83%, 25,74%, etc.

Implementar como está faria a Norminha dizer "você concluiu 0%" para um aluno que terminou o curso.

**Fonte correta**, em ordem de preferência:

1. `inscricoes.percentual_progresso` (DECIMAL(5,2)) e `inscricoes.apto_certificado` (TINYINT(1)) —
   colunas materializadas; é **exatamente** o que a tela do aluno exibe (`AreaCursoService.php:125-126`).
   1 query, sem recálculo. Mantidas atualizadas pelos controllers V2.
2. `LmsElegibilidadeService::calcularElegibilidadeConteudoUnificado()` — cálculo ao vivo sobre o
   conteúdo unificado, 1 query. Use para os **motivos** e para detectar defasagem da coluna materializada.

As duas podem divergir levemente (observado 31,25% ao vivo × 31,58% materializado), porque a coluna
só é reescrita quando `recalcularInscricao()` roda. **Para o texto exibido ao aluno, use a coluna
materializada** — o critério de sucesso da Onda 0 é "idêntico à tela do LMS".

### C2 — O alias do middleware V2 é `auth.v2`, não `v2auth`

`app/Core/Router.php:238`. O plano usa `v2auth` em várias etapas. Alias válidos:
`auth`, `auth.v2`, `csrf`, `permission:<chave>`.

### C3 — 🔴 Nenhum middleware devolve JSON; todos redirecionam

`AuthenticateMiddleware` → `302 /login`. `V2AuthenticateMiddleware` → `302 /v2/login`.
`CsrfMiddleware` → `302` para o referer. Nenhum retorna 401/403/422 em JSON.

O contrato da API da Norminha (seção 5.2 do plano) exige `401`/`403`/`422`. Um `fetch()` que recebe
302 seguido de 200 do HTML de login **parece sucesso** para o JS.

A Etapa 5 precisa de um guard próprio para `/api/norminha/*` que devolva JSON com o status correto —
não dá para reutilizar os middlewares existentes sem adaptação. Decida entre: (a) middleware novo
`auth.api`; (b) validar sessão e CSRF dentro do `NorminhaController` usando `Session`/`Csrf`
diretamente, sem middleware de auth na rota. Registre a escolha aqui antes de implementar.

### C4 — O banco é MariaDB 10.5.29, não MySQL 5.7

`SELECT VERSION()` → `10.5.29-MariaDB-log`. MariaDB 10.5 suporta CTE, window functions e CHECK.
**Mantenha a disciplina 5.7** (é política do `AGENTS.md` e o ambiente local pode diferir), mas saiba
que a restrição é de política, não do servidor.

Tipos de PK confirmados, todos `BIGINT(20) UNSIGNED`: `usuarios.id`, `inscricoes.id`,
`cursos_eventos.id`, `turmas.id`. As FKs da migration 073 podem seguir o plano sem ajuste.

### C5 — A Norminha está DESLIGADA em produção — montagem V2 RESOLVIDA na Etapa 6

`tutor_configuracoes.tutor_ativo = 0`. O widget não renderiza em nenhuma página hoje
(verificado por HTTP em 9 rotas: zero ocorrências de `id="norminha-tutor"`).

Existem 6 falas ativas (`home`, `area_aluno`, `aula`, `curso`, `institucional` ×2). Não há fala de
contexto `publico`, que é o contexto em que **todas** as rotas `/v2/*` caem hoje.

Isso muda dois cálculos do plano:
- **O risco da Etapa 6 é menor do que o plano afirma.** O componente não está montado em nenhuma
  página viva. O que continua rodando no `<head>` de toda página do layout legado é o script inline
  de `localStorage` (`layout.php:158-177`) — esse sim precisa da guarda de smoke.
- **Não existe baseline de uso.** O Checkpoint 0 mede um recurso que será ligado do zero.

### C6 — `resolverContexto()` não conhecia `/v2/` — RESOLVIDO na Etapa 6

`app/Services/TutorVirtualService.php:147-184` mapeia `/`, `/cursos`, `/area-curso`,
`/aluno/curso/...`, etc. Nenhuma rota `/v2/*` é reconhecida: todas caem em `contexto = 'publico'`.
A Etapa 7 precisa estender esse mapa para `/v2/aluno` → `area_aluno` e `/v2/aula` → `aula`.

### C7 — `conteudo_itens.tipo` inclui `quiz`

ENUM real: `'etiqueta','texto','arquivo','link','avaliacao_textual','video','quiz','html','video_incorporado'`.
O plano lista tudo menos `quiz`. **`quiz` é avaliação valendo nota** — o `assessment_context` e o
filtro de gabarito do `NorminhaKnowledgeService` precisam cobrir `quiz` e `avaliacao_textual`.

### C8 — Cache-busting quebrado — RESOLVIDO na Etapa 6

`layout.php:29-32` procura os arquivos em `BASE_PATH . '/public_html/assets/...'`, mas eles estão em
`assets/`. `is_file()` falha, o parâmetro `&f=<filemtime>` nunca é adicionado, e a única chave de
cache é a constante literal `?v=20260610-4` (linhas 194 e 232).

**A Etapa 6 precisa incrementar essa constante ou corrigir o caminho**, senão os alunos continuarão
com o CSS/JS antigos em cache depois do deploy.

### C9 — `tests/` tem 9 arquivos, não 5

`tests/Unit/`: `_bootstrap.php`, `quiz_sorteio.php`, `quiz_rascunho.php`, `quiz_simulado_pnd.php`,
`quiz_system.php`, `checkout_rapido_fase0.php`, `checkout_rapido_fase1.php`,
`revisor_academic_scope.php`, `revisor_permissoes.php`.

`tests/Smoke/` **confirmado ausente** — a Etapa 0.5 continua obrigatória.
`CLAUDE.md` cita `php tests/Unit/professor_academic_scope.php`, arquivo que também não existe.

### C10 — Estado do repositório — RESOLVIDO em 22/08/2026

Situação encontrada: working tree de produção com 64 arquivos modificados não commitados e 33 não
rastreados, incluindo **quatro migrations aplicadas no banco e ausentes do git** (067, 068, 069,
071). Um clone não reproduzia o schema.

Resolvido antes do Prompt 1:

- tag `pre-norminha-20260822` marca o estado anterior (idêntico a `origin/frontend-v4`);
- backup completo em `/home/desbloqueiacursos/backups/pre-norminha-20260822-202256/`
  (banco 11 MB / 121 tabelas + arquivos 184 MB / 2.158 arquivos, ambos verificados);
- cinco commits registram o estado real da produção, separando o que já estava no ar do que foi
  feito nas Etapas 0 e 0.5;
- working tree limpo;
- ambiente de desenvolvimento em `/home/desbloqueiacursos/norminha-dev` (worktree na branch
  `feat/norminha-v1`, banco `desbloqueiacursos_dev`) — ver `docs/norminha/AMBIENTE-DEV.md`.

**A partir daqui, nunca trabalhe dentro de `public_html`.** Aquele diretório é servido ao vivo.

### C14 — O que a Etapa 6 mudou nos pontos acima

- **C5:** o componente passou a ser montado em `resources/views/v2/layout.php`, que é agora o
  **ponto único** da V2. Não replicar em view de página: a guarda do smoke reprova duplicação.
- **C6:** `resolverContexto()` reconhece `/v2/aluno`, `/v2/aula`, `/v2/quiz`, `/v2/atividade`,
  `/v2/catalogo`, `/v2/curso`, `/v2/checkout` e o restante de `/v2/` como institucional.
- **C8:** as versões de asset passaram a ser resolvidas por uma função que tenta `assets/` **e**
  `public_html/assets/`. Só `v4-claude.*` e `dc-main.*` moram na segunda; todo o resto falhava
  calado. Um bloco duplicado que recalculava `v4-claude` com a lógica antiga foi removido.
- O script inline de preferência de minimizado virou o parcial
  `resources/views/components/tutor_norminha_head.php`, compartilhado pelos dois layouts.
- CSS e JS da Norminha agora só são servidos quando o componente existe na página.

**Saudação padrão nas áreas de estudo.** Todas as falas cadastradas têm `rota` preenchida, e o
fallback `TutorFala::buscarPorContexto()` exige `rota IS NULL` — ou seja, nunca dispara. Como as
falas apontam para rotas do V1 (`/aluno/cursos`, `/curso/*`), na V2 nenhuma casava, e o componente
não aparecia. `componenteParaLayout()` passou a usar uma saudação padrão quando o contexto é
`area_aluno`, `aula`, `avaliacao` ou `curso`. Fora dessas áreas o comportamento antigo vale: sem
fala, sem componente.

### C12 — Para o certificado, use `calcularParaInscricao()`, não a versão de conteúdo

O plano manda ler elegibilidade por `calcularElegibilidadeConteudoUnificado()`. Esse método é o
certo para **conteúdo** (1 query, 1,0 ms), mas **não devolve `situacao`** — devolve `apto_conteudo`,
que é outra coisa.

Quem determina `inscricoes.apto_certificado` é `AptidaoCertificadoService::recalcularInscricao()`, na
linha 46: `$apto = situacao IN ('apto','certificado_emitido')`, onde `situacao` vem de
**`LmsElegibilidadeService::calcularParaInscricao($inscricao)`**.

Ou seja: usar a versão de conteúdo faria a Norminha discordar da tela do aluno em casos reais —
observado `apto_conteudo = true` com `apto_certificado = 0` na inscrição 97. Como o critério de
sucesso da Onda 0 é "situação de certificado idêntica à da tela", a fonte correta é
`calcularParaInscricao()`.

`recalcularInscricao()` continua **proibido**: ele escreve. `calcularParaInscricao()` não — o
service inteiro tem zero escritas, verificado por busca e por contador do MySQL.

| Método | Queries | Devolve | Uso na Norminha |
|---|---|---|---|
| `calcularElegibilidadeConteudoUnificado()` | 1 | contagens de conteúdo, `apto_conteudo`, `bloqueios` | progresso |
| `calcularParaInscricao()` | 8 | **`situacao`** + `motivos` em PT-BR | certificado |

⚠️ **Teto de tamanho é obrigatório.** Numa inscrição real do banco, `calcularParaInscricao()`
devolveu **99 motivos e 64 KB** (`motivos_texto` com 11.637 caracteres). O `NorminhaToolsService`
corta em 5 motivos e informa `motivos_total` e `motivos_omitidos`.

### C15 — 🔴 O modelo padrão do plano não existe mais

O plano mestre fixa `gpt-4.1` como default de `OPENAI_MODEL`. Consultada a documentação oficial em
**23/08/2026**, `gpt-4.1` **não consta mais na lista de modelos atuais**. Ele não aparece como
formalmente desativado, mas seus snapshots datados (ex.: `gpt-4.1-nano-2025-04-14`) têm desligamento
marcado para **23/10/2026**, com migração recomendada para a família `gpt-5.6`.

Modelos correntes na mesma consulta, com preço por milhão de tokens (entrada/saída):

| Modelo | Entrada | Saída | Perfil |
|---|---|---|---|
| `gpt-5.6-luna` | US$ 0,20 | US$ 1,20 | econômico |
| `gpt-5.6-terra` | US$ 2,00 | US$ 12,00 | equilibrado |
| `gpt-5.6-sol` | US$ 4,00 | US$ 20,00 | trabalho profissional complexo |

**Nenhuma troca foi feita por conta própria**, como o Prompt 9 exige. `OPENAI_MODEL` vai **vazio**
no `.env.example`, e `config/ai.php` não tem constante de fallback: sem modelo definido, o service
recusa o pedido com `payload_invalido` antes de qualquer rede. A escolha é decisão do responsável.

Para tutoria fundamentada em conteúdo oficial já recuperado, a diferença de capacidade entre as
faixas tende a importar menos do que a diferença de preço — dez vezes entre `luna` e `terra`. Mas
isso é hipótese, não medição: vale testar as duas com perguntas reais do painel de telemetria antes
de fixar.

> Preço, disponibilidade e formato mudam. Reconfira na documentação oficial no dia da habilitação.

### C16 — Formatos da Responses API confirmados em 23/08/2026

Verificados na documentação oficial antes de escrever o `OpenAIService`:

- **Definição de ferramenta:** `{ "type": "function", "name", "description", "parameters", "strict" }`
  — sem o aninhamento `function: {...}` do Chat Completions.
- **Chamada na resposta:** item em `output` com
  `{ "type": "function_call", "id", "call_id", "name", "arguments" }`, onde `arguments` é **string
  JSON**, não objeto.
- **Devolução do resultado:** `{ "type": "function_call_output", "call_id", "output" }`.
- **`parallel_tool_calls`** é parâmetro válido de topo. A documentação observa que o recurso vale
  para modelos a partir do GPT-5 — a V1 usa `false`, então o efeito prático é o mesmo.
- **Tokens em cache** ficam aninhados em `usage.input_tokens_details.cached_tokens`. Ignorá-los faria
  o painel de custo superestimar.

O parser percorre o array `output` item a item e **ignora tipos desconhecidos** (como `reasoning`),
em vez de depender de campos de conveniência que podem sumir numa versão nova.

### C13 — Não existe pré-requisito nem liberação progressiva

Nenhuma coluna, tabela ou service implementa bloqueio entre itens. `conteudo_itens.abre_em` é um
ENUM de apresentação (`mesma_pagina`, `nova_aba`, `modal`…), não uma data de liberação.

Portanto "próximo passo" é literalmente o próximo item não concluído na ordem
`conteudo_modulos.ordem, conteudo_itens.ordem`. Nenhuma regra de bloqueio foi inventada, conforme o
Prompt 3 exige.

### C11 — O usuário do banco é superusuário

`desbloqueia_user` tem `GRANT ALL PRIVILEGES ON *.*`. Qualquer injeção de SQL em qualquer ponto do
sistema alcança o servidor MySQL inteiro, não só o banco da aplicação. Não foi alterado (mexer em
grants de produção sem janela é arriscado), mas deve entrar no hardening da Etapa 15.

### C17 — 🔴 Um edit apagou quatro folhas de estilo, e o admin foi ao ar sem aparência

Na Etapa 6 eu quis remover do layout legado apenas o CSS e o script inline da
Norminha, substituídos pelo parcial compartilhado. O edit levou junto quatro
`<link>` vizinhos:

- `/assets/css/app.css` — **fora de qualquer condicional**, é a folha base de
  todas as páginas renderizadas por `resources/views/layout.php`;
- `/assets/css/frontend.css`;
- `/assets/css/frontend-v2.css`;
- `/assets/css/orientacao-usuario.css`.

E `View::render()` manda **tudo** por esse layout — inclusive o painel
administrativo, que carrega `app.css` + `admin.css`. Sem a primeira, o admin
inteiro ficou sem estilo. Foi assim que o responsável descobriu, em 23/08, um
dia depois do deploy.

**O que falhou não foi só o edit.** Nenhum dos 331 testes olhava para folha de
estilo. Eles verificavam status HTTP, erro de PHP, duplicação do componente da
Norminha e comportamento de dados. Todos passavam com o site sem aparência,
porque nenhum perguntava se a página tinha aparência.

**Correção:** as quatro folhas foram restauradas na posição original — conferido
por diff contra `pre-onda0-20260823`, e a única diferença que resta é
`tutor-norminha.css`, que saiu de propósito.

**Cobertura:** `smoke_guarda_layout()` passou a exigir que todo documento HTML
completo traga a folha do seu layout — `app.css` no legado, `v2-main.css` na V2.
Verificado por mutação: reproduzindo o bug, 12 das 34 verificações reprovam.

**A lição, que vale além deste caso:** um teste que só olha status e exceção
declara sucesso para uma página que chegou ilegível. Depois de mexer em layout,
alguma verificação precisa afirmar que a página continua **vestida**, não apenas
que ela respondeu.

### C18 — 🔴 O painel do chat foi ao ar sem fundo

Relatado pelo responsável em 23/08/2026: no celular, o chat aparecia
transparente e o texto ficava ilegível sobre o conteúdo da página.

**A causa.** Quando a Norminha deixou de ser um balão de fala e virou chat, a
marcação passou de `.norminha-tutor__card` para `.norminha-tutor__panel`. Todo o
tratamento visual — fundo, borda, raio, sombra e a fita colorida do topo —
continuou preso à classe antiga, que sumiu do HTML e permaneceu no CSS.

O painel só tinha regras de layout: `display: flex`, `max-height`,
`overflow: hidden`. Nenhuma superfície. No desktop, sobre página clara, a falta
passa despercebida — foi assim que ela sobreviveu à Etapa 5, à Etapa 15 e a dois
deploys.

**A correção.** O painel recebeu a superfície, com fundo **opaco**. O balão
antigo usava 96–99% de opacidade, aceitável para um enfeite e não para a
superfície onde se lê uma conversa: os 4% restantes são exatamente a sujeira que
atrapalha a leitura.

**O que apareceu junto.** Escrevendo o teste, mais três classes órfãs do mesmo
período (`__shell`, `__avatar-wrap`, `__avatar`), a animação `.is-speaking` —
morta duas vezes, porque nem a classe era aplicada (o JS usa
`data-estado-avatar`) nem os elementos existiam —, o keyframe que só ela
consumia, e uma classe no caminho oposto: `__pensando-texto` estava no HTML sem
regra nenhuma, herdando o tamanho de fonte do tema onde a Norminha estivesse
montada.

**Cobertura.** Quatro casos novos em `norminha_arquitetura.php`:

1. toda classe da Norminha usada no HTML tem regra no CSS;
2. nenhuma regra do CSS aponta para classe que HTML e JS não usam mais;
3. o painel declara `background`, `border` e `box-shadow`;
4. o fundo do painel é opaco.

Verificado por mutação: remover o fundo e trocá-lo por `rgba(...,0.85)` são os
dois pegos.

**A lição.** Pela segunda vez em dois dias um defeito de aparência atravessou a
suíte inteira — antes foi o `app.css` apagado do layout legado, agora o painel
sem superfície. Testes que olham comportamento, dados e segurança declaram
sucesso para uma tela que ninguém consegue ler. Depois de mexer em CSS ou em
layout, é preciso alguma verificação que afirme que a coisa **tem aparência**,
não apenas que respondeu.

