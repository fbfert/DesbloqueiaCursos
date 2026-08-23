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

### C5 — A Norminha está DESLIGADA em produção

`tutor_configuracoes.tutor_ativo = 0`. O widget não renderiza em nenhuma página hoje
(verificado por HTTP em 9 rotas: zero ocorrências de `id="norminha-tutor"`).

Existem 6 falas ativas (`home`, `area_aluno`, `aula`, `curso`, `institucional` ×2). Não há fala de
contexto `publico`, que é o contexto em que **todas** as rotas `/v2/*` caem hoje.

Isso muda dois cálculos do plano:
- **O risco da Etapa 6 é menor do que o plano afirma.** O componente não está montado em nenhuma
  página viva. O que continua rodando no `<head>` de toda página do layout legado é o script inline
  de `localStorage` (`layout.php:158-177`) — esse sim precisa da guarda de smoke.
- **Não existe baseline de uso.** O Checkpoint 0 mede um recurso que será ligado do zero.

### C6 — `TutorVirtualService::resolverContexto()` não conhece `/v2/`

`app/Services/TutorVirtualService.php:147-184` mapeia `/`, `/cursos`, `/area-curso`,
`/aluno/curso/...`, etc. Nenhuma rota `/v2/*` é reconhecida: todas caem em `contexto = 'publico'`.
A Etapa 7 precisa estender esse mapa para `/v2/aluno` → `area_aluno` e `/v2/aula` → `aula`.

### C7 — `conteudo_itens.tipo` inclui `quiz`

ENUM real: `'etiqueta','texto','arquivo','link','avaliacao_textual','video','quiz','html','video_incorporado'`.
O plano lista tudo menos `quiz`. **`quiz` é avaliação valendo nota** — o `assessment_context` e o
filtro de gabarito do `NorminhaKnowledgeService` precisam cobrir `quiz` e `avaliacao_textual`.

### C8 — Cache-busting da Norminha está quebrado

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
