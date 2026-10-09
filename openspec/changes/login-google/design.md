# Design

## Context

- **Login único.** Há um só login (`POST /login` → `AuthController::login` → `AuthService::login`) para todos os perfis. O destino sai de `AuthService::resolveLoginRedirect`, pelo RBAC.
- **Sessão.** Fica nas chaves `usuario_id`, `usuario_nome`, `usuario_email`, `usuario_cpf` e `usuario_telefone`, gravadas em `AuthService::login` (linhas 203-209) depois de `Session::regenerate()`.
- **Núcleo sem sessão.** `AuthService::autenticarCredenciais` é usado pelo site e pelo app. O app emite tokens com `AppTokenService::emitirParaLogin`.
- **`usuarios`.**
  - `cpf VARCHAR(14) NOT NULL UNIQUE` (sql/001).
  - `senha_hash` e `nome` já aceitam `NULL`, e `cadastro_status`/`cadastro_origem` já existem (sql/071).
  - Há registros legados com `cpf = ''`.
- **CPF vira `''` em vários pontos.** `Usuario::create`, `createPendente`, `updateProfile` e `updateAdmin` convertem o CPF com `(string)`/`preg_replace`, então `NULL` vira `''`. Com o `UNIQUE`, o segundo `''` quebra. `Usuario::findByLogin` faz `email = :email OR cpf = :cpf` com `cpf = ''` quando o login é por e-mail. Por isso **um usuário com `cpf = ''` casaria com qualquer login por e-mail inexistente**.
- **Certificados.**
  - A emissão é sempre manual: `emitir`, `emitirRapidaIndividual`, `emitirLoteManual`, `emitirComExcecaoAdministrativa` e `reemitir`, que convergem em `CertificadoService::emitirInterno($inscricaoId, $opcoes, $actor, $ip, $ua, $contexto)`.
  - O CPF vem de `participantes_pedido.cpf` (aceita `NULL`, tem `usuario_id`).
  - A emissão rápida usa `usuarios.cpf`.
  - Hoje um participante sem CPF recebe certificado com o CPF em branco.
- **Sem HTTP compartilhado.** Não há cliente HTTP comum: cada serviço tem seu `curl` privado (`PushService::http`). `PushService` já tem `base64url` e RS256 com `openssl`.
- **Contrato da API.** Está em `openspec/changes/api-app-v1/contrato.md`, que existe só na branch `feature/api-app-v1`. O código dela já está em `frontend-v4` (commit e9f2047), mas os artefatos OpenSpec não. A API só aceita GET e POST, com erro `{"erro":{"codigo","mensagem","campos"}}`.

## Goals / Non-Goals

**Goals:**
- Um único núcleo de identidade Google usado pelo site e pelo app.
- Nenhuma mudança no comportamento do login por senha.
- O recurso fica desligado por configuração até as credenciais existirem.
- CPF `NULL` tratado de forma consistente: nunca gravar `''` em conta nova.

**Non-Goals:**
- Biblioteca OAuth genérica para vários provedores. A tabela é genérica; o código é específico do Google, e a Apple terá o seu próprio validador quando vier.
- Converter os `''` legados (só passam a ser tratados como "sem CPF" na leitura).
- Reescrever o fluxo de emissão de certificados.

## Decisions

### 1. Fluxo do site: Authorization Code + PKCE + `nonce`, sem script do Google
**Escolhido:** `/login/google` redireciona para `https://accounts.google.com/o/oauth2/v2/auth` com `response_type=code`, `scope=openid email profile`, `state`, `nonce`, `code_challenge` (S256) e `prompt=select_account`. O callback troca o código em `https://oauth2.googleapis.com/token`.

**Por quê:**
- funciona sem JavaScript;
- não carrega script de terceiro;
- é o fluxo recomendado para aplicações com servidor.

**Alternativas:**
- Google Identity Services/One Tap: script de terceiro em toda tela de login, menos controle visual.
- Fluxo implícito: obsoleto.

**Detalhes do `state`:** a sessão guarda `google_oauth = {state, nonce, verifier, destino, criado_em}`. O callback consome a chave antes de qualquer outra verificação; isso garante uso único. Validade de 10 minutos.

**Sessão no callback:** `SESSION_SAME_SITE=Lax` mantém o cookie de sessão na navegação GET de volta do Google, então não é preciso mexer na configuração.

