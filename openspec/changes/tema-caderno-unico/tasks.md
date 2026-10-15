# Tasks

## 0. Pré-requisito

- [ ] 0.1 Rodar `SELECT tipo, COUNT(*) FROM conteudo_itens GROUP BY tipo` no banco de produção. Registrar o resultado no fim deste arquivo.
  - Se existir o tipo `atividade`, parar e levar o caso ao responsável antes do grupo 7 (ver Open Questions do design).
  - Verificação: resultado anotado.

## 1. Tema fixo

- [x] 1.1 Em `App\Support\TemaPublico`, fazer:
  - `ativo()` devolver sempre `caderno`;
  - `aplicarPrevia()` virar no-op e `emPrevia()` devolver `false`;
  - atualizar o comentário da classe.
  - Verificação: `tests/Unit/tema_publico.php` (reescrito) passa com `TEMA_PUBLICO=v2` e com prévia na sessão.
- [x] 1.2 Em `config/app.php`, deixar o padrão de `tema_publico` em `caderno`. Em `.env.example`, marcar `TEMA_PUBLICO` como obsoleta.
  - Verificação: `php -l`.

## 2. Destino do login e login exigido

- [x] 2.1 Criar `SafeRedirect::caminhoInterno`. Aceita `/v2`, `/admin`, `/professor`, `/revisor` e as rotas legadas do aluno, com as mesmas rejeições de `v2Path`.
  - Verificação: casos de aceite e de rejeição em `tests/Unit/safe_redirect_interno.php`. Rejeições: `//x`, `https:`, `\`, CR/LF e `/v2/../admin`.
- [x] 2.2 Em `LoginDestino`, fazer:
  - `sucesso` usar `caminhoInterno` e, sem destino, mandar admin para `/admin/dashboard`, professor para `/professor/dashboard`, revisor para `/revisor` e aluno para `/v2/pos-login`;
  - `erro` devolver sempre o login do tema.
  - Verificação: `tests/Unit/login_destino.php` cobre os quatro perfis e o destino externo descartado. `php tests/Unit/google_login_service.php` continua passando.
- [x] 2.3 Fazer o `AuthenticateMiddleware` redirecionar para `/v2/login`, com `?redirect=` validado por `caminhoInterno` em GET.
  - Verificação: com `curl`, `/admin/dashboard` sem sessão leva a `/v2/login?redirect=%2Fadmin%2Fdashboard`. Depois do login de admin, chega ao painel.

## 3. Redirecionamento das rotas legadas

- [x] 3.1 Criar `App\Support\RotaLegada` com o mapa da decisão 2 do design (destino, status e parâmetros preservados).
  - Verificação: `tests/Unit/rota_legada.php` cobre cada linha do mapa, com preservação e descarte de parâmetros.
- [x] 3.2 Criar `RotaLegadaController::redirecionar`, com dois casos que precisam de dado:
  - item de conteúdo resolvido pelo tipo;
  - resultado do quiz resolvido pela tentativa do próprio usuário (404 do tema para tentativa alheia).
  - Verificação: por HTTP contra o banco local (fixture `tests/Fixtures/tema_caderno_aluno.sql`): quiz, texto e atividade vão à tela certa; tentativa inexistente ou alheia dá 404 do tema.
- [x] 3.3 Em `routes/web.php`, apontar as rotas GET V1 do escopo para `RotaLegadaController`, e fazer `/` renderizar a home do tema direto (sem redirecionar; `HOME_VERSION` perde efeito). Ajustar `AuthController::minhaPagina` para mandar o aluno a `/v2/aluno/`.
  - Verificação: `curl -I` em cada rota legada no Docker local (porta 8010) devolve o status e o `Location` do mapa.

## 4. Telas novas no tema

- [ ] 4.1 "Sair da conta":
  - criar `caderno/sair.php` e `caderno/pages/sair.php`;
  - `AuthController::logoutConfirm` renderiza o tema, com POST em `/v2/logout`;
  - o POST legado `/logout` passa a redirecionar para `/v2/login`.
  - Verificação: com sessão de aluno e de admin, `/logout` mostra a tela do tema; confirmar leva ao login do tema com "Você saiu da sua conta."; cancelar volta à área do perfil.
- [ ] 4.2 Pagamento concluído:
  - criar a rota `GET /v2/checkout/sucesso` (`auth.v2`) e `CheckoutController::sucessoV2`, com os dados e a checagem de dono de `sucesso()`;
  - criar as views `caderno/checkout/sucesso.php` e `pages/checkout-sucesso.php`, com os estados aprovado, em confirmação, gratuito e comprovante em análise.
  - Verificação: pedido do próprio aluno mostra o estado certo; pedido alheio dá o 404 do tema.
- [ ] 4.3 Certificado online:
  - criar a rota `GET /v2/certificados/versao-online`;
  - `CertificadosController::versaoOnline` renderiza o tema, com o certificado em `iframe srcdoc`, o PDF (quando a pessoa tem acesso) e a validação.
  - Verificação: código válido mostra as duas páginas do certificado; código inválido dá o 404 do tema; com a validação pública desligada, dá o 404 do tema.
- [ ] 4.4 Checkout rápido:
  - `/comprar` renderiza o tema, com os mesmos campos, validações, eventos de conversão (Google Ads e WhatsApp) e `/comprar/status`;
  - checkout rápido desligado dá o 404 do tema.
  - Verificação: com `CHECKOUT_RAPIDO_ATIVO=true` local, enviar uma compra de teste; com `false`, conferir o 404.
- [ ] 4.5 Descadastro: `/pedido-recuperacao/descadastrar` renderiza o tema, com o mesmo resultado.
  - Verificação: abrir com token válido e com token inválido.
- [ ] 4.6 Páginas dinâmicas: `PaginasController::showByRoute` renderiza a view `institucional` do tema.
  - Verificação: uma página publicada no banco local abre no tema; uma não publicada dá o 404 do tema.

## 5. Erros no tema

- [ ] 5.1 Em `V2ErrorPage`, trocar `isV2Path` por `usaTemaPublico`, que vale para tudo exceto `/admin`, `/professor`, `/revisor` e `/api`.
  - Verificação: `/pagina-que-nao-existe` dá 404 no tema; um 404 em `/admin/...` continua no layout do admin.
- [ ] 5.2 Em `ErrorHandler::render`, renderizar o 500 no tema fora do backoffice, com HTML mínimo embutido se o próprio tema falhar.
  - Verificação: uma exceção forçada numa rota pública local dá 500 no tema, sem caminho de arquivo nem trace, e com registro no log.
- [ ] 5.3 Trocar os `View::render('errors/40x')` diretos de `AreaCursoController`, `CertificadosController` e `CheckoutRapidoController` por `V2ErrorPage`.
  - Verificação: `grep -rn "errors/40" app/Controllers` só encontra controllers do backoffice.

## 6. Links internos

- [ ] 6.1 AbacatePay: atualizar `returnUrl` e `completionUrl` em `AbacatePayService`, e os destinos `ja_pago` e `sem_cobranca` em `CheckoutController::pagarAbacatepay`, para `/v2/checkout/*`.
  - Verificação: teste unitário do montador de URLs; cobrança de teste no sandbox, se houver chave local.
- [ ] 6.2 Curso gratuito e inscrição existente: atualizar `CheckoutController` (linhas 186, 939 e 979) e `Helpers::urlAcessoCursoAluno` para destinos do tema.
  - Verificação: inscrição gratuita local termina em `/v2/aluno/`.
- [ ] 6.3 `oficial_url` em `V2/AulaController` e `V2/AtividadeController`: passa a apontar para a aula do tema. O tipo legado mostra o aviso de conteúdo indisponível.
  - Verificação: `grep -rn "'/aluno/curso/" app/Controllers/V2` sem resultados.
- [ ] 6.4 Certificados:
  - `online_href` passa a ser `/v2/certificados/versao-online`;
  - a URL de validação gravada passa a ser `/v2/certificados/validar` em `CertificadoService`, `CertificadoPlaceholderService`, `InscricaoService` e `AppApi/CatalogoPresenter`.
  - Verificação: `php tests/Unit/certificado_retencao.php` e os testes de certificado existentes passam; um certificado emitido localmente traz a URL nova no QR.
- [ ] 6.5 Categorias da home, termos e privacidade:
  - atualizar `V2/HomeController`, `caderno/partials/lombada.php`, `v2/pages/home.php`, `V2/CadastroController` e `partials/botao-google.php`.
  - Verificação: o HTML da home e do cadastro não traz `/categorias/`, `/termos-de-uso` nem `/politica-de-privacidade` sem `/v2`.
- [ ] 6.6 Cupom promocional: o `Router` envia `voltar=<caminho sem cupom>` e `cupomPromocional` guarda o cupom e segue para o destino no tema (`/v2/catalogo` por padrão).
  - Verificação: `/cursos/detalhe?curso_id=12&cupom=X` termina em `/v2/curso?curso_id=12`, com o cupom na sessão.
- [ ] 6.7 Trocar por constantes de `V2Nav` os fallbacks V1 em views e controllers V2 e do caderno (`/meus-cursos`, `/login`, `/cadastro`, `/recuperar-senha`, `/cursos`, `/categorias`).
  - Verificação: `grep` nas pastas `resources/views/caderno`, `resources/views/v2` e `app/Controllers/V2` não encontra esses caminhos sem `/v2`.

## 7. Smoke e verificação

- [ ] 7.1 Em `tests/Smoke/rotas.php`, fazer:
  - incluir as rotas legadas, com status e `Location` esperados;
  - incluir as telas novas: `/logout` com sessão, `/v2/checkout/sucesso` sem sessão terminando no login, `/v2/certificados/versao-online` com código inválido dando 404 e uma rota inexistente dando 404 do tema;
  - criar a guarda `proibe_legado` (classe `frontend-theme` no corpo), ligada por padrão nas rotas do escopo;
  - documentar tudo em `tests/Smoke/README.md`.
  - Verificação: `php tests/Smoke/smoke.php http://127.0.0.1:8010` e `--modo=todos` com o usuário de teste local terminam com `0`.
- [ ] 7.2 Rodar `php -l` em todos os PHP alterados (no 8.3 do Docker e no 8.4) e `node --check` nos JS alterados.
  - Verificação: zero erros e zero `Deprecated`.
- [ ] 7.3 Rodar os testes unitários e de API afetados: `tests/Unit/*` novos e existentes de login, Google, certificado e quiz, e `tests/Api/google_e2e.php`.
  - Verificação: todos terminam sem falha.
- [ ] 7.4 Revisar os textos de interface novos (sair, pagamento concluído, certificado online, descadastro, erros, aviso de conteúdo indisponível) contra `docs/padrao-editorial-ptbr.md`.
  - Verificação: acentuação correta e nenhum termo em inglês visível.
- [ ] 7.5 Medir o orçamento de desempenho nas telas novas de checkout e autenticação: até 15 KB de JS e 25 KB de CSS do tema, comprimidos.
  - Verificação: medição anotada no fim deste arquivo.
- [ ] 7.6 Fazer o percurso manual no Docker local:
  - login como aluno, professor e admin pelo `/login`;
  - "Sair";
  - abrir uma aula pela URL antiga;
  - validar certificado pela URL antiga;
  - link de cupom;
  - página dinâmica.
  - Verificação: nenhuma tela com o visual antigo.

## 8. Documentação

- [ ] 8.1 Atualizar `CLAUDE.md` (seção de tema) e `docs/2026-10-06-tema-caderno.md` para dizer que o caderno é fixo. Registrar o roteiro de deploy e de rollback em `docs/deploy.md`.
  - Verificação: revisão do texto.

## Workflow follow-up

- Deploy com `scripts/deploy_vps.sh`, sem migração, seguido do smoke completo em produção e dos testes manuais da seção "Migration Plan" do design.
- Arquivar a mudança com `/opsx:archive`. Antes, arquivar `api-app-v1` e `login-google`, que estão pendentes.
- Em seguida, abrir a mudança `backoffice-caderno`, começando por uma rodada de definição visual.
