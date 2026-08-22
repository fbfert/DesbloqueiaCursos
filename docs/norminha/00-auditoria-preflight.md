# Norminha IA V1 — Auditoria pré-flight (Etapa 0)

**Data:** 22/08/2026 · **Branch:** `frontend-v4` · **Commit:** `f7ff3ce`
**Ambiente auditado:** produção (`/home/desbloqueiacursos/public_html`, `https://desbloqueiacursos.com.br`)
**Natureza:** somente leitura. Únicas escritas: este arquivo, `CONTEXTO-EXECUCAO.md` e a correção de
segurança descrita no item 13 (autorizada em separado).

As decisões operacionais estão condensadas em `docs/norminha/CONTEXTO-EXECUCAO.md`. Este documento
traz a evidência.

---

## 1. Fluxo atual da Norminha, do layout ao banco

```
Requisição HTTP
  └─ public_html/index.php  (BASE_PATH = PUBLIC_PATH = raiz web)
       └─ Router::dispatch  →  Controller  →  View::render()
            └─ resources/views/layout.php                    ← ÚNICO ponto de montagem
                 ├─ [head] <script inline> layout.php:158-177
                 │     lê localStorage 'norminha_tutor_minimized_v1'
                 │     (legadas: norminha_tutor_closed_until / _closed_v2 / _closed)
                 │     aplica TTL e escreve a classe .norminha-tutor-minimized
                 │     em document.documentElement ANTES da renderização
                 ├─ [head] <link> /assets/css/tutor-norminha.css?v=20260610-4   (linha 194)
                 ├─ [head] <script defer> /assets/js/tutor-norminha.js?v=...    (linha 232)
                 │
                 └─ if ($useFrontendTheme && !empty($tutorNorminha))            (320 e 341)
                      require resources/views/components/tutor_norminha.php
                        └─ renderiza <div id="norminha-tutor">
                             painel + avatar + título + texto + áudio + launcher

$tutorNorminha vem de:
  TutorVirtualService::componenteParaLayout($requestPath, $_GET)   layout.php:133
    ├─ resolverContexto()      rota → home|institucional|checkout|cursos|curso|area_aluno|aula|publico
    ├─ configuracoes()         → TutorConfiguracao::allIndexed()   → tabela tutor_configuracoes
    │     if (tutor_ativo == 0) return null       ← É O CASO HOJE
    ├─ contextoHabilitado()    → tutor_home / tutor_area_aluno / tutor_cursos / tutor_checkout
    └─ TutorFala::buscarAtiva($contexto)          → tabela tutor_falas
          resolve por aula > módulo > curso > rota > contexto
          if (!$fala) return null
```

**As duas chamadas em 320 e 341 são ramos mutuamente exclusivos** de um `if/else` sobre `$isV4Theme`.
Não há duplicação. Verificado por HTTP: zero páginas renderizam o componente mais de uma vez.

Admin: `/admin/tutor-norminha` → `TutorNorminhaController` → `TutorNorminhaService` (1.147 linhas,
CRUD de falas/avatares/configurações) → `tutor_falas` + `tutor_configuracoes` (migrations 051/052/053).

**Estado em produção:** `tutor_ativo = 0`. O componente não é renderizado em nenhuma rota.
`resources/views/v2/layout.php` e `v2/auth-layout.php` **não têm nenhuma referência à Norminha**.

## 2. Fluxo da área do aluno

```
V2 (alvo recomendado)                      LEGADO (secundário)
─────────────────────────────────────      ────────────────────────────────────────
GET /v2/aluno    → V2\AlunoController      GET /area-curso            → AreaCursoController
GET /v2/aula     → V2\AulaController       GET /aluno/curso/{i}/{c}/{t}
GET /v2/quiz     → V2\QuizController       GET /meus-cursos           → MeusCursosController
GET /v2/atividade→ V2\AtividadeController
     ↓                                          ↓
guard NO CONTROLLER:                       middleware 'auth' na rota:
  Session::get('usuario_id')                 Session::get('usuario_id')
  senão 302 /v2/login?origem=v2_aluno        senão 302 /login
     ↓                                          ↓
resources/views/v2/layout.php              resources/views/layout.php
  (SEM Norminha)                             (COM Norminha)

Dados acadêmicos (comuns aos dois):
  Inscricao ────────────────── matrícula do aluno; colunas materializadas
                               percentual_progresso DECIMAL(5,2), apto_certificado TINYINT(1)
  ConteudoModulo ──────────── conteudo_modulos  (ordem, status, deleted_at)
  ConteudoItem ───────────── conteudo_itens    (tipo, obrigatorio, ordem, status, deleted_at)
  ConteudoProgressoAluno ─── conteudo_progresso_aluno
                               UNIQUE (aluno_id, inscricao_id, item_id)
  LmsElegibilidadeService ── elegibilidade de certificado, ao vivo, 1 query
  LmsCriterioConclusaoService → resolver($cursoId, $turmaId)
```

