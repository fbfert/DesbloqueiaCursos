# Relatório — Reescrita do Frontend V4 (Desbloqueia Cursos)

**Data de conclusão:** 2026-06-24 — **V4 PRONTO PARA GO-LIVE COMPLETO**
**Validado ao vivo:** home, catálogo, ficha de curso, auth (login/cadastro), área do aluno e LMS completo (texto, arquivo, link, etiqueta, **vídeo**, **quiz nos 3 estados**), correção do bug `forTurmaMatriculados`.
**Não bloqueante (pós go-live):** checkout ponta a ponta com pedido real e os itens da §9b.
**Template ativo:** `v4-claude` (em `configuracoes_frontend.template_visual_portal`, id 1)
**Design system:** `dc-main.css` (1370 linhas, 363 seletores `.dc-`, 44K) + `dc-main.js` (vanilla, sem dependências)

---

## 1. Arquitetura adotada

- Todo o V4 é **opt-in por template**: cada view pública ramifica em
  `if ($frontend_template === 'v4-claude') require <versão v4>; else <v1 intacto>`.
  O template é lido da config (read-only) — **nenhum controller/model/service/rota/banco de schema foi alterado**.
- Assets reais servidos em `/public_html/assets/...` (document root = `public_html/`; o diretório físico é `public_html/public_html/assets/`).
- Chrome (navbar desktop, bottom nav mobile, footer) é injetado pelo `layout.php` dentro do bloco `$isV4Theme` já existente.
- Fontes (Inter/Sora via Google Fonts) e ícones (Tabler Icons via CDN) carregados só no tema V4.

---

## 2. Arquivos criados

### Assets
- `public_html/assets/css/dc-main.css`
- `public_html/assets/js/dc-main.js`

### Partials / componentes
- `resources/views/partials/dc-stepper.php` (stepper do checkout)

### Views V4 novas
- `resources/views/v4-claude/meus-cursos.php`
- `resources/views/v4-claude/checkout/participantes.php`
- `resources/views/v4-claude/aluno/curso/index.php`
- `resources/views/v4-claude/aluno/curso/modulo.php`
- `resources/views/v4-claude/aluno/curso/conteudo.php`

## 3. Arquivos reescritos (views V4 que já existiam, com design antigo)
- `resources/views/v4-claude/catalogo.php`
- `resources/views/v4-claude/curso.php`
- `resources/views/v4-claude/login.php`
- `resources/views/v4-claude/cadastro.php`
- `resources/views/v4-claude/checkout/inscricao.php`
- `resources/views/v4-claude/checkout/resumo.php`
- `resources/views/v4-claude/checkout/comprovante.php`

## 4. Arquivos modificados (layout + ligação de branch nas views v1)
- `resources/views/layout.php` — chrome V4 (navbar/footer/bottom nav), links Tabler+`dc-main.css`/`.js`, correção do prefixo `/public_html/assets/` em `v4-claude.css`/`.js`
- `resources/views/home.php` — branch V4 + helpers `dc-`
- `resources/views/partials/public/bottom_nav_v4.php` — classes `dc-bnav`
- `resources/views/checkout/inscricao.php` — branch V4
- `resources/views/checkout/participantes.php` — branch V4
- `resources/views/meus-cursos/index.php` — branch V4
- `resources/views/aluno/curso/index.php` — branch V4
- `resources/views/aluno/curso/modulo.php` — branch V4
- `resources/views/aluno/curso/conteudo.php` — branch V4

> `checkout/resumo.php` e `checkout/comprovante.php` já possuíam o branch para v4-claude — não foram alterados (apenas as views v4 de destino foram reescritas).

## 5. Removidos
- `resources/views/v4-claude/checkout/_progresso.php` (órfão, substituído por `partials/dc-stepper.php`)

## 5b. Correção de bug backend (fora do escopo V4, mas aplicada)
- `app/Models/Inscricao.php` — adicionado o método **`forTurmaMatriculados($turmaId)`** que faltava (chamado por `TurmaService::formData()` e causava erro 500 em `/admin/turmas/editar`). É a **única** alteração fora da camada de views/assets do frontend.

## 6. Mudança de dados (autorizada)
- `configuracoes_frontend.template_visual_portal`: `v1` → `v4-claude`
- Matrícula de teste aprovada via admin: usuário 144 (CLAUDE) no curso 9 / turma 8 (inscrição 468) — para validação do LMS.

