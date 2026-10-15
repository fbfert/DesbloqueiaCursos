# Design

## Context

- **Como o tema é renderizado hoje.** As páginas do tema moram nas rotas `/v2/*`. Os controllers
  V2 chamam `TemaPublico::view($nome)`, que escolhe `caderno/<nome>` ou `v2/<nome>` conforme
  `TEMA_PUBLICO` e a prévia de sessão. Todas as views V2 já têm par no caderno; só os partials
  ficam de fora.
- **O que vem do V1.** O visual antigo chega por três caminhos:
  1. controllers V1 que chamam `$this->view()`, cujo layout é `resources/views/layout.php`;
  2. `V2ErrorPage`, que só usa o tema quando o caminho começa com `/v2`;
  3. `ErrorHandler::render`, que usa sempre `errors/500` V1.
- **Login exigido.** O `AuthenticateMiddleware` (`auth`) manda para `/login` V1. O
  `V2AuthenticateMiddleware` (`auth.v2`) manda para `/v2/login?redirect=` usando
  `SafeRedirect::v2Path`, que aceita só caminhos `/v2/...`.
- **Destino depois do login.** `LoginDestino::sucesso` manda quem entra pelo tema, sem destino,
  para `/v2/pos-login`. Essa tela só oferece área do aluno e catálogo, então hoje admin e professor
  entram pelo `/login` V1. Quando `/login` virar redirecionamento, esse caminho deixa de existir.
- **Sub-tema `v4-claude`.** É escolhido no admin (`template_visual_portal`) e só vale dentro das
  views V1. Deixa de ser alcançável quando as rotas V1 do escopo redirecionarem.
- **Tipos de item do LMS.** Os tipos válidos são `etiqueta`, `texto`, `arquivo`, `link`,
  `avaliacao_textual`, `video`, `quiz`, `html` e `video_incorporado`. A aula V2 só manda para a
  rota oficial V1 no estado "externo", que serve ao tipo legado `atividade`. Esse tipo não é mais
  cadastrável e não existe no banco local; falta confirmar no de produção.

## Goals / Non-Goals

**Goals:**

- Nenhuma rota GET do escopo (site público e área do aluno) renderiza `resources/views/layout.php`.
- URLs antigas que existem fora do site continuam funcionando por redirecionamento: QR codes,
  PDFs, e-mails, anúncios e o retorno do AbacatePay.
- Admin, professor e revisor continuam entrando e chegando às suas áreas pelo login do tema.

**Non-Goals:**

- Redesenhar o backoffice. Isso é da mudança `backoffice-caderno`.
- Apagar views V1, views V2, `v4-claude` e rotas POST V1. Fica para uma limpeza posterior; aqui elas
  só deixam de ser alcançadas.
- Mudar o contrato da API do app ou o layout dos e-mails.

## Decisions

### 1. Tema fixo em `TemaPublico`

`TemaPublico::ativo()` passa a devolver sempre `caderno`. `aplicarPrevia()` vira um no-op, mantido
para não quebrar quem chama. `emPrevia()` devolve `false`, o que faz o partial `aviso-previa` sumir
sozinho.

`TemaPublico::view()` continua resolvendo pelo arquivo: o caderno tem todas as views, e a queda para
`v2/` fica como rede de segurança sem efeito prático. Remover `TemaPublico` inteiro tocaria dezenas
de controllers sem ganho, então fica para a limpeza.

Alternativa descartada: só mudar o padrão de `config/app.php` para `caderno`. Um `.env` com
`TEMA_PUBLICO=v2` voltaria tudo para a V2, contrariando o requisito "Tema único".

### 2. Redirecionamento num ponto só: `App\Support\RotaLegada`

Nasce uma classe de suporte com um mapa declarativo das rotas legadas: caminho V1, destino no tema,
status (301 ou 302) e parâmetros preservados. Em `routes/web.php`, as rotas GET V1 do escopo passam
a apontar para `RotaLegadaController::redirecionar`, que consulta o mapa. Os controllers V1 originais
ficam intactos para a limpeza posterior.

Preferi isso a editar cada método V1 para devolver um redirecionamento. O mapa fica testável em
`tests/Unit/` sem servidor, e o rollback é trocar as linhas de rota de volta.

Mapa:

