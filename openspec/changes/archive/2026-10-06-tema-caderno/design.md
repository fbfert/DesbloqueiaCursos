# Design

Direção aprovada em sessão de brainstorming (05–06/10/2026), com protótipos navegáveis de home e catálogo aprovados em desktop e celular. Este documento consolida as decisões; a proposta tem o porquê e a spec `tema-publico` tem os requisitos.

## Context

- O site público é a V2: controllers em `app/Controllers/V2/` renderizam shims `resources/views/v2/<nome>.php`, que apontam `$contentView` para `resources/views/v2/pages/<nome>.php` e incluem `v2/layout.php` (ou `v2/auth-layout.php` nas telas de autenticação). O checkout renderiza `v2/checkout/<nome>` em `CheckoutController::renderCheckout()`, e a página de erro sai de `App\Support\V2ErrorPage`.
- Todo o site público carrega `v2/assets/css/v2-main.css` (103 KB) e `v2/assets/js/v2-main.js` (192 KB, com código legado do modo demonstração), Google Fonts (Inter, Sora) e Tabler Icons por CDN em `@latest`.
- Área do aluno, aula e quiz usam os mesmos arquivos — por isso o tema não pode ser uma repintura da V2.
- Público majoritariamente classe C/D, no celular (Android de entrada, dados pré-pagos).
- O ambiente local (Docker, PHP 8.3 + MariaDB 10.5) não tem cursos nem capas: só os dados de homologação das migrations.

## Goals / Non-Goals

**Goals:**
- Um tema visual próprio e autêntico ("trilha no caderno") para a vitrine e o funil de compra, com animações de alto padrão que rodam bem num Android de entrada.
- Ativação, prévia e reversão sem deploy de código.
- Zero mudança em regra de negócio, dados, rotas e banco.

**Non-Goals:**
- Fase seguinte (área do aluno, aula, quiz, atividade, minha conta).
- Remover a V2 das páginas migradas nesta mudança.
- Biblioteca de animação, build de front-end ou npm em produção.

## Decisions

### 1. Seletor de tema: `App\Support\TemaPublico`

```php
TemaPublico::view('curso');            // 'caderno/curso' ou 'v2/curso'
TemaPublico::view('checkout/resumo');  // idem para o checkout
TemaPublico::ativo();                  // 'caderno' | 'v2'
```

- `ativo()`: se a sessão tem prévia (`tema_previa`), usa ela; senão `config/app.php → tema_publico` (lido de `TEMA_PUBLICO`, qualquer valor diferente de `caderno` vira `v2`, no mesmo padrão de `home_version`).
- `view($nome)`: com tema `caderno` e `resources/views/caderno/<nome>.php` existente, devolve `caderno/<nome>`; senão `v2/<nome>`. Isso permite entregar página por página atrás da chave.
- **Prévia:** um middleware leve (ou chamada no início de `TemaPublico::ativo()`) lê `?tema=caderno|v2` e grava/remove `tema_previa` na sessão **somente** se o usuário logado tiver `conteudo.gerenciar` (via `RbacService::userHasPermission`). Para os demais, o parâmetro é ignorado.
- Nos controllers: troca de `View::render('v2/x', ...)` por `View::render(TemaPublico::view('x'), ...)`. Uma linha por ponto de renderização.

*Alternativa descartada:* interceptar dentro de `App\Core\View::render()` todo prefixo `v2/`. Seria invisível para quem lê o controller e afetaria páginas fora do escopo (aluno, aula, quiz) que também renderizam `v2/...`.

### 2. Estrutura de arquivos

```
resources/views/caderno/
  layout.php              casca: head, fontes, CSS/JS do tema, topo, rodapé, barra inferior, Norminha
  auth-layout.php         casca das telas de autenticação (folha centralizada)
  partials/               topo, rodape, bnav, foto-curso, lombada, trilha-hero, trilha-modulos,
                          carimbo, postit, checklist-checkout, recibo, icones (sprite SVG)
  <pagina>.php            shims com o mesmo nome dos da V2 (home, catalogo, curso, ...)
  pages/<pagina>.php      conteúdo
  checkout/<etapa>.php
assets/caderno/
  caderno.css             tokens, base, componentes, páginas (um arquivo, ≤ 25 KB gz)
  caderno.js              núcleo de movimento + módulos por página (≤ 15 KB gz)
  fontes/                 InstrumentSerif-Regular/Italic, Geist (variável 400–700), Kalam-Regular — woff2, subconjunto latin
```

- Os shims do tema recebem exatamente as variáveis que os shims V2 recebem; helpers de apresentação que hoje vivem dentro das views V2 são reaproveitados por `require` quando forem puros, ou copiados para `partials/` quando misturarem marcação V2.
- Cache busting por `filemtime`, no padrão de `v2/layout.php`.
- `assets/caderno/` é público por design (como `assets/` hoje); nenhuma regra nova no `.htaccess`.

### 3. Sistema visual

Tokens (CSS custom properties):

