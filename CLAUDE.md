# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projeto

Portal de Cursos e Eventos (Polo Rainbow / "Desbloqueia Cursos") — aplicação PHP MVC sem framework pesado, para gestão de cursos/eventos, turmas, pedidos/checkout, área do aluno, área do professor, backoffice/admin, certificados e financeiro/repasses.

## Comandos

Não há `composer.json` nem `package.json` na raiz — o backend não usa gerenciador de dependências (sem `vendor/`), e os ativos de front-end (CKEditor 5) já estão pré-compilados em `public_html/assets/vendor/ckeditor5/`.

Servidor local:
```bash
cp .env.example .env
php -S 127.0.0.1:8000 -t public_html
```

Validação de sintaxe PHP/JS (rodar nos arquivos alterados antes de finalizar):
```bash
php -l caminho/Para/Arquivo.php
node --check public_html/assets/js/algum-arquivo.js
```

Smoke test (rotas críticas, requer URL acessível):
```bash
php tests/Smoke/smoke.php https://polorainbow.com.br
```
Retorna `0` se todas as rotas responderam como esperado, `1` se alguma falhou.

Teste unitário (executar diretamente com PHP, sem framework de testes):
```bash
php tests/Unit/professor_academic_scope.php
```

Migrations: arquivos SQL simples e numerados em `sql/` (ex.: `054_pedido_recuperacao_v1.sql`), compatíveis com MySQL 5.7. Não há runner automático — aplique manualmente em ordem numérica.

## Arquitetura

### Bootstrap e roteamento

- Entry point único: `public_html/index.php`. Registra o autoloader (`app/Core/Autoloader.php`, mapeia `App\*` para `app/*.php`), carrega `.env` (`App\Core\Env`), inicia sessão e registra `App\Core\App`.
- `routes/web.php` (rotas web) e `routes/api.php` (base de API) são `require`-ados e populam o `App\Core\Router` via `$app->get(...)`, `$app->post(...)` e `$app->postWithoutCsrf(...)`.
- `$app->post()` injeta automaticamente o middleware `csrf`. Para webhooks externos (ex.: `/webhooks/abacatepay`) use `postWithoutCsrf`.
- Rotas com `{param}` são compiladas para regex (`App\Core\Router::compilePattern`); parâmetros chegam em `$request->params()`/`route()`.
- Se nenhuma rota corresponder a um GET, o `Router` tenta resolver via `PaginasController::showByRoute` (páginas dinâmicas de banco) antes de devolver 404.
- Exceções não tratadas em rotas `/admin/*` são logadas (`Logger::error`) e redirecionam para `/admin/dashboard` com flash de erro; fora de `/admin`, a exceção é relançada.

### Camadas

- **Controllers** (`app/Controllers/`, com subpastas `Admin/`, `Professor/`, `Api/`, `Webhooks/`): devem ser finos — apenas capturam `Request`, chamam Services e devolvem `Response`/`View`. Estendem `App\Core\Controller`, que fornece helpers como `view()`, `json()`, `redirect()`, `submitAction()` e `redirectAfterCrudSave()` (padroniza o fluxo "salvar e continuar / salvar e saída / salvar e novo / salvar e copiar" dos formulários admin).
- **Services** (`app/Services/`): toda regra de negócio mora aqui (cálculo de rateio, elegibilidade de cupom, certificados, financeiro, RBAC, lixeira, etc.). `app/Services/Payments/` contém integrações de gateway (AbacatePay).
- **Models** (`app/Models/`): acesso a dados via PDO puro (`App\Core\Database::connection()`), sem ORM. Métodos retornam arrays associativos (`PDO::FETCH_ASSOC`). SQL é escrito manualmente nos models, com cuidado de compatibilidade MySQL 5.7 (ex.: subqueries em vez de window functions).
- **Views** (`resources/views/`): PHP puro renderizado por `App\Core\View::render()`, que injeta automaticamente `csrfToken`, `csrfField`, `old`/`oldInput` (old input após erro de validação) e envolve o conteúdo em `resources/views/layout.php`, exceto quando `$useLayout = false`.

### Middleware

`app/Middleware/`: `AuthenticateMiddleware` (`auth`), `CsrfMiddleware` (`csrf`, automático em POST) e `PermissionMiddleware` (`permission:<chave>`, ligado ao RBAC em `RbacService`). Resolvidos por alias string no `Router::resolveMiddleware`.

### Autenticação e RBAC

