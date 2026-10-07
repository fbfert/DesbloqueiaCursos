# Tarefas

Contexto obrigatório: `contrato.md` (formato exato de cada endpoint), `design.md`, `proposal.md` e
`specs/` desta mudança. Nada é aplicado em produção; trabalho só na branch `feature/api-app-v1`.

## 1. Base

- [x] 1.1 Migração `sql/082_app_mobile.sql` (`app_tokens`, `app_dispositivos`, `app_notificacoes`, `app_limites_taxa`), aditiva, MySQL 5.7/MariaDB 10.5; aplicar num banco novo montado com todas as migrações.
- [x] 1.2 Bootstrap: sem `session_start` (e sem captura de origem de tráfego) em `/api/app/*`; `$_SESSION` vazio só em memória.
- [x] 1.3 `Router::postApp()` / `App::postApp()`; 404 JSON para `/api/app/*` desconhecido; tratador de exceção/erro JSON (`500 erro_interno`, log via `Logger`).
- [x] 1.4 `AppTokenService` (emitir, renovar com rotação, detectar reuso, revogar dispositivo/outros) + model `AppToken`.
- [x] 1.5 `AppAuthenticateMiddleware` (`auth.app`) + `App\Support\AppAuth` + versão mínima (`426`); auditoria dos eventos de segurança.
- [x] 1.6 `AppLimiteTaxaService` (por IP e por login) para login, cadastro e recuperação de senha.
- [x] 1.7 `.htaccess`: repassar `Authorization` ao PHP.

## 2. Extrações (sem mudança de comportamento no site)

- [x] 2.1 `AuthService::autenticarCredenciais()` (núcleo do login sem sessão); `login()` usa e mantém mensagens.
- [x] 2.2 `ConteudoAcessoAlunoService`: contexto da inscrição, navegação anterior/próximo, abertura de item (registro de acesso, auto-conclusão de texto/HTML, entregas da avaliação), arquivo do item; `AreaCursoController` passa a usar.
- [x] 2.3 `PedidoService::pedidoPodeSerCanceladoPeloAluno()` / `cancelarPeloAluno()` usados por `MeusCursosController`, `V2\AlunoController` e API.
- [x] 2.4 `PagamentoAbacatepayService::iniciarParaPedido()` usado por `CheckoutController::pagarAbacatepay` e pela API.

## 3. Endpoints (`app/Controllers/Api/App/`)

- [x] 3.1 Presenters (`App\Support\AppApi\`) e resposta padrão (`data`/`meta`/`erro`).
- [x] 3.2 Auth: login, refresh, logout, cadastro, recuperar-senha.
- [x] 3.3 Config e perfil (`/me`, `/me/senha`).
- [x] 3.4 Inscrições, árvore, abrir item, concluir, arquivo.
- [x] 3.5 Quiz (estado, iniciar, rascunho, tempo, enviar, tentativa).
- [x] 3.6 Avaliação textual (estado, envio multipart, imagem).
- [x] 3.7 Pedidos (lista, cancelar, comprovante, AbacatePay).
- [x] 3.8 Certificados (lista, PDF só do dono) e catálogo público.
- [x] 3.9 Dispositivos e notificações.

## 4. Push

- [x] 4.1 `PushService` (FCM HTTP v1, JWT RS256 com openssl, cache do token OAuth2, timeouts curtos, transporte injetável, nunca lança).
- [x] 4.2 Ganchos PT-BR: pedido aprovado/pago, comprovante reprovado, avaliação corrigida, atividade corrigida/devolvida, quiz corrigido, certificado emitido, conteúdo novo (só enfileira).
- [x] 4.3 `scripts/push_reenviar_pendentes.php` (cron) e variáveis no `.env.example`.

## 5. Testes e fechamento

- [x] 5.1 Unitários: `app_token_service`, `app_push_service`, `app_presenters`, `app_extracoes` (paridade das extrações).
- [x] 5.2 Integração: `tests/Api/montar_banco.php` + fixtures + `tests/Api/app_api_e2e.php` cobrindo todo o contrato (401/403/404/422/423/426/429 inclusos); `tests/Api/README.md`.
- [x] 5.3 Smoke anônimo contra o servidor local (site HTML intacto) e rota nova crítica no smoke (`/api/app/v1/config`).
- [x] 5.4 Todos os `tests/Unit/*.php` existentes executados; nenhuma regressão causada pela mudança.
- [x] 5.5 `php -l` em todos os PHP alterados/criados (8.4, sem `Deprecated`).
- [x] 5.6 Revisão dos textos PT-BR (mensagens da API e do push) com acentuação correta.
- [x] 5.7 `docs/2026-10-07-api-app-mobile.md`: Firebase, variáveis, cron, migração, roteiro de deploy e rollback.