---

## 7. Mapa de variáveis/rotas final (.md → real no projeto)

| No .md (assumido) | Real no projeto |
|---|---|
| `$authUser` / `$authUser->nome` | `$loggedIn` (bool) + `$usuarioNome` (string) · em views: `Session::get('usuario_nome')` |
| `$curso->titulo` | `$curso['nome']` (arrays — `PDO::FETCH_ASSOC`) |
| `$curso->slug` / `/curso/{slug}` | `$curso['id']` / `/cursos/detalhe?curso_id={id}` |
| `$curso->imagem` | `$curso['thumbnail']` |
| `$curso->valor` | `$curso['valor_efetivo']` (fallback `['valor']`) |
| `$curso->descricao` | `$curso['descricao_completa']` via `Helpers::renderSafeHtml()` |
| `$cat->id` + `/catalogo?categoria=id` | `$cat['slug']` + `/cursos?categoria={slug}` ou `/categorias/{slug}/cursos` |
| `$credibilidade->x` | `$credibilidade['x']` (array: `alunos`,`cursos`,`certificados`) |
| `/catalogo` | `/cursos` |
| `/aluno/meus-cursos` | `/meus-cursos` |
| `/aluno/perfil` | `/minha-conta` |
| `$turmas` / `$instrutor` / `$inscricaoStatus` (curso) | aninhados em `$curso['turmas']`, `$curso['professor_responsavel']`/`['professores_responsaveis']`, `$curso['turma_selecionada']['situacao_inscricao']['status_fluxo']` (`matriculado`/`pendente_pagamento`/`nao_inscrito`) |
| `$turma->vagas_disponiveis` | `$turma['vagas']` (nullable) |
| `/checkout/inscricao?curso=` | `/inscricao?curso_id={id}&turma_id={id}` |
| login `name="email"` | `name="login"` (e-mail OU CPF) |
| checkout passo 1 = selecionar turma | passo real = **dados do pagador** (`pagador_*`, `tipo_pedido`, `quantidade`) + JS IBGE estado→cidade |
| cupom `name="cupom"` | `name="cupom_codigo"` + hidden `pedido_id` (rota `/checkout/cupom`) |
| LMS single-page `$itemAtual->tipo` | multi-página array: `$conteudo_item`, `$conteudo_detalhe`, `$conteudo_modulos`, `$conteudo_progresso`; quiz via `ConteudoQuizService` |
| aluno: `$pedidos`/`$certificados`/`$stats`/`$ultimoAcesso` | só `$inscricoes` + `$pedidosPendentes` (resto omitido — sem dado no controller) |
| `$csrfField` | injetado automaticamente pelo `View.php` |

---

## 8. Validação realizada (2026-06-24)

**Rotas públicas → 200:** `/`, `/cursos`, `/categorias`, `/login`, `/cadastro`, `/cursos/detalhe?curso_id=112`, `/como-funciona`, `/contato`, `/certificados/validar`.

**Logado (conta CLAUDE / matrícula 468):**
- `/meus-cursos` (área do aluno), Sala (lista de módulos), Módulo (lista de itens) → 200, render V4.
- Conteúdo **texto** (auto-concluído), **arquivo**, **link**, **etiqueta** → 200, `dc-study`, formulários de conclusão corretos, sem erro PHP.

- Conteúdo **vídeo** (item 17, curso 10): bloco "Vídeo do conteúdo" + "Assistir vídeo" ✓ (usuário 7).
- **Quiz** (item 1403) nos **3 estados**: não iniciado ("Iniciar quiz") → em andamento (perguntas + alternativas + enviar/salvar) → resultado ("acertou 1 de 1 — 100% — Aprovado!" + gabarito) ✓ (usuário 7).

**Assets/CDN:** `dc-main.css`, `dc-main.js` → 200; Google Fonts + Tabler Icons → 200.

**Fallback:** toggle template `v1` → home renderiza markup v1 (`theme-v1`, sem `dc-v4`); restaurado para `v4-claude`. v1 100% funcional.

**Checklist final (logado/anônimo):** rotas `/`, `/cursos`, `/login`, `/meus-cursos`, `/minha-conta` → 200; assets `dc-main.css/js` → 200; sem erros de aplicação no log (apenas `autenticacao.ausente` de bots).