- Login por e-mail **ou** CPF (`AuthService`), CPF/e-mail únicos, bloqueio temporário após `MAX_LOGIN_ATTEMPTS` tentativas por `LOCK_MINUTES`.
- Permissões controladas por `RbacService` + `PermissionMiddleware`. Professores só veem cursos/turmas atribuídos (`ProfessorAcademicScopeService`) e nunca veem comprovantes PIX.

### Lixeira e auditoria

- Toda exclusão (lógica ou física) passa por `TrashService::record()`, que **exige justificativa** (`requireReason`) e grava snapshot em JSON na tabela `lixeira`.
- Mudanças relevantes devem gerar log/histórico (`AuditService`, `Logger::info/error/warning` grava em `storage/logs/app-YYYY-MM-DD.log`).

### Conteúdo/LMS e editor rico

- O "Conteúdo Unificado" (`Conteudo*` models/services) representa módulos/itens de curso (texto, arquivo, vídeo, link, avaliação textual, etiqueta).
- Editor rico usa **CKEditor 5 local** (`public_html/assets/vendor/ckeditor5/`, build Classic 41.4.2, GPL — sem CDN, sem licenseKey). Integração em `public_html/assets/js/conteudo-editor.js` + `conteudo-editor.css`, carregado por `resources/views/layout.php` com versionamento via querystring (`?v=...`).
- HTML do editor é sempre processado por `Helpers::decodeEditorHtml()` → `App\Support\HtmlSanitizer::clean()` → `Helpers::renderSafeHtml()` antes de ser exibido. Tags/atributos perigosos (`script`, `iframe`, `object`, `embed`, `form`, `input`, `on*`, `javascript:`/`data:` em `href`/`src`) são removidos pelo sanitizer — qualquer alteração no editor ou no sanitizer deve preservar esse comportamento.

### Armazenamento

- `config/storage.php` define caminhos absolutos para `storage/{app,logs,tmp,uploads,cache,private_uploads,trash}`, todos **fora** de `public_html`. Acesso a arquivos privados (comprovantes PIX, certificados, materiais) deve passar por rotas controladas, nunca por link direto.

### Regras de negócio centrais (não óbvias)

- Cursos/eventos são separados de turmas/edições.
- Cupom **não** se aplica a curso em promoção.
- Rateio é calculado sobre o **valor líquido recebido** e tem **teto de 75%** (configurável, mas nunca acima disso).
- Certificados têm código alfanumérico + QR Code + validação pública.
- Recuperação de senha usa token com validade de 60 minutos.
- PIX é manual no MVP (comprovante anexado pelo pagador, aprovação manual no admin).

## Fluxo de desenvolvimento (Spec Kit)

O projeto segue um fluxo `spec -> plan -> tasks -> implement -> validação`, definido em `.specify/memory/constitution.md` e exemplificado em `specs/0000-adocao-spec-kit/`. Para mudanças relevantes, crie/atualize spec, plano e tarefas antes de implementar. Princípios fixos dessa constituição:

- Regras de negócio centralizadas em Services; controllers finos.
- Compatibilidade obrigatória com MySQL 5.7.
- Mudanças incrementais — evitar overengineering.

## Regra editorial obrigatória (PT-BR)

Todo texto exibido ao usuário (frontend público, área do aluno, área do professor, admin/backoffice — incluindo mensagens de erro/sucesso, labels, botões e títulos) deve estar em **português brasileiro com acentuação correta** (ver `docs/padrao-editorial-ptbr.md`). Revise textos em views e em mensagens retornadas por controllers/services antes de concluir qualquer alteração.

## Deploy

Deploy é manual para Linux/cPanel (sem CI). `public_html/` deve conter apenas `index.php`, `.htaccess` e `assets/`; `app/`, `config/`, `resources/`, `routes/`, `sql/` e `storage/` ficam fora da área pública. Veja `docs/deploy.md`, `docs/go-live-checklist.md` e `docs/rollback.md` para o roteiro completo. Scripts de envio por FTP estão em `scripts/ftp_upload_*.ps1`.

## Cuidados ao editar arquivos

- Arquivos com `Cópia em conflito` no nome são artefatos de sincronização do Dropbox (ignorados pelo git) — não são parte do código ativo; não edite nem leve em conta seu conteúdo.
- `_cleanup_quarantine/`, `.backups/` e `deploy/_backup_local/` são áreas de quarentena/backup local (gitignored) — não as use como referência de código atual.
