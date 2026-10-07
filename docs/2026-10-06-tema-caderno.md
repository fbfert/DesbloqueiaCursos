# Tema caderno: o site público ganha identidade própria, atrás de uma chave

Data: 2026-10-06
Mudança OpenSpec: `openspec/changes/tema-caderno/` (proposta, spec, design e tarefas)
Branch: `feat/tema-caderno` (commits `646900d` a este relatório); fase 2 (área do
aluno) em `feat/caderno-fase2`, mudança `openspec/changes/tema-caderno-aluno/`
Migration: **nenhuma**
Estado: **implementado e validado em ambiente local** (vitrine, autenticação,
checkout e, na fase 2, área do aluno, aula, atividade, quiz e minha conta);
aguardando deploy com `TEMA_PUBLICO=v2` e validação pela prévia de administrador

O site público V2 funcionava, mas tinha cara de template genérico, e a home
listava quase o catálogo inteiro. O público é majoritariamente classe C/D, no
celular, muitas vezes Android de entrada com dados pré-pagos. Esta entrega cria
o tema **caderno**: a trilha de estudo desenhada à caneta num caderno, com papel
pautado, post-it, fita crepe, carimbo, estante de cadernos e fotos coladas, e
movimento feito só com APIs nativas do navegador.

Nada muda para o aluno enquanto a chave `TEMA_PUBLICO` não for virada.

## O que muda para quem usa

**Para o aluno (depois da virada da chave):**

- **Home** mais curta: abertura com a trilha que se desenha e o carimbo
  "Certificado desbloqueado", estante de categorias, até 6 cursos em destaque,
  "o que você leva", ranking dos mais procurados e chamada final. Deixa de
  listar o catálogo completo.
- **Catálogo**: busca como linha de caderno, categorias como divisórias de
  fichário com contador, "Filtrar e ordenar" numa folha que sobe (sem JS os
  filtros aparecem inline e funcionam por GET, como hoje), grade de fotos
  coladas e paginação por números.
- **Curso**: capa colada grande, ficha de inscrição lateral no desktop e barra
  fixa com preço e "Desbloquear" no celular, conteúdo programático como trilha
  vertical de módulos, turmas como fichas pautadas.
- **Categorias, institucionais, validar certificado** (carimbo "VÁLIDO") e
  **página de erro** ("esta página foi arrancada do caderno").
- **Login, pós-login, cadastro, recuperar e redefinir senha** numa folha limpa
  centralizada; força da senha como marca-texto.
- **Checkout** (inscrição, participantes, resumo, pagamento, comprovante,
  comprovante enviado): checklist de etapas riscado à caneta, resumo como
  recibo serrilhado, PIX com instruções em post-it, comprovante "grampeado" e
  carimbo "Comprovante em análise".
- **Campo de cupom no resumo do pedido.** A V2 só exibia cupom já aplicado; o
  tema passa a oferecer o campo (rota nova `POST /v2/checkout/cupom`, que
  reaproveita `PedidoService::aplicarCupomAoPedido()`). Cupom recusado mostra
  **o motivo** dado pelo backend, não uma mensagem genérica.
- Fontes e ícones do próprio servidor: as páginas do tema **não fazem nenhuma
  requisição** a Google Fonts nem ao CDN do Tabler.

**Fase 2 (entregue, mudança `tema-caderno-aluno`):** área do aluno (cursos,
pedidos, certificados, perfil), minha conta, aula, atividade e quiz também
passam para o tema quando a chave está em `caderno`. Ver "Fase 2: páginas do
aluno" abaixo. Com a chave em `v2` nada muda.

**Continuam sempre na V2:** admin, área do professor e do revisor.

**Para o administrador:** quem tem `conteudo.gerenciar` pode ver o tema em
produção antes da virada, na própria sessão, com `?tema=caderno` (ver
"Como ativar a prévia"). Uma faixa "Prévia do tema caderno — sair" fica no
topo enquanto a prévia estiver ativa.

**Para todos (já no deploy, com a chave em `v2`):** o botão da Norminha sem
avatar deixou de ser um disco branco vazio e mostra o "N" de reserva (correção
pedida pelo usuário durante a entrega, vale também para a V2).

## Decisões

**Chave + prévia por sessão, e não troca direta.** `TEMA_PUBLICO=caderno` é o
único valor que ativa o tema, comparado sem diferenciar maiúsculas e sem os
espaços das pontas (`Caderno` ou ` caderno ` também valem); qualquer outro
valor, ou a ausência da chave, mantém a V2. A prévia só é gravada na sessão de quem tem `conteudo.gerenciar`;
para os demais o parâmetro `?tema` é ignorado e uma prévia antiga é removida.
Página do escopo sem view no tema cai na V2, sem erro.

**Mesmas variáveis da V2.** Cada view do tema recebe exatamente o que a view V2
correspondente recebe; nos controllers mudou só a linha de seleção de view
(`TemaPublico::view('x')`). Duas exceções aditivas, aceitas na revisão:

- **R8** — `CatalogoController` inclui a chave `total` em cada chip de
  categoria (contador das divisórias). A contagem por categoria não pode ser
  calculada na view quando há paginação. A V2 ignora a chave.
- **R9** — `CategoriasController` inclui a chave `descricao` (já existia no
  banco e era descartada), usada na lista acessível de categorias.

**Contraste AA acima do protótipo.** Duas mudanças visuais em relação ao
protótipo aprovado, por exigência de WCAG AA na spec:

- **R6** — o botão de compra laranja mantém o fundo `#FF6A00`, mas o texto
  passou de branco (2,9:1, reprova) para violeta `#22104A` (5,9:1).
