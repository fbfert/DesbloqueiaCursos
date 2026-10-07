# API do app do aluno (`/api/app/v1`) e push

Data: 2026-10-07 · Branch: `feature/api-app-v1` · Mudança OpenSpec: `openspec/changes/api-app-v1/`

Backend do app nativo de alunos (Android primeiro). O contrato com o app — formato de cada
endpoint — está em `openspec/changes/api-app-v1/contrato.md` e é a fonte única de verdade.
**Nada desta mudança foi aplicado em produção.** Este documento é o roteiro para quando for.

## 1. O que entra no servidor

| Peça | Onde |
|---|---|
| Rotas | `routes/app.php` (carregado por `index.php`) |
| Controllers (finos) | `app/Controllers/Api/App/*` |
| JSON do contrato | `app/Support/AppApi/*Presenter.php`, `Resposta`, `Tempo`, `Formato`, `ArquivoResposta`, `Versao` |
| Autenticação | `AppTokenService`, `AppAuthenticateMiddleware` (`auth.app`), `App\Support\AppAuth` |
| Limite de taxa | `AppLimiteTaxaService` (login, cadastro, recuperar senha) |
| Push | `PushService` (FCM HTTP v1), `PushEventosService` (eventos do site), `scripts/push_reenviar_pendentes.php` |
| Extrações usadas também pelo site | `ConteudoAcessoAlunoService`, `PedidoService::cancelarPeloAluno`, `PagamentoAbacatepayService`, `AuthService::autenticarCredenciais` |
| Banco | `sql/082_app_mobile.sql` |

### Como a API se comporta

- **Sem sessão PHP** em `/api/app/*`: `index.php` não chama `session_start` (nenhum `Set-Cookie`,
  nenhum arquivo de sessão por chamada). `$_SESSION` existe só em memória e vazio; a identidade vem
  de `App\Support\AppAuth`, preenchido pelo middleware `auth.app`. Os Services recebem o id do
  usuário por parâmetro.
- **Sem CSRF**: POSTs registrados com `$app->postApp()` (só aceita caminhos `/api/app/*`).
  `postWithoutCsrf` continua reservado a webhook.
- **Tokens opacos** (32 bytes aleatórios); no banco só o SHA-256. Access 1 h, refresh 60 dias
  rotativo. Reuso de refresh já trocado revoga todos os tokens do aparelho (`401 sessao_revogada`).
  Novo login no mesmo `device_id` derruba a sessão anterior dele. Troca de senha pelo app revoga
  os outros aparelhos.
- **Erros sempre em JSON**: caminho desconhecido → `404 nao_encontrado`; exceção/aviso do PHP →
  `500 erro_interno` sem detalhe (o detalhe vai para `storage/logs`).
- **Versão mínima**: build de `X-App-Version: 1.0.0 (N)` menor que `APP_MOBILE_VERSAO_MINIMA_ANDROID`
  → `426` em toda rota autenticada. Sem o cabeçalho, não bloqueia.
- **Datas**: ISO-8601 com fuso (`APP_TIMEZONE`, padrão `America/Sao_Paulo`). Colunas gravadas
  pelo PHP (`date()`, UTC na VPS) e pelo MySQL (`NOW()`, Brasília) são lidas cada uma no seu fuso
  (ver `docs/2026-08-15-fuso-horario-php-mysql.md` e `App\Support\AppApi\Tempo`).

## 2. Variáveis de ambiente (`.env`)

```dotenv
APP_TIMEZONE=America/Sao_Paulo
APP_MOBILE_VERSAO_MINIMA_ANDROID=1      # versionCode mínimo aceito (426 abaixo disso)
APP_MOBILE_VERSAO_ATUAL_ANDROID=1       # informado em GET /config
APP_MOBILE_URL_SUPORTE=                 # padrão: <APP_URL>/contato
APP_MOBILE_URL_TERMOS=                  # padrão: <APP_URL>/v2/termos-de-uso
APP_MOBILE_URL_PRIVACIDADE=             # padrão: <APP_URL>/v2/politica-de-privacidade
APP_MOBILE_CABECALHO_IP=                # vazio = REMOTE_ADDR; atrás de CDN: ex. CF-Connecting-IP

FCM_ENABLED=false
FCM_PROJECT_ID=
FCM_SERVICE_ACCOUNT_PATH=
```

`APP_KEY` precisa estar preenchida (já é obrigatória): é o sal dos contadores do limite de taxa.
`APP_URL` é usada nas URLs absolutas (imagens, validação de certificado, `url_compra`).

