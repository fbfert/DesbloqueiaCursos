# Prompt — deploy do tema caderno único

Cole o bloco abaixo numa sessão do Claude Code aberta na raiz do repositório
(`DesbloqueiaCursos`, branch `frontend-v4`), com acesso SSH à VPS.

```text
Vamos fazer o deploy da mudança OpenSpec "tema-caderno-unico" na VPS de produção.
Commits: 721d143 (proposta), b6114d4, ed8a224 e af7d2f6, na frontend-v4. Fale comigo em
português. Antes de qualquer passo que altere a produção (backup, cópia de código, .env), me
mostre o que vai fazer e espere eu dizer "pode".

Leia antes: docs/deploy.md §3.6, o cabeçalho de scripts/deploy_vps.sh,
openspec/changes/tema-caderno-unico/proposal.md e tasks.md (inclusive o "Registro da
verificação" no fim).

Contexto:
- VPS AlmaLinux + Virtualmin (não é cPanel), PHP 8.2/8.3/8.4 por domínio. O ErrorHandler
  transforma qualquer aviso, inclusive Deprecated, em 500.
- O que a mudança faz: o tema caderno passa a ser o único visual do site e da área do aluno.
  As rotas V1 redirecionam ao tema (301 nas públicas, 302 nas logadas e no checkout), todo login
  passa por /v2/login, os erros 403/404/500 saem no tema e o AbacatePay devolve o aluno para
  /v2/checkout/sucesso.
- Não há migração. O último deploy foi o do login com Google (42aa1d0, em 15/10). A migração
  083 já está aplicada.
- O deploy é feito com scripts/deploy_vps.sh, rodado NA VPS como o usuário do domínio (nunca
  root), sobre um pacote do git archive.
- Me pergunte o host, a porta e o usuário SSH. Não grave nem repita senhas na conversa.

Etapa 0 — conferências locais
1. git status limpo e HEAD == origin/frontend-v4 (no mínimo af7d2f6). Se não, pare e me avise.
2. Rodar php -l nos .php alterados desde 42aa1d0 (git diff --name-only 42aa1d0 -- '*.php') e
   estes testes unitários sem banco: tema_publico, safe_redirect_interno, login_destino,
   rota_legada e abacatepay_urls_retorno (php tests/Unit/<nome>.php).

Etapa 1 — estado da produção (só leitura)
1. Ver ~/deploy-historico.log: o último deploy registrado deve ser o de 42aa1d0 (ou 30042bf).
2. Rodar no banco de produção e me mostrar o resultado:
     SELECT tipo, COUNT(*) FROM conteudo_itens WHERE deleted_at IS NULL GROUP BY tipo;
   Se aparecer o tipo "atividade", pare e me pergunte. Depois do deploy, esses itens mostram ao
   aluno "Esta atividade não está disponível por aqui".
3. No .env (sem me mostrar senhas), conferir APP_ENV=production e APP_URL exatamente
   https://desbloqueiacursos.com.br. Me dizer os valores atuais de TEMA_PUBLICO e
   HOME_VERSION, que perdem efeito depois do deploy.
4. Conferir se o checkout rápido está ligado: registro em configuracoes_checkout_rapido ou
   CHECKOUT_RAPIDO_ATIVO no .env. Isso decide se /comprar deve responder 200 ou 404.

Etapa 2 — pacote e simulação
1. Gerar o pacote na máquina local:
   git -c core.autocrlf=false archive --format=tar.gz --prefix=pacote/ \
       -o deploy-$(git rev-parse --short HEAD).tar.gz HEAD
2. Enviar o pacote e scripts/deploy_vps.sh (a versão nova, do pacote) para ~/deploy/ na VPS.
3. Rodar com --simular e me mostrar a lista de arquivos novos ou alterados. Devem ser os arquivos
   dos commits b6114d4, ed8a224 e af7d2f6 (app/, resources/views/, routes/, assets/caderno/,
   v2/assets/css/, scripts/, index.php, config/app.php). Se aparecer algo inesperado, pare e me
   pergunte.

Etapa 3 — deploy (só depois do meu "pode")
   bash ~/deploy/deploy_vps.sh --pacote ~/deploy/deploy-<hash>.tar.gz \
        --url https://desbloqueiacursos.com.br
   (sem --migracoes: esta mudança não tem migração)
   O script verifica /, /cursos, /login, /sitemap.xml e /v2/catalogo com -L. Ele segue os novos
   redirecionamentos e deve terminar em 200. Se o script terminar com avisos, me mostre e não
   siga adiante.

Etapa 4 — verificação
1. Rodar da minha máquina o smoke anônimo:
     php -d curl.cainfo="C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt" \
         tests/Smoke/smoke.php https://desbloqueiacursos.com.br
   Como o Google está ligado em produção, adicione SMOKE_GOOGLE=ativo. A guarda nova
   proibe_legado falha se alguma página do site vier no layout V1. Qualquer FAIL: pare e me mostre.
2. Rodar o smoke autenticado. Me peça para definir SMOKE_USER e SMOKE_PASS de um usuário de
   teste dedicado (nunca um aluno real), e use --modo=todos.
3. Conferir com curl -I estes redirecionamentos:
   - /login → 301 /v2/login;
   - /meus-cursos → login com ?redirect=;
   - /certificados/validar?codigo=X → 301 /v2/certificados/validar?codigo=X;
   - /checkout/sucesso?pedido_id=1 → /v2/checkout/sucesso;
   - /pagina-que-nao-existe → 404 no tema (corpo com caderno.css, sem frontend-theme).
4. Conferir storage/logs/app-AAAA-MM-DD.log na VPS: nenhum erro novo desde o deploy.

Etapa 5 — testes manuais (me guie, um por vez)
1. Entrar pelo /login como admin, como professor e como aluno. Cada um deve chegar à própria área.
   Depois, sair por "Sair": a tela tem que ser a do tema, e o login tem que mostrar
   "Você saiu da sua conta.".
2. Abrir /admin/pedidos sem sessão, entrar como admin e conferir que volta para /admin/pedidos.
3. Pagamento online de ponta a ponta, com valor baixo, num curso de teste. Ao voltar do
   AbacatePay, a tela tem que ser "Inscrição confirmada" ou "Estamos confirmando o seu pagamento",
   no tema.
4. Ler o QR code de um certificado emitido antes do deploy. Tem que abrir a validação no tema.
5. Abrir um link de cupom promocional de um curso. Tem que cair na página daquele curso no tema,
   com a mensagem de cupom salvo.
6. Abrir um link antigo de aula do LMS (/aluno/curso/...). Tem que cair na aula do tema.

Etapa 6 — fechamento
1. Marcar a tarefa 0.1 em openspec/changes/tema-caderno-unico/tasks.md com o resultado da
   consulta da etapa 1.
2. Commit no padrão dos deploys anteriores ("Deploy: tema caderno único em produção"), com o
   resultado do smoke e dos testes manuais. Me pergunte antes do push.
3. Me perguntar se apago TEMA_PUBLICO e HOME_VERSION do .env. É opcional, porque elas não têm
   mais efeito.
4. Arquivar no OpenSpec, nesta ordem: api-app-v1, login-google e tema-caderno-unico
   (/opsx:archive). Depois rodar openspec validate --all.

Se algo der errado:
- Código: bash ~/deploy/deploy_vps.sh --reverter ~/backups/deploy-<data>. Não há banco a reverter.
- Os 301 já entregues ficam em cache nos navegadores, mas só levam a páginas /v2/*, que existem
  também na versão anterior.
```
