# Tasks

## 0. Pré-requisito

- [x] 0.1 Trazer para `frontend-v4`, a partir de `origin/feature/api-app-v1`, os artefatos OpenSpec da `api-app-v1` (`openspec/changes/api-app-v1/`, com `contrato.md`) e os testes da API (`tests/Api/` e `tests/Unit/app_*.php`), que ficaram fora do commit de deploy e9f2047, sem alterar código de aplicação (`git checkout origin/feature/api-app-v1 -- <caminhos>`). Verificar com `openspec list` mostrando `api-app-v1` e `login-google`, e com `php tests/Unit/app_token_service.php` passando.

## 1. Banco de dados

- [x] 1.1 Criar `sql/083_login_google.sql` com `ALTER TABLE usuarios MODIFY cpf VARCHAR(14) NULL`, `usuario_identidades` e `certificados_retidos`, conforme design §6 e §9. Verificar aplicando no MariaDB do Docker local, conferindo com `SHOW CREATE TABLE` e inserindo dois usuários com `cpf NULL` sem erro de `UNIQUE`.
- [x] 1.2 Registrar a 083 em `docs/deploy.md`, na lista de migrações a aplicar, com a nota de rollback do `cpf`. Verificar lendo a seção atualizada.

## 2. CPF nulo no model e nos pontos de leitura

- [x] 2.1 Em `Usuario`: criar `normalizarCpf`, `semCpf` e `findByEmail`; aplicar `normalizarCpf` em `create`, `createPendente`, `updateProfile` e `updateAdmin`; fazer `findByLogin` só comparar CPF quando o login tiver 11 dígitos. Verificar com um teste novo, `tests/Unit/usuario_cpf_nulo.php`, que cubra: login por e-mail inexistente com um usuário de `cpf = ''` na base não casa; `create` sem CPF grava `NULL`.
- [x] 2.2 `AuthService::updateAccount` passa a aceitar conta sem CPF e trata o CPF como somente leitura quando já preenchido. `Api\App\MeController::atualizar` para de reenviar o CPF. Verificar com teste unitário de `updateAccount` para os dois casos e com `POST /me` de usuário sem CPF respondendo 200 no teste da API.
- [x] 2.3 Varrer os pontos do levantamento que leem `cpf`/`usuario_cpf` e corrigir os que quebram com `NULL`, mantendo o comportamento para quem tem CPF. A lista está em design §7: checkout (prefill e `updateProfile`), `PedidoService`, `PagamentoAbacatepayService`, `PresenteCampanhaService`, `PedidosController` da API, `GestaoAcessoService` e placeholders de e-mail e certificado. Verificar com `php tests/Unit/checkout_logado_e2e.php`, `checkout_rapido_fase0.php`, `checkout_rapido_fase1.php` e `cupom_checkout.php`, mais um caso novo no `checkout_logado_e2e.php` com um usuário sem CPF.

## 3. Núcleo Google

- [x] 3.1 Criar `config/auth.php` (seção `google`) e as chaves `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` e `GOOGLE_APP_CLIENT_IDS` em `.env.example`, com comentário. Verificar que `google.ativo` é falso sem as chaves e verdadeiro com elas (teste unitário).
- [x] 3.2 Criar `App\Services\Google\GoogleIdTokenVerifier`: JWKS com cache em `storage/cache`, nova busca por `kid` desconhecido no máximo uma vez por minuto, PEM a partir de `n`/`e`, `openssl_verify` e validação de `iss`, `aud`, `exp` (tolerância de 60 s), `iat` e `nonce`, com busca e relógio injetáveis. Verificar com `tests/Unit/google_id_token.php`, que gera um par RSA no teste: assinatura válida e inválida, `alg` diferente de RS256, `aud` errado, `iss` errado, token expirado e dentro da tolerância, `nonce` divergente, `kid` desconhecido que gera nova busca.
- [x] 3.3 Criar o model `UsuarioIdentidade` (`findByProvedorSub`, `findByUsuario`, `vincular`, `tocarUso`, `remover`). Verificar com teste unitário contra o banco local: o segundo vínculo do mesmo `sub` é recusado pelo `UNIQUE`.
- [x] 3.4 Criar `App\Services\Google\GoogleLoginService::resolverUsuario`: ordem `sub` → e-mail verificado → criação em transação, com perfil aluno, consentimentos, auditoria, e-mail de boas-vindas depois do commit, recusa de conta inativa e de e-mail de conta excluída, e tratamento da corrida no `UNIQUE`. Verificar com `tests/Unit/google_login_service.php`: já vinculado, e-mail trocado no Google, vínculo por e-mail (gera auditoria e e-mail), e-mail não verificado, conta inativa, conta bloqueada por senha entrando, criação nova com `cpf NULL` e `cadastro_origem = 'google'`.
- [x] 3.5 Criar o modelo de e-mail `email.google-vinculado` e `EmailService::googleVinculado`, com data e hora do vínculo e orientação de contato. Verificar com o teste 3.4, conferindo a linha em `emails_envios` com `entidade_tipo = 'usuario'`.
- [x] 3.6 Extrair `AuthService::abrirSessao(array $usuario)` de `login()` sem mudar o comportamento. Verificar que o login por senha continua funcionando (smoke `--modo=todos` local) e que `php tests/Unit/checkout_logado_e2e.php` (que faz login) passa.

