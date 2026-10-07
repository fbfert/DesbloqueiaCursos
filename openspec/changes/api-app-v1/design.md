# App do Aluno — Desbloqueia Cursos — Design

Data: 2026-10-07. Status: aprovado em conversa; o usuário autorizou seguir para a construção
sem novas paradas ("tome as decisões, depois me passe um relatório").

## Objetivo

App nativo para **alunos** do Desbloqueia Cursos (site em produção, com alunos reais):
1. Estar na Play Store.
2. Push notifications.
3. Experiência nativa: telas próprias, leitura offline de textos/PDFs, login persistente com biometria.

Fora do escopo: professor/admin, compra dentro do app (catálogo nativo, compra abre o site no
navegador — evita Google Play Billing para conteúdo digital), vídeo offline (YouTube/Vimeo
não permitem), fluxo legado de aula/módulo (`concluir-aula`/`concluir-modulo`).

Plataforma: Android primeiro; camada de dados em Kotlin Multiplatform (`:shared`) para iOS depois.

## Abordagem escolhida: B — tudo nativo

Inclui quiz, avaliação textual, pedidos (cancelar, comprovante PIX, pagar via AbacatePay no
navegador) e certificados nativos.

## Arquitetura

```
App (este repo)
  :shared  (KMP: androidTarget + iOS declarados)  Ktor client, kotlinx.serialization,
           SQLDelight (cache offline), repositórios, modelos, TokenStore (expect/actual)
  :app     (Compose + Material 3) telas, ViewModels, Navigation, WorkManager (downloads),
           FCM, BiometricPrompt, armazenamento criptografado
        │  HTTPS JSON + Bearer
Site PHP (backend-php/, branch feature/api-app-v1)
  /api/app/v1/*  Controllers/Api/App/*  →  Services existentes
  AppTokenService + middleware auth.app (sem sessão, sem CSRF)
  PushService (FCM HTTP v1, JWT com openssl) + app_dispositivos + app_notificacoes
```

Princípios:
- A API **não duplica regra de negócio**: controllers finos chamam os Services do site. Regras presas
  em controllers HTML são extraídas para Services e o controller HTML passa a usá-los também.
- **Site em produção:** nenhuma mudança de comportamento no site HTML. Refatorações são
  extrações puras, cobertas por teste. Nada de deploy, nada de migration em produção, nada na `main`.
- Compatibilidade: PHP sem Composer, MySQL 5.7, sintaxe compatível com o resto do código.
- App offline-first nas leituras (cache SQLDelight, mostra cache e atualiza em background);
  "concluir item" entra em fila local quando offline e sincroniza depois.
- Versão no caminho (`/v1`) e `/config` com versão mínima para forçar atualização.

## Contrato

`docs/api-app-v1.md` (cópia em `backend-php/openspec/changes/api-app-v1/`).

## Decisões tomadas (autonomamente, com justificativa)

| Decisão | Por quê |
|---|---|
| Tokens opacos aleatórios (não JWT), guardados como SHA-256 em `app_tokens` | Revogáveis no banco, sem lib, nada sensível em claro |
| Access 1 h, refresh 60 dias rotativo, reuso ⇒ revoga dispositivo | Login persistente sem token eterno; detecta vazamento |
| Só GET/POST | O `Router` do site só suporta esses verbos |
| `Router::postApp` em vez de `postWithoutCsrf` | `postWithoutCsrf` é reservado a webhook |
| Identidade da requisição via `AppAuth` (contexto por requisição), sem `session_start` em `/api/app/*` | Não criar sessão/cookie para cada chamada do app; serviços que leem sessão recebem o id explicitamente |
| Push: grava em `app_notificacoes` e tenta envio imediato com timeout curto; script de cron reenvia pendentes | Não há worker no servidor; falha de FCM nunca quebra a ação do admin |
| `FCM_ENABLED=false` por padrão | Só liga após configurar o projeto Firebase na VPS |
| Firebase no app só ativa se `app/google-services.json` existir | Projeto compila sem a conta Firebase criada |
| Biometria é local (destrava o refresh token guardado) | Servidor não precisa saber de biometria |
| PDF de certificado na API exige dono | A rota pública do site não é alterada (fora do escopo) |

## Testes

- Backend: testes unitários no estilo do repo (`php tests/Unit/*.php`) + teste de integração da API
  contra MySQL 5.7 em Docker com as migrations aplicadas e dados de fixture; `php -l` em tudo.
- App: testes unitários do `:shared` (repositórios com Ktor MockEngine), testes de ViewModel,
  `assembleDebug` e `lint`; teste de ponta a ponta contra o backend local em Docker.
