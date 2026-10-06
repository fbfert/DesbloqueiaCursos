# Tema caderno — Plano de implementação

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: use superpowers:subagent-driven-development (recomendado) ou superpowers:executing-plans para executar este plano tarefa a tarefa. Os passos usam checkbox (`- [ ]`) e são rastreados pelo OpenSpec (`/opsx:apply tema-caderno`).

**Goal:** Entregar o tema público "caderno" (vitrine + autenticação + checkout) atrás da chave `TEMA_PUBLICO`, com prévia para administradores e campo de cupom no checkout.

**Architecture:** Um seletor (`App\Support\TemaPublico`) decide, por página, entre `resources/views/caderno/<nome>` e `resources/views/v2/<nome>`; os controllers V2 trocam só a linha de renderização. O tema tem layout, partials, CSS e JS próprios em `assets/caderno/`, sem dependência externa; movimento com Web Animations API, IntersectionObserver e View Transitions.

**Tech Stack:** PHP 8 (MVC próprio, sem Composer), MySQL 5.7/MariaDB 10.5, CSS e JS vanilla, fontes woff2 locais (SIL OFL).

**Spec:** `openspec/changes/tema-caderno/` — `proposal.md`, `specs/tema-publico/spec.md`, `design.md`. Leia os três antes de começar. Protótipos aprovados (fonte visual e de código a portar): `.superpowers/brainstorm/8571-1791297896/content/` — `proto.css`, `proto.js`, `home-pagina.html`, `catalogo-pagina.html`, `kit.html`, `caderno.html`.

## Global Constraints

- `TEMA_PUBLICO`: só o valor exato `caderno` ativa o tema; qualquer outro valor ou ausência = `v2`.
- Prévia: `?tema=caderno` / `?tema=v2`, gravada na sessão (`tema_previa`) **somente** para usuário com `conteudo.gerenciar`; ignorada para os demais.
- Escopo do tema: home, catálogo, categorias, curso, institucionais, validar certificado, erro, login, pós-login, cadastro, recuperar e redefinir senha, checkout (inscrição, participantes, resumo, pagamento, comprovante, comprovante enviado). Aluno, aula, quiz, atividade, minha conta: **sempre V2**.
- Orçamento por página: JS do tema ≤ 15 KB gzip, CSS do tema ≤ 25 KB gzip; Lighthouse mobile: LCP ≤ 2,5 s, CLS ≤ 0,1.
- Nenhuma requisição a Google Fonts nem ao CDN do Tabler Icons nas páginas do tema.
- Tokens de cor (valores exatos): papel `#FCFCFA`, violeta `#22104A`, tinta `#1F3FA8`, laranja `#FF6A00`, laranja-texto `#B84A00`, post-it `#FFE98A`, papelão `#B98F5E`, fita `rgba(236,224,190,.94)`, pauta a cada 32 px. Laranja nunca em texto pequeno.
- Fontes: Instrument Serif (títulos), Geist (texto/interface), Kalam (no máximo 1–2 anotações por tela, nunca informação essencial).
- Conteúdo e ações presentes no HTML do servidor; estados ocultos de animação só sob `html.anima`; trava remove `.anima` em 2,5 s.
- Movimento reduzido: nada anima, tudo no estado final, sem view transitions. Modo leve (`saveData` ou `hardwareConcurrency <= 2`): só cenas principais, versão curta.
- Toda view do tema recebe **as mesmas variáveis** que a view V2 correspondente; nenhuma regra de negócio, model ou migration nova.
- Todo texto de interface em PT-BR com acentuação correta (`docs/padrao-editorial-ptbr.md`). Escapar tudo com `Helpers::e()`.
- Validar todo PHP alterado com `php -l` e todo JS com `node --check` (dentro do container: `docker compose -f docker/local/compose.yml exec -T app php -l <arquivo>`).
- Commits pequenos, um por tarefa, mensagem em português no estilo do repositório, terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

- Usuário **sem** permissão manda `?tema=caderno` → precisa continuar vendo o tema da chave, e uma prévia que ele tivesse antes é removida. Teste em 1.1.
- Página do escopo **sem view no tema** com a chave em `caderno` → precisa cair na V2 sem erro, porque a entrega é por etapas. Teste em 1.1 e verificação em cada tarefa de página.
- Cupom recusado por regra (curso em promoção, expirado, limite) → o aluno precisa ver **o motivo**, não o genérico; hoje o V1 perde o motivo porque `CupomService` devolve `errors` e o controller lê `message`. Teste em 10.1.
- JS falha ou não carrega em celular fraco → nada pode ficar invisível. Verificação em 3.3 e em 13.2.
- Curso sem turma aberta, sem conteúdo programático ou sem preço promocional → a página do curso não pode quebrar nem mostrar seções vazias. Verificação em 7.3 com a fixture.