## 3. DECISÃO CRÍTICA — qual área do aluno é o alvo

**Recomendação: V2** (`/v2/aluno`, `/v2/aula`).

As duas estão vivas e registradas. Evidência a favor da V2:

| Evidência | Fonte |
|---|---|
| `HOME_VERSION=v2` em produção — `/` já serve `V2HomeController` | `.env`, `config/app.php:16` |
| Controllers V2 mantidos em jul/2026 | `app/Controllers/V2/*` (22–27/07) |
| `AreaCursoService.php` parado desde 23/06/2026 | `ls -la` |
| Controllers V2 escrevem `inscricoes.percentual_progresso` — mantêm a coluna materializada fresca | `V2/AulaController.php`, `V2/QuizController.php` |
| Namespace V2 tem tratamento próprio de 404, login e redirect seguro | `V2ErrorPage`, `SafeRedirect`, `V2AuthenticateMiddleware` |

**Não integrar nas duas.** A área legada continua acessível e protegida; fica sem Norminha na V1.

⚠️ Custo não previsto: o componente **não existe** na V2. A Etapa 6/7 cria o ponto de montagem em
`resources/views/v2/layout.php` e estende `TutorVirtualService::resolverContexto()` para `/v2/*`
(hoje toda rota V2 cai em `contexto = 'publico'`, e não há fala cadastrada para esse contexto).

## 4. `conteudo_progresso_aluno` — CONFIRMADO

Verificado via `SHOW COLUMNS` / `SHOW INDEX` no banco de produção. Todas as colunas que o plano
afirma existir, existem, com os tipos afirmados. **Adicionalmente existe `deleted_at`**, que o plano
não menciona e que toda query precisa filtrar.

`UNIQUE uk_conteudo_progresso_aluno_contexto (aluno_id, inscricao_id, item_id)`: confirmado.

Distribuição real de `status` (1.952 linhas ativas): `concluido` 1.784 · `em_andamento` 163 ·
`reprovado` 4 · `pendente_correcao` 1. Os valores `nao_iniciado` e `acessado` **não ocorrem** —
a ausência de linha é que representa "não iniciado".

As queries das regras 1, 2 e 3 estão em `CONTEXTO-EXECUCAO.md`, validadas contra dados reais
(0,7–0,8 ms cada). Resultado do teste na inscrição 142:

- Regra 1 → item 196 "3.3 Foto, capa e apresentação visual do perfil", status `em_andamento`,
  `ultimo_acesso_em = 2026-06-03 22:53:39` → rótulo **"você parou aqui"**.
- Regra 2 → item 175 "1.3 O que é primeiro emprego" → rótulo **"seu próximo passo é"**.

A distinção de rótulo exigida pelo plano é sustentável com dado real.

## 5. Rate limit — CONFIRMADO AUSENTE

Zero ocorrências de `rate limit`, `ratelimit`, `throttle`, `RateLimit`, `Throttle` em `app/`,
`config/`, `routes/`. Nenhuma tabela correlata.

Extensões PHP no servidor: `curl`, `json`, `mbstring`, `pdo_mysql`. **Sem Redis, sem APCu, sem
Memcached.** A tabela `norminha_uso` em MySQL com `INSERT ... ON DUPLICATE KEY UPDATE` é a única
abordagem viável, exatamente como o plano previu.

Único precedente de limitação: `AuthService` (`MAX_LOGIN_ATTEMPTS` / `LOCK_MINUTES`) no login.

## 6. `LmsElegibilidadeService` — CONFIRMADO

