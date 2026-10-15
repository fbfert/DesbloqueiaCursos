# Proposal

## Why

O tema caderno já cobre a vitrine, a autenticação, o checkout e o estudo, mas várias páginas ainda
abrem no visual antigo (V1). Isso acontece por quatro caminhos:

- rotas antigas que continuam renderizando, como `/login`, `/meus-cursos` e `/cursos`;
- telas que nunca ganharam versão nova, como "Sair da conta" e o retorno do pagamento online;
- páginas de erro fora de `/v2`;
- links das próprias páginas novas que levam a destinos V1, como "Abrir atividade", as categorias
  da home e o retorno do AbacatePay.

O responsável decidiu, em 15/10/2026, que o caderno é o único visual do site público e da área do
aluno. Nenhuma dessas páginas pode mais abrir no visual antigo.

## What Changes

- **Caderno fixo.** O tema caderno passa a ser o único visual do site público e da área do aluno.
  - **BREAKING:** `TEMA_PUBLICO` deixa de alternar com a V2.
  - **BREAKING:** a prévia `?tema=` é removida.
  - As views V2 continuam no repositório, mas nenhuma página do escopo as usa.
- **Telas novas no caderno** para o que ainda não tem versão no tema:
  - "Sair da conta" (`GET /logout`);
  - pagamento concluído (`/v2/checkout/sucesso`, novo destino do AbacatePay);
  - certificado na versão online (`/v2/certificados/versao-online`);
  - checkout rápido (`/comprar`);
  - descadastro da recuperação de pedido (`/pedido-recuperacao/descadastrar`);
  - páginas institucionais dinâmicas do banco (qualquer rota resolvida por `PaginasController`);
  - erros 403, 404 e 500 fora de `/admin`, `/professor` e `/revisor`.
- **Redirecionamento das rotas antigas.** Toda rota GET V1 do site público e da área do aluno que já
  tem equivalente no tema redireciona para ela. Páginas públicas usam 301 e páginas logadas ou do
  checkout usam 302. A query string útil é preservada. Isso inclui:
  - autenticação e conta: login, cadastro, recuperação e redefinição de senha, minha conta e
    "minha página" do aluno;
  - vitrine: catálogo, curso, categorias, sobre, contato e como funciona;
  - checkout V1;
  - área do aluno e estudo: `/meus-cursos`, `/pedidos`, `/area-curso/*`, `/aluno/curso*/*` e o
    resultado do quiz;
  - certificados: validação e visualização;
  - a home `/`, independentemente de `HOME_VERSION`.
- **Links e redirecionamentos internos corrigidos** para destinos no tema:
  - retorno e conclusão do AbacatePay;
  - curso gratuito e inscrição já existente;
  - "Abrir atividade";
  - erros de material e link da aula;
  - certificado online e URL de validação gravada em certificados novos;
  - categorias da home;
  - termos e privacidade do cadastro e do botão do Google;
  - link de cupom promocional;
  - login exigido por rotas protegidas pelo middleware `auth`, que passa a mandar para `/v2/login`.
- **Garantia no smoke test.** Nenhuma rota do escopo pode responder com o layout V1. Cada rota
  antiga precisa redirecionar para o destino esperado.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `tema-publico`: o tema passa de configurável a único.
  - A seleção por `TEMA_PUBLICO` e a prévia saem.
  - O escopo passa a incluir a área do aluno legada, que agora redireciona.
  - Entram requisitos para as rotas legadas (redirecionamento), as telas que faltavam, as páginas
    de erro e a proibição de links do tema que levem ao visual V1.

## Impact

- **Código:**
  - `app/Support/TemaPublico.php`, `V2ErrorPage.php`, `V2Nav.php` e `LoginDestino.php`;
  - `app/Core/ErrorHandler.php` e `Router.php` (404 e páginas dinâmicas);
  - `AuthenticateMiddleware`;
  - controllers V1 que viram redirecionamento: `AuthController`, `CursosController`,
    `CategoriasController`, `PagesController`, `CheckoutController`, `MeusCursosController`,
    `AreaCursoController`, `QuizController` e `CertificadosController`;
  - outros controllers: `CheckoutRapidoController`, `PedidoRecuperacaoController`,
    `PaginasController` e V2 (`AulaController`, `AtividadeController`, `AlunoController`,
    `HomeController`, `CadastroController`);
  - services: `AbacatePayService`, `CertificadoService`, `CertificadoPlaceholderService`,
    `InscricaoService` e `CupomService`;
  - views novas em `resources/views/caderno/`;
  - `tests/Smoke/rotas.php`.
- **Banco:** nenhuma migração.
- **URLs externas:**
  - QR codes e PDFs de certificados já emitidos continuam válidos, porque as URLs antigas
    redirecionam;
  - links de e-mails antigos também redirecionam;
  - cobranças do AbacatePay criadas antes do deploy voltam para `/checkout/sucesso`, que redireciona.
- **Deploy:** só código, com o mesmo `scripts/deploy_vps.sh`, sem migração. Depois do deploy:
  - `TEMA_PUBLICO` no `.env` de produção perde efeito;
  - rodar o smoke completo.
- **Rollback:** reverter o código com `deploy_vps.sh --reverter`. Não há dado a desfazer.
- **Admin, professor e revisor:** o login deles passa a ser o `/v2/login` do caderno, que já
  encaminha cada perfil para a sua área. O "Sair" do backoffice cai na tela nova.

## Fora de escopo

- **Visual do backoffice.** As telas de admin, professor e revisor ficam para a mudança seguinte,
  `backoffice-caderno`. Até lá, os erros dentro dessas áreas continuam no layout atual.
- **Remoção de código morto.** Views V1 públicas, views V2, o sub-tema `v4-claude`, a opção
  "template visual do portal" no admin e as rotas POST do checkout V1 ficam para uma limpeza
  posterior. Esta mudança só garante que nenhuma rota do escopo as alcança.
- **E-mails transacionais.** O layout dos e-mails não muda; só os links passam a apontar para o tema.
- **App e API** (`/api/app/v1`): sem mudança de contrato. O app recebe a nova URL de validação nos
  certificados emitidos depois do deploy.