- **R7** — o carimbo usa `#B84A00` (laranja-texto) em borda e texto, com
  opacidade 1, e é `aria-hidden`: é ornamento da cena, e a informação que ele
  carrega aparece também em texto normal onde importa.
- **N2** (pedido do usuário) — o anel de foco global passou de laranja
  (2,8:1 sobre o papel) para a tinta `#1F3FA8`, 3 px com recuo de 3 px
  (7,25:1 a 8,99:1 nos fundos do tema; na faixa violeta da prévia o anel é
  amarelo post-it).
- **Onda final** — o número de cursos das lombadas (10,5 px) perdeu a
  opacidade de 88 %: branco sobre as sete cores de lombada fica entre 4,66:1
  (`#A8641A`) e 16,9:1, antes 4,0:1 na pior. O placeholder dos campos passou
  de `--apagado` (2,8:1 sobre o papel) para `--texto2` (7,2:1).

**Cupom só onde o backend aceita (R10).** O campo aparece só enquanto o pedido
aceita cupom: some com comprovante em análise, com pedido pago ou aprovado e
também com pedido cancelado, expirado ou reembolsado (a mesma lista fixa de
`CupomService::pedidoStatusBloqueadoParaCupom`, em que o backend sempre
recusa). **Corrigido nesta onda**, junto da acentuação da mensagem de
`CupomService` ("Cupom privado sem relações configuradas.").

**Movimento nativo, sem biblioteca.** Web Animations API, IntersectionObserver
e `@view-transition { navigation: auto; }` (a foto do catálogo "vira" a capa do
curso). GSAP foi descartado: as cenas são sequências simples e o peso não se
paga no 4G pré-pago.

**Nada fica escondido.** Estados ocultos de animação só existem sob a classe
`html.anima`, posta por um script inline mínimo no `<head>`. Uma trava tira
`.anima` em 2,5 s se `caderno.js` não assumir (`window.CADERNO_TRAVA`, R2).
A trilha da home, quando já está ao menos em parte na tela, começa sozinha no
mesmo prazo de 2,5 s, contado do início da resposta como a trava (R11); se
`caderno.js` chegar depois da trava, a home não esconde nem anima nada.
Movimento reduzido: nada anima e não há view transitions. Modo leve
(`saveData` ou `hardwareConcurrency <= 2`): só a cena principal, versão curta,
sem revelações ao rolar.

**PHP 7.1 no código de aplicação (R1).** Durante a implementação a versão de
PHP da produção não estava documentada; o código novo evita `fn`, `match`,
`?->`, `str_contains` e propriedades tipadas, como o restante do projeto.
Depois da entrega o responsável informou que a produção roda **PHP 8.2 ou
8.4**: o código do tema é compatível com as duas (nenhum arquivo PHP desta
mudança emite aviso no 8.4). Os parâmetros com default `null` sem tipo
nullable do código anterior (que o `ErrorHandler` transformaria em erro 500 no
8.4) foram corrigidos na mudança `compat-php84`: as 28 ocorrências reais, e o
lint 8.4 de todos os arquivos versionados dá zero `Deprecated`. Com isso, PHP
8.2, 8.3 e 8.4 podem ser usados; em código novo, use `?Tipo $x = null`.

## Arquitetura

| Parte | Onde | O que faz |
|---|---|---|
| Seletor | `app/Support/TemaPublico.php` | `ativo()`, `view($nome)` (cai na V2 sem view no tema), `aplicarPrevia()`, `emPrevia()` |
| Chave | `config/app.php` → `tema_publico`; `.env.example` | `TEMA_PUBLICO=v2` por padrão |
| Prévia | `index.php` (requisição GET com `?tema=`, logo depois do `ErrorHandler::register` e antes do roteamento) | grava ou remove `tema_previa` na sessão, só com `conteudo.gerenciar` |
| Controllers | `app/Controllers/V2/{Home,Catalogo,Categorias,Curso,Institucional,CertificadoValidacao,Login,Cadastro,RecuperarSenha}Controller.php`, `app/Support/V2ErrorPage.php`, `CheckoutController::renderCheckout()` | uma linha de seleção de view cada; `CheckoutController::aplicarCupomV2()` novo |
| Rotas | `routes/web.php` | `POST /v2/checkout/cupom` (`auth.v2`, CSRF) |
| Views | `resources/views/caderno/` | `layout.php` e `auth-layout.php`; shims com o mesmo nome da V2; conteúdo em `pages/`; 21 partials (topo, rodapé, barra inferior, foto colada, lombada, trilhas, carimbo, post-it, recibo, checklist, sprite de ícones…) |
| CSS | `assets/caderno/caderno.css` | tokens, base, componentes e páginas num arquivo só |
| JS | `assets/caderno/caderno.js` | núcleo de movimento + módulos por página (`Caderno.pagina('nome', init)` só roda quando `<body data-pagina>` corresponde) |
| Fontes | `assets/caderno/fontes/` | Instrument Serif (títulos), Geist variável (texto), Kalam (anotações) — woff2, SIL OFL (textos das licenças em `fontes/OFL-*.txt`) |
| Ícones | `resources/views/caderno/partials/icones.php` | sprite SVG inline de traço de caneta, substitui o Tabler |
| Mensagens | `app/Services/PedidoService.php`, `app/Services/CupomService.php` | acentuação das mensagens do caminho de aplicação de cupom (o texto muda, a regra não) |
| Norminha | `resources/views/components/tutor_norminha.php`, `assets/js/tutor-norminha.js` | "N" de reserva no botão sem avatar (N1) |
| Testes | `tests/Unit/tema_publico.php` (7), `tests/Unit/cupom_checkout.php` (7), `tests/Smoke/rotas.php` + `smoke.php` | seletor, prévia, cupom; smoke nos dois valores da chave |
| Dados locais | `tests/Fixtures/tema_caderno_vitrine.sql` | categorias, cursos, turmas, módulos, professores e pedidos de exemplo — **somente local** |