| Rota legada | Destino | Status |
|---|---|---|
| `/` com `HOME_VERSION≠v2` | `/v2/` | 301 |
| `/login` | `/v2/login` | 301 |
| `/cadastro` | `/v2/cadastro` | 301 |
| `/recuperar-senha`, `/esqueci-minha-senha` | `/v2/recuperar-senha` | 301 |
| `.../redefinir` (as duas) | `/v2/recuperar-senha/redefinir` (preserva o token) | 302 |
| `/cursos` | `/v2/catalogo` (`busca`, `categoria`, `pagina`) | 301 |
| `/cursos/detalhe` | `/v2/curso` (`curso_id`, `turma_id`) | 301 |
| `/categorias` | `/v2/categorias` | 301 |
| `/categorias/{slug}/cursos` | `/v2/catalogo/?categoria={slug}` | 301 |
| `/sobre` | `/v2/quem-somos` | 301 |
| `/contato` | `/v2/onde-estamos` | 301 |
| `/como-funciona` | `/v2/como-funciona-a-sala-virtual` | 301 |
| `/inscricao`, `/checkout/inscricao` | `/v2/checkout/inscricao` (query inteira) | 302 |
| `/checkout/participantes` | `/v2/checkout/participantes` (query inteira) | 302 |
| `/checkout/resumo`, `/checkout/cupom` | `/v2/checkout/resumo` (`pedido_id`) | 302 |
| `/checkout/comprovante` | `/v2/checkout/comprovante` (`pedido_id`) | 302 |
| `/checkout/sucesso` | `/v2/checkout/sucesso` (`pedido_id`) | 302 |
| `/meus-cursos`, `/aluno/meus-cursos` | `/v2/aluno/` | 302 |
| `/pedidos` | `/v2/aluno/?aba=pedidos` | 302 |
| `/minha-conta` | `/v2/minha-conta` | 302 |
| `/area-curso`, `/aluno/cursos`, `/aluno/curso/{i}/{c}/{t}` | `/v2/aula/` (inscrição, curso, turma) | 302 |
| `.../modulo` e `.../modulo/{m}` | `/v2/aula/` (+ `modulo_id`) | 302 |
| `.../conteudo/item` e `.../conteudo/{id}` | por tipo do item (ver abaixo) | 302 |
| `/aluno/cursos/quiz/resultado` | `/v2/quiz` com o contexto da tentativa | 302 |
| `/certificados`, `/certificados/validar` | `/v2/certificados/validar` (`codigo`) | 301 |
| `/certificados/show` | `/v2/certificados/validar` (`codigo`) | 301 |
| `/certificados/versao-online` | `/v2/certificados/versao-online` (`codigo`) | 301 |

Casos que precisam de dado além da URL:

- **Item de conteúdo.** O destino depende do tipo do item, que o redirecionamento lê pelo
  `ConteudoCursoService` já existente: `quiz` vai para `/v2/quiz`, `avaliacao_textual` para
  `/v2/atividade` e os demais para `/v2/aula/` com `conteudo_id`. A verificação de acesso continua na
  página de destino.
- **Resultado do quiz.** Hoje `/aluno/cursos/quiz/resultado` só recebe `tentativa_id`. O
  redirecionamento busca a tentativa do próprio usuário e monta inscrição, curso, turma, módulo e
  item. Tentativa de outra pessoa ou inexistente dá o 404 do tema.
- **`/minha-pagina`.** Fica como está para admin e professor. Para o aluno, o destino muda de
  `/meus-cursos` para `/v2/aluno/`.

Ficam como estão, porque não são páginas e o tema já as usa: rotas de arquivo, imagem e download
(`/aluno/cursos/conteudo/arquivo/download`, `.../link/acessar`, `.../avaliacao/imagem`,
`/aluno/cursos/atividade/arquivo`, `/certificados/pdf`) e os endpoints AJAX do quiz. Os erros delas
passam a sair no tema (decisão 4).

### 3. Telas novas no tema

Todas seguem o padrão existente: uma casca em `caderno/<nome>.php`, que define `$contentView` e
`$paginaTema` e faz o `require` do layout, e o conteúdo em `caderno/pages/<nome>.php`. A
renderização usa `View::render(TemaPublico::view(...), $data, false)` com os dados de layout de
`V2Nav`.