Assinatura e retorno documentados em `CONTEXTO-EXECUCAO.md`. Somente leitura: um `SELECT` com
`INNER JOIN conteudo_modulos`, `LEFT JOIN conteudo_progresso_aluno`, `LEFT JOIN
conteudo_avaliacoes_textuais` e subquery de última entrega; agregação em PHP. 1 query, ~1,7 ms.

`AptidaoCertificadoService` expõe `contexto()`, `salvarConfiguracao()` e `recalcularInscricao()`.
**Confirmado: não há leitura por inscrição.** `recalcularInscricao()` escreve — proibido.

**Achado adicional:** `ProgressoService::recalcularInscricao()` também escreve. O plano só proíbe o
homônimo do `AptidaoCertificadoService`. Ambos entram na lista de proibições.

## 7. Custo de `AreaCursoService::carregarAluno()` — MEDIDO

Instrumentado com `SHOW SESSION STATUS LIKE 'Questions'` em inscrição real (136 itens de progresso):

| Chamada | Queries | Tempo | Payload | Chaves de topo |
|---|---|---|---|---|
| `carregarAluno()` | **32** | 25,1 ms | 12,8 KB | 17 |
| `resumoAluno()` | 13 | 4,3 ms | 0,2 KB | 7 |
| `calcularElegibilidadeConteudoUnificado()` | **1** | 1,7 ms | 0,4 KB | 12 |

Chaves de `carregarAluno()`: `instrucoes, modulos, materiais, links, atividades, inscricoes,
inscricao, curso, turma, selected_modulo, selected_aula, selected_atividade,
selected_atividade_entrega, percentual_progresso, apto_certificado, progresso, elegibilidade`.

**Recomendação: consultar os Models diretamente.** 32 queries por mensagem de chat é desproporcional
para montar um DTO de ~10 campos. Nenhuma escrita foi encontrada em `AreaCursoService` (grep por
INSERT/UPDATE/DELETE: zero) — a chamada é segura, apenas cara.

## 8. Arquivos a criar e modificar na V1

**Criar**
```
sql/073_norminha_conversas.sql
app/Models/NorminhaConversa.php · NorminhaMensagem.php · NorminhaFeedback.php · NorminhaUso.php
app/Services/NorminhaContextService.php · NorminhaToolsService.php · NorminhaService.php
app/Services/NorminhaRateLimitService.php
app/Services/NorminhaKnowledgeService.php · NorminhaPromptService.php · OpenAIService.php   [Onda 1]
app/Controllers/Api/NorminhaController.php
tests/Smoke/smoke.php · rotas.php · README.md · baseline.json                               [Etapa 0.5]
tests/Unit/norminha_models.php · norminha_context.php · norminha_tools.php · norminha_service.php
tests/Unit/norminha_knowledge.php                                                            [Onda 1]
docs/norminha/08-telemetria.md · 15-privacidade.md · 16-qa.md · 17-deploy.md · 19-review-go-live.md
```

**Modificar**
```
routes/api.php                              rotas /api/norminha/*
resources/views/v2/layout.php               NOVO ponto de montagem (não existia)
resources/views/components/tutor_norminha.php   shell do chat
assets/js/tutor-norminha.js                 cliente de chat
assets/css/tutor-norminha.css               estilos do chat
resources/views/layout.php                  bump da constante ?v= (ver item 10)
app/Services/TutorVirtualService.php        resolverContexto() reconhecer /v2/*
app/Services/TutorNorminhaService.php       toggles de IA (Onda 1)
app/Controllers/Admin/TutorNorminhaController.php + views admin   diagnóstico e telemetria
config/ai.php · .env.example                bloco openai                                     [Onda 1]
CLAUDE.md                                   comando real de smoke
```

## 9. Migrations

Maior número: **`072_perfil_revisor_comentarios.sql`**. Próximo livre: **`073`**.
74 arquivos `.sql` em `sql/`, sem runner automático.

## 10. Pontos de risco de regressão

