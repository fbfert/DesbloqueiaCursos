# Ambiente de desenvolvimento — Norminha IA V1

Montado em 22/08/2026 para atender às condições de segurança da seção 14.1 do Plano Mestre.
Antes disso, a única cópia do projeto era a produção, editada diretamente.

## O que existe

| | Produção | Desenvolvimento |
|---|---|---|
| Diretório | `/home/desbloqueiacursos/public_html` | `/home/desbloqueiacursos/norminha-dev` |
| Branch | `frontend-v4` | `feat/norminha-v1` |
| Banco | `desbloqueiacursos` | `desbloqueiacursos_dev` |
| E-mail | ativo | **desligado** (`MAIL_ENABLED=false`) |
| Gateway de pagamento | ativo | **desligado** (chaves vazias) |
| Norminha | `tutor_ativo=0` | `tutor_ativo=1` |
| Alcançável pela web | sim | **não** (fora do docroot) |

Os dois são **git worktrees do mesmo repositório**: compartilham o histórico, mas têm arquivos e
branch independentes. Commitar no dev não altera um único byte da produção.

```bash
git worktree list
# /home/desbloqueiacursos/public_html   [frontend-v4]      <- produção
# /home/desbloqueiacursos/norminha-dev  [feat/norminha-v1] <- desenvolvimento
```

## Como trabalhar

```bash
cd /home/desbloqueiacursos/norminha-dev

# sobe o servidor (o router reproduz o front controller do .htaccess)
php -S 127.0.0.1:8000 -t . tests/Smoke/router.php

# em outro terminal, a rede de regressão
php tests/Smoke/smoke.php http://127.0.0.1:8000
```

**Nunca** rode `git checkout` de outra branch dentro de `public_html`: aquele diretório é servido
ao vivo, e trocar de branch troca os arquivos que os alunos estão acessando neste instante.

## Migrations

Aplique sempre **primeiro no dev**:

```bash
mysql -u <usuario> -p desbloqueiacursos_dev < sql/073_norminha_conversas.sql
```

Só depois de validada, e com backup novo, a migration vai para produção. Não há runner automático —
a ordem numérica é aplicada à mão.

## Recriar o banco de desenvolvimento

Quando o dev divergir demais da produção, jogue fora e recrie:

```bash
mysqldump --single-transaction --quick --routines --triggers \
  --default-character-set=utf8mb4 desbloqueiacursos | gzip > /tmp/prod.sql.gz

mysql -e "DROP DATABASE IF EXISTS desbloqueiacursos_dev;
          CREATE DATABASE desbloqueiacursos_dev DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
zcat /tmp/prod.sql.gz | mysql desbloqueiacursos_dev
mysql -e "UPDATE desbloqueiacursos_dev.tutor_configuracoes SET valor='1' WHERE chave='tutor_ativo';"
```

⚠️ O dev contém **cópia dos dados reais de alunos** — nomes, CPF, e-mails, pedidos. Vale a mesma
proteção da produção: nunca exponha esse diretório pela web e não copie o banco para uma máquina
sem controle.

## Testes HTTP da API

`tests/Unit/norminha_api.php` fala HTTP com um servidor real, porque middleware,
CSRF e status HTTP não aparecem em teste de unidade. Ele precisa de credenciais:

```bash
php -S 127.0.0.1:8000 -t . tests/Smoke/router.php    # em um terminal
NORMINHA_API_CREDS=/caminho/creds.json php tests/Unit/norminha_api.php
```

O arquivo de credenciais é um JSON com `e1`, `e2`, `senha`, `u1`, `u2` e `inscricao`.
Para gerá-lo, defina uma senha conhecida para dois alunos **no banco de dev**:

```php
$hash = password_hash('SuaSenhaDeTeste', PASSWORD_DEFAULT);
// UPDATE usuarios SET senha_hash = :hash, status='ativo', tentativas_login=0,
//        bloqueado_ate=NULL WHERE id IN (:aluno_a, :aluno_b)
```

⚠️ **Só no banco de desenvolvimento.** Os alunos usados aqui são cópias de contas
reais; alterar a senha deles em produção tiraria o acesso de gente de verdade.
O script recusa rodar se `DATABASE()` for `desbloqueiacursos`.

O teste zera `norminha_uso` desses usuários no início — sem isso, a janela de
rate limit consumida por uma execução anterior derrubaria a seguinte.

## Backup de referência

`/home/desbloqueiacursos/backups/pre-norminha-20260822-202256/`

- `banco.sql.gz` — 11 MB, 121 tabelas, dump verificado
- `arquivos.tar.gz` — 184 MB, 2.158 arquivos, incluindo o `.env` de produção
- `gitignore.anterior`, `lixo/` — estado anterior

Modo 700/600, fora do docroot. Ponto de retorno no git: tag `pre-norminha-20260822`.

## O que este ambiente NÃO resolve

- **Não é homologação com URL própria.** É um servidor local na mesma máquina. Para testar
  comportamento dependente de HTTPS, domínio ou cookies `Secure`, ainda falta um subdomínio real.
- **Compartilha o servidor MySQL com a produção.** Bancos separados, mas mesma instância — e o
  usuário da aplicação tem `GRANT ALL PRIVILEGES ON *.*`, então um erro de código com nome de banco
  hard-coded pode alcançar a produção. Sempre confira `DB_DATABASE` antes de rodar script de escrita.
- **Não há CI.** A suíte de smoke precisa ser rodada à mão.
