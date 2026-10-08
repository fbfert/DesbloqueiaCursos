# Proposal

## Why

O Desbloqueia Cursos vai ganhar um app nativo para alunos (Android primeiro, camada de dados em
Kotlin Multiplatform). O app precisa estar na Play Store, receber notificações push e oferecer
experiência nativa com login persistente e leitura offline. Hoje o site só fala HTML com sessão PHP
e CSRF; não existe API que um app consiga consumir com segurança. Esta mudança cria a API
`/api/app/v1` descrita em `contrato.md` (fonte única de verdade entre backend e app) e o envio de
push, sem duplicar regra de negócio e sem mudar o comportamento do site HTML em produção.

## What Changes

- **Autenticação do app sem sessão**: tokens opacos (`random_bytes(32)`, só o SHA-256 no banco),
  access de 1 h e refresh rotativo de 60 dias por dispositivo; reuso de refresh já usado revoga
  todos os tokens do dispositivo (`401 sessao_revogada`). Login, cadastro e recuperação de senha
  reaproveitam `AuthService` (mesma `politica_login`, bloqueio por tentativas, status `ativo`), com
  limite de taxa por IP e por login (`429` com `Retry-After`).
- **Infraestrutura de API**: `Router::postApp()` (POST sem CSRF, distinto de `postWithoutCsrf`, que
  segue reservado a webhook); middleware `auth.app` (Bearer, `401 token_expirado`/`nao_autenticado`,
  `426 atualizacao_obrigatoria` por versão mínima); bootstrap sem `session_start` em `/api/app/*`;
  tratador de erro JSON (`500 erro_interno`, sem vazar PHP) e `404` JSON para caminhos desconhecidos.
- **Endpoints do contrato** em `app/Controllers/Api/App/` com uma camada de apresentação
  (`App\Support\AppApi\*Presenter`) que fixa o formato JSON: config, perfil, inscrições e árvore de
  conteúdo, abrir/concluir item, download de arquivo, quiz completo, avaliação textual com imagens,
  pedidos (cancelar, comprovante PIX, AbacatePay), certificados (PDF só do dono), catálogo público,
  dispositivos e histórico de notificações.
- **Extrações puras** (o controller HTML passa a usar o mesmo Service, sem mudança de comportamento):
  `ConteudoAcessoAlunoService` (contexto da inscrição, navegação anterior/próximo, abertura de item
  com auto-conclusão de texto/HTML, resolução do arquivo do item); regras de cancelamento do pedido
  pelo aluno em `PedidoService` (lista de status antes duplicada em `MeusCursosController` e no
  `V2\AlunoController`); `PagamentoAbacatepayService::iniciarParaPedido` (antes preso em
  `CheckoutController::pagarAbacatepay`); `AuthService::autenticarCredenciais` (núcleo do login sem
  escrever sessão; `login()` continua igual).
- **Push (FCM HTTP v1)**: `PushService` grava toda notificação em `app_notificacoes` e tenta o envio
  com timeout curto (conexão 2 s, total 4 s); nunca lança exceção para quem chamou. JWT RS256 com
  `openssl_sign`, token OAuth2 em cache em `storage/cache`. Ganchos com mensagens PT-BR em: pedido
  aprovado/pago (aprovação manual, comprovante aprovado, gateway, status), comprovante reprovado,
  avaliação textual corrigida, atividade corrigida/devolvida, quiz discursivo corrigido, certificado
  emitido e conteúdo novo publicado (este só enfileira; o cron envia). Script de cron
  `scripts/push_reenviar_pendentes.php` reenvia pendentes/falhas.
- **Migração `sql/082_app_mobile.sql`** (aditiva): `app_tokens`, `app_dispositivos`,
  `app_notificacoes`, `app_limites_taxa`.
- **Testes**: unitários em `tests/Unit/` e teste de ponta a ponta `tests/Api/app_api_e2e.php` contra
  MariaDB 10.5 em Docker com todas as migrações aplicadas.

**Fora de escopo:** área do professor/revisor/admin no app; compra dentro do app (o catálogo é só
leitura e a compra abre o site); vídeo offline; fluxo legado de aula/módulo
(`concluir-aula`/`concluir-modulo`); alteração da rota pública `/certificados/pdf`; verbos HTTP além
de GET/POST; worker de fila residente (o envio pendente roda por cron); deploy.

## Capabilities

### New Capabilities
- `api-app`: API JSON `/api/app/v1` para o app do aluno (autenticação por token, perfil, cursos,
  conteúdo, quiz, avaliação, pedidos, certificados, catálogo).
- `notificacoes-push`: registro de dispositivos, histórico de notificações e envio por FCM.

### Modified Capabilities
(nenhuma — as extrações não mudam requisitos do site)

## Impact

- **Migração nova**: `sql/082_app_mobile.sql` (só `CREATE TABLE IF NOT EXISTS`; rollback =
  `DROP TABLE` das quatro tabelas, sem efeito no site). O código tolera a ausência das tabelas: os
  ganchos de push só registram erro no log.
- **Configuração nova** (`.env.example`): `APP_MOBILE_VERSAO_MINIMA_ANDROID`,
  `APP_MOBILE_VERSAO_ATUAL_ANDROID`, `APP_MOBILE_URL_SUPORTE`, `APP_MOBILE_URL_TERMOS`,
  `APP_MOBILE_URL_PRIVACIDADE`, `FCM_ENABLED=false`, `FCM_PROJECT_ID`, `FCM_SERVICE_ACCOUNT_PATH`.
- **Servidor**: `.htaccess` passa o cabeçalho `Authorization` ao PHP (Apache + PHP-FPM o descartam
  por padrão); cron novo para push pendente. Ver `docs/2026-10-07-api-app-mobile.md`.
- **Site HTML**: sem mudança de comportamento. Controllers alterados só por extração:
  `AreaCursoController`, `MeusCursosController`, `V2\AlunoController`, `CheckoutController`.
- **Produção**: nada é aplicado por esta mudança; deploy e migração seguem o roteiro do doc.
