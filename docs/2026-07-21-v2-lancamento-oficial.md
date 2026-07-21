# V2 vira a Home oficial (HOME_VERSION=v2) — segurança, SEO, paridade de funcionalidades e correções pós-lançamento

Data: 2026-07-21
Escopo: preparação e execução do lançamento oficial da V2 como experiência
padrão do site (`HOME_VERSION=v2` em produção), incluindo os itens de
segurança/infra levantados como bloqueadores, três telas novas para fechar
paridade com a V1, e duas correções feitas já com a V2 no ar a partir de
testes reais do usuário.

Fora de escopo (não alterado): regras de pedido/financeiro, gateway de
pagamento, RBAC/admin, V1 (mantido 100% acessível nas suas rotas originais
para rollback).

## 1. Segurança — remoção de scripts de diagnóstico com credenciais expostas

Removidos 10 arquivos do webroot (`public_html/` raiz): `qa_dump.php`,
`qa_file_exists.php`, `qa_files.php`, `remote_diag.php`, `remote_diag2.php`,
`remote_diag3.php`, `remote_diag_content_counts.php`, `remote_diag_files.php`,
`remote_diag_roles.php`, `remote_set_test_password.php`.

Continham a senha do banco de produção em texto plano e um endpoint de
redefinição de senha para os usuários 2 e 7 protegido só por um token fraco
hardcoded. Nunca estiveram sob controle de versão (não aparecem no
histórico do git) — eram artefatos soltos no servidor.

## 2. SEO — robots.txt, sitemap.xml dinâmico e canonical

- `public_html/robots.txt` (novo): bloqueia `/admin`, `/professor`, `/aluno`,
  `/checkout`, áreas de autenticação; referencia `Sitemap:
  https://desbloqueiacursos.com.br/sitemap.xml`.
- `app/Controllers/SitemapController.php` (novo): gera XML dinâmico
  reaproveitando `CursoService::listPublic()` e `CategoriaService::listPublic()`
  — sem tabela/cache própria, sempre reflete o catálogo real.
- `routes/web.php`: `GET /sitemap.xml`.
- `resources/views/v2/layout.php`: `<link rel="canonical">` calculado a partir
  do path + querystring atuais (auto-referencial, sem mapear cada página ao
  par V1).

## 3. Documentação de deploy/rollback

`docs/deploy.md`, `docs/go-live-checklist.md`, `docs/rollback.md` — já eram
referenciados pelo `CLAUDE.md` mas não existiam. Escritos a partir de uma
auditoria completa do fluxo real (deploy manual via FTP para cPanel, sem CI).

## 4. Paridade V2 — três telas que só existiam na V1

### 4.1 Editar cadastro (`/v2/minha-conta`)

`app/Controllers/V2/ContaController.php` (novo) + `resources/views/v2/conta.php`
(shim) + `resources/views/v2/pages/conta.php`. Reaproveita
`AuthService::updateAccount()`/`accountData()` — mesma regra de validação da
V1 (nome/email/cpf/telefone/estado/cidade, dropdown de cidade via API do
IBGE, troca de senha). `AlunoController::editarHref` passou a apontar para
cá em vez de `/minha-conta` (V1).

### 4.2 Redefinir senha por token (`/v2/recuperar-senha/redefinir`)

`AuthController::resetPassword()` (compartilhado com a V1) passou a usar
`resolveOrigemRedirect()` — o mesmo helper de whitelist interna já usado por
`requestPasswordReset()` — em vez de redirecionar sempre para a rota V1.
`RecuperarSenhaController::redefinir()` (novo, V2) renderiza o formulário
usando `auth-layout.php` (sem navbar/rodapé completos, como as demais telas
de autenticação da V2).

### 4.3 Cancelar pedido na área do aluno (`/v2/aluno/pedidos/cancelar`)

`AlunoController::cancelarPedido()` (novo) espelha exatamente a regra da V1
(`MeusCursosController::cancelarPedido()`): mesma lista de status
cancelável pelo aluno (`rascunho, aguardando_pagamento, pendencia,
aguardando_reenvio, comprovante_enviado, em_analise`), mesmo service
(`PedidoService::registrarStatus()`), mesma exigência de motivo. A tela de
pedidos ganhou um `<details>` com formulário de confirmação por pedido.

## 5. E-mails transacionais V2-aware

Links que apontavam para rotas V1 (recuperação de senha, boas-vindas,
carrinho abandonado, presente concedido) passaram a apontar para as rotas
V2 equivalentes:

- `EmailService::passwordReset()` — link de redefinição.
- `EmailModeloService::buildContext()` — `login_url`, `meus_cursos_url`,
  `area_curso_url`, `home_url` (variáveis `{sistema.*}` disponíveis a
  qualquer template customizado no admin).
- `PedidoRecuperacaoService` — link do resumo do pedido (carrinho
  abandonado) e link de contato/WhatsApp.
- `PresenteCampanhaService` — link "Acessar meus cursos" do e-mail de
  presente concedido.