Cache busting por `filemtime` (`?v=`), como no layout V2. `assets/caderno/` é
público como o resto de `assets/`; nenhuma regra nova no `.htaccess`.

## Fase 2: páginas do aluno

Mudança OpenSpec `openspec/changes/tema-caderno-aluno/` (branch
`feat/caderno-fase2`). Mesma regra da fase 1: cada view do tema recebe as
mesmas variáveis da V2, e nos controllers (`AlunoController`,
`ContaController`, `AulaController`, `AtividadeController`, `QuizController`)
mudou só a seleção de view (`TemaPublico::view('x')`). Nenhum service, model,
rota ou migration foi alterado.

### Arquitetura das páginas do aluno

| Parte | Onde | O que faz |
|---|---|---|
| Views | `resources/views/caderno/{aluno,conta,aula,atividade,quiz}.php` (shims) e `.../pages/*.php` | shim com `$contentView`, `$paginaTema` e as flags abaixo; conteúdo em `pages/` |
| Parciais | `resources/views/caderno/partials/` | `aula-sumario`, `aula-conteudo`, `aula-tabelas`, `aula-barra`, `quiz-questao`, `quiz-relogio`, `quiz-prova-nav`, `quiz-revisao`, `quiz-barra` |
| Flag `$cadernoAluno` | shim da página | `layout.php`/`head.php` carregam `caderno-aluno.css` e `caderno-aluno.js` (depois de `caderno.js`, ambos com `defer` e `?v=filemtime`). Home, catálogo e as demais páginas do tema **não** referenciam esses arquivos |
| Flag `$cadernoEstudo` | aula, atividade, quiz | `<body data-estudo>`, sem a barra inferior geral (`bnav`); no celular entra a `barra-estudo` fixa (anterior · posição · próxima, ou as ações da atividade/quiz) |
| CSS | `assets/caderno/caderno-aluno.css` | somente as páginas do aluno; usa os tokens de `caderno.css` |
| JS | `assets/caderno/caderno-aluno.js` | módulos `aluno`, `conta`, `aula`, `atividade` e `quiz`, com `Caderno.pagina('nome', init[, sempre])`; o que é função (envio protegido, rascunho do quiz, cronômetro, cidades do IBGE) roda também com movimento reduzido |
| Fixture | `tests/Fixtures/tema_caderno_aluno.sql` | **somente local**: aluna 9001 com cursos, pedidos, aula de todos os tipos, 9 quizzes (inclusive prova com cronômetro, tentativa em andamento e um quiz de uma pergunta só) e 8 estados de atividade. Login `aluno.caderno@teste.local` / `Local@12345` (também por CPF `987.654.321-00`). Idempotente (ids 9001–9999): reaplicar zera os estados. O cabeçalho do arquivo tem o mapa estado → URL |
| Testes | `tests/Unit/tema_publico.php` (8), `tests/Smoke/rotas.php` | seletor com fallback para a V2; smoke com as duas folhas nas páginas do aluno e `SMOKE_AULA_URL`, `SMOKE_QUIZ_URL`, `SMOKE_ATIVIDADE_URL` opcionais (ver `tests/Smoke/README.md`) |

Os ids, nomes de campo, rotas de POST e contratos JSON da V2 foram
preservados (`v2-quiz-answer-form`, `/aluno/cursos/quiz/rascunho`, `/tempo`,
`/revisao` etc.), para o tema poder ser desligado sem tocar no backend.

### Desvios deliberados em relação à V2

Todos para mais segurança ou acessibilidade, sem regra de negócio nova:

- **Sem `alert()`/`confirm()`.** Avisos viram texto na página e a confirmação
  de envio com perguntas em branco é um painel inline com os botões "Enviar
  mesmo assim" e "Revisar".
- **Enter num botão de alternativa (radio) não envia o quiz.** No quiz curto,
  fora da última pergunta, vale como "Próxima"; na prova, abre a revisão. A V2
  enviava o formulário inteiro pelo envio implícito do navegador.
- **Salvamento automático com tratamento de falha permanente.** Falhas
  temporárias (rede, 5xx) tentam de novo em 3 s, 6 s, 12 s… até 30 s. Falhas
  permanentes (sessão expirada, CSRF/página desatualizada, tentativa já enviada)
  param de tentar, mostram o motivo e oferecem "Tentar salvar de novo", que
  renova o token pela própria página. A V2 só tentava na próxima alteração.
- **Cidade com plano B.** Se a API do IBGE falha ou demora mais de 8 s, o
  select de cidade vira campo de texto com a cidade atual e um aviso; sem JS a
  cidade atual já vem como opção selecionada (a V2 a perdia).
- **Abas da área do aluno com rótulo curto abaixo de 420 px:** "Cursos" em vez
  de "Meus cursos" (o nome acessível continua completo).
- **Cronômetro da prova** conta contra o relógio do aparelho (aba em segundo
  plano não atrasa), usa `role="timer"` sem `aria-live` (a V2 anunciava a cada
  segundo) e tem trava contra laço de recarga na expiração.
- Percentuais em PT-BR ("66,7%" em vez de "66.7%") e plurais corretos
  ("1 pergunta", "3 perguntas").

## Verificação

Ambiente: Docker local (PHP 8.3 com `php -S`, MariaDB 10.5), fixture aplicada,
`TEMA_PUBLICO=caderno`, Chrome 151 headless, Lighthouse 12.8.2.

### Orçamento por página

Medido com `gzip -c` (nível padrão) sobre os arquivos servidos, e no HTML real
de cada página do escopo. Valores reconferidos depois da onda final de
correções (na Tarefa 13 eram 63.330 → 15.032 B e 31.890 → 9.721 B).