---

## 1. Seletor de tema e prévia administrativa

**Files:**
- Create: `app/Support/TemaPublico.php`
- Create: `tests/Unit/tema_publico.php`
- Modify: `config/app.php` (chave `tema_publico`), `.env.example` (`TEMA_PUBLICO=v2` com comentário), `index.php` (aplicar prévia após `Session::start()`), `resources/views/caderno/partials/aviso-previa.php` (criado aqui, usado pelo layout na tarefa 2)

**Interfaces:**
- Produces:
  - `TemaPublico::decidir(string $config, ?string $previa): string` — pura; `'caderno'` só se `$previa === 'caderno'` ou (`$previa === null` e `$config === 'caderno'`); `$previa === 'v2'` força `'v2'`.
  - `TemaPublico::caminhoView(string $tema, string $nome, string $baseViews): string` — pura; `'caderno/'.$nome` se `$tema === 'caderno'` e existir `$baseViews.'/caderno/'.$nome.'.php'`; senão `'v2/'.$nome`.
  - `TemaPublico::ativo(): string` — lê config + `Session::get('tema_previa')`.
  - `TemaPublico::view(string $nome): string` — `caminhoView(ativo(), $nome, BASE_PATH.'/resources/views')`.
  - `TemaPublico::emPrevia(): bool`.
  - `TemaPublico::aplicarPrevia(?string $param, $usuarioId, callable $temPermissao): void` — se `$param` ∈ {`caderno`,`v2`} e `$temPermissao($usuarioId)` → `Session::put('tema_previa', $param)` (para `v2`, `Session::forget`); sem permissão, `Session::forget('tema_previa')`.

- [ ] 1.1 Escrever `tests/Unit/tema_publico.php` (padrão `describe/it/expect` de `tests/Unit/_bootstrap.php`, sem banco; simular sessão com `$_SESSION = array()`), cobrindo: `decidir('caderno', null) === 'caderno'`; `decidir('v2', null) === 'v2'`; `decidir('qualquer', null) === 'v2'`; `decidir('', null) === 'v2'`; `decidir('v2', 'caderno') === 'caderno'`; `decidir('caderno', 'v2') === 'v2'`; `caminhoView('caderno','home',$dirTmp)` com arquivo `caderno/home.php` criado num diretório temporário → `'caderno/home'`; sem o arquivo → `'v2/home'`; `caminhoView('v2','home',…) === 'v2/home'`; `aplicarPrevia('caderno', 7, fn => true)` grava `tema_previa=caderno`; `aplicarPrevia('caderno', 7, fn => false)` não grava e remove prévia existente; `aplicarPrevia('qualquer', 7, fn => true)` não altera; `aplicarPrevia('v2', 7, fn => true)` remove a prévia.
- [ ] 1.2 Rodar `docker compose -f docker/local/compose.yml exec -T app php tests/Unit/tema_publico.php` — esperado: falha (classe inexistente).
- [ ] 1.3 Implementar `App\Support\TemaPublico` com as assinaturas acima; `config/app.php` ganha `'tema_publico' => strtolower(trim((string) Env::get('TEMA_PUBLICO', 'v2'))) === 'caderno' ? 'caderno' : 'v2'`; `.env.example` ganha `TEMA_PUBLICO=v2` com comentário explicando prévia e rollback.
- [ ] 1.4 Em `index.php`, logo após `Session::start()`: se `GET` e `isset($_GET['tema'])`, chamar `TemaPublico::aplicarPrevia($_GET['tema'], Session::get('usuario_id'), fn($id) => $id && (new RbacService())->userHasPermission($id, 'conteudo.gerenciar'))`. Só toca o banco quando o parâmetro existe.
- [ ] 1.5 Criar `resources/views/caderno/partials/aviso-previa.php`: faixa fina no topo "Prévia do tema caderno · Sair da prévia" (link para a URL atual com `tema=v2`), renderizada só quando `TemaPublico::emPrevia()`.
- [ ] 1.6 Rodar o teste — esperado: todos passam. `php -l` nos arquivos alterados.
- [ ] 1.7 Commit "Seletor do tema publico e previa para administradores".

## 2. Fundação visual: fontes, ícones, CSS, layouts e dados locais

**Files:**
- Create: `assets/caderno/fontes/{instrument-serif-400.woff2, instrument-serif-400-italic.woff2, geist-vf.woff2, kalam-400.woff2}`, `assets/caderno/caderno.css`, `resources/views/caderno/layout.php`, `resources/views/caderno/auth-layout.php`, `resources/views/caderno/partials/{topo.php, rodape.php, bnav.php, icones.php}`, `tests/Fixtures/tema_caderno_vitrine.sql`, `assets/caderno/exemplos/` (capas de exemplo locais)
- Modify: `.claude/skills/` (skills de design, ver 2.1)