## 4. Fluxo web

- [x] 4.1 Criar `GoogleOAuthService` (URL de autorização com `state`, `nonce`, PKCE S256 e `prompt=select_account`; troca do código por token via `curl` com timeout de 5 s; nenhum token ou código no log). Verificar com teste unitário da montagem da URL e do `code_challenge` (vetor do RFC 7636).
- [ ] 4.2 Criar `GoogleAuthController` com `GET /login/google` e `GET /login/google/callback` em `routes/web.php`: 404 quando desligado, `state` de uso único e com 10 minutos de validade, destino validado por `SafeRedirect`, mensagens de erro da spec, `AccessLogService` com o evento `login_google` e redirecionamento para `/conta/completar` quando a conta é nova. Verificar manualmente no Docker local com uma conta Google real (vinculada, nova e cancelamento) e com o callback repetido, que deve recusar. — Fluxo automatizado em `tests/Api/google_e2e.php` (11 casos, contra o "Google de teste"); falta só o teste com conta Google real, que depende do projeto no Google Cloud.
- [x] 4.3 Criar os partials `botao-google.php` nos temas caderno e v2, com SVG inline e texto de consentimento com links, e incluí-los em login e cadastro dos dois temas, só quando `google.ativo`. Verificar visualmente no celular (360 px) e no desktop, nos dois temas, e conferir que o botão some sem as chaves.
- [x] 4.4 Adicionar ao smoke (`tests/Smoke/rotas.php`): `/login/google` em `anonimo`, com status 404 quando o ambiente não tem as chaves, ou com `destino` contendo `accounts.google.com` quando tem; documentar a variação em `tests/Smoke/README.md`. Verificar rodando `php tests/Smoke/smoke.php http://127.0.0.1:8010`.

## 5. CPF pendente e conta

- [x] 5.1 Criar `ContaCpfService::informar` (validação, `cpf_ja_informado`, `cpf_em_uso`, `UPDATE` condicional, cópia para `participantes_pedido`, `cadastro_status` completo, sessão, auditoria e liberação de certificados depois do commit com captura de falha). Verificar com `tests/Unit/conta_cpf.php`: os quatro resultados e a corrida de dois `informar` simultâneos (só um grava).
- [x] 5.2 Criar `GET`/`POST /conta/completar` (autenticada) e as views caderno e v2 "Complete seu cadastro", com "Fazer isso depois" preservando o destino. Verificar manualmente no fluxo de conta nova da tarefa 4.2.
- [x] 5.3 Em `/minha-conta` (views caderno, v2 e V1 `auth/account.php`): CPF editável só quando vazio (via `ContaCpfService`), bloco "Conta Google" com e-mail e opção de desvincular só para quem tem senha (desvincular audita e passa pelo `TrashService`). Verificar manualmente os três estados: sem CPF, com CPF, e sem senha com Google.
- [x] 5.4 Criar o lembrete fixo de CPF pendente nas páginas da área do aluno dos dois temas (partial incluído pelo layout do aluno; confirmar antes qual layout envolve essas páginas em cada tema). Verificar que o aviso aparece em "Meus cursos", na página do curso e em "Meus certificados" para um usuário sem CPF e some depois de salvar.
- [x] 5.5 No checkout (normal e rápido), pedir o CPF do pagador quando a conta não tem CPF e, depois de criar o pedido, chamar `ContaCpfService::informar`, sem bloquear o pedido se a gravação for recusada. Verificar com o teste ponta a ponta do checkout logado usando um usuário sem CPF: o pedido é criado e o CPF consta na conta.

## 6. Retenção de certificados