| Recurso | Bruto | gzip | Orçamento | Uso |
|---|---|---|---|---|
| `caderno.css` | 63.317 B | **15.023 B** | 25.600 B | 59 % |
| `caderno.js` + script inline do `<head>` | 32.641 B | **10.008 + ~180 B** | 15.360 B | 66 % |

As 21 páginas do escopo carregam o mesmo `caderno.css` e o mesmo `caderno.js`
(não há CSS ou JS por página), então o orçamento vale igual para todas:
home, catálogo, categorias, curso, quatro institucionais, validar certificado,
erro 404, login, cadastro, recuperar senha, redefinir senha, pós-login e as
seis etapas do checkout. Participantes e comprovante enviado só abrem em
estados específicos do pedido; usam o mesmo layout e os mesmos arquivos.

Fora do tema, mas na mesma página: a Norminha (componente compartilhado com a
V2) soma 6.665 B gzip de JS e 4.368 B de CSS em todas as páginas públicas,
exceto o checkout; `conteudo-html-embed` soma 362 B de JS e 274 B de CSS
(`gzip -c` de `assets/js/tutor-norminha.js`, `assets/css/tutor-norminha.css`,
`assets/js/conteudo-html-embed.js` e `assets/css/conteudo-html-embed.css`,
conferido na onda final).

### Lighthouse mobile (Moto G Power, 4G lenta simulada)

Duas medições: o servidor local cru (`php -S`, sem compressão, TTFB ~500 ms) e
o mesmo servidor atrás de um proxy local que comprime com gzip, como faria um
Apache com `mod_deflate`.

| Página | LCP (cru) | LCP (gzip) | CLS | Acessibilidade | Desempenho (cru / gzip) |
|---|---|---|---|---|---|
| Home `/` | 3,1 s | **2,0 s** (1,8 s na repetição) | 0,01 | 100 | 83–91 / 99–100 |
| Catálogo `/v2/catalogo` | 2,7 s | **2,2 s** | 0 | 98 | 95 / 98 |
| Curso `/v2/curso/?curso_id=15` | 2,8 s | **2,0 s** | 0 | 100 | 94 / 99 |
| Resumo `/v2/checkout/resumo?pedido_id=12` (logado) | 2,1 s | **1,8 s** | 0 | 100 | 98 / 93 |

- **Com compressão**, as quatro páginas cumprem LCP ≤ 2,5 s, CLS ≤ 0,1 e
  acessibilidade ≥ 95. **Sem compressão**, home, catálogo e curso passam do
  LCP: são cerca de 122 KiB de texto a mais na 4G lenta (auditoria
  `uses-text-compression` do Lighthouse), dos quais cerca de 47 KiB no CSS
  que bloqueia a renderização (63.330 B brutos − 15.032 B com gzip, tabela
  acima na medição da Tarefa 13).
- **A produção hoje não comprime nem define cache para os arquivos estáticos**
  (conferido em 06/10/2026: `curl -H "Accept-Encoding: gzip"` em
  `/v2/assets/css/v2-main.css` volta sem `Content-Encoding` e sem
  `Cache-Control`). O TTFB da produção é menor (~0,12 s contra ~0,5 s local),
  o que tira cerca de 0,4 s do LCP cru, mas não basta para a home. Ver
  "Antes de virar a chave".
- **Capas.** A capa do curso é o elemento LCP da página do curso. Na fixture
  as capas têm 13–15 KB; **em produção as capas atuais têm 1,7–2,0 MB cada**
  (PNG). Com elas, o LCP da página do curso em 4G lenta fica muito acima de
  2,5 s em qualquer tema. Para comparação, a home V2 em produção hoje mede
  LCP 41,5 s, desempenho 65 e acessibilidade 92 no mesmo Lighthouse. No tema,
  as capas fora da página do curso têm `loading="lazy"`
  (`resources/views/caderno/partials/foto-curso.php`, usado na home e no
  catálogo); a capa da página do curso, que é o LCP, usa
  `fetchpriority="high"`.
- Catálogo 98 e não 100 por `heading-order`: os títulos dos cards são `h3`
  sem um `h2` antes na página.

### Requisições de terceiros

Nas dez execuções locais do Lighthouse, todas as requisições foram para o
próprio servidor: **zero** para `fonts.googleapis.com`, `fonts.gstatic.com` ou
`@tabler/icons`. O HTML das 19 páginas que renderizaram na medição também não
referencia esses domínios.

### Acessibilidade

Feito com Chrome headless via DevTools Protocol, Firefox 156 e WebKit 26.6.
**NVDA e TalkBack não estavam disponíveis**; a parte de leitor de tela foi
conferida pela árvore de acessibilidade do Chrome
(`Accessibility.getFullAXTree`), que é o que esses leitores consomem.

- **Teclado, de ponta a ponta** (celular 412 px): "Pular para o conteúdo" é o
  primeiro Tab e leva o foco ao `<main>`; home → "Escolher meu curso" →
  catálogo → foto do curso → "Desbloquear" → login (com `?redirect=`) →
  senha errada → senha certa → inscrição do checkout, tudo só com Tab e Enter.
- **Foco visível:** percorridas todas as paradas de foco de home (40),
  catálogo (39), categorias (32), curso (23), login (20), cadastro (29) e
  validar certificado (22) a 1280 px, e home, catálogo, curso e login a 412 px.
  Todo controle muda de aparência ao receber foco (anel de tinta de 3 px; nas
  divisórias, anel post-it; nos campos, sublinhado de tinta) e nenhum fica fora
  da tela ou coberto pelo topo fixo ou pela barra inferior — com a Norminha
  minimizada (ver limitações).
