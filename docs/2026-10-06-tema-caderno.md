# Tema caderno: o site público ganha identidade própria, atrás de uma chave

Data: 2026-10-06
Mudança OpenSpec: `openspec/changes/tema-caderno/` (proposta, spec, design e tarefas)
Branch: `feat/tema-caderno` (commits `646900d` a este relatório)
Migration: **nenhuma**
Estado: **implementado e validado em ambiente local**; aguardando deploy com
`TEMA_PUBLICO=v2` e validação pela prévia de administrador

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

**Continuam sempre na V2:** área do aluno, aula, quiz, atividade e minha conta.
Admin, área do professor e do revisor não mudam.

**Para o administrador:** quem tem `conteudo.gerenciar` pode ver o tema em
produção antes da virada, na própria sessão, com `?tema=caderno` (ver
"Como ativar a prévia"). Uma faixa "Prévia do tema caderno — sair" fica no
topo enquanto a prévia estiver ativa.

**Para todos (já no deploy, com a chave em `v2`):** o botão da Norminha sem
avatar deixou de ser um disco branco vazio e mostra o "N" de reserva (correção
pedida pelo usuário durante a entrega, vale também para a V2).

## Decisões

**Chave + prévia por sessão, e não troca direta.** `TEMA_PUBLICO=caderno` é o
único valor que ativa o tema; qualquer outro valor, ou a ausência da chave,
mantém a V2. A prévia só é gravada na sessão de quem tem `conteudo.gerenciar`;
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

**Cupom só onde o backend aceita (R10).** O campo aparece só enquanto o pedido
aceita cupom. A regra da view foi registrada para incluir também pedidos
cancelados, expirados e reembolsados (o backend sempre recusa nesses status);
esse ajuste está na onda de correções finais da branch, junto da acentuação
de `CupomService` ("Cupom privado sem relacoes configuradas.").

**Movimento nativo, sem biblioteca.** Web Animations API, IntersectionObserver
e `@view-transition { navigation: auto; }` (a foto do catálogo "vira" a capa do
curso). GSAP foi descartado: as cenas são sequências simples e o peso não se
paga no 4G pré-pago.

**Nada fica escondido.** Estados ocultos de animação só existem sob a classe
`html.anima`, posta por um script inline mínimo no `<head>`. Uma trava tira
`.anima` em 2,5 s se `caderno.js` não assumir (`window.CADERNO_TRAVA`, R2).
Movimento reduzido: nada anima e não há view transitions. Modo leve
(`saveData` ou `hardwareConcurrency <= 2`): só a cena principal, versão curta,
sem revelações ao rolar.

**PHP 7.1 no código de aplicação (R1).** A versão de PHP da produção não é
documentada; o código novo evita `fn`, `match`, `?->`, `str_contains` e
propriedades tipadas, como o restante do projeto.

## Arquitetura

| Parte | Onde | O que faz |
|---|---|---|
| Seletor | `app/Support/TemaPublico.php` | `ativo()`, `view($nome)` (cai na V2 sem view no tema), `aplicarPrevia()`, `emPrevia()` |
| Chave | `config/app.php` → `tema_publico`; `.env.example` | `TEMA_PUBLICO=v2` por padrão |
| Prévia | `index.php` (início da requisição GET com `?tema=`) | grava ou remove `tema_previa` na sessão, só com `conteudo.gerenciar` |
| Controllers | `app/Controllers/V2/{Home,Catalogo,Categorias,Curso,Institucional,CertificadoValidacao,Login,Cadastro,RecuperarSenha}Controller.php`, `app/Support/V2ErrorPage.php`, `CheckoutController::renderCheckout()` | uma linha de seleção de view cada; `CheckoutController::aplicarCupomV2()` novo |
| Rotas | `routes/web.php` | `POST /v2/checkout/cupom` (`auth.v2`, CSRF) |
| Views | `resources/views/caderno/` | `layout.php` e `auth-layout.php`; shims com o mesmo nome da V2; conteúdo em `pages/`; 21 partials (topo, rodapé, barra inferior, foto colada, lombada, trilhas, carimbo, post-it, recibo, checklist, sprite de ícones…) |
| CSS | `assets/caderno/caderno.css` | tokens, base, componentes e páginas num arquivo só |
| JS | `assets/caderno/caderno.js` | núcleo de movimento + módulos por página (`Caderno.pagina('nome', init)` só roda quando `<body data-pagina>` corresponde) |
| Fontes | `assets/caderno/fontes/` | Instrument Serif (títulos), Geist variável (texto), Kalam (anotações) — woff2, SIL OFL |
| Ícones | `resources/views/caderno/partials/icones.php` | sprite SVG inline de traço de caneta, substitui o Tabler |
| Mensagens | `app/Services/PedidoService.php`, `app/Services/CupomService.php` | acentuação das mensagens do caminho de aplicação de cupom (o texto muda, a regra não) |
| Norminha | `resources/views/components/tutor_norminha.php`, `assets/js/tutor-norminha.js` | "N" de reserva no botão sem avatar (N1) |
| Testes | `tests/Unit/tema_publico.php` (7), `tests/Unit/cupom_checkout.php` (7), `tests/Smoke/rotas.php` + `smoke.php` | seletor, prévia, cupom; smoke nos dois valores da chave |
| Dados locais | `tests/Fixtures/tema_caderno_vitrine.sql` | categorias, cursos, turmas, módulos, professores e pedidos de exemplo — **somente local** |