### 2. Validação do `id_token` com JWKS próprio (`GoogleIdTokenVerifier`)
**Escolhido:**
1. Separar o JWT e exigir `alg = RS256`.
2. Achar a chave pelo `kid` no JWKS de `https://www.googleapis.com/oauth2/v3/certs`.
3. Montar a chave pública PEM a partir de `n`/`e` (codificação ASN.1 DER em PHP puro, cerca de 40 linhas).
4. Verificar com `openssl_verify(..., OPENSSL_ALGO_SHA256)`.
5. Validar `iss` ∈ {`accounts.google.com`, `https://accounts.google.com`}, `aud` ∈ lista do canal, `exp` (+60 s de tolerância), `iat` e `nonce` (quando esperado).

**Cache do JWKS:** em `storage/cache/google_jwks.json`, com expiração pelo `max-age` do `Cache-Control` (mínimo de 1 h, máximo de 24 h).
- Se chegar um `kid` desconhecido, uma nova busca, limitada a uma por minuto (marca de tempo no próprio arquivo), para não virar amplificador de requisições.
- A busca do JWKS e o relógio são injetáveis (callable), para o teste unitário usar um par RSA gerado na hora.

**No site, o token chega direto do Google por TLS** e, pela especificação OIDC, poderia dispensar a verificação de assinatura. Mesmo assim verificamos, para ter um único caminho de código, testado nos dois canais.

**Alternativa:** endpoint `tokeninfo`. O Google o desaconselha em produção, e ele acrescenta uma chamada de rede por login.

### 3. Cliente HTTP mínimo para o Google
Um método privado `requisitar()` em `GoogleOAuthService`, no padrão de `PushService::http`:
- `curl` com `CURLOPT_SSL_VERIFYPEER`;
- timeout de 5 s;
- checagem de `function_exists('curl_init')`.

Não vale extrair um cliente compartilhado agora: seriam três implementações para refatorar, fora do escopo.

### 4. Resolução da conta (`GoogleLoginService::resolverUsuario(array $claims, string $canal, $ip, $ua)`)
**Ordem:** `sub` vinculado → e-mail verificado de usuário existente → criação.

**Retorno:** `['ok', 'usuario', 'novo' => bool, 'vinculado_agora' => bool, 'erro' => codigo]`. Os códigos de erro (`email_nao_verificado`, `conta_inativa`) mapeiam para mensagens do site e para a API.

- **Busca por e-mail:** um método novo `Usuario::findByEmail` com comparação exata (sem passar por `findByLogin`, por causa do problema do `cpf = ''`).
- **Contas excluídas:** usuário com `deleted_at` não é vinculado. Como `email` é `UNIQUE` também para registros excluídos, não dá para criar conta nova com esse e-mail. O login é recusado com "Não foi possível entrar com o Google agora…" e o motivo vai para o log. O caso é raro, e o atendimento resolve.
- **Criação, em transação:**
  1. `Usuario::createPorProvedor` com `cpf = NULL`, `senha_hash = NULL`, `cadastro_status = 'pendente'` e `cadastro_origem = 'google'`;
  2. o perfil `aluno`;
  3. os consentimentos `termos_uso` e `politica_privacidade` (versão `1.0`, consentido, com IP e UA) e `comunicacoes_marketing` não consentido, iguais aos do cadastro. `usuario_consentimentos` não tem coluna de origem, então a origem Google fica registrada em `usuarios.cadastro_origem = 'google'` e no `AuditService` (`autenticacao.google_conta_criada`). Quando o responsável publicar a nova versão dos termos, a versão do consentimento passa a vir de configuração. Isso fica fora desta mudança;
  4. a identidade.

  O e-mail de boas-vindas sai depois do commit.
- **Corrida no primeiro acesso:** se o mesmo usuário fizer dois logins simultâneos, o `UNIQUE (provedor, sub)` faz o segundo insert falhar. Nesse caso o serviço relê pelo `sub` e segue.
- **Vínculo automático:**
  - `AuditService::record('autenticacao.google_vinculado', 'usuario', $id, ['email_google'=>..., 'canal'=>...])`;
  - e-mail `EmailService::googleVinculado(...)` via `sendTemplate`, com o modelo novo `email.google-vinculado`.