| Token | Valor | Papel fixo |
|---|---|---|
| `--papel` | #FCFCFA | fundo, com pauta a cada 32 px e margem vermelha |
| `--violeta` | #22104A | texto e ação principal |
| `--tinta` | #1F3FA8 | caneta: orientação, trilha, links, anotações |
| `--laranja` | #FF6A00 | conquista: carimbo, desconto, o único botão de compra da tela; nunca em texto pequeno |
| `--laranja-texto` | #B84A00 | laranja quando precisa ser texto pequeno |
| `--postit` | #FFE98A | avisos |
| `--papelao` | #B98F5E | estante e rodapé |
| `--fita` | rgba(236,224,190,.94) | fita crepe |

- **Papel:** pauta e margem desenhadas com `linear-gradient` (custo zero). Furos de fichário só a partir de 900 px.
- **Tipografia:** Instrument Serif só em títulos; Geist em todo texto de leitura e interface; Kalam em no máximo uma ou duas anotações por tela, nunca com informação essencial.
- **Componentes:** botão principal (afunda ao toque), botão de compra laranja, botão secundário contornado à caneta (SVG), link sublinhado à caneta; campos "escrever na linha" (sem caixa, rótulo sempre visível, erro em texto e cor); post-it; fita crepe; selo; carimbo (circular e retangular); marca-texto; checklist de etapas; foto colada (card de curso com rotação leve e fita); lombada (categoria); divisória de fichário (filtro de categoria); recibo serrilhado (resumo do pedido); cupom recortado; rodapé de papelão.
- **Ícones:** sprite SVG inline com cerca de 20 ícones de traço de caneta, em `partials/icones.php`, substituindo Tabler.
- **Capas:** permanecem as atuais, exibidas como foto colada.

### 4. Sistema de movimento

**Princípio:** um momento orquestrado por página; o resto discreto e consistente.

| Página | Cena |
|---|---|
| Home | trilha à caneta se desenha → ✓ rabiscado em cada etapa → carimbo "Certificado desbloqueado" cai com leve tremor da folha; números contam |
| Categorias / home | estante: lombadas sobem da prateleira; hover/foco puxa a lombada |
| Curso | conteúdo programático como trilha vertical de módulos que se desenha conforme a rolagem; no celular, barra fixa com preço e "Desbloquear" |
| Checkout | etapa concluída é riscada à caneta; no envio do comprovante, carimbo "Comprovante em análise" |
| Validar certificado | carimbo "VÁLIDO" (verde) ou aviso de não encontrado |

**Técnica:**
- Web Animations API (`element.animate`) para cenas; `IntersectionObserver` para revelações (uma vez só, 12–16 px, ~500 ms); traçado por `stroke-dashoffset`; contadores por `requestAnimationFrame`; `CSS.registerProperty` para `--mt` (marca-texto e barras).
- O tremor de mão dos traços vem **embutido no desenho** (paths desenhados com irregularidade), não de filtro SVG — filtro sob animação re-rasteriza a cada quadro e pesa no celular.
- **Transição entre páginas:** `@view-transition { navigation: auto; }` no CSS do tema; card do catálogo/home e capa da página do curso compartilham `view-transition-name: capa-<id>`, e o título `titulo-<id>`. Sem suporte, navegação comum.
- **Gate de movimento:** script inline mínimo no `<head>` adiciona `html.anima` só quando não há movimento reduzido; estados iniciais ocultos existem apenas sob `.anima`; trava de segurança remove `.anima` em 2,5 s; `caderno.js` carrega com `defer`.
- **Modo leve:** `navigator.connection.saveData` ou `hardwareConcurrency <= 2` → só cenas principais, versão curta, sem revelações ao rolar.
- **Movimento reduzido:** nada anima; tudo no estado final; sem view transitions.
- Na home, a cena da trilha só começa quando a trilha está inteira na tela (no celular ela fica abaixo da dobra).

*Alternativa descartada:* GSAP (core ~27 KB gz). As cenas aprovadas são sequências simples que a Web Animations API cobre; o ganho de ergonomia não paga o peso no 4G pré-pago. Reavaliar se uma cena futura exigir timeline complexa com scrub.

### 5. Páginas