| Tela | Rota | Controller |
|---|---|---|
| Sair da conta | `GET /logout` (mantida) | `AuthController::logoutConfirm` renderiza o tema. O formulário faz POST em `/v2/logout`. |
| Pagamento concluído | `GET /v2/checkout/sucesso` (nova, `auth.v2`) | `CheckoutController::sucessoV2`, com os mesmos dados de `sucesso()` e a mesma checagem de dono. |
| Certificado online | `GET /v2/certificados/versao-online` (nova) | `CertificadosController::versaoOnline` passa a renderizar o tema. As páginas do certificado continuam em `iframe srcdoc`, isoladas do CSS do tema. |
| Checkout rápido | `GET /comprar` (mantida) | `CheckoutRapidoController::mostrar` renderiza o tema, com os mesmos campos, scripts de conversão e `status` em JSON. |
| Descadastro | `GET /pedido-recuperacao/descadastrar` (mantida) | `PedidoRecuperacaoController` renderiza o tema. |
| Páginas dinâmicas | rota do banco | `PaginasController::showByRoute` reaproveita a view `institucional` do tema e `V2InstitucionalContent::build`. |

Sobre algumas delas:

- **Sair da conta.** O POST `/v2/logout` exige `auth.v2`. Isso vale para qualquer perfil, porque
  esse middleware só checa `usuario_id`. O POST legado `/logout`, usado pelo cabeçalho do
  backoffice, passa a redirecionar para `/v2/login`.
- **Pagamento concluído.** É uma rota nova no namespace `/v2` porque o tema já trata `/v2/checkout/*`
  como checkout: navegação, passos e orçamento de JS.
- **Certificado online.** Mantive a renderização em `iframe` porque o HTML do certificado é
  autocontido e foi desenhado para impressão.

### 4. Erros no tema fora do backoffice

- **`V2ErrorPage::render`.** Passa a usar o tema para qualquer caminho que não comece com `/admin`,
  `/professor`, `/revisor` ou `/api`. O método `isV2Path` vira `usaTemaPublico`.
- **`ErrorHandler::render` (500).** Aplica a mesma regra a partir de `$_SERVER['REQUEST_URI']`. Se a
  renderização do tema falhar dentro do próprio tratamento de erro, cai num HTML mínimo embutido,
  sem layout. Isso evita um laço de exceção.
- **Controllers V1 do escopo.** Quem ainda renderiza `errors/404` ou `errors/403` direto
  (`AreaCursoController`, `CertificadosController`, `CheckoutRapidoController`) passa a usar
  `V2ErrorPage`.

### 5. Login exigido e destino por perfil

- **`AuthenticateMiddleware`.** Passa a mandar para `/v2/login`. Em GET, preserva o destino com um
  validador novo, `SafeRedirect::caminhoInterno`. Ele aceita `/v2/...`, `/admin...`, `/professor...`,
  `/revisor...` e as rotas legadas do aluno, que redirecionam depois, e reaproveita as mesmas
  rejeições de `v2Path`: esquema, `//`, controle e barra invertida.
- **`LoginDestino::sucesso`.** Usa `caminhoInterno` no lugar de `v2Path`. Sem destino, consulta o
  perfil da sessão, pela mesma regra de `AuthController::minhaPagina`: admin vai para
  `/admin/dashboard`, professor para `/professor/dashboard` e revisor para `/revisor`. Quem não tem
  nenhum desses perfis vai para `/v2/pos-login`. O login pelo Google já passa por `LoginDestino` e
  herda a regra.
- **Formulários do tema.** Login e cadastro já enviam `origem=v2`. Depois do redirecionamento, todo
  login vem do tema, então `LoginDestino::erro` também passa a devolver sempre o login do tema.

### 6. Links internos corrigidos