**Interfaces:**
- Consumes: `TemaPublico::emPrevia()` e o partial `aviso-previa.php` (tarefa 1).
- Produces:
  - Layout: o shim de página define `$contentView` (caminho absoluto de `resources/views/caderno/pages/<pagina>.php`) e `$paginaTema` (string, vira `data-pagina` no `<body>`), depois `require BASE_PATH.'/resources/views/caderno/layout.php'`. O layout recebe as mesmas variáveis de navegação de `v2/layout.php` (`V2Nav::links`, `loggedIn`, `usuarioPrimeiroNome`, `pageTitle`, `pageDescription`, canonical).
  - Ícones: `caderno_icone(string $nome, string $classe = ''): string` em `partials/icones.php` (retorna `<svg><use href="#i-$nome"/></svg>` com `aria-hidden="true"`); sprite com: `menu, fechar, busca, filtro, seta-esq, seta-dir, inicio, cursos, certificado, usuario, relogio, ao-vivo, sob-demanda, presencial, simulado, calendario, vagas, cadeado, check, grampo, copiar, cartao, pix, alerta, whatsapp`.
  - Classes CSS do kit (usadas por todas as páginas): `.folha .furos .miolo .topo .nav .bnav .t1 .t2 .lead .mao .marcado .btn .btn-laranja .btn-sec .link .campo .postit .fita .selo .carimbo .foto .lomb .estante .estante-rolo .aba .abas .sheet .recibo .cupom-recorte .checklist .rv` (nomes do protótipo `proto.css`, mais `.campo`, `.recibo`, `.cupom-recorte`, `.checklist` do `kit.html`).

- [ ] 2.1 Instalar as skills de design para a implementação: copiar `skills/frontend-design/` de `github.com/anthropics/skills` e as skills de animação/design-engineering de `github.com/emilkowalski/skills` para `.claude/skills/`, **lendo cada arquivo antes** (só Markdown; rejeitar qualquer script, hook ou instrução de rede). Não instalar `impeccable` nem `gsap-skills`.
- [ ] 2.2 Baixar as fontes (fontsource, jsDelivr): `…/fontsource/fonts/instrument-serif@latest/latin-400-normal.woff2`, `…/latin-400-italic.woff2`, `…/fontsource/fonts/geist:vf@latest/latin-wght-normal.woff2`, `…/fontsource/fonts/kalam@latest/latin-400-normal.woff2` para `assets/caderno/fontes/` com os nomes acima; conferir `file` = Web Open Font Format 2.
- [ ] 2.3 Escrever `assets/caderno/caderno.css` portando `proto.css` + componentes de `kit.html` (campo na linha, post-it, carimbo retangular, selos, checklist, recibo serrilhado, cupom recortado com borda tracejada e tesoura), `@font-face` locais com `font-display: swap`, tokens dos Global Constraints, `@view-transition { navigation: auto; }` dentro de `@media (prefers-reduced-motion: no-preference)`, e `@media (prefers-reduced-motion: reduce)` zerando transições.
- [ ] 2.4 Escrever `partials/icones.php` (sprite SVG inline, traço de caneta irregular, `stroke="currentColor"`) e `caderno_icone()`.
- [ ] 2.5 Escrever `layout.php` e `auth-layout.php`: `<head>` com preload de `geist-vf.woff2` e `instrument-serif-400.woff2`, script inline de gate (`if(!matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('anima');setTimeout(function(){document.documentElement.classList.remove('anima')},2500)`), CSS e `caderno.js` (`defer`) com `?v=filemtime`, `<body data-pagina="<?= $paginaTema ?>">`, aviso de prévia, topo, conteúdo, rodapé, bnav (só `layout.php`), montagem da Norminha via `resources/views/v2/partials/norminha_montagem.php` (mesma do `v2/layout.php`).
- [ ] 2.6 Escrever `tests/Fixtures/tema_caderno_vitrine.sql` (só ambiente local; cabeçalho dizendo "NÃO aplicar em produção"): 6 categorias (Prova Nacional Docente, Cursos Formativos, Direito na Prática, Neurociência, Inteligência Artificial, Extensão Curricular), 12 cursos ativos espelhando o catálogo real (títulos, preços, `valor_promocional`, `em_promocao` em 2 deles, `carga_horaria`, `modalidade`, `thumbnail` apontando para `assets/caderno/exemplos/`), turmas abertas para 10 deles (1 curso sem turma, 1 sem conteúdo programático), conteúdo programático com 4–6 módulos nos demais; páginas institucionais `quem-somos` e `onde-estamos` publicadas na tabela `paginas` (no formato que `V2\InstitucionalController` lê); 3 imagens de exemplo 16:9 em `assets/caderno/exemplos/` (geradas, sem marcas de terceiros). Aplicar: `docker exec -i desbloqueia-db-1 mysql -uroot desbloqueia_local < tests/Fixtures/tema_caderno_vitrine.sql`.
- [ ] 2.7 Verificar: `php -l` nos PHP; `gzip -c assets/caderno/caderno.css | wc -c` ≤ 25600.
- [ ] 2.8 Commit "Fundacao visual do tema caderno".