### 5. Abertura de sessão compartilhada
Extrair `AuthService::abrirSessao(array $usuario)` das linhas 203-209. `login()` e o callback do Google passam a usá-lo.
- A chave `usuario_cpf` recebe `''` quando o CPF é `NULL`, mantendo o formato atual da sessão, que é lido como string em vários lugares.
- O evento de acesso é `login_google` no site e `app_login_google` no app, via `AccessLogService::record`.

### 6. Tabela `usuario_identidades`
```sql
CREATE TABLE IF NOT EXISTS usuario_identidades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    provedor VARCHAR(20) CHARACTER SET ascii NOT NULL,
    sub VARCHAR(191) CHARACTER SET ascii NOT NULL,
    email VARCHAR(191) NULL,
    created_at DATETIME NOT NULL,
    ultimo_uso_em DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_identidades_sub (provedor, sub),
    UNIQUE KEY uk_usuario_identidades_usuario (usuario_id, provedor),
    CONSTRAINT fk_usuario_identidades_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- **Tamanho dos índices:** `provedor` e `sub` usam `CHARACTER SET ascii`, e o índice `(provedor, sub)` fica em 211 bytes, abaixo do limite de 767 bytes do MySQL 5.7 sem `innodb_large_prefix`. Em `utf8mb4` passaria do limite. Os dois valores são ASCII por natureza: o `sub` do Google tem cerca de 21 dígitos, e o da Apple, cerca de 44 caracteres.
- O tipo `BIGINT UNSIGNED` é o mesmo de `usuarios.id` e `inscricoes.id` (sql/001 e sql/005).
- **Desvincular** remove a linha, registra no `TrashService::record` com justificativa automática "Desvinculado pelo próprio usuário" e audita.

### 7. CPF `NULL`
- **Migração:** `ALTER TABLE usuarios MODIFY cpf VARCHAR(14) NULL;` mantém o `uk_usuarios_cpf`. O `UNIQUE` aceita vários `NULL` no MySQL 5.7 e no MariaDB 10.5.
- **Model:** um helper `Usuario::normalizarCpf($cpf)` devolve `null` para vazio e é usado em `create`, `createPendente`, `updateProfile` e `updateAdmin`.
  - Conta **nova** nunca grava `''`.
  - O efeito colateral em `updateProfile`/`updateAdmin` é que um legado `''` vira `NULL` quando editado. É desejável e seguro (não conflita com o `UNIQUE`).
- **`findByLogin`:** só inclui a condição de CPF quando o login tem 11 dígitos. Isso fecha o casamento acidental com `cpf = ''`.
- **Definição de "sem CPF":** `cpf IS NULL OR cpf = ''`, centralizada em `Usuario::semCpf(array $u)`.
- **`AuthService::updateAccount`:** deixa de exigir o CPF. Quando o usuário já tem CPF, o campo enviado é ignorado (só leitura). Quando está vazio, um CPF enviado passa por `ContaCpfService::informar`.
- **`MeController::atualizar` da API:** para de reenviar o CPF, e com isso deixa de dar 422 para conta sem CPF.
- **Formulários do admin** (`GestaoAcessoService`): continuam exigindo o CPF ao **criar** usuário pelo admin. Na **edição**, o CPF vazio passa a ser aceito apenas se já estava vazio.

### 8. `ContaCpfService::informar($usuarioId, $cpf, $canal, $ip, $ua)`
É o único ponto que grava o CPF informado pelo próprio usuário. Site, app e checkout usam ele.

1. Normaliza e valida o dígito.
2. Recusa se a conta já tem CPF (`cpf_ja_informado`).
3. Confere a unicidade (`cpf_em_uso`).
4. Faz um `UPDATE usuarios SET cpf = :cpf WHERE id = :id AND (cpf IS NULL OR cpf = '')` e confere as linhas afetadas, para evitar corrida.
5. Copia o CPF para `participantes_pedido` com `usuario_id = :id AND (cpf IS NULL OR cpf = '')`.
6. Muda `cadastro_status` de `pendente` para `completo` quando a origem é `google`.
7. Atualiza `usuario_cpf` na sessão, quando há sessão.
8. Audita.
9. **Depois do commit**, chama `CertificadoRetencaoService::liberarDoUsuario($usuarioId)`, com captura de exceção: a falha fica registrada na retenção e no log, e o CPF continua salvo.

**No checkout:** depois que o pedido é criado, se a conta não tem CPF, chama `informar()` com o CPF do pagador. Uma recusa (por exemplo, CPF de outra conta) não bloqueia o pedido, porque o comportamento atual do checkout com esse CPF já é aceito; apenas não grava na conta.

### 9. Retenção de certificados (`certificados_retidos` + `CertificadoRetencaoService`)
**Escolhido:** uma tabela própria, em vez de um novo valor no ENUM `certificados.status`. As colunas `cpf_participante`/`cpf_mascarado`/`codigo` da tabela `certificados` são `NOT NULL`, e um certificado retido ainda não tem código nem PDF. Usar `certificados` exigiria afrouxar restrições que protegem a validação pública.

```sql
CREATE TABLE IF NOT EXISTS certificados_retidos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    modo VARCHAR(30) NOT NULL,          -- individual | rapida | lote | excecao
    opcoes TEXT NOT NULL,               -- JSON de $opcoes
    contexto TEXT NOT NULL,             -- JSON de $contexto (sem dados pessoais)
    solicitado_por BIGINT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'aguardando_cpf', -- aguardando_cpf | emitido | cancelado
    certificado_id BIGINT UNSIGNED NULL,
    ultima_falha VARCHAR(500) NULL,
    tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    aviso_enviado_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_certificados_retidos_inscricao (inscricao_id, status),
    KEY idx_certificados_retidos_usuario (usuario_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- **Por que `TEXT` e não `JSON`:** no MariaDB 10.5, `JSON` é só um alias de `LONGTEXT`. Com `TEXT` o código não depende de funções JSON do banco.
- **Uma retenção ativa por inscrição:** a regra fica no serviço, não num `UNIQUE`. A mesma inscrição pode acumular linhas `emitido`/`cancelado` ao longo do tempo (reter, emitir, revogar, reter de novo), e o MySQL 5.7 não tem índice parcial. `reter()` faz `SELECT ... FOR UPDATE` por `(inscricao_id, 'aguardando_cpf')` dentro da transação. Se já houver retenção ativa, atualiza `opcoes`/`solicitado_por` em vez de inserir outra.

**Ponto de retenção, dentro de `emitirInterno`:**
- A checagem acontece depois de carregar a inscrição e o participante, e antes de gerar o código.
- Condição: o CPF do participante está vazio, e o participante (ou a inscrição) está ligado a um `usuario_id`.
  - Se esse usuário **tem** CPF, usa o CPF dele e o grava no participante.
  - Se **não tem**, chama `CertificadoRetencaoService::reter(...)` e devolve `['ok' => false, 'retido' => true, 'message' => 'Certificado retido: …']`.
- Isso cobre todos os modos, que convergem nesse método.
- Para `emitirRapidaIndividual`, que lê `usuarios.cpf`, vale a mesma regra.

**Liberação:** `liberarDoUsuario` chama de novo `emitirInterno` com as `opcoes`/`contexto` guardados e `actorUserId = solicitado_por`, para que a autoria e as permissões continuem as de quem pediu.
- Se essa pessoa perdeu a permissão, a emissão falha. A falha fica registrada em `ultima_falha` e aparece na lista do admin. É o comportamento correto: ninguém emite em nome de quem não pode.

**Resultado do lote:** os controllers do admin (lote, individual, rápida) passam a contar `retido` à parte e mostram "N certificados retidos aguardando CPF". O aviso "Certificado emitido sem CPF do participante." aparece quando o participante não tem conta.

**E-mail ao aluno:** `aviso_enviado_em` é a referência para o limite de 24 h por usuário (a consulta usa o `MAX` do usuário).

**Lista do admin:** `/admin/certificados/retidos`, com a permissão de gerir certificados. Cancelar exige justificativa (`TrashService::record` com o snapshot da linha).

### 10. API do app
- `POST /auth/google` em `Api\App\AuthController::google`: valida os campos, aplica `AppLimiteTaxaService` por IP, chama o verificador com `GOOGLE_APP_CLIENT_IDS` (lista separada por vírgula, onde também entra o client ID web, porque o Credential Manager do Android emite o token com `aud` = client ID web), chama `GoogleLoginService` e depois `emitirParaLogin`.
- `pendencias` entra no `UsuarioPresenter::usuario`, a partir de `Usuario::semCpf`.
- `POST /me/cpf` chama `ContaCpfService::informar`.
- O contrato (`contrato.md` da `api-app-v1`) recebe as seções novas. Como esse arquivo não está em `frontend-v4`, a tarefa 0 traz os artefatos da `api-app-v1` para a branch antes.

### 11. Configuração e views
- **`config/auth.php` novo:** `google.client_id`, `google.client_secret`, `google.app_client_ids` (array) e `google.ativo` (verdadeiro só com `client_id` + `client_secret`; o app exige também uma lista não vazia).
- **Botão:** um partial por tema, `caderno/partials/botao-google.php` e `v2/partials/botao-google.php`, com SVG inline e o texto de consentimento com links para as páginas de termos e privacidade já usadas no cadastro.
- **Tela "Complete seu cadastro":** `GET`/`POST /conta/completar`, autenticada, renderizada com `TemaPublico::view('completar-cadastro')`. O callback redireciona para ela quando `novo = true`, guardando o destino original na sessão.
- **Lembrete de CPF:** usa o `postit` do caderno em `.al-avisos` (`caderno/pages/aluno.php`) e o equivalente em `v2/pages/aluno.php`. "Todas as páginas da área do aluno" é implementado num partial incluído pelo layout do aluno nos dois temas. A tarefa 6.2 confirma qual layout envolve as páginas do aluno em cada tema.
- **`/minha-conta`:** nas views `caderno/pages/conta.php` e `v2/pages/conta.php`, o CPF fica `readonly` quando preenchido, e há um bloco "Conta Google".

## Risks / Trade-offs

- **[Conta Google comprometida vira acesso de admin]** → Mitigação: e-mail de aviso a cada vínculo novo e auditoria. O Google já aplica a própria proteção da conta. Decisão consciente do responsável (todos os perfis, com vínculo automático).
- **[E-mail de domínio próprio verificado no Google por terceiro que controla a caixa]** → É o mesmo risco da recuperação de senha por e-mail que existe hoje; o vínculo automático não piora isso.
- **[Pontos que leem CPF e não toleram vazio]**, por exemplo o Pix da AbacatePay com o CPF do pagador → Mitigação: o checkout continua exigindo o CPF do pagador, e a tarefa de varredura revisa a lista do levantamento (cerca de 25 pontos) com testes nos fluxos críticos.
- **[Emissão automática fora do contexto do admin]** (sem sessão do admin, possivelmente dentro de uma requisição do app) → Mitigação: `emitirInterno` já recebe o ator por parâmetro. O tempo de geração do PDF entra na requisição do aluno; com poucos certificados por aluno, é aceitável. O serviço emite no máximo 5 por requisição. As retenções restantes continuam `aguardando_cpf` (o CPF já está salvo) e são liberadas na próxima vez que o aluno abrir a área do aluno ou "Meus certificados", no site ou no app: `liberarDoUsuario` é chamado ali quando há retenção ativa e o usuário já tem CPF.
- **[Rotação de chaves do Google]** → Mitigação: nova busca no `kid` desconhecido, com limite de frequência.
- **[MySQL 5.7 sem `innodb_large_prefix`]** → Mitigação: `sub` em ASCII (ver Decisão 6).
- **[O app depende do contrato da `api-app-v1` não mesclado]** → Mitigação: a tarefa 0 traz os artefatos para `frontend-v4`, e o arquivamento desta mudança acontece depois da `api-app-v1`.

## Migration Plan

1. **Deploy:** aplicar `sql/083_login_google.sql` (aditiva; `MODIFY cpf ... NULL` não altera dados) e depois publicar o código. Sem as chaves `GOOGLE_*`, nada aparece para o usuário; só a retenção de certificado e as correções de CPF vazio passam a valer.
2. **Google Cloud:**
   - tela de consentimento OAuth (nome, logo, links de termos e privacidade);
   - client web com o redirect URI de produção e o local;
   - client Android (SHA-1 da assinatura).
3. **`.env` da VPS:** preencher as chaves, testar com uma conta Google de teste e conferir o log.
4. **Rollback:**
   - remover as chaves `GOOGLE_*` desliga o login pelo Google na hora;
   - para reverter o código, as tabelas novas podem ficar;
   - `cpf` só volta a `NOT NULL` se `SELECT COUNT(*) FROM usuarios WHERE cpf IS NULL` der 0.

## Open Questions

- **Limite de emissões automáticas por requisição:** 5 é uma estimativa e deve ser ajustado depois de medir o tempo real de geração do PDF em produção. É só uma constante; não muda specs nem tarefas.