| Origem | Hoje | Passa a |
|---|---|---|
| `AbacatePayService` (`returnUrl` e `completionUrl`) | `/checkout/resumo` e `/checkout/sucesso` | `/v2/checkout/resumo` e `/v2/checkout/sucesso` |
| `CheckoutController::pagarAbacatepay` (`ja_pago`, `sem_cobranca`) | `/checkout/sucesso` | `/v2/checkout/sucesso` |
| Curso gratuito (`CheckoutController`, nas linhas 186, 939 e 979) | `/minha-pagina` | `/v2/aluno/` |
| Inscrição existente (`Helpers::urlAcessoCursoAluno`) | `/aluno/curso/...` | `/v2/aula/?...` |
| `oficial_url` da aula e da atividade (estado "externo") | `/aluno/curso/.../conteudo/{id}` | aula do tema com `conteudo_id`; o tipo legado mostra "conteúdo indisponível" |
| `online_href` dos certificados (`V2/AlunoController`) | `/certificados/show` | `/v2/certificados/versao-online` |
| URL de validação gravada (`CertificadoService`, `CertificadoPlaceholderService`, `InscricaoService`, `CatalogoPresenter`) | `/certificados/validar` | `/v2/certificados/validar` |
| Categorias da home (`V2/HomeController`, `lombada.php`, `v2/pages/home.php`) | `/categorias/{slug}/cursos` | `/v2/catalogo/?categoria={slug}` |
| Termos e privacidade (`V2/CadastroController`, `botao-google.php`) | `/termos-de-uso` e `/politica-de-privacidade` | `V2Nav::TERMOS` e `V2Nav::PRIVACIDADE` |
| Cupom: o `Router` manda todo `?cupom=` para `/cupom`, que volta para `/cursos` | perde o curso | `/cupom?codigo=X&voltar=<caminho sem cupom>`; depois de guardar o cupom, segue para o destino no tema |
| Fallbacks em views e controllers V2 (`/meus-cursos`, `/login`, `/cadastro`, `/cursos`...) | V1 | constantes de `V2Nav` |

Os certificados já emitidos guardam a URL antiga, e ela continua válida pelo 301. Não há migração
para reescrevê-la.

### 7. Guarda no smoke

`tests/Smoke/rotas.php` ganha duas coisas:

- **Uma lista de rotas legadas** com status e `Location` esperados.
- **Uma guarda nova, `proibe_legado`, ligada por padrão em todas as rotas do escopo.** Ela falha se
  o corpo trouxer o marcador do layout V1. O marcador é a classe `frontend-theme` no `<body>`, que só
  `resources/views/layout.php` emite. Rotas de `/admin`, `/professor` e `/revisor` ficam de fora.

## Risks / Trade-offs

- **O tipo legado `atividade` pode existir em produção.**
  - Risco: itens desse tipo ficariam sem tela.
  - Mitigação: uma tarefa roda `SELECT tipo, COUNT(*) FROM conteudo_itens GROUP BY tipo` em
    produção antes do apply. Se houver itens, a aula mostra "conteúdo indisponível, fale com a
    secretaria" e o caso volta ao responsável antes do deploy.
- **Retorno com 301 fica em cache no navegador.** Um 301 errado é difícil de desfazer.
  - Mitigação: só as páginas públicas estáveis usam 301. Checkout, aluno e redefinição usam 302. O
    mapa é coberto por teste unitário.
- **Admin acostumado ao `/login` V1.** O login do tema não tem o mesmo visual.
  - Mitigação: o destino por perfil (decisão 5) leva cada um à sua área. O texto do login não fala
    só com aluno.
- **Página de erro 500 depende do tema.** Se o próprio layout do tema falhar, entra o HTML mínimo
  embutido (decisão 4).
- **Orçamento de JS e CSS do tema.** As telas novas são estáticas e quase não acrescentam peso. O
  checkout rápido traz os scripts de conversão próprios, que já existem hoje e não contam como JS do
  tema.

## Migration Plan

1. Deploy só de código com `scripts/deploy_vps.sh`, sem migração.
2. Rodar o smoke completo, anônimo e com `--modo=todos`, e conferir as rotas legadas e a guarda
   `proibe_legado`.
3. Testar à mão:
   - login como admin, professor e aluno;
   - um pagamento online de ponta a ponta;
   - leitura de um QR code de certificado antigo;
   - um link de cupom promocional.
4. Apagar `TEMA_PUBLICO` do `.env`. É opcional, porque a chave não tem mais efeito.

**Rollback:** `deploy_vps.sh --reverter <backup>`. Os 301 já entregues seguem em cache nos
navegadores de quem os recebeu, mas só levam a páginas `/v2/*`, que continuam existindo.

## Open Questions

- Existem itens do tipo `atividade` no banco de produção? A resposta decide se precisamos de uma
  tela própria para eles ou se basta o aviso de conteúdo indisponível.