## 3. Núcleo de movimento

**Files:**
- Create: `assets/caderno/caderno.js`

**Interfaces:**
- Consumes: `html.anima`, `body[data-pagina]`, classes do kit (tarefa 2).
- Produces (objeto global `window.Caderno`):
  - `Caderno.animar(el, keyframes, opcoes): Animation|null` (easing padrão `cubic-bezier(.2,.8,.2,1)`, `fill: 'both'`).
  - `Caderno.tracar(pathEl, opcoes): Animation|null` (stroke-dashoffset do comprimento até 0, easing linear).
  - `Caderno.contar(el, duracaoMs): void` (lê `data-n`, formata `pt-BR`).
  - `Caderno.carimbar(el, atrasoMs): void` (keyframes do protótipo: `scale(2.3) rotate(-32deg)` opacidade 0 → `scale(1) rotate(-14deg)` .92 com quique; tremor de 220 ms no pai fora do modo leve).
  - `Caderno.aoVer(el, callback, opcoes): void` (IntersectionObserver, uma vez só).
  - `Caderno.leve: boolean`, `Caderno.reduzido: boolean`.
  - `Caderno.pagina(nome, init)` — registra o módulo; executa `init()` só se `body.dataset.pagina === nome` e movimento não reduzido.
  - Revelação automática: todo `.rv` ganha `.visto` ao entrar na tela (desligada no modo leve: `.rv` já visível).

- [ ] 3.1 Implementar `caderno.js` portando `proto.js` para a interface acima; `CSS.registerProperty('--mt')`; ao iniciar, cancelar a trava de 2,5 s do layout; em movimento reduzido remover `.anima` e não registrar nada.
- [ ] 3.2 `node --check assets/caderno/caderno.js`; `gzip -c assets/caderno/caderno.js | wc -c` — anotar o tamanho (núcleo deve ficar ≤ 5 KB gzip para sobrar orçamento às páginas).
- [ ] 3.3 Verificação de segurança: num shim temporário de teste, provocar `throw` antes do init e confirmar que em ≤ 2,5 s nenhum elemento `.rv` fica com opacidade 0 (`getComputedStyle`). Remover o shim.
- [ ] 3.4 Commit "Nucleo de movimento do tema caderno".

## 4. Home

**Files:**
- Create: `resources/views/caderno/home.php` (shim), `resources/views/caderno/pages/home.php`, `resources/views/caderno/partials/{trilha-hero.php, foto-curso.php, lombada.php, carimbo.php}`
- Modify: `app/Controllers/V2/HomeController.php` (linha do `View::render`), `assets/caderno/caderno.js` (módulo `home`)

**Interfaces:**
- Consumes: variáveis do `HomeController` V2 (`featuredCourses`, `topCourses`, `categories`, `heroStats`, `heroTitulo`, `heroSubtitulo`, `coursePalette` e as de navegação); `caderno_icone()`; `Caderno.*`.
- Produces: `partials/foto-curso.php` espera `$curso` (mesmo formato dos itens de `featuredCourses`/`cursos` V2) e `$vtNome` opcional; emite `style="view-transition-name: capa-<id>"` na imagem e `titulo-<id>` no `<h3>`, link para a URL do curso V2. `partials/lombada.php` espera `$categoria` (formato dos itens de `categories`). `partials/carimbo.php` espera `$linhas` (array de 3 strings) e `$cor` (`laranja|verde|tinta`).

