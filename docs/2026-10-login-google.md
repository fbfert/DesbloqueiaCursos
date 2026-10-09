# Login com Google (site e app)

Mudança OpenSpec `login-google` (`openspec/changes/login-google/`). Este documento é o roteiro de
**configuração, deploy, verificação e rollback**. Regras de negócio e decisões técnicas estão na
proposta, nas specs e no `design.md` da mudança.

## O que o recurso faz

- Botão **"Entrar com Google"** nas telas de login e cadastro (temas caderno e v2), para todos os
  perfis. O destino depois de entrar segue as permissões, como no login por senha.
- **Conta já vinculada** entra pelo identificador fixo da pessoa no Google (`sub`).
- **Conta existente com o mesmo e-mail verificado** é vinculada automaticamente; a pessoa recebe o
  e-mail "Sua conta foi vinculada ao Google" e o vínculo vai para a auditoria.
- **Pessoa nova** vira aluno sem senha e sem CPF, e vê a tela "Complete seu cadastro" (com "Fazer isso
  depois"). Enquanto não informar o CPF, a área do aluno mostra o aviso "Falta o seu CPF".
- **Certificado de aluno sem CPF fica retido** quando a secretaria emite, e é emitido sozinho quando o
  aluno informa o CPF. Lista em **Admin → Certificados → Aguardando CPF** (`/admin/certificados/retidos`).
- **App:** `POST /api/app/v1/auth/google`, `usuario.pendencias` e `POST /api/app/v1/me/cpf` — ver
  `openspec/changes/api-app-v1/contrato.md`.

Sem as chaves `GOOGLE_*` no `.env` o recurso fica **desligado**: o botão some e as rotas respondem 404.
A retenção de certificados e as correções de CPF vazio valem com ou sem as chaves.

## 1. Google Cloud (uma vez)

No [Google Cloud Console](https://console.cloud.google.com/), com a conta da instituição:

1. **Projeto:** crie (ou escolha) um projeto, por exemplo `Desbloqueia Cursos`.
2. **Google Auth Platform → Branding** (antiga "Tela de consentimento OAuth"):
   - nome do app: `Desbloqueia Cursos`; e-mail de suporte; logo (opcional — logo exige verificação
     da marca pelo Google, que leva alguns dias);
   - **domínio autorizado:** `desbloqueiacursos.com.br`;
   - links da **página inicial**, **Política de Privacidade** e **Termos de Uso** do portal
     (`https://desbloqueiacursos.com.br/v2/politica-de-privacidade` e `.../v2/termos-de-uso`).
3. **Audience (Público):** tipo **Externo** e **Publicar o app** ("Em produção"). Em modo "Teste",
   só os e-mails cadastrados como testadores conseguem entrar.
4. **Data access (Escopos):** apenas `openid`, `.../auth/userinfo.email` e `.../auth/userinfo.profile`.
   São escopos não sensíveis: não exigem verificação do Google.
5. **Clients → Create client → Web application** (`Desbloqueia Cursos — site`):
   - **Authorized redirect URIs** — precisam bater exatamente com `APP_URL` + `/login/google/callback`:
     - `https://desbloqueiacursos.com.br/login/google/callback`
     - para testar localmente no Docker: `http://127.0.0.1:8010/login/google/callback`
   - Ao criar, copie o **Client ID** e o **Client secret**. O secret só aparece nessa hora; guarde num
     cofre de senhas. Se perder, gere outro no mesmo client.
6. **Clients → Create client → Android** (`Desbloqueia Cursos — app Android`), quando o app for
   integrar: nome do pacote do app e **SHA-1** do certificado de assinatura (o de upload e o da Play
   Store, se usar a assinatura do Google Play). Esse client não tem secret.
7. **iOS**, quando houver: client do tipo iOS com o Bundle ID. Lembrete: com login do Google no app
   iOS, a App Store exige também "Entrar com a Apple" (mudança futura).

## 2. `.env` do servidor

```dotenv
GOOGLE_CLIENT_ID=123456789012-xxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx
# Client IDs aceitos no app: o client WEB (o Android usa ele como aud) e, quando houver, o iOS.
GOOGLE_APP_CLIENT_IDS=123456789012-xxxxxxxx.apps.googleusercontent.com
```

- Só `GOOGLE_CLIENT_ID` + `GOOGLE_CLIENT_SECRET` → liga o site. Só `GOOGLE_APP_CLIENT_IDS` → liga o app.
- `APP_URL` precisa ser a URL pública exata (com `https`), porque compõe o endereço de retorno.
- O servidor precisa alcançar `https://oauth2.googleapis.com` e `https://www.googleapis.com` (troca
  do código e chaves públicas). As chaves ficam em cache em `storage/cache/google_jwks.json`.
- `GOOGLE_JWKS_ARQUIVO_TESTE` e `GOOGLE_TOKEN_URL_TESTE` são só dos testes automatizados e são
  **ignoradas** com `APP_ENV=production`. Nunca as defina no servidor.

## 3. Deploy

1. Backup do banco (como sempre).
2. Aplicar `sql/083_login_google.sql` (ver `docs/deploy.md` §3.5). É aditiva e idempotente.
3. Publicar o código.
4. Rodar o smoke **sem** as chaves: `/login/google` precisa responder 404.
5. Preencher as chaves no `.env` (seção 2).
6. Rodar o smoke **com** o recurso ligado:
   `SMOKE_GOOGLE=ativo php tests/Smoke/smoke.php https://desbloqueiacursos.com.br` — `/login/google`
   precisa terminar em `accounts.google.com`.
7. Teste manual com uma conta Google de teste: pessoa nova (tela "Complete seu cadastro"), conta já
   cadastrada com o mesmo e-mail (chega o e-mail de vínculo) e cancelamento na tela do Google.
8. Conferir `storage/logs/app-AAAA-MM-DD.log` (eventos `Login Google: ...`).

## 4. Rollback

- **Desligar na hora:** apagar `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` e `GOOGLE_APP_CLIENT_IDS` do
  `.env`. Quem já tem conta Google vinculada continua podendo entrar por e-mail/CPF e senha, se tiver
  senha; quem foi criado pelo Google usa "Esqueci minha senha" para criar uma.
- **Código:** reverter o deploy não exige reverter a 083 (as tabelas novas podem ficar).
- **`usuarios.cpf` de volta a `NOT NULL`:** só se `SELECT COUNT(*) FROM usuarios WHERE cpf IS NULL`
  der 0.

## 5. Testes automatizados

Sem Google real — um "Google de teste" assina tokens com chave própria:

```bash
# unitários (sem banco)
php tests/Unit/google_id_token.php
php tests/Unit/google_oauth.php
# unitários com banco de teste (tests/Api/README.md)
php tests/Unit/usuario_cpf_nulo.php
php tests/Unit/google_login_service.php
php tests/Unit/certificado_retencao.php
# ponta a ponta: site + API do app
php tests/Api/montar_banco.php
php -S 127.0.0.1:8099 -t . tests/Api/router.php
php -S 127.0.0.1:8098 tests/Api/google_stub.php
php tests/Api/google_e2e.php
```

No Windows, o PHP local pode não ter o pacote de certificados (CA) para falar com o Google real; os
testes acima não dependem disso. Para o smoke com `SMOKE_GOOGLE=ativo` a partir do Windows, use
`php -d curl.cainfo="C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt" tests/Smoke/smoke.php ...`.