Cache busting por `filemtime` (`?v=`), como no layout V2. `assets/caderno/` é
público como o resto de `assets/`; nenhuma regra nova no `.htaccess`.

## Verificação

Ambiente: Docker local (PHP 8.3 com `php -S`, MariaDB 10.5), fixture aplicada,
`TEMA_PUBLICO=caderno`, Chrome 151 headless, Lighthouse 12.8.2.

### Orçamento por página

Medido com `gzip -c` (nível padrão) sobre os arquivos servidos, e no HTML real
de cada página do escopo.

| Recurso | Bruto | gzip | Orçamento | Uso |
|---|---|---|---|---|
| `caderno.css` | 63.330 B | **15.032 B** | 25.600 B | 59 % |
| `caderno.js` + script inline do `<head>` | 31.890 B | **9.721 + ~180 B** | 15.360 B | 64 % |

As 21 páginas do escopo carregam o mesmo `caderno.css` e o mesmo `caderno.js`
(não há CSS ou JS por página), então o orçamento vale igual para todas:
home, catálogo, categorias, curso, quatro institucionais, validar certificado,
erro 404, login, cadastro, recuperar senha, redefinir senha, pós-login e as
seis etapas do checkout. Participantes e comprovante enviado só abrem em
estados específicos do pedido; usam o mesmo layout e os mesmos arquivos.

Fora do tema, mas na mesma página: a Norminha (componente compartilhado com a
V2) soma 6.665 B gzip de JS e 4.368 B de CSS em todas as páginas públicas,
exceto o checkout; `conteudo-html-embed` soma 362 B de JS e 274 B de CSS.

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
  LCP: são cerca de 122 KiB de texto a mais na 4G lenta, dos quais cerca de
  47 KiB no CSS que bloqueia a renderização.
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
  LCP 41,5 s, 31,6 MB transferidos, desempenho 65 e acessibilidade 92 no mesmo
  Lighthouse. No tema, as capas fora da página do curso têm `loading="lazy"`.
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

### Testes

- Smoke `tests/Smoke/smoke.php`: **43/43** com `TEMA_PUBLICO=caderno`
  (reexecutado em 06/10/2026) e 43/43 com `TEMA_PUBLICO=v2` (Tarefa 12).
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
   arte, decisão que segue fora do escopo: é reexportar as mesmas imagens em
   640–1280 px e formato comprimido. Sem isso a página do curso não cumpre o
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
`php tests/Smoke/smoke.php https://desbloqueiacursos.com.br`.

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
  até 4,5 s antes da cena começar sozinha.
- No modo leve, os números da home ainda contam (versão curta) e as animações
  do checkout não consultam o modo leve.
- Módulos do curso sempre expandidos: cursos longos ficam extensos.
- Imagens e ícones próprios das categorias da V2 não aparecem nas lombadas.
- Safari < 16: `.folha` usa `overflow: hidden` e, nessas versões, o topo fixo
  pode deixar de ser fixo; não foi possível testar.
- `tests/Smoke/rotas.php` fixa `curso_id=15`, que só existe na fixture; contra
  a produção essa verificação cai no catálogo.
- O checkout logado (participantes, pagamento) não tem cobertura automatizada.

## Achados anteriores ao tema

Encontrados durante a entrega, não causados por ela, e registrados para
correção própria:

1. **Validador de CPF aceita dígitos verificadores errados.**
   `tests/Unit/checkout_rapido_fase1.php` "CPF invalido e recusado" já falhava
   antes do tema (15 passou, 1 falhou, reexecutado em 06/10/2026). Afeta o
   cadastro e o checkout nas duas versões.
2. **Mensagens do `AuthService` sem acento**, iguais na V2: "Dados de acesso
   invalidos.", "Informe um e-mail valido.", "Informe um CPF valido.", "A
   confirmacao da senha nao confere.", "O aceite dos termos de uso e
   obrigatorio."
3. **Painel da Norminha sem JS** abre por cima do conteúdo e mostra `<br>`
   literal.
4. **Produção sem compressão e sem cache de estáticos**, e **capas de 2 MB**:
   afetam a V2 hoje tanto quanto o tema (ver "Antes de virar a chave").