- [ ] 4.1 `HomeController`: `View::render(TemaPublico::view('home'), $data, false)`.
- [ ] 4.2 Montar `pages/home.php` conforme `home-pagina.html` e a seção 5 do design: abertura (título, marca-texto, `Escolher meu curso`, números de `heroStats`, trilha com rótulos Inscrição/Aulas/Simulado, anotação, carimbo) → estante (`categories`, com lado explicativo no desktop) → até **6** de `featuredCourses` (`array_slice`) → "O que você leva de cada curso." (3 provas fixas, texto do protótipo) → "Os mais procurados." (`topCourses` com barra proporcional ao maior número de alunos) → chamada final. Seções vazias não são renderizadas.
- [ ] 4.3 Módulo `Caderno.pagina('home', …)`: cena da trilha (adiada até a trilha estar inteira na tela), contagem dos números, estante (`aoVer`), ranking (`aoVer`).
- [ ] 4.4 Verificar com `.env` `TEMA_PUBLICO=caderno` e a fixture aplicada: `curl -s http://127.0.0.1:8010/ | grep -c 'data-pagina="home"'` = 1; em navegador 360 px e 1360 px a página corresponde ao protótipo aprovado; JS desativado → tudo visível; com `TEMA_PUBLICO=v2` a home é a V2. `php -l`, `node --check`.
- [ ] 4.5 Commit "Home no tema caderno".

## 5. Catálogo

**Files:**
- Create: `resources/views/caderno/catalogo.php`, `resources/views/caderno/pages/catalogo.php`, `resources/views/caderno/partials/divisorias.php`
- Modify: `app/Controllers/V2/CatalogoController.php`, `assets/caderno/caderno.js` (módulo `catalogo`)

**Interfaces:**
- Consumes: variáveis do `CatalogoController` V2 (`cursos`, `chips`, `categoriaSelecionada`, `tituloCategoria`, `totalCursos`, `paginacao`, `modalidadesView`, `estado` e as de navegação); `foto-curso.php` (tarefa 4).

- [ ] 5.1 `CatalogoController`: `TemaPublico::view('catalogo')`.
- [ ] 5.2 `pages/catalogo.php` conforme `catalogo-pagina.html`: título, busca (form `GET` existente, campo com o mesmo `name` da V2), divisórias de categoria como links/`GET` (funcionam sem JS), botão "Filtrar e ordenar" que abre a folha (sem JS, os filtros ficam visíveis inline abaixo da busca), contagem, grade de `foto-curso`, paginação como números de página a partir de `paginacao`, estado vazio com anotação.
- [ ] 5.3 Módulo `catalogo`: folha que sobe (com foco preso, Esc fecha, foco volta ao botão); FLIP ao trocar de categoria quando a navegação for por JS; busca instantânea **só** quando todos os cursos já estão na página (sem paginação), senão submete o form.
- [ ] 5.4 Verificar: abas e folha operáveis só pelo teclado; sem JS, filtros por `GET` funcionam; 360/768/1360 px; `php -l`, `node --check`.
- [ ] 5.5 Commit "Catalogo no tema caderno".

## 6. Categorias

**Files:**
- Create: `resources/views/caderno/categorias.php`, `resources/views/caderno/pages/categorias.php`
- Modify: `app/Controllers/V2/CategoriasController.php`

**Interfaces:**
- Consumes: `categorias` do controller; `lombada.php` (tarefa 4).

- [ ] 6.1 `CategoriasController`: `TemaPublico::view('categorias')`.
- [ ] 6.2 Página: estante completa com todas as categorias (lombadas grandes, altura variando por quantidade de cursos), cada lombada linkando para o catálogo filtrado; abaixo, lista acessível das mesmas categorias (nome, contagem, descrição se houver).
- [ ] 6.3 Verificar teclado (Tab percorre lombadas, Enter abre), 360/1360 px, `php -l`.
- [ ] 6.4 Commit "Categorias no tema caderno".

## 7. Curso

**Files:**
- Create: `resources/views/caderno/curso.php`, `resources/views/caderno/pages/curso.php`, `resources/views/caderno/partials/{trilha-modulos.php, ficha-inscricao.php, turma-ficha.php}`
- Modify: `app/Controllers/V2/CursoController.php` (os dois `View::render`), `assets/caderno/caderno.js` (módulo `curso`)

**Interfaces:**
- Consumes: `curso`, `estadoIndisponivel`, `mensagem`, `titulo` do controller (ler `resources/views/v2/pages/curso.php` para o formato de `$curso`: turmas, módulos, seções textuais, preços, professor); `foto-curso` não — a capa aqui usa `view-transition-name: capa-<id>` e `titulo-<id>`.

- [ ] 7.1 `CursoController`: `TemaPublico::view('curso')` nos dois pontos.
- [ ] 7.2 Página: capa colada grande + título (com os nomes de view transition) → ficha de inscrição (preço atual, original riscado, turma, botão `Desbloquear` com o mesmo destino da V2; lateral `position: sticky` ≥ 900 px; barra fixa inferior no celular acima do bnav) → seções da V2 na mesma ordem e com os mesmos títulos (Sobre o curso, O que você vai aprender, Objetivo geral, Público-alvo, Pré-requisitos, Metodologia, Ementa, Conteúdo programático, Avaliação, Produto final), cada uma só se tiver conteúdo → conteúdo programático como trilha vertical (`trilha-modulos.php`) → turmas abertas como fichas pautadas → professor como assinatura. Estado indisponível com a mensagem da V2.
- [ ] 7.3 Verificar com a fixture: curso completo, curso sem turma (sem botão de compra quebrado, mensagem da V2), curso sem conteúdo programático (seção ausente), curso em promoção (preço original riscado); transição catálogo→curso no Chrome; `php -l`, `node --check`.
- [ ] 7.4 Commit "Pagina do curso no tema caderno".

