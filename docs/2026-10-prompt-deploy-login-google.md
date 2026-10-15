# Prompt — deploy do login com Google

Cole o bloco abaixo numa sessão do Claude Code aberta na raiz do repositório
(`DesbloqueiaCursos`, branch `frontend-v4`), com acesso SSH à VPS.

```text
Vamos fazer o deploy da mudança OpenSpec "login-google" (commit 4902685 na frontend-v4) na
VPS de produção. Fale comigo em português. Antes de qualquer passo que altere a produção
(backup, migração, cópia de código, .env), me mostre o que vai fazer e espere eu dizer "pode".

Leia antes: docs/2026-10-login-google.md (seções 1 a 4), docs/deploy.md §3.5,
o cabeçalho de scripts/deploy_vps.sh e openspec/changes/login-google/tasks.md.

Contexto:
- VPS AlmaLinux + Virtualmin (não é cPanel), PHP 8.2/8.3/8.4 por domínio. O ErrorHandler
  transforma qualquer aviso, inclusive Deprecated, em 500.
- O deploy é feito com scripts/deploy_vps.sh, rodado NA VPS como o usuário do domínio
  (nunca root), sobre um pacote do git archive. O script faz lint, backup de código e banco,
  aplica só as migrações listadas, copia preservando .env/storage/uploads e verifica rotas.
- Me pergunte o host, a porta e o usuário SSH. Não grave nem repita senhas na conversa.
- As chaves GOOGLE_* eu mesmo colo no .env do servidor. O Client secret nunca passa por aqui.

Etapa 0 — conferências locais
1. git status limpo e HEAD == origin/frontend-v4 (no mínimo 4902685). Se não, pare e me avise.
2. php -l nos .php alterados desde e9f2047 (o último deploy) e os testes unitários do Google:
   php tests/Unit/google_id_token.php e php tests/Unit/google_oauth.php.

Etapa 1 — estado da produção (só leitura)
1. Ver ~/deploy-historico.log na VPS: quais migrações e commits já foram aplicados.
2. No banco de produção, conferir se existem as tabelas das migrações 080, 081, 082 e 083
   (082: app_tokens; 083: usuario_identidades e certificados_retidos) e se usuarios.cpf já
   aceita NULL. A 082 já foi aplicada em 08/10.
3. No .env do servidor (sem me mostrar senhas), conferir: APP_ENV=production, APP_URL
   exatamente https://desbloqueiacursos.com.br, TEMA_PUBLICO, e que NÃO existem
   GOOGLE_JWKS_ARQUIVO_TESTE nem GOOGLE_TOKEN_URL_TESTE.
4. Atenção: o último deploy (e9f2047) publicou só os arquivos da API do app. O pacote do
   HEAD da frontend-v4 pode trazer também o tema caderno (fica desligado sem
   TEMA_PUBLICO=caderno), a compat-php84 e código que depende das migrações 080/081.
   Me diga se 080/081 já estão aplicadas. Se não estiverem, me pergunte antes de incluí-las.

Etapa 2 — pacote e simulação
1. Gerar o pacote na máquina local:
   git -c core.autocrlf=false archive --format=tar.gz --prefix=pacote/ \
       -o deploy-$(git rev-parse --short HEAD).tar.gz HEAD
2. Enviar o pacote e scripts/deploy_vps.sh para ~/deploy/ na VPS.
3. Rodar com --simular e me mostrar a lista de arquivos novos ou alterados. Separe o que é
   da login-google do que é de outras mudanças. Se aparecer algo inesperado, pare e me pergunte.

Etapa 3 — deploy (só depois do meu "pode")
   bash ~/deploy/deploy_vps.sh --pacote ~/deploy/deploy-<hash>.tar.gz \
        --migracoes "083" --url https://desbloqueiacursos.com.br
   (adicione 080/081 na frente só se eu tiver autorizado na etapa 1)
   A 083 é aditiva e idempotente e precisa rodar ANTES do código, o que o script já garante.
   Se o script terminar com avisos, me mostre e não siga adiante.

Etapa 4 — verificação com o recurso desligado
1. Smoke anônimo da minha máquina:
   php -d curl.cainfo="C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt" \
       tests/Smoke/smoke.php https://desbloqueiacursos.com.br
2. /login/google precisa responder 404 (sem chaves = desligado).
3. Conferir storage/logs/app-AAAA-MM-DD.log na VPS: nenhum erro novo.

Etapa 5 — ligar o Google
1. Me peça para colar no .env do servidor GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET e
   GOOGLE_APP_CLIENT_IDS (= o mesmo Client ID do site). Espere eu confirmar.
2. Smoke com o recurso ligado:
   SMOKE_GOOGLE=ativo php -d curl.cainfo="C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt" \
       tests/Smoke/smoke.php https://desbloqueiacursos.com.br
   /login/google precisa terminar em accounts.google.com.
3. Me guie no teste manual (tarefa 4.2): (a) conta Google nova → tela "Complete seu
   cadastro"; (b) conta Google com e-mail de um usuário já cadastrado → vínculo automático e
   e-mail "Sua conta foi vinculada ao Google"; (c) cancelar na tela do Google → volta ao login
   com mensagem. Depois confira no log os eventos "Login Google: ...".

Etapa 6 — fechamento
1. Marcar 4.2 e 8.1 em openspec/changes/login-google/tasks.md.
2. Commit no padrão de e9f2047 ("Deploy: login com Google em produção"), com as migrações
   aplicadas e o resultado do smoke. Me pergunte antes do push.
3. Arquivar no OpenSpec, nesta ordem: api-app-v1 e depois login-google (/opsx:archive),
   e rodar openspec validate --all.

Se algo der errado:
- Desligar na hora: apagar GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET e GOOGLE_APP_CLIENT_IDS do .env.
- Código: bash ~/deploy/deploy_vps.sh --reverter ~/backups/deploy-<data> (o banco não é revertido;
  a 083 pode ficar).
- usuarios.cpf só volta a NOT NULL se SELECT COUNT(*) FROM usuarios WHERE cpf IS NULL der 0.
```
