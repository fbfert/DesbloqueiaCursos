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
