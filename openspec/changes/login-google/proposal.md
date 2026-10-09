# Proposal

## Why

Hoje só se entra no portal com e-mail/CPF e senha, e o cadastro exige CPF e senha antes de qualquer coisa — atrito alto na compra e no primeiro acesso, sobretudo no celular e no app do aluno, que acabou de ir para produção. "Entrar com Google" reduz esse atrito no site e no app ao mesmo tempo e prepara a base para "Entrar com a Apple", exigida pela App Store quando o app oferece login de terceiros.

## What Changes

- Botão "Entrar com Google" nas telas de login e cadastro dos temas `caderno` e `v2`, com fluxo OpenID Connect por redirecionamento (`state`, `nonce`, PKCE) em `/login/google` e `/login/google/callback`. Vale para **todos os perfis** (aluno, professor, revisor, admin); o destino pós-login continua sendo decidido pelo RBAC, como no login atual.
- Nova rota da API do app `POST /api/app/v1/auth/google`, que recebe o `id_token` do SDK nativo do Google e devolve os mesmos tokens do `/auth/login`.
- Resolução da conta: pelo identificador do Google (`sub`) já vinculado; senão, **vínculo automático** a uma conta existente com o mesmo e-mail, somente se o Google declarar o e-mail verificado; senão, **criação imediata de conta de aluno** sem senha e sem CPF, com consentimento de termos/privacidade registrado com origem Google. Conta inativa ou bloqueada nunca entra.
- Todo vínculo automático é auditado e gera e-mail de aviso à pessoa.
- Validação do `id_token` com as chaves públicas do Google (JWKS) em cache, sem dependência externa nova.
- **CPF pendente**: `usuarios.cpf` passa a aceitar `NULL`. Enquanto o CPF estiver vazio, a área do aluno mostra um lembrete fixo, a primeira entrada pelo Google oferece a tela "Complete seu cadastro" (com "Fazer isso depois") e a API do app expõe `pendencias: ["cpf"]` em `GET /me` e o novo `POST /api/app/v1/me/cpf`. O CPF só pode ser informado pelo próprio aluno enquanto estiver vazio.
- **Certificado retido aguardando CPF**: a emissão é manual (admin). Quando o admin emite para um participante sem CPF ligado a uma conta sem CPF, o certificado não sai: fica retido com os parâmetros da solicitação, o aluno recebe e-mail pedindo o CPF e, ao informá-lo, o CPF é copiado para os participantes dele e os certificados retidos são emitidos automaticamente. Participante sem conta mantém o comportamento atual, com aviso ao admin.
- Em `/minha-conta`, exibição da conta Google vinculada e opção de desvincular (somente para quem tem senha).
- Configuração por `.env` (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_APP_CLIENT_IDS`); sem essas chaves o botão não aparece e as rotas respondem 404 — o deploy pode ir antes da configuração no Google Cloud.

**Fora de escopo:**
- "Entrar com a Apple" (mudança futura; a tabela de identidades já nasce genérica para recebê-la).
- Ajuste do texto da Política de Privacidade e dos Termos de Uso (será feito pelo responsável após esta mudança).
- Uso de qualquer API do Google além da identificação (Drive, Agenda etc.) e armazenamento de tokens de acesso/refresh do Google.
- Google One Tap / script JS do Google nas páginas.
- Telas de login V1 legadas (`resources/views/auth/*`) e `v4-claude`.
- Autenticação em dois fatores.
- Conversão em massa dos CPFs legados gravados como `''` para `NULL`.

## Capabilities

### New Capabilities
- `autenticacao`: entrada no portal e no app por provedor de identidade externo (Google), vínculo e desvínculo de identidades, criação de conta a partir do provedor e regras de segurança do fluxo.
- `conta-aluno`: dados cadastrais do próprio usuário com pendência de CPF — lembrete no site, tela de completar cadastro, regras para o aluno informar o CPF.
- `certificados`: retenção de certificado de aluno sem CPF e emissão automática quando o CPF é informado.
- `api-app`: contrato da API do app. A capability nasce na mudança `api-app-v1` (branch `feature/api-app-v1`, ainda não arquivada nem mesclada em `frontend-v4`); este delta só acrescenta requisitos (`/auth/google`, `pendencias`, `/me/cpf`, `POST /me` sem CPF) e deve ser arquivado depois dela.

### Modified Capabilities
(nenhuma — nenhuma das capabilities existentes, `midia-cursos`, `revisao-conteudo` e `tema-publico`, muda de requisito)

## Impact

- **Migração nova:** `sql/083_login_google.sql` — cria `usuario_identidades` e altera `usuarios.cpf` para `NULL` (mantendo o `UNIQUE`); cria `certificados_retidos`. Aditiva, compatível com MySQL 5.7/MariaDB 10.5.
- **Código:** `AuthService` (extração de abertura de sessão), novos `GoogleIdTokenVerifier`/`GoogleOAuthService`/`GoogleLoginService`, model `UsuarioIdentidade`, `AuthController` (rotas web), `Api/App/AuthController` e `MeController`, `CertificadoService`, views de login/cadastro/minha-conta/área do aluno (caderno e v2), `routes/web.php`, `routes/app.php`, `.env.example`.
- **Pontos que leem CPF** precisam tolerar `NULL` (checkout, certificado, presenters da API, admin).
- **Externo:** projeto no Google Cloud com tela de consentimento OAuth e client IDs web, Android e iOS; URI de redirecionamento `https://desbloqueiacursos.com.br/login/google/callback`.
- **Deploy:** aplicar a 083 antes do código; configurar o `.env` depois. **Rollback:** remover as chaves `GOOGLE_*` desliga o recurso sem reverter código; a 083 pode permanecer (aditiva). Reverter `cpf` para `NOT NULL` só é possível se nenhuma conta sem CPF tiver sido criada.
- **App:** o app precisa integrar o SDK do Google e tratar `pendencias` — trabalho do lado do app, coordenado com o contrato documentado.
