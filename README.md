# Polo Rainbow

Bootstrap inicial em PHP MVC para evoluir para um portal de cursos e eventos.

## Stack

- PHP MVC sem framework pesado
- MySQL 5.7 via PDO
- Frontend mobile-first
- Rotas web e base para API
- Deploy preparado para Linux/cPanel
- Arquivos privados fora de `public_html`
- Base para logs, auditoria, lixeira e autenticação futura

## Estrutura

```text
app/
  Controllers/
    Api/
      HealthController.php
    HomeController.php
  Core/
    App.php
    Autoloader.php
    Controller.php
    Database.php
    Env.php
    ErrorHandler.php
    Helpers.php
    Logger.php
    Request.php
    Response.php
    Router.php
    Session.php
    Validator.php
    View.php
  Models/
    .gitkeep
  Services/
    AuditService.php
    AuthService.php
    FileStorageService.php
    TrashService.php
config/
  app.php
  database.php
  storage.php
public_html/
  .htaccess
  index.php
  assets/
    css/
      app.css
resources/
  views/
    errors/
      404.php
      500.php
    home.php
    layout.php
routes/
  api.php
  web.php
sql/
  .gitkeep
storage/
  cache/
  logs/
  private_uploads/
  trash/
```

## Execucao Local

Copie o ambiente:

```bash
cp .env.example .env
```

Inicie o servidor embutido do PHP:

```bash
php -S 127.0.0.1:8000 -t public_html
```

Rotas iniciais:

```text
GET /
GET /api/health
```

## cPanel

Configure o document root do dominio ou subdominio para `public_html/`.

Mantenha `storage/` fora da pasta publica. Ele deve ter permissao de escrita para:

- `storage/logs`
- `storage/cache`
- `storage/private_uploads`
- `storage/trash`

## Banco

A conexao PDO esta preparada em `app/Core/Database.php` e configurada por `config/database.php`.

As migrations futuras devem ser SQL simples em `sql/`, sem recursos incompatíveis com MySQL 5.7.

## Proximas Etapas

1. Banco de dados inicial.
2. Autenticacao e perfis.
3. Catalogo de cursos, eventos e turmas.
4. Pedidos, cupons e PIX manual.
5. Certificados, financeiro, logs, auditoria e lixeira.

## Area Academica

A base academica do portal foi preparada para acompanhar a inscricao aprovada do aluno e o escopo do professor.

### Rotas principais

- `GET /area-curso`
- `GET /area-curso/modulo`
- `GET /professor/area-curso`
- `GET /admin/area-curso`
- `GET /admin/academico`
- `GET /professor/academico`

### Regras suportadas

- presenca minima por curso ou turma
- progresso por aulas ou modulos
- avaliacao com nota minima
- aptidao para certificado
- logs, auditoria e lixeira para mudancas relevantes

## Configuracoes Globais

A camada central de configuracoes governa identidade institucional, certificados, frontend, seguranca e futura operacao financeira.

### Rotas principais

- `GET /admin/configuracoes-globais`
- `GET /admin/configuracoes-globais/certificados`
- `GET /admin/configuracoes-globais/financeiro`
- `GET /admin/configuracoes-globais/frontend`
- `GET /admin/configuracoes-globais/seguranca`

### Regras suportadas

- nome fantasia, razao social, CNPJ, cidade e UF
- prefixo do certificado
- template visual do portal
- politica de login e validade do reset de senha
- data de corte financeiro e teto de rateio
- e-mails institucionais para operacao do portal

## Financeiro e Repasses

A base financeira do portal trabalha com apuracao mensal por competencia, rateio sobre receita liquida, repasses por professor e controle fiscal PF/PJ.

### Rotas principais

- `GET /admin/financeiro`
- `GET /admin/financeiro/repasses`
- `GET /professor/financeiro`

### Regras suportadas

- rateio sobre a base liquida do pedido
- desconto de cupom reduz a base de apuracao
- teto maximo de rateio configuravel, limitado a 75%
- fechamento mensal por competencia
- professor PF ou PJ com controle fiscal
- exigencia de nota fiscal para PJ
- espelho de RPA para PF
- logs, auditoria e rastreio de mudancas relevantes

## Dashboard Executivo

O painel principal consolida indicadores comerciais, academicos, operacionais e financeiros.

### Rotas principais

- `GET /admin`
- `GET /admin/dashboard`
- `GET /professor`
- `GET /professor/dashboard`

### Indicadores suportados

- vendas por dia, semana e mes
- novos usuarios por periodo
- pedidos pendentes e comprovantes em analise
- inscricoes ativas e concluidas
- certificados emitidos
- cursos/eventos mais vendidos
- receita por periodo
- repasses por competencia
- total a pagar e total pago a professores

## QA e Homologacao

O pacote de validacao do portal fica em `docs/` e `tests/`.

### Arquivos principais

- `docs/qa-checklist.md`
- `docs/homologacao.md`
- `tests/Smoke/smoke.php`
- `tests/Fixtures/homologacao.md`

### Smoke test

```bash
php tests/Smoke/smoke.php https://polorainbow.com.br
```

O script valida rotas criticas do portal, incluindo dashboard admin, dashboard professor, areas protegidas, financeiro, configuracoes globais, certificados e validacao publica, e retorna `0` quando tudo responde como esperado.