## 8. Autenticação

**Files:**
- Create: `resources/views/caderno/{login.php, pos-login.php, cadastro.php, recuperar-senha.php, recuperar-senha-redefinir.php}` (shims sobre `auth-layout.php`) e `resources/views/caderno/pages/` correspondentes
- Modify: `app/Controllers/V2/LoginController.php` (login e pós-login), `CadastroController.php`, `RecuperarSenhaController.php`, `assets/caderno/caderno.js` (módulo `cadastro`: força da senha)

**Interfaces:**
- Consumes: variáveis de cada controller (ex.: `loginAction`, `old`, `errors`, `redirectSeguro`, `origemFlag`, `cadastroAction`, `termosHref`, `privacidadeHref`, `recuperarAction`, `redefinirAction`, `token`). **Campos, `name`s, ações, CSRF e campos ocultos idênticos aos da V2** — ler cada `resources/views/v2/pages/<pagina>.php`.

- [ ] 8.1 Trocar a renderização nos controllers por `TemaPublico::view(...)`.
- [ ] 8.2 Páginas: folha centralizada, campos "escrever na linha" com rótulo visível e erro em texto junto do campo, mostrar/ocultar senha, máscara de CPF existente reaproveitada; no cadastro, força da senha como marca-texto que preenche (aria-live com o texto da força).
- [ ] 8.3 Verificar fluxos reais no ambiente local: login com `aluno.homologacao@polorainbow.com.br` / `Local@12345` (redireciona como na V2), login inválido mostra erro, cadastro novo, pedido de recuperação; `php -l`, `node --check`.
- [ ] 8.4 Commit "Autenticacao no tema caderno".

## 9. Checkout: etapas

**Files:**
- Create: `resources/views/caderno/checkout/{inscricao.php, participantes.php, resumo.php, pagamento.php, comprovante.php, comprovante-enviado.php}`, `resources/views/caderno/partials/{checklist-checkout.php, recibo.php, postit.php}`
- Modify: `app/Controllers/CheckoutController.php` (`renderCheckout()`), `assets/caderno/caderno.js` (módulo `checkout`)

**Interfaces:**
- Consumes: os dados de cada etapa que `renderCheckout()` já monta (ler `resources/views/v2/pages/checkout-*.php` e `v2/checkout/*.php`); `carimbo.php` (tarefa 4).
- Produces: `partials/checklist-checkout.php` espera `$etapaAtual` ∈ {`inscricao`,`participantes`,`resumo`,`pagamento`,`comprovante`}; `partials/recibo.php` espera `$pedido` (formato do resumo V2) e `$slotCupom` (HTML opcional, preenchido na tarefa 10).

- [ ] 9.1 `renderCheckout()`: `View::render(TemaPublico::view('checkout/' . $nome), $data, false)`.
- [ ] 9.2 Etapas com os **mesmos forms, `name`s, ações, CSRF e campos ocultos da V2**: checklist riscado (no celular "Etapa N de 5"); inscrição (pagador, compra para mim/terceiros/lote, quantidade); participantes; resumo com recibo serrilhado; pagamento (online quando habilitado + PIX com instruções em post-it e botão copiar chave); comprovante (área de envio "grampeada", estados enviar/reenviar/em análise/pago da V2); comprovante enviado com carimbo "COMPROVANTE EM ANÁLISE".
- [ ] 9.3 Módulo `checkout`: risco da etapa concluída ao carregar a próxima; carimbo na tela de enviado; copiar chave PIX com retorno em texto.
- [ ] 9.4 Verificar ponta a ponta no local com a fixture: pedido para mim, pedido para terceiros, envio de comprovante (imagem pequena), estados em análise e pago (alterando o status no banco local); `php -l`, `node --check`.
- [ ] 9.5 Commit "Checkout no tema caderno".

## 10. Cupom no checkout

**Files:**
- Modify: `app/Controllers/CheckoutController.php` (nova ação), `routes/web.php`, `app/Services/PedidoService.php` (mensagens de `aplicarCupomAoPedido`), `app/Services/CupomService.php` (mensagens de `validarCupomNoPedido`, `validateCupomContext`, `persistirAplicacao`), `resources/views/caderno/checkout/resumo.php` (campo)
- Create: `tests/Unit/cupom_checkout.php`