- **Estante:** lista de links, cada um com nome completo ("Prova Nacional
  Docente 5 cursos").
- **Divisórias:** `nav` "Categorias" com links "Prova Nacional Docente, 5
  cursos"; a ativa com `aria-current`.
- **Folha de filtros:** `dialog` modal "Filtrar e ordenar"; grupos nomeados
  (Modalidade, Faixa de preço, Carga horária, Ordenar por, Destaques) com
  rádios e caixa de seleção nomeados; o foco entra na folha, 25 Tabs seguidos
  não saem dela, Esc fecha e devolve o foco ao botão, que volta a
  `aria-expanded="false"`.
- **Erros de campo:** no cadastro, os seis campos com erro recebem
  `aria-invalid="true"` e `aria-describedby` apontando para a mensagem; no
  login, o campo "E-mail ou CPF" é anunciado com a mensagem de erro; no cupom,
  `aria-invalid` + mensagem "Cupom não encontrado." associada.
- **Movimento reduzido** (emulado no Chrome, no WebKit e por preferência no
  Firefox): `html.anima` nunca entra, zero animações em execução em 11 páginas
  do escopo, nenhum conteúdo oculto na tela nem ao rolar; a regra
  `@view-transition` fica desativada.
- **JS bloqueado:** nas 11 páginas, nenhum conteúdo oculto, inclusive ao rolar
  até o fim; filtros do catálogo inline, busca, filtro de preço e divisória
  funcionando por GET; login, resumo com cupom, pagamento e envio de
  comprovante com os formulários do servidor; o botão "Copiar chave" do PIX,
  que depende de JS, fica oculto.
- **`caderno.js` falhando** (arquivo bloqueado): em 0,8 s o conteúdo animável
  ainda está oculto; em 3,2 s a trava já tirou `.anima` e nada fica oculto.

### Navegadores

| Navegador | Como | Resultado |
|---|---|---|
| Chrome desktop | Chrome headless 1280×800 via DevTools Protocol | teclado, foco, AX tree, sem JS, movimento reduzido: ok |
| Chrome Android | Lighthouse (Moto G Power, 4G lenta simulada) e emulação 412×915 | ver tabela do Lighthouse; layout sem rolagem horizontal |
| Firefox 156 | Firefox instalado, headless, via WebDriver BiDi | 9 páginas sem erro de console, fontes do tema carregadas, nada oculto, folha de filtros e Esc ok, catálogo → curso ok, movimento reduzido ok |
| Safari / WebKit | WebKit 26.6 do Playwright no Windows, perfil iPhone 13 | 9 páginas sem erro, sem rolagem horizontal, topo fixo continua fixo ao rolar, folha de filtros ok, movimento reduzido ok |
| Safari < 16 / iOS real | — | **não testado** |

Sem suporte a view transitions entre documentos, a navegação é comum e nada
quebra (Firefox e WebKit acima).

**Modo leve** (`hardwareConcurrency = 2` injetado antes do carregamento):
`Caderno.leve = true`; a home faz a cena curta da trilha (12 animações na
abertura, contra 13 no modo normal, com duração de 1,4 s contra 2,4 s) e
**nenhuma** animação nova ao rolar a página até o fim (no modo normal, 11);
categorias e curso não animam.

### Páginas do aluno (fase 2): orçamento e Lighthouse

Ambiente igual ao da fase 1 (Docker local, `TEMA_PUBLICO=caderno`, Chrome 151,
Lighthouse 12.8.2, perfil celular: Moto G Power, CPU 4× mais lenta, 4G lenta
simulada), **autenticado** como a aluna da fixture (cookie de sessão em
`--extra-headers`), cru e atrás de um proxy local que comprime com gzip (mesmo
método da fase 1). 07/10/2026.

Orçamento (`gzip -9`), valendo para as cinco páginas do aluno:

| Recurso | Bruto | gzip | Orçamento | Uso |
|---|---|---|---|---|
| `caderno.css` + `caderno-aluno.css` | 64.116 + 29.816 B | 14.951 + 7.968 = **22.919 B** | 25.600 B | 90 % |
| `caderno.js` + `caderno-aluno.js` | 32.641 + 44.960 B | 9.982 + 13.691 = **23.673 B** | 25.600 B | 92 % |

`caderno-aluno.*` só é requisitado nas páginas do aluno (o smoke confere que
home e catálogo não o referenciam). Sem terceiros: 0 requisições externas nas
oito medições.

| Página | LCP (cru) | LCP (gzip) | CLS | Acessibilidade | Desempenho (cru / gzip) |
|---|---|---|---|---|---|
| `/v2/aluno` | 3,3 s | **2,2 s** | 0,00 | 100 | 89 / 95 |
| Aula (texto, item 9002) | 3,2 s | **2,0–2,4 s** | 0,00 | 98 | 89 / 95 |
| Quiz em andamento, curto (9016) | 2,9–3,0 s | **2,0–2,1 s** | 0,00 | 100 | 93 / 96–99 |
| Prova em andamento, com cronômetro (9017) | 3,1–3,3 s | **1,95–2,2 s** | 0,00 | 100 | 68–89 / 98–99 |

- Como na fase 1, **sem compressão** o LCP passa de 2,5 s (o local tem TTFB de
  ~0,5 s e a 4G lenta simulada pesa os ~170 KB de CSS/JS descomprimidos); com
  `mod_deflate` (pendência 3) todas ficam dentro do orçamento. A aula tem
  margem pequena: variou de 2,0 a 2,4 s nas repetições.
- Acessibilidade 98 na aula por `heading-order`: o conteúdo de texto da
  fixture começa com um `<h3>` logo depois do `<h1>`. É conteúdo do editor
  (dados), não do tema.
- **CLS do quiz corrigido nesta etapa.** A primeira medição deu 0,43 na prova e
  0,14 no quiz curto: o script de `caderno-aluno.js` mostra a navegação e
  esconde as perguntas que não são a atual depois da primeira pintura. Agora
  (a) o progresso e a navegação reservam o espaço enquanto o módulo não
  inicia, (b) as perguntas ficam invisíveis até o módulo escolher a atual (com
  trava em CSS que revela tudo em 4 s se o script não vier, inclusive com
  movimento reduzido), (c) a margem da primeira pergunta fica dentro do
  contêiner (`display: flow-root`) e (d) a fonte manuscrita (Kalam) é
  pré-carregada só na página do quiz, porque a troca de fonte tardia refluía
  as linhas de progresso. Resultado: CLS no máximo 0,001 nas duas últimas rodadas de medição (oito execuções).

### Acessibilidade das páginas do aluno

Chrome headless via DevTools Protocol, perfil temporário, sessão da fixture.
NVDA e TalkBack não estavam disponíveis; leitor de tela conferido pela árvore
de acessibilidade e pelas regiões vivas.

- **360 px sem rolagem horizontal** (`scrollWidth = clientWidth = 360`) nas
  cinco páginas e em 15 estados: aluno (4 abas), minha conta, aula (texto,
  visão geral, HTML), atividade (enviar, em correção), quiz (antes, curto,
  prova, resultado do curto, resultado da prova).
- **Teclado.** Percorridas 70 paradas de Tab em cada um dos 15 estados a
  360 px: o foco é sempre visível (anel de 3 px em tinta; campos de texto com o
  anel do contêiner). Sumário da aula: Enter abre e fecha o módulo, e Tab entra
  nos itens. Abas da área do aluno: links com `role="tab"` (como a V2), todos
  alcançáveis por Tab. Quiz curto: o grupo de alternativas é uma parada só,
  seta para baixo troca a alternativa e salva, Enter vai para a próxima pergunta
  (foco no fieldset e anúncio "Pergunta 3 de 3"). Prova: ordem Índice →
  "Marcada para revisão" → alternativas → "Revisar e enviar" → barra inferior.
- **Defeito encontrado e corrigido: foco coberto pela barra fixa.** A 360 px,
  campos e links perto do fim da tela (textarea da atividade, alternativa,
  "Revisar e enviar", links do rodapé) ficavam **sob** a barra inferior
  (`bnav` ou `barra-estudo`) ao receberem foco por Tab. Corrigido com
  `scroll-padding-bottom` em `caderno-aluno.css` (celular); depois, 0 paradas
  cobertas nas seis páginas testadas, com uma exceção conhecida: o botão
  "Mostrar senha" de minha conta fica sob o botão flutuante da Norminha
  (componente compartilhado, ver "Limitações conhecidas").
- **Movimento reduzido** (emulado): `html.anima` nunca entra e há 0 animações
  em execução em aluno, aula, atividade, quiz (andamento e resultado) e minha
  conta; sem o emulado, só o carimbo do resultado do quiz anima (1 animação).
  Não há conteúdo oculto por animação (os 4 elementos com opacidade 0 da prova
  são os botões de rádio nativos, invisíveis por desenho sobre o rótulo).
- **Contraste dos estados** (WCAG AA: 4,5:1 texto, 3:1 não texto), calculado
  a partir dos tokens:

| Estado | Par | Razão |
|---|---|---|
| Concluído (módulo e passo feitos) | branco sobre tinta `#1F3FA8` | 8,99 |
| Concluído (✓ do sumário, não texto) | tinta sobre papel | 8,76 |
| Concluído / salvo / certa | verde `#1E7A55` sobre papel · sobre branco | 5,15 · 5,29 |
| Atual (item do sumário) | violeta sobre o marca-texto laranja | 12,25 |
| Atual (passo/índice) | tinta sobre branco | 8,99 |
| Erro (texto, "Incorreta", obrigatória, botão cancelar) | vermelho `#C2362B` sobre papel · sobre branco; branco sobre vermelho | 5,30 · 5,45; 5,45 |
| Tempo acabando | vermelho sobre `#FFF1EE` | 4,94 |
| Marcada para revisão (texto) | laranja-texto `#B84A00` sobre papel | 5,09 |
| Texto secundário | `#5A5078` sobre papel | 7,16 |

  Dois pares abaixo do limite foram corrigidos: o rótulo "Comentário" do
  gabarito (vermelho sobre o post-it, 4,48 → 4,90 com `#B83228`) e a bolinha
  do sumário (item não concluído; borda `--apagado`, 2,83:1 como não texto →
  `--texto2`, 7,16:1). O vermelho do relógio com tempo acabando também
  vem acompanhado de texto e aviso (`#quiz-tempo-aviso`), não só de cor.

### Testes

- Fase 2: `tests/Unit/tema_publico.php` 8 passou; `norminha_arquitetura` 24;
  `quiz_rascunho` "Autosave OK"; `otimizador_imagem` 25 verificações. Smoke:
  anônimo 42/42; `--modo=todos` com a aluna da fixture e as três variáveis
  `SMOKE_*_URL` 49/49; com `TEMA_PUBLICO=v2` 47/47. `php -l` no container
  (8.3) e no PHP 8.4 do host, sem `Deprecated`, em todos os 49 PHP alterados
  desde a base da fase 2; `node --check` em `caderno-aluno.js`.
- Smoke `tests/Smoke/smoke.php` (reexecutado na onda final): com
  `TEMA_PUBLICO=caderno`, **42/42** sem `SMOKE_CURSO_ID` e **43/43** com
  `SMOKE_CURSO_ID=15` (a ficha do curso da fixture); com `TEMA_PUBLICO=v2`,
  43/43 com `SMOKE_CURSO_ID=15`. A rota com curso real só entra quando a
  variável está definida, para não depender de um id fixo.
- Paridade de formulários, campos e links entre V2 e caderno em 18 páginas:
  sem diferença de `action`, `name` ou link; só a âncora da seção de turmas
  muda (`#v2-curso-turmas` → `#turmas`).
- `tests/Unit/tema_publico.php`: 7 passou. `tests/Unit/cupom_checkout.php`:
  7 passou.
- Textos: varredura do texto visível, `aria-label`, `placeholder`, `title` e
  `alt` de 20 telas do tema contra uma lista de palavras sem acento: nenhuma
  ocorrência na interface do tema. As que sobram vêm do backend (ver abaixo).

## Como ativar a prévia em produção

1. Subir os arquivos com `TEMA_PUBLICO=v2` no `.env` de produção (o padrão).
   Para o aluno nada muda.
2. Entrar com um usuário que tenha `conteudo.gerenciar`.
3. Abrir qualquer página do escopo com `?tema=caderno` — por exemplo
   `https://desbloqueiacursos.com.br/v2/?tema=caderno`. A prévia fica gravada
   na sessão; a faixa "Prévia do tema caderno — sair" aparece no topo.
4. Navegar normalmente; para sair, clicar em "sair" na faixa ou abrir qualquer
   página com `?tema=v2`. Encerrar a sessão também encerra a prévia.

Com `HOME_VERSION=v1`, a raiz `/` continua sendo a Home V1; a home do tema
aparece em `/v2/`.

## Antes de virar a chave

1. **Ligar compressão e cache dos estáticos no Apache** (`mod_deflate` para
   HTML, CSS, JS e SVG; `mod_expires`/`Cache-Control` longo para
   `assets/caderno/`, que já usa `?v=filemtime`). Sem isso, home, catálogo e
   curso ficam acima de 2,5 s de LCP na 4G lenta. Ver `docs/deploy.md`.
2. **Reduzir o peso das capas atuais** (1,7–2,0 MB por PNG). Não é trocar a
   arte, decisão que segue fora do escopo. **Implementado** (mudança
   `capas-otimizadas`): uploads novos já são otimizados e o script
   `scripts/otimizar_capas.php` trata as existentes — falta **rodá-lo na VPS**
   (simulação, conferir, `--aplicar`; ver `docs/deploy.md` §3.2). Sem isso a página do curso não cumpre o
   LCP, e a home e o catálogo gastam dezenas de MB de dados pré-pagos de quem
   rolar a página — problema que a V2 já tem hoje.
3. Validar pela prévia, com dados reais, cada página do escopo e uma compra de
   ponta a ponta (PIX e, se ligado, pagamento online), incluindo cupom válido
   e cupom recusado.

## Como virar a chave

No `.env` de produção:

```
TEMA_PUBLICO=caderno
```

Sem deploy de código. Só com aprovação do produto. Depois da virada, rodar
`SMOKE_CURSO_ID=<id de um curso publicado> php tests/Smoke/smoke.php https://desbloqueiacursos.com.br`
(sem a variável, a ficha de curso com curso real é pulada).

## Rollback

`TEMA_PUBLICO=v2` no `.env`. Volta na próxima requisição, sem deploy. As views
V2 continuam no repositório e não foram alteradas. Se for preciso remover o
código, reenviar as versões anteriores dos controllers, `index.php`,
`config/app.php`, `routes/web.php`, `PedidoService`, `CupomService` e
`V2ErrorPage`, e apagar `app/Support/TemaPublico.php`,
`resources/views/caderno/` e `assets/caderno/`.

## O que não subir

- `tests/Fixtures/tema_caderno_vitrine.sql` — **nunca aplicar em produção**:
  cria cursos, categorias, turmas, usuários e pedidos de exemplo.
- `assets/caderno/exemplos/` — capas de exemplo da fixture; não fazem falta em
  produção.

## Limitações conhecidas

- **Norminha aberta cobre metade da tela no primeiro acesso.** Sem a
  preferência "minimizada" salva, o painel abre por cima de cerca de 40 % da
  tela do celular (e do canto inferior direito no desktop), e o foco do teclado
  passa por links que ficam atrás dele. É o comportamento atual do componente,
  igual na V2; sem JS o painel também abre por cima e mostra `<br>` literal.
  O anel de foco do botão da Norminha é violeta a 28 % de opacidade, abaixo
  de 3:1.
- Títulos dos cards do catálogo em `h3` sem `h2` antes (Lighthouse
  `heading-order`, acessibilidade 98).
- Erros de login sem campo associado vão para o campo "E-mail ou CPF"; o campo
  de senha nunca recebe `aria-invalid`.
- No celular em pé, quem não rola vê o espaço da trilha da home em branco por
  até 2,5 s, contados do início da resposta, antes da cena começar sozinha; a
  cena pode começar com a trilha só em parte na tela (R11). Medido na onda
  final em 360×780 sem rolar: a cena começa entre 2,5 e 2,6 s depois do
  início da resposta (2,1–2,3 s depois do evento `load`).
- Qualquer 404 ou 403 sob `/v2/...` (inclusive `/v2/aluno/...`, que continua
  na V2) usa a página de erro do tema quando a chave está em `caderno`.
- Trocar de turma na página do curso não é anunciado por leitor de tela (sem
  região `aria-live`); só o texto do link clicado muda.
- "Pagar com PIX" no pagamento só leva à etapa do comprovante, onde ficam a
  chave PIX e o envio do comprovante.
- No modo leve, os números da home ainda contam (versão curta) e as animações
  do checkout não consultam o modo leve.
- Módulos do curso sempre expandidos: cursos longos ficam extensos.
- Imagens e ícones próprios das categorias da V2 não aparecem nas lombadas.
- Safari < 16: `.folha` usa `overflow: hidden` e, nessas versões, o topo fixo
  pode deixar de ser fixo; não foi possível testar.
- O checkout logado (participantes, pagamento) não tem cobertura automatizada.
- ~~"Mostrar senha" sob o botão da Norminha a 360 px e foco sob a barra
  inferior na vitrine~~ — corrigidos em 07/10/2026 (`caderno.css`): no
  celular, `scroll-padding-bottom` mantém o foco acima da bnav e do botão da
  Norminha em todas as páginas do tema; o botão some enquanto um campo de
  formulário (ou o "Mostrar senha") está em foco e volta ao sair; na
  autenticação, sem bnav, ele desce para o canto. Medido a 360 px (login,
  cadastro, minha conta, home, catálogo): nenhum alvo de foco coberto.
- Quiz em tela larga (>= 900 px): as instruções são abertas por JS; a abertura
  pode deslocar o conteúdo logo abaixo (CLS medido só no perfil celular).

## Achados anteriores ao tema

Encontrados durante a entrega, não causados por ela, e registrados para
correção própria:

1. ~~**Validador de CPF aceita dígitos verificadores errados.**~~ Diagnóstico
   corrigido em 07/10/2026: o cálculo estava certo; o teste falhava porque o
   `.env` local tem `ALLOW_TEST_CPFS=true`, que aceita CPFs de teste como
   `11111111111`. Agora a chave é ignorada com `APP_ENV=production` (proteção
   contra ligá-la por engano) e o teste fixa o ambiente; 17 passou, 0 falhou.
2. **Mensagens do `AuthService` sem acento**, iguais na V2. No login: "Dados
   de acesso invalidos.", "Usuario sem permissao de acesso."; no cadastro:
   "Informe um e-mail valido.", "Informe um CPF valido.", "A confirmacao da
   senha nao confere.", "O aceite dos termos de uso e obrigatorio.", "O aceite
   da politica de privacidade e obrigatorio.", "Este e-mail ja esta
   cadastrado.", "Este CPF ja esta cadastrado."; na redefinição de senha:
   "A confirmacao da senha nao confere.", "Token invalido ou expirado."
3. **Painel da Norminha sem JS** abre por cima do conteúdo e mostra `<br>`
   literal.
4. **Produção sem compressão e sem cache de estáticos**, e **capas de 2 MB**:
   afetam a V2 hoje tanto quanto o tema (ver "Antes de virar a chave").

## Pendências

Estado em 06/10/2026: mudança integrada em `frontend-v4`, enviada ao GitHub e
**ainda não publicada** na VPS (AlmaLinux + Virtualmin, PHP 8.2/8.3/8.4). As
mudanças OpenSpec `fila-revisao-admin` e `tema-caderno` já foram arquivadas
(`openspec/changes/archive/`), e seus requisitos estão em `openspec/specs/`
(`revisao-conteudo` e `tema-publico`). Em ordem:

1. **Versão de PHP do domínio no Virtualmin.** 8.2, 8.3 ou 8.4: a mudança
   `compat-php84` corrigiu as 28 ocorrências reais de `Tipo $x = null` sem `?`
   (lint 8.4 de todos os arquivos versionados: zero `Deprecated`). A ressalva do
   `ErrorHandler` (qualquer aviso vira erro 500) continua valendo para código
   novo: use `?Tipo $x = null` e rode `php -l` também no 8.4.
2. **Deploy na VPS com `TEMA_PUBLICO=v2`** (nada muda para o aluno) e validação
   pela prévia de administrador (`?tema=caderno`), página por página, com os
   dados reais — em especial o checkout com um pedido de teste e o cupom.
3. **Ligar compressão e cache no Apache do Virtualmin** conforme
   `docs/deploy.md` §3.1 e conferir `Content-Encoding: gzip` com `curl -I`.
4. **Capas dos cursos em tamanho de web** (hoje 1,7–2,0 MB cada): código
   pronto (upload otimiza sozinho); rodar `scripts/otimizar_capas.php` na VPS
   (`docs/deploy.md` §3.2).
5. **Virar a chave** (`TEMA_PUBLICO=caderno`) depois de 2–4 validados, com
   aprovação do produto; rollback é voltar para `v2`.
6. **Páginas do aluno no tema (fase 2): entregue**, mas só vale em produção
   depois dos itens 2–5: validar pela prévia, com um aluno real de teste, a
   área do aluno, uma aula, uma atividade e um quiz de ponta a ponta (inclusive
   prova com cronômetro).
7. **Próxima mudança: remover as views V2 do aluno** (`resources/views/v2/`
   de aluno, conta, aula, atividade e quiz) **depois da virada da chave** e do
   período de confiança; até lá elas são o rollback (`TEMA_PUBLICO=v2`).
8. ~~**Progresso da área do aluno divergente na fixture.**~~ Resolvido em
   07/10/2026: era dado de teste. O app grava `concluido` em
   `conteudo_progresso_aluno` ao corrigir/aprovar uma atividade e ao aprovar
   um quiz; a fixture não tinha essas linhas. Com elas, percentual e contagem
   batem (4 de 17 obrigatórios = 23,53 %).

Fora do tema, achados nesta entrega e ainda abertos: testes
`norminha_knowledge`, `norminha_tools` e dois casos de `quiz_system` que já
falhavam com a base local; mensagens sem acento no `AuthService` e em outros
services (as views do tema traduzem parte delas pelo texto exato, então a
correção precisa mexer nos dois lados). O painel da Norminha passou a começar
minimizado no celular (até 640 px) quando a pessoa ainda não escolheu; abrir
ou minimizar fica salvo (`norminha_tutor_minimized_v1`: `1` minimizada, `0`
aberta).