- [x] 6.1 Criar o model `CertificadoRetido` e `CertificadoRetencaoService` (`reter` com `SELECT ... FOR UPDATE` e atualização da retenção ativa, `liberarDoUsuario` com no máximo 5 por chamada, `cancelar` com justificativa via `TrashService`, aviso ao aluno por e-mail com limite de 24 h). Verificar com `tests/Unit/certificado_retencao.php`: reter duas vezes a mesma inscrição gera uma linha; liberar emite e marca `emitido`; uma falha grava `ultima_falha` e mantém `aguardando_cpf`; dois retidos do mesmo aluno geram um único e-mail.
- [x] 6.2 Ligar a retenção em `CertificadoService::emitirInterno` e `emitirRapidaIndividual`: participante sem CPF com conta com CPF usa o CPF da conta e o grava no participante; conta sem CPF retém; participante sem conta emite com o aviso "Certificado emitido sem CPF do participante.". Verificar com teste unitário dos três caminhos e com a emissão manual no admin local.
- [x] 6.3 Ajustar os controllers do admin de certificados (individual, rápida, lote, exceção, reemissão) para exibir a mensagem de retenção e a contagem de retidos no resumo do lote. Verificar manualmente com um lote misto no admin local.
- [x] 6.4 Criar `/admin/certificados/retidos` (lista com aluno, curso, turma, data, autor e última falha, e cancelamento com justificativa), com a permissão de gerir certificados, e um link no menu de certificados. Verificar manualmente e com o smoke em `protegidas` (sem sessão termina no login).
- [x] 6.5 Chamar `liberarDoUsuario` ao abrir a área do aluno e "Meus certificados" (site e `GET /certificados` da API) quando houver retenção ativa e o usuário tiver CPF. Verificar com o teste 6.1 estendido: 7 retidos são liberados em duas aberturas.

## 7. API do app

- [x] 7.1 Criar `POST /auth/google` (`Api\App\AuthController::google`) em `routes/app.php`: validação, limite de taxa por IP, `aud` em `GOOGLE_APP_CLIENT_IDS`, `GoogleLoginService`, `emitirParaLogin`, `usuario_novo`, os códigos de erro da spec e 404 quando desligado. Verificar estendendo `tests/Api/app_api_e2e.php`, usando o verificador com a chave de teste injetada.
- [x] 7.2 Incluir `pendencias` em `UsuarioPresenter::usuario` e criar `POST /me/cpf` (`MeController::cpf`) via `ContaCpfService`. Verificar no teste da API: `GET /me` sem CPF devolve `["cpf"]`; `POST /me/cpf` cobre 200, 422, `409 cpf_em_uso` e `409 cpf_ja_informado`.
- [x] 7.3 Atualizar `openspec/changes/api-app-v1/contrato.md` com `POST /auth/google`, `pendencias`, `POST /me/cpf` e a orientação do SDK (Credential Manager no Android, `aud` = client ID web). Verificar que os exemplos JSON do contrato batem com as respostas reais do teste 7.1 e 7.2.

## 8. Documentação, textos e validação final

- [ ] 8.1 Escrever `docs/2026-10-login-google.md` com o passo a passo do Google Cloud (tela de consentimento, clients web e Android, redirect URIs de produção e local), as chaves do `.env`, o deploy e o rollback. Verificar seguindo o documento para configurar o ambiente local do zero. — Documento escrito em `docs/2026-10-login-google.md`; falta seguir o passo a passo no Google Cloud real (aguardando o responsável criar o projeto).
- [x] 8.2 Revisar todos os textos de interface novos e alterados (botões, mensagens de erro e sucesso, lembrete, e-mails, tela de completar cadastro, admin de retidos) conforme `docs/padrao-editorial-ptbr.md`. Verificar com `grep` pelas mensagens novas e conferindo a acentuação.
- [x] 8.3 Rodar `php -l` em todos os PHP alterados ou criados, nas versões 8.3 (Docker) e 8.4, e `node --check` em qualquer JS alterado. Verificar com zero erros e zero `Deprecated`.
- [x] 8.4 Rodar todos os testes unitários novos, os de checkout e `app_*`, o `tests/Api/app_api_e2e.php` e o smoke local `--modo=todos`. Verificar que todos terminam com código 0.
- [x] 8.5 Rodar `openspec validate login-google --strict`. Verificar sem erros.

## Workflow follow-up

- Arquivar `api-app-v1` antes de arquivar `login-google`, porque o delta `api-app` desta mudança acrescenta requisitos à capability criada por ela.
- Depois do deploy, o responsável ajusta a Política de Privacidade e os Termos de Uso (menção ao login com Google) e, se necessário, a versão do consentimento.
- Mudança futura: "Entrar com a Apple" reutilizando `usuario_identidades`, obrigatória antes de publicar o app no iOS.
