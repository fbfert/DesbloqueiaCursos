# Testes da API do app (`/api/app/v1`)

Ambiente local completo para a API do app do aluno: MariaDB 10.5 em Docker com **todas** as
migrações de `sql/`, fixtures com alunos e cursos de teste e o servidor embutido do PHP.
É também o backend que o time do app usa no emulador Android (`http://10.0.2.2:8099`).

Nada aqui fala com produção. O banco precisa terminar em `_teste` e os scripts recusam outro.

## 1. Banco (uma vez)

```bash
docker run -d --name desbloqueia-test-db \
  -e TZ=America/Sao_Paulo -e MARIADB_ROOT_PASSWORD=teste_root_123 -e MARIADB_DATABASE=desbloqueia_app_teste \
  -p 33061:3306 mariadb:10.5 \
  --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci --default-time-zone=-03:00

php tests/Api/montar_banco.php          # recria a base, aplica sql/*.sql em ordem e as fixtures
php tests/Api/montar_banco.php --so-fixtures   # só reaplica as fixtures (volta ao estado conhecido)
```

O fuso `-03:00` reproduz a VPS (MySQL em horário de Brasília, PHP em UTC).

## 2. Servidor

```bash
php -S 127.0.0.1:8099 -t . tests/Api/router.php
```

`tests/Api/router.php` define o ambiente de teste (banco do container, e-mail, AbacatePay e FCM
desligados, `APP_URL=http://10.0.2.2:8099`) antes de carregar o app — um `.env` local não é usado.
Para um aparelho físico na mesma rede, suba em `0.0.0.0:8099` e use o IP da máquina.

## 3. Usuários de teste (senha de todos: `Local@12345`)

| Login | Para quê |
|---|---|
| `aluno.caderno@teste.local` (CPF 987.654.321-00) | aluna principal: 3 inscrições (9001 com todos os tipos de conteúdo, quizzes 9011–9018, avaliações 9021–9028), 8 pedidos em vários status, certificado `CADFIX-2026-0001` |
| `aluno.b@teste.local` | outro aluno: inscrição 9101 e certificado `APPFIX-2026-0101` (para testar isolamento) |
| `inativo@teste.local` | conta inativa (403 `conta_inativa`) |
| `bloqueio@teste.local` | usado no teste de bloqueio por tentativas (423 `conta_bloqueada`) |

Os testes alteram dados (concluem itens, enviam quiz, cancelam pedido). Rode `--so-fixtures`
para voltar ao estado inicial. Limites de taxa ficam em `app_limites_taxa`
(`DELETE FROM app_limites_taxa` libera um 429 durante testes manuais).

## 4. Testes

```bash
php tests/Api/app_api_e2e.php      # todos os endpoints do contrato + 401/403/404/422/423/426/429/500
php tests/Api/paridade_html.php    # site HTML antes (fb7b504) x depois das extrações: respostas e efeitos no banco

# unitários (os que usam banco apontam para o container)
export DB_HOST=127.0.0.1 DB_PORT=33061 DB_DATABASE=desbloqueia_app_teste DB_USERNAME=root DB_PASSWORD=teste_root_123
php tests/Unit/app_presenters.php
php tests/Unit/app_token_service.php
php tests/Unit/app_push_service.php
php tests/Unit/app_extracoes.php

# smoke do site (modo anônimo) contra o servidor local
php tests/Smoke/smoke.php http://127.0.0.1:8099
```

## 5. Exemplos rápidos

```bash
curl -s http://127.0.0.1:8099/api/app/v1/config
curl -s -X POST http://127.0.0.1:8099/api/app/v1/auth/login -H 'Content-Type: application/json' \
  -d '{"login":"aluno.caderno@teste.local","senha":"Local@12345","device_id":"emulador-0001","device_name":"Pixel"}'
curl -s http://127.0.0.1:8099/api/app/v1/inscricoes -H 'Authorization: Bearer <access_token>' -H 'X-App-Version: 1.0.0 (1)'
```

Push: com `FCM_ENABLED=false` as notificações são só gravadas (aparecem em `GET /notificacoes`).
Para testar FCM de verdade, exporte `FCM_ENABLED=true`, `FCM_PROJECT_ID` e
`FCM_SERVICE_ACCOUNT_PATH` antes de subir o servidor e rode `php scripts/push_reenviar_pendentes.php`
com as mesmas variáveis `DB_*` do container.