| # | Risco | Situação real | Gravidade |
|---|---|---|---|
| 1 | Layout global | Ponto de montagem único, dois ramos exclusivos. Sem duplicação hoje | Média |
| 2 | Script inline no `<head>` | `layout.php:158-177` roda em toda página do layout legado, antes da renderização. Erro aqui quebra a página inteira | **Alta** |
| 3 | Norminha desligada | `tutor_ativo=0`: o componente não renderiza hoje. Reduz o risco imediato da Etapa 6 e elimina o baseline de uso | Informativo |
| 4 | Cache-busting quebrado | `layout.php:29-32` aponta para `BASE_PATH/public_html/assets/...`; arquivos estão em `assets/`. `is_file()` falha, `&f=` nunca é emitido. Única chave: `?v=20260610-4` literal | **Alta** na Etapa 6 |
| 5 | Tema `v4-claude` | Identificador de frontend em `ConfiguracaoGlobalService`, `ConfiguracaoFrontend`, layout e 3 views. Não renomear | Baixa |
| 6 | CSRF | `$app->post()` injeta `csrf` automaticamente. Não usar `postWithoutCsrf` | Baixa |
| 7 | Autenticação em API | Nenhum middleware devolve JSON — todos redirecionam 302. Ver item 11 | **Alta** |
| 8 | Duplicação de widget | Não ocorre hoje; a Etapa 7 introduz um segundo layout (V2) e passa a poder ocorrer | Média |
| 9 | Working tree sujo | 64 modificados + 33 não rastreados em produção. Sem rollback confiável | **Alta** |

## 11. Como proteger `POST /api/norminha/chat`

**CSRF:** `Router::post()` (`app/Core/Router.php:20-26`) faz `array_unshift($middleware, 'csrf')`
automaticamente. Basta registrar com `$app->post()`. `CsrfMiddleware` valida
`$request->input('_token')` — e como `Request::capture()` decodifica `application/json`
(`Request.php:44-50`), o JS pode enviar `_token` no **corpo JSON**, sem header customizado.
O token vem de `Csrf::token()`, disponível na View como `$csrfToken`.

**Autenticação — aqui o plano precisa de correção.** Alias reais: `auth`, `auth.v2`, `csrf`,
`permission:<chave>` (`Router.php:235-240`) — **não existe `v2auth`**.

O problema: nenhum middleware responde em JSON.

| Middleware | Falha de auth | Falha de CSRF |
|---|---|---|
| `AuthenticateMiddleware` | `302 → /login` | — |
| `V2AuthenticateMiddleware` | `302 → /v2/login` | — |
| `CsrfMiddleware` | — | `302 → referer` |

Um `fetch()` seguindo o 302 recebe `200` com o HTML do login e interpreta como sucesso.

**Duas saídas, escolha antes da Etapa 5:**

- **(a)** Criar `ApiAuthenticateMiddleware` (alias `auth.api`) que devolve
  `401 {"ok":false,"error":"nao_autenticado"}`, e um `ApiCsrfMiddleware` que devolve `403`.
  Mais limpo; exige registrar aliases novos em `Router::resolveMiddleware`.
- **(b)** Registrar as rotas com `postWithoutCsrf` e validar sessão + `Csrf::validate()` dentro do
  `NorminhaController`, devolvendo JSON. Menos arquivos novos, mas contraria a convenção do projeto
  de usar `$app->post()` — e `postWithoutCsrf` está reservado a webhooks externos.

**Recomendo (a).** Registre a decisão em `CONTEXTO-EXECUCAO.md` antes de escrever a Etapa 5.

Observação: as rotas `/v2/aluno`, `/v2/aula`, `/v2/quiz`, `/v2/atividade`, `/v2/minha-conta` **não
declaram middleware** — o guard está dentro de cada controller. Funciona (verificado: anônimo recebe
302 para `/v2/login?origem=v2_aluno`), mas não é o padrão declarativo. Não mexer nisso na V1.

## 12. Pendências, ambiguidades e inconsistências

1. **🔴 `ProgressoService::resumoAluno()` retorna 0% para todos.** Lê o modelo legado `aulas`, vazio.
   Em 12 inscrições reais: 11 divergem da tela; a 12ª "coincide" só porque o valor real também é 0.
   Detalhe e correção em `CONTEXTO-EXECUCAO.md` § C1. **É o defeito mais grave do plano.**