- **Home:** abertura (título com marca-texto, "Escolher meu curso", números, trilha + carimbo) → estante de categorias → até 6 em destaque (dados de `featuredCourses`) → "o que você leva" (ao vivo/sob demanda, simulado oficial, certificado com validação pública) → mais procurados como ranking com barras de marca-texto proporcionais a alunos (`topCourses`) → chamada final → rodapé.
- **Catálogo:** busca como linha de caderno; categorias como divisórias de fichário (borda direita da folha ≥ 1100 px; abas roláveis no celular); "Filtrar e ordenar" em folha que sobe; grade de fotos coladas; paginação como números de página. Os filtros continuam sendo o `GET /v2/catalogo` existente (funciona sem JS); o JS só melhora (filtragem instantânea quando todos os cursos já estão na página, animação FLIP).
- **Curso:** capa colada grande + título; ficha de inscrição (preço, turma, "Desbloquear") lateral fixa no desktop e barra fixa no celular; seções atuais com títulos sublinhados à caneta; conteúdo programático como trilha vertical; turmas como fichas pautadas; professor como assinatura.
- **Checkout:** uma coluna no celular; checklist riscado no topo ("Etapa N de 5" no celular); resumo como recibo serrilhado (lateral no desktop, recolhível no celular); campo de cupom no visual de cupom recortado (ver decisão 7); pagamento online quando habilitado e PIX com instruções em post-it; comprovante "grampeado" (área de envio com grampo); carimbo de comprovante em análise; mesmos formulários, campos, CSRF e endpoints atuais.
- **Login, pós-login, cadastro, recuperação e redefinição de senha:** folha limpa centralizada; força da senha como marca-texto que preenche.
- **Institucionais:** texto longo alinhado à pauta. **Validar certificado:** linha para o código + carimbo de resultado. **Erro:** "esta página foi arrancada do caderno", com borda rasgada e caminho de volta.
- **Norminha:** mesma montagem (`v2/partials/norminha_montagem.php`), com ajustes de pele no CSS do tema.

### 6. Dados para desenvolvimento local

O banco local não tem cursos, categorias com cursos nem capas. Uma fixture SQL local (em `tests/Fixtures/`, nunca aplicada em produção) cria categorias, cursos, turmas e módulos de exemplo; as capas usam imagens de exemplo locais. A validação com dados reais acontece pela prévia de administrador em produção.

### 7. Campo de cupom no checkout (decisão do produto, 06/10/2026)

A V2 não tem campo para digitar cupom — `checkout-resumo` só exibe o cupom já aplicado; o campo existe apenas no checkout V1. O tema passa a oferecê-lo no resumo do pedido, no visual de "cupom recortado".

- **Rota nova:** `POST /v2/checkout/cupom` com `auth.v2` (CSRF automático), em `CheckoutController::aplicarCupomV2()`, fina, no mesmo padrão de `enviarComprovanteV2()`: lê `pedido_id` e `cupom_codigo` do corpo, chama o **mesmo** `PedidoService::aplicarCupomAoPedido()` (que valida a propriedade do pedido, registra acesso negado e delega a `CupomService::aplicarAoPedido()`), e redireciona sempre para `/v2/checkout/resumo?pedido_id=N` (PRG), com flash de sucesso ou de erro em `cupom_codigo`.
- **Por que não reaproveitar `POST /checkout/cupom`:** ele redireciona fixo para o resumo V1 (`/checkout/resumo`), o que tiraria o aluno do tema no meio do funil. Mudar o destino dele afetaria o fluxo V1, que segue no ar para rollback.
- **Pré-preenchimento:** o campo vem com `Session::get('cupom_promocional_codigo')` quando houver (o mesmo valor que o V1 usa), entregue à view pelos dados do resumo.
- **Quando aparece:** só enquanto o pedido aceita cupom — a view usa o mesmo critério que o backend aplica; se o backend recusar, a mensagem dele é exibida.
- **Mensagens:** as mensagens de usuário do caminho de aplicação (`PedidoService::aplicarCupomAoPedido()` e as validações de `CupomService::aplicarAoPedido()`) hoje estão sem acentuação ("Cupom nao encontrado.", "Curso em promocao nao aceita cupom."). Elas passam a ter acentuação correta, conforme a regra editorial do projeto. O texto muda; a regra não.
- **Sem mudança de regra de negócio, banco ou permissão.**

## Risks / Trade-offs

- [Duas versões das mesmas páginas por um tempo] → só as do escopo; views V2 removidas na mudança que migrar o restante do site; o seletor cai na V2 quando falta view no tema.
- [Orçamento de 15 KB de JS estourar com os módulos por página] → medir a cada etapa (tarefa explícita); módulos de página só executam quando `data-pagina` corresponde.
- [View Transitions só em Chromium/Safari recente] → aprimoramento progressivo; o público principal (Chrome Android) é coberto.
- [Capas neon contrastam com o papel] → aceito pelo produto; moldura de foto colada atenua; troca de capas é trabalho futuro de arte.
- [Pauta em CSS desalinhar com texto em zoom/fonte do sistema] → a pauta é decorativa; nenhum layout depende do alinhamento exato.
- [Prévia por sessão confundir quem administra] → indicador visível "Prévia do tema caderno — sair" no topo enquanto ativa.
- [Diferenças sutis de dados entre shims V2 e caderno] → os shims do tema recebem as mesmas variáveis; testes de smoke rodam com a chave em `caderno` e em `v2`.

## Migration Plan

1. Entregar por etapas, todas atrás da chave, com `TEMA_PUBLICO=v2` em produção: (a) base — seletor, layout, kit, movimento, fontes, ícones; (b) home e catálogo; (c) curso e categorias; (d) autenticação; (e) checkout; (f) institucionais, validação e erro.
2. Após cada etapa: deploy, validação pela prévia de administrador com dados reais, medição de desempenho.
3. Virada: `TEMA_PUBLICO=caderno` com aprovação do produto. Rollback: `TEMA_PUBLICO=v2`, sem deploy.
4. Sem migration de banco.
