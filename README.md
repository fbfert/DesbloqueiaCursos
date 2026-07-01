# Polo Rainbow

Portal de cursos e eventos em PHP MVC, preparado para Linux/cPanel, MySQL 5.7, checkout, area do curso, certificados, financeiro, dashboard e homologacao.

## Stack

- PHP MVC sem framework pesado
- MySQL 5.7 via PDO
- Frontend mobile-first
- Rotas web e base para API
- Deploy preparado para Linux/cPanel
- Arquivos privados fora de `public_html`
- Logs, auditoria e lixeira com justificativa

## Documentacao operacional

- [Documentação do projeto](docs/projeto.md)
- [Deploy](docs/deploy.md)
- [Go-live checklist](docs/go-live-checklist.md)
- [Rollback](docs/rollback.md)
- [QA checklist](docs/qa-checklist.md)
- [Homologacao](docs/homologacao.md)
- [Padrao editorial PT-BR](docs/padrao-editorial-ptbr.md)
- [Refatoracao admin desktop-first](docs/refatoracao-admin-desktop-2026-04.md)
- [Ajuste do editor de conteúdo na área do curso](docs/ajuste-editor-conteudo-area-curso-2026-05-27.md)
- [Recuperação de pedidos: redirecionamento corrigido](docs/2026-06-12-recuperacao-pedidos-redirecionamento-corrigido.md)
- [Recuperação de pedidos: automação por Cron](docs/2026-06-12-recuperacao-pedidos-cron-automacao.md)
- [Recuperação de pedidos: entrega final](docs/2026-06-12-recuperacao-pedidos-entrega-final.md)
- [Integração da API do Claude](specs/0001-integracao-api-claude/spec.md)

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
- `storage/tmp`
- `storage/uploads`
- `storage/private_uploads`
- `storage/trash`

## Deploy e Go-live

Antes de liberar em producao:

1. Fazer backup do banco.
2. Fazer backup dos arquivos.
3. Aplicar as migrations em ordem numerica.
4. Subir o codigo para o FTP.
5. Validar `APP_URL`, banco e SMTP.
6. Validar as rotas criticas e os fluxos principais.
7. Confirmar logs e upload privado.

Consulte os roteiros em `docs/deploy.md`, `docs/go-live-checklist.md` e `docs/rollback.md` para a sequencia operacional completa.

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

## Integração com o Claude

Configure as variáveis abaixo no `.env` antes de usar a integração:

- `ANTHROPIC_ENABLED`
- `ANTHROPIC_API_KEY`
- `ANTHROPIC_BASE_URL`
- `ANTHROPIC_MODEL`
- `ANTHROPIC_MAX_TOKENS`
- `ANTHROPIC_TEMPERATURE`
- `ANTHROPIC_TIMEOUT`

A rota interna de teste é:

```text
POST /api/claude/teste
```

Ela aceita `application/json` ou formulário com pelo menos o campo `prompt`.