## 3. Firebase (push)

1. No [console do Firebase](https://console.firebase.google.com/), criar (ou abrir) o projeto do app
   e registrar o app Android com o `applicationId` do app. O `google-services.json` vai para o
   projeto Android (time do app).
2. Configurações do projeto → **Contas de serviço** → *Gerar nova chave privada*. Baixa um JSON com
   `client_email`, `private_key`, `token_uri`.
3. Copiar o JSON para a VPS **fora do docroot** (a raiz do repositório é o docroot), por exemplo
   `/home/<usuario>/private/firebase-desbloqueia.json`, com `chmod 600` e dono = usuário do PHP.
   Nunca versionar.
4. No `.env`: `FCM_PROJECT_ID=<id do projeto>`, `FCM_SERVICE_ACCOUNT_PATH=<caminho absoluto>`,
   `FCM_ENABLED=true`.
5. Testar: registrar um aparelho pelo app, disparar um evento (ex.: aprovar um pedido de teste) e
   conferir `app_notificacoes.envio_status = 'enviado'`. Erros vão para o log
   (`push.oauth_falhou`, `push.envio_falhou`, `push.conta_servico_ausente`).

O token OAuth2 do Google fica em cache em `storage/cache/fcm_access_token.json` (`chmod 600`).
A conta de serviço só precisa do papel padrão criado pelo Firebase (Firebase Cloud Messaging API
habilitada no Google Cloud do projeto).

### Quando o push é disparado

| Evento | Onde | Tipo |
|---|---|---|
| Pedido aprovado/pago (aprovação manual, comprovante aprovado, gateway, status) | `PedidoService::aprovarPedido`, `registrarStatus`, `confirmarPagamentoGateway`, `ComprovantePixService::aprovar` | `pedido_aprovado` |
| Comprovante PIX reprovado | `ComprovantePixService::reprovar` | `comprovante_reprovado` |
| Avaliação textual corrigida/devolvida | `ConteudoAvaliacaoTextualService::corrigirEntrega` | `avaliacao_corrigida` |
| Atividade corrigida/devolvida | `AtividadeService::corrigirEntrega/devolverEntrega` | `atividade_corrigida` |
| Última discursiva do quiz corrigida | `ConteudoQuizDiscursivaService::corrigir` | `quiz_corrigido` |
| Certificado emitido | `InscricaoService::registrarStatus` (`certificado_emitido`) | `certificado_emitido` |
| Item publicado (criado publicado ou passou a publicado) | `ConteudoCursoService::salvarItemComDetalhes` | `conteudo_novo` (só enfileira) |

Regras: o aviso é disparado **depois do commit** da ação; nunca lança exceção (falha só vai para o
log); gasta no máximo ~5 s dentro da requisição de origem; `conteudo_novo` só grava as
notificações como pendentes (pode ser a turma inteira) e o cron envia.

## 4. Cron

```cron
*/5 * * * * cd /caminho/do/site && /usr/bin/php8.3 scripts/push_reenviar_pendentes.php >> storage/logs/cron-push.log 2>&1
```

(Use o mesmo PHP do domínio no Virtualmin.) O script: envia pendentes, reenvia falhas até 5
tentativas, descarta (marca `falhou`/`expirada_sem_envio`) as com mais de 48 h, remove tokens
vencidos/revogados há mais de 30 dias e contadores antigos do limite de taxa. Usa lock em
`storage/tmp`, então execuções sobrepostas não duplicam envio. Com `FCM_ENABLED=false` só faz a
limpeza.

## 5. Apache: cabeçalho Authorization

Com PHP-FPM/CGI o Apache descarta `Authorization`, e todo Bearer viraria `401`. O `.htaccess` da
raiz agora repassa o cabeçalho (`SetEnvIf` + `RewriteRule ... [E=HTTP_AUTHORIZATION:...]`). Se o
Virtualmin usar outra configuração, a alternativa é `CGIPassAuth On` no vhost. O middleware também
lê `REDIRECT_HTTP_AUTHORIZATION` e `apache_request_headers()`.

Teste depois do deploy:

```bash
curl -s https://desbloqueiacursos.com.br/api/app/v1/config
curl -s -o /dev/null -w '%{http_code}\n' https://desbloqueiacursos.com.br/api/app/v1/me      # 401
# com um token de teste: deve dar 200, não 401
curl -s https://desbloqueiacursos.com.br/api/app/v1/me -H 'Authorization: Bearer <token>'
```

## 6. Migração 082

`sql/082_app_mobile.sql` — só `CREATE TABLE IF NOT EXISTS` (`app_tokens`, `app_dispositivos`,
`app_notificacoes`, `app_limites_taxa`), utf8mb4, compatível com MySQL 5.7/MariaDB 10.5. Pode rodar
mais de uma vez. Testada num banco novo montado com as 83 migrações em ordem (0 erros).

O código tolera a ausência das tabelas: os ganchos de push só registram erro no log; as rotas da
API que dependem delas respondem `500 erro_interno`. Ou seja, **aplicar a migração antes de
publicar o código** (ou junto) — o site HTML não depende dela.

## 7. Roteiro de deploy (checklist)

1. Backup do banco e dos arquivos (ver `docs/deploy.md`).
2. Aplicar `sql/082_app_mobile.sql`.
3. Publicar o código da branch (arquivos novos e alterados — `git diff --stat fb7b504..feature/api-app-v1`),
   incluindo `.htaccess`, `index.php` e `routes/app.php`.
4. `.env`: variáveis da seção 2 (com `FCM_ENABLED=false` no primeiro momento).
5. Conferir o repasse do `Authorization` (seção 5) e `GET /api/app/v1/config`.
6. Smoke: `php tests/Smoke/smoke.php https://desbloqueiacursos.com.br` (inclui as rotas da API).
7. Login de teste pela API com um usuário de teste (nunca aluno real), `GET /inscricoes`, abrir um
   item, `POST /auth/logout`.
8. Firebase (seção 3), `FCM_ENABLED=true`, cron (seção 4). Testar um push com aparelho de teste.
9. Publicar o app com `versionCode` ≥ `APP_MOBILE_VERSAO_MINIMA_ANDROID`.

## 8. Rollback

- Código: voltar os arquivos alterados; o site HTML só depende das extrações, que têm paridade
  testada (`tests/Api/paridade_html.php`). Remover `require routes/app.php` desliga a API inteira.
- Push: `FCM_ENABLED=false` para de enviar imediatamente (as notificações continuam gravadas).
- App desatualizado ou incidente: subir `APP_MOBILE_VERSAO_MINIMA_ANDROID` força atualização;
  `DELETE FROM app_tokens` (ou `UPDATE app_tokens SET revogado_em = NOW()`) desloga todos os apps.
- Banco: `DROP TABLE app_limites_taxa, app_notificacoes, app_dispositivos, app_tokens;` — nenhuma
  tabela do site é alterada pela 082.

## 9. Testes

Ver `tests/Api/README.md` (MariaDB 10.5 em Docker, servidor `php -S`, usuários de teste). Resumo:
`tests/Api/app_api_e2e.php` cobre todo o contrato; `tests/Api/paridade_html.php` prova que o site
HTML responde e grava igual ao commit anterior; unitários `tests/Unit/app_*.php`.

## 10. Limitações conhecidas

- **Datas mistas no banco**: a API corrige a leitura por coluna, mas o defeito de fundo (PHP sem
  `date.timezone`) continua; configurar `date.timezone = America/Sao_Paulo` no PHP da VPS exige
  revisar `Tempo::isoPhp` (passaria a ler esse fuso automaticamente, sem mudança de código).
- **HTML de itens do tipo `html`**: no site rodam num iframe com scripts; na API saem sanitizados
  (`HtmlSanitizer`), como pede o contrato — conteúdo interativo com `<script>` perde a interação.
- **`pix.copia_e_cola`** é sempre `null`: o PIX é manual (chave fixa em `PedidoService::CHAVE_PIX`).
- **Cancelamento e comprovante pelo app**: o contrato não define corpo; aceitam `motivo` e
  `motivo_reenvio` opcionais (padrões "Cancelado pelo aluno no aplicativo." / "Reenvio pelo
  aplicativo.").
- **Refresh concorrente**: duas renovações simultâneas com o mesmo refresh são tratadas como reuso
  e derrubam o aparelho — o app deve serializar o refresh.
- **Limite de taxa** conta pelo IP do socket; atrás de CDN configure `APP_MOBILE_CABECALHO_IP`.
- **PDF de certificado sem logo**: o PNG transparente embutido em
  `CertificadoService::logoTransparenteDataUri()` tem CRC inválido; com libpng estrito (visto no
  ambiente local) o PDF falha — também na rota pública do site. Em produção há logo configurado.
  Não corrigido nesta mudança (fora do escopo); a fixture de teste configura um logo.