**Interfaces:**
- Consumes: `PedidoService::aplicarCupomAoPedido($pedidoId, $cupomCodigo, $actorUserId, $ip, $ua): array` (existente; retorno `ok` + `message` **ou** `errors`).
- Produces: `CheckoutController::aplicarCupomV2(Request $request): Response`; rota `POST /v2/checkout/cupom` com `array('auth.v2')`; `CheckoutController::mensagemResultadoCupom(array $resultado): string` (estática, pura): `message` se houver, senão `implode(' ', errors)`, senão `'Não foi possível aplicar o cupom.'`.

- [ ] 10.1 Escrever `tests/Unit/cupom_checkout.php` (transação + rollback, inserção de usuário/curso/pedido/item no padrão de `tests/Unit/quiz_rascunho.php`; cupom com `codigo`, `nome`, `status='ativo'`, `escopo='todo_site'`, desconto percentual 10): (a) `aplicarCupomAoPedido` com código inexistente → texto contém `'Cupom não encontrado.'`; (b) pedido cujo item tem curso com `em_promocao = 1` → texto contém `'Curso em promoção não aceita cupom.'` e o pedido não ganha cupom; (c) pedido inexistente → `'Pedido não encontrado.'`; (d) usuário que não é dono → mensagem contém `'Você não tem permissão'`; (e) `mensagemResultadoCupom(['ok'=>false,'errors'=>['A.','B.']]) === 'A. B.'`, com `message` presente → `message`, vazio → `'Não foi possível aplicar o cupom.'`.
- [ ] 10.2 Rodar — esperado: falha nas mensagens sem acento e no método inexistente.
- [ ] 10.3 Acentuar todas as mensagens de usuário dos métodos listados em Files (ex.: `Cupom não encontrado.`, `Pedido não encontrado.`, `Curso em promoção não aceita cupom.`, `Cupom não está ativo.`, `Este cupom não é válido para o curso selecionado.`, `Quantidade mínima de vagas não atingida.`, `Limite de uso por usuário atingido.`, `Pedido já possui outro cupom aplicado.`, `Cupom inválido.`, `Você não tem permissão para aplicar cupom neste pedido.`) sem alterar condição alguma.
- [ ] 10.4 Implementar `mensagemResultadoCupom()` e `aplicarCupomV2()`: `pedido_id` e `cupom_codigo` do corpo; código vazio → flash de erro `cupom_codigo` "Informe o código do cupom."; chama `aplicarCupomAoPedido` com usuário da sessão, IP e user agent; sucesso → flash `success` "Cupom aplicado. O novo total já está no resumo."; falha → flash `errors['cupom_codigo'] = mensagemResultadoCupom($resultado)`; sempre `redirect('/v2/checkout/resumo?pedido_id=' . (int) $pedidoId)`. Registrar a rota ao lado das demais `/v2/checkout/*`.
- [ ] 10.5 Campo no `caderno/checkout/resumo.php` dentro do recibo (`$slotCupom`), visual "cupom recortado": só quando `empty($comprovanteAguardandoAprovacao) && empty($pedidoPagoOuAprovado)`; `value` pré-preenchido com `$cupomPromocional`; form `POST /v2/checkout/cupom` com `csrfField` e `pedido_id`; erro de `errors['cupom_codigo']` junto do campo; cupom aplicado exibido com código e desconto.
- [ ] 10.6 Rodar o teste — passa. Verificação HTTP local: cupom válido aplica e volta ao resumo do tema; inexistente mostra o motivo acentuado; POST sem `_token` é rejeitado e registrado em `auditoria_logs` como `seguranca.csrf.rejeitado`. Rodar `tests/Unit/checkout_rapido_fase0.php` e `checkout_rapido_fase1.php` para garantir que nada do checkout regrediu. `php -l`.
- [ ] 10.7 Commit "Campo de cupom no checkout do tema caderno".

## 11. Institucionais, validação de certificado e erro

**Files:**
- Create: `resources/views/caderno/{institucional.php, certificados-validar.php, erro.php}` e `pages/` correspondentes
- Modify: `app/Controllers/V2/InstitucionalController.php`, `app/Controllers/V2/CertificadoValidacaoController.php`, `app/Support/V2ErrorPage.php`

**Interfaces:**
- Consumes: `institTitulo`, `institResumo`, `institHtml` (já sanitizado — renderizar como a V2 faz), `institMapEmbedUrl`, `institMapLinkUrl`, `institBreadcrumb`; `certificado`, `codigo`, `cpf`, `erro`; dados de `V2ErrorPage`. `carimbo.php`.