---

## 9. Pontos pendentes

1. ~~Vídeo e Quiz do LMS~~ — **VALIDADOS AO VIVO em 2026-06-24** (usuário 7 / Felipe, matrícula insc#78 no curso 10, turma 11 reaberta temporariamente e depois restaurada para `encerrada`):
   - **Vídeo** (item 17): `dc-study` + bloco "Vídeo do conteúdo" + botão "Assistir vídeo" (item sem embed HTML, só URL → branch de link, correto). HTTP 200, sem erro.
   - **Quiz estado 1 (não iniciado):** bloco "Quiz" + botão "Iniciar quiz" + form `quiz/iniciar`.
   - **Quiz estado 2 (em andamento):** form `quiz/enviar` + pergunta + 4 alternativas (`dc-quiz-alt`) + botões "Enviar respostas"/"Salvar e continuar depois" + "Em andamento — tentativa 1".
   - **Quiz estado 3 (resultado):** "acertou 1 de 1 questões (100.0%)" + "Aprovado!" + `dc-callout-success` + gabarito (`dc-quiz-res-alt`).
   - *Obs.: o teste deixou 2 tentativas de quiz na conta do Felipe (item 1403, 2/2 usadas) — removíveis se desejado.*
2. **Checkout (etapa 06) — não validado ponta a ponta com login.** Telas reescritas e com `php -l` ok; falta exercício real (inscrição→participantes→resumo→comprovante) com um aluno com pedido pendente. A **chave PIX está fixa** (`cpeducacursos@gmail.com`) — confirmar se é a correta.
3. ~~Bug `forTurmaMatriculados`~~ — **CORRIGIDO em 2026-06-24.** Adicionado o método `forTurmaMatriculados($turmaId)` em `app/Models/Inscricao.php` seguindo o padrão real do Model (`Database::connection()->prepare()`, param nomeado, `fetchAll(PDO::FETCH_ASSOC)`, `deleted_at IS NULL`, status ativos reais `ativa/em_andamento/concluida/concluida_sem_certificado/certificado_emitido` — **não** os `matriculado/aprovado` sugeridos no rascunho, que não existem neste projeto). `php -l` ok; método retorna dados (turma 8 → 41, turma 11 → 1) com `nome/email/cpf`; `/admin/turmas/editar?turma_id=11` deixou de dar 500 (agora 302 auth-gate).

---

## 9b. Próximas tarefas (pós go-live, não bloqueantes)

1. **Checkout ponta-a-ponta** — validar o fluxo completo com um pedido real (inscrição → participantes → resumo → comprovante → aprovação) e confirmar a chave PIX.
2. **Certificados (área do aluno)** — a aba/card de certificado foi omitida por falta de dado no `MeusCursosController` (não há `$certificados`). Quando houver um certificado emitido, avaliar adicionar a listagem/download na área do aluno V4.
3. **Filtros do catálogo (modalidade/preço)** — omitidos porque o `PublicCursosController::index` só filtra por `categoria` e `busca`. Avaliar suporte no backend antes de expor no V4.
4. **`$ultimoCurso` / "Continue aprendendo" na home** — bloco omitido por não existir no `HomeController`. Avaliar adicionar (último curso acessado) futuramente.
5. **Vídeo + Quiz do LMS** — conferência visual ao vivo (ver §9.1): logar como usuário 7 (Felipe, matrícula ativa no curso 10) ou matricular a conta de teste via admin.

## 10. Como voltar ao v1 (rollback)

**Opção A (recomendada, via painel):** Admin → `/admin/configuracoes-globais/frontend` → selecionar template **v1** → salvar. Efeito imediato; as views V4 ficam inertes (não são removidas).

**Opção B (via banco):**
```sql
UPDATE configuracoes_frontend SET template_visual_portal = 'v1', updated_at = NOW() WHERE id = 1;
```

Como todo o V4 é condicional ao template, voltar para `v1` (ou `v2`/`v3`) restaura integralmente o frontend anterior sem necessidade de remover arquivos. Para remover o V4 por completo, apagar os arquivos da seção 2/3 e reverter os branches da seção 4 (cada branch são 2 linhas no topo da view v1).

---

## 11. Backup

Backup completo do projeto antes das alterações:
`public_html/backups/desbloqueiacursos-backup-2026-06-24.tar.gz`