2. **🔴 Vazamento de dados em produção** — ver item 13.
3. `LmsElegibilidadeService` aceita `$turmaId` e o ignora (`$clauseTurma` sempre vazio, linha 99).
4. `conteudo_itens.tipo` inclui `quiz`, ausente da lista do plano. É avaliação valendo nota.
5. Cache-busting da Norminha quebrado (item 10.4).
6. `CLAUDE.md` documenta `tests/Smoke/smoke.php` (não existe) e
   `tests/Unit/professor_academic_scope.php` (não existe).
7. `DEEPSEEK_API_KEY` presente no `.env` de produção, **sem uma única referência no código**.
   Chave viva sem consumidor — revogar.
8. `app/Core/Router.php.tmp` versionado ao lado de `Router.php`. Resto de edição; remover.
9. Arquivo `defaults()` (18 KB) e ` \.\n \.` (0 bytes) na raiz — lixo de shell.
10. Banco é MariaDB 10.5.29, não MySQL 5.7 (§ C4).
11. Working tree com 64 modificados não commitados (§ C10).
12. **`/v2/como-funciona-a-sala-virtual` retorna 404.** Rota registrada
    (`routes/web.php:118`) e anunciada no `sitemap.xml` (`SitemapController.php:37`), mas a página
    está excluída em `paginas` (`deleted_at` preenchido). Encontrado pela suíte de smoke da
    Etapa 0.5 na primeira execução. Corrigir: republicar a página no admin **ou** remover rota,
    entrada do sitemap e `V2Nav::COMO_FUNCIONA_SALA`.

## 13. 🔴 Achado de segurança fora de escopo — CORRIGIDO

Não faz parte do plano da Norminha, mas foi encontrado durante a inspeção do bootstrap e é grave.

**Causa:** `index.php:3-4` define `BASE_PATH = PUBLIC_PATH = __DIR__`, e o deploy usa essa pasta
como raiz web. Logo `app/`, `config/`, `sql/`, `storage/` e `backups/` ficam **dentro do docroot**.
O `.htaccess` bloqueava apenas `.git` e `.env`.

**Confirmado por HTTP antes da correção:**

| Caminho | Antes | Conteúdo |
|---|---|---|
| `/backups/desbloqueiacursos-backup-2026-06-24.tar.gz` | **200** (144 MB) | backup completo site + banco |
| `/backups/*.sql.gz` | **200** | 9 dumps do banco |
| `/storage/private_uploads/comprovantes_pix/.../*.pdf` | **200** | 132 comprovantes PIX (nome, CPF, dados bancários) |
| `/storage/private_uploads/certificados/` | sem proteção | 41 certificados |
| `/storage/logs/*.log` | **200** | 104 logs com IP, user-agent, `usuario_id` |
| `/sql/*.sql` | **200** | 74 migrations — schema completo |

Os `.htaccess` dentro de `comprovantes_pix/*/` só contêm `Options -ExecCGI` + `AddType text/plain`:
impedem execução, não download.

**Correção aplicada** em `.htaccess` (raiz), autorizada pelo responsável, backup em
`.htaccess.bak-pre-hardening`:

```apache
RewriteRule ^(app|backups|config|docs|resources|routes|scripts|specs|sql|storage|tests)(/|$) - [F,L]
<FilesMatch "\.(sql|sql\.gz|gz|tgz|tar|log|bak|ini|sh|ps1|md|example|dist|lock|yml|yaml)$">
    Require all denied
</FilesMatch>
```

Seguro porque **nenhum** desses diretórios é referenciado como URL web: todos os acessos usam
caminho de sistema de arquivos (`BASE_PATH . '/storage/...'`) e são servidos por rotas controladas.
Verificado por grep antes de aplicar.

**Verificação pós-correção:** os 10 caminhos sensíveis retornam 403. 16 rotas públicas, os assets e
uploads da Norminha e as rotas protegidas continuam com o comportamento anterior.

**Pendente com o responsável (não automatizável):**
1. Rotacionar `DB_PASSWORD`, `ABACATEPAY_API_KEY`, `ABACATEPAY_WEBHOOK_SECRET` — o backup de 144 MB
   provavelmente contém o `.env`.
2. Mover `backups/` para fora do docroot.
3. Revogar `DEEPSEEK_API_KEY`.
4. Verificar nos logs do Apache se houve download desses caminhos.
5. Avaliar obrigação de comunicação à ANPD (dado pessoal e financeiro exposto).