- [ ] 11.1 Trocar a renderização por `TemaPublico::view(...)` nos três pontos.
- [ ] 11.2 Institucional com texto longo alinhado à pauta (`line-height` múltiplo de 32 px no corpo); validar certificado com campo "escrever na linha" e resultado com carimbo verde "VÁLIDO" ou aviso de não encontrado, mesmos campos da V2; erro "Esta página foi arrancada do caderno." com borda rasgada (CSS `clip-path`), código de status preservado e links de volta.
- [ ] 11.3 Verificar: `/v2/quem-somos` com página cadastrada na fixture, `/v2/rota-que-nao-existe` → 404 com página do tema, validação de certificado com código inexistente; `php -l`.
- [ ] 11.4 Commit "Institucionais, certificado e erro no tema caderno".

## 12. Smoke e paridade com a chave nos dois valores

**Files:**
- Modify: `tests/Smoke/rotas.php` (rotas do escopo que faltarem, inclusive `/v2/categorias` e `/v2/certificados/validar`), `tests/Smoke/README.md` (como rodar com `TEMA_PUBLICO` nos dois valores)

- [ ] 12.1 Rodar `php tests/Smoke/smoke.php http://127.0.0.1:8010` no container com `TEMA_PUBLICO=v2` e depois com `TEMA_PUBLICO=caderno` (editar o `.env` local) — esperado nos dois: mesmas rotas PASS (as 2 falhas conhecidas de páginas sem dados em produção não contam se a fixture cobrir `quem-somos`), nenhuma página com erro PHP, guarda de layout da Norminha passando.
- [ ] 12.2 Paridade: para home, catálogo, curso e resumo do checkout, comparar com `TEMA_PUBLICO` nos dois valores que todo link de ação (`href` de cursos/turmas/inscrição, `action` dos forms, `name` dos campos) existe nas duas versões — script descartável com `curl` + `grep -o 'action="[^"]*"\|name="[^"]*"'` e `diff`; diferenças só onde o design prevê (campo de cupom).
- [ ] 12.3 Commit "Smoke do tema caderno nos dois valores da chave".

## 13. Desempenho, acessibilidade e entrega

**Files:**
- Create: `docs/<data-da-entrega>-tema-caderno.md` (nome no padrão `AAAA-MM-DD-...` dos demais relatórios)
- Modify: `docs/deploy.md` (seção sobre `TEMA_PUBLICO` e prévia)

- [ ] 13.1 Orçamento: para cada página do escopo, `gzip -c` de `caderno.css` e `caderno.js` dentro de 25600 e 15360 bytes; Lighthouse mobile (`npx -y lighthouse@12 <url> --only-categories=performance,accessibility --form-factor=mobile --output=json --quiet --chrome-flags="--headless"`) em home, catálogo, curso e resumo: LCP ≤ 2,5 s, CLS ≤ 0,1, acessibilidade ≥ 95; nenhuma requisição a `fonts.googleapis.com`, `fonts.gstatic.com` ou `@tabler/icons`.
- [ ] 13.2 Acessibilidade manual: percorrer home → catálogo → curso → login → checkout só com teclado; foco visível em todos os controles; leitor de tela (NVDA ou TalkBack) anuncia estante, divisórias, folha de filtros e erros de campo; movimento reduzido ativado → nada se move e tudo aparece; JS bloqueado → tudo visível e funcional.
- [ ] 13.3 Navegadores: Chrome desktop, Chrome Android (DevTools perfil Moto G Power + rede 4G lenta), Firefox e Safari/WebKit — sem view transitions onde não houver suporte, sem quebra. Modo leve: com `Object.defineProperty(navigator, "hardwareConcurrency", {value: 2})` injetado antes do carregamento (DevTools > Sources > Overrides no início de `caderno.js`), a home faz só a cena curta da trilha e as seções não animam ao rolar.
- [ ] 13.4 Revisar todos os textos novos de interface e mensagens em PT-BR com acentuação (`docs/padrao-editorial-ptbr.md`).
- [ ] 13.5 Escrever o relatório de entrega em `docs/` no padrão dos demais (o que muda, decisões, verificação, como ativar a prévia, como virar a chave, rollback) e atualizar `docs/deploy.md`.
- [ ] 13.6 Commit "Relatorio de entrega do tema caderno".

## Workflow follow-up

- Deploy com `TEMA_PUBLICO=v2` em produção; validar cada página pela prévia `?tema=caderno` logado com um usuário que tenha `conteudo.gerenciar`.
- Virar para `TEMA_PUBLICO=caderno` só com aprovação do produto; rollback é voltar para `v2`.
- Arquivar com `/opsx:archive tema-caderno` depois da virada validada.
- Próxima mudança: área do aluno, aula, quiz e minha conta no tema, e remoção das views V2 migradas.