Não alterado (fora de escopo): links de validação pública de certificado
(`/certificados/validar` — usados em QR Codes já impressos/emitidos, trocar
quebraria certificados físicos existentes) e links administrativos internos
(`/admin/...` — não existe V2 de admin).

## 6. Atividade com upload de arquivo (sistema legado) — decisão de escopo

Investigado se valia a pena dar paridade V2 ao sistema legado de atividades
com upload (tabelas `atividades`/`atividades_entregas`, usado por
`AreaCursoController::enviarAtividade()` na V1 — **separado** do "Conteúdo
Unificado" que a V2 já integra via `AtividadeController`/
`ConteudoAvaliacaoTextualService`, que só suporta resposta em texto).

Conferido no banco: **zero registros ativos** (não deletados) nas duas
tabelas — as 20 linhas existentes em `atividades` são todas de teste/
validação, já com soft-delete. Decisão: não bloqueia o lançamento; recomenda-se
tratar como aceitável ficar só na V1 (ou avaliar depreciar) em vez de
construir uma ponte para uma funcionalidade sem uso real hoje.

## 7. O switch em si — `HOME_VERSION=v2`

`.env` de produção ganhou `HOME_VERSION=v2`. `routes/web.php` (rota `/`) já
tinha a lógica pronta desde a Fase 2.13 (`config/app.php` →
`'home_version'`): com o valor `v2`, `V2HomeController` passa a responder na
raiz do site; a V1 continua 100% acessível em `/home` (rotas próprias, nada
removido). Rollback: apagar a linha (ou trocar para `v1`) — sem tocar em
código, documentado em `docs/rollback.md`.

## 8. Correção pós-lançamento — validação pública de certificados perdendo dados no POST

Reportado pelo usuário: `https://desbloqueiacursos.com.br/v2/certificados/validar/`
"não funcionou" em um teste real.

**Causa raiz:** existe um diretório físico `v2/certificados/validar/` no
servidor, herdado de um protótipo estático anterior à V2 dinâmica atual
(o mesmo protótipo deixou diretórios equivalentes para várias outras rotas,
ex.: `v2/aluno/`, `v2/login/`, `v2/checkout/` — a maioria já tem um
`index.php`-ponte que encaminha para o front controller real; alguns, como
este, ainda tinham só o `index.html` estático por cima). Como o diretório
existe, o Apache emite um 301 de `/v2/certificados/validar` (sem barra) para
a versão com barra — para qualquer método HTTP, inclusive POST. O
formulário de validação enviava o POST para a URL **sem** barra; ao seguir
o 301, o navegador reenvia como GET (comportamento padrão de HTTP),
descartando código, CPF e token CSRF. O aluno via o formulário "reiniciar"
sem mensagem nenhuma.

Auditados todos os demais POST da V2 (login, cadastro, checkout, aula,
quiz, atividade, cancelar pedido) — nenhum outro colide com um diretório
físico no mesmo path exato; o problema era específico desta rota.

**Correção:** `resources/views/v2/pages/certificados-validar.php` (form
action e link "Validar outro certificado") e todos os `certificadosHref`
espalhados pelos controllers V2 (mais a constante canônica
`V2Nav::CERTIFICADOS`) passaram a apontar direto para a URL com barra —
elimina o redirect em vez de mexer no diretório físico (mais seguro, já que
outras rotas dependem da mesma pasta para a ponte já existente).

Validado em produção: POST com sessão e token CSRF reais agora retorna 200
com a mensagem real de validação, em vez de 302 (CSRF) ou perda silenciosa
de dados.

## 9. Rodapé V2 — reversão de uma decisão unilateral

Durante a auditoria de lançamento eu tinha removido "Termos de Uso",
"Política de Privacidade" e "Remova-me" da coluna "Institucional" do
rodapé (mantendo só na faixa legal inferior), por julgar redundante. Essa
duplicação já era conhecida e tinha sido deixada de propósito na Fase 2.14
("ficaram duplicados; não removidos de lá por decisão explícita ainda
pendente do time" — ver histórico interno da fase). O usuário pediu de
volta; restaurados os três links na coluna Institucional
(`resources/views/v2/partials/footer.php`), mantendo também a faixa legal
inferior como estava.

## Commits desta fase

- `399321f` — segurança, SEO, docs, conta/senha/cancelamento V2, e-mails V2-aware.
- `e4e3675` — correção da validação de certificados perdendo dados no POST.
- `deb86ac` — reversão da remoção dos links legais da coluna Institucional.

## Não exercitado / recomendado antes de considerar 100% fechado

- Fluxo completo em navegador real (não só via curl/reflection) do cancelamento
  de pedido e da edição de cadastro.
- `/v2/contato/` está servindo uma página estática de demonstração antiga
  ("nada é enviado de verdade") em vez da tela real do
  `V2InstitucionalController::contato` — mesmo problema de diretório físico
  do item 8, mas neste caso sem o `index.php`-ponte. Ainda não corrigido
  (identificado, aguardando decisão do usuário).
