# Deploy

Guia operacional para publicar o Polo Rainbow em Linux/cPanel com controle de banco, storage, manutencao e rollback simples.

## 1. Estrutura em producao

```text
/home1/<usuario>/
├─ .env
├─ app/
├─ config/
├─ docs/
├─ public_html/
├─ resources/
├─ routes/
├─ sql/
└─ storage/
```

Dentro de `public_html/` mantenha somente:

- `index.php`
- `.htaccess`
- `assets/`

Nao publique `app/`, `config/`, `resources/`, `routes/`, `sql/`, `storage/` nem `.env` dentro da area publica.

## 2. Variaveis de ambiente obrigatorias

### Aplicacao

- `APP_NAME`
- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`

### Banco

- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_CHARSET`

### E-mail

- `MAIL_DRIVER`
- `MAIL_ENABLED`
- `MAIL_QUEUE_PROCESSING`
- `MAIL_FROM_EMAIL`
- `MAIL_FROM_NAME`
- `MAIL_REPLY_TO`
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `SMTP_ENCRYPTION`

### Sessao

- `SESSION_NAME`
- `SESSION_LIFETIME`
- `SESSION_SECURE`
- `SESSION_SAME_SITE`

## 3. Checklist do ambiente PHP

Antes do go-live confirme no PHP de producao:

- versao compativel com a aplicacao e com a extensao PDO MySQL
- extensao `pdo_mysql`
- extensao `openssl`
- extensao `mbstring`
- extensao `json`
- extensao `fileinfo`
- extensao `gd` ou a extensao usada pela geracao de PDF/QR Code, se aplicavel no host
- timezone configurado para `America/Sao_Paulo`
- `post_max_size` suficiente para formulario e upload de comprovante
- `upload_max_filesize` suficiente para comprovante e materiais privados
- `max_execution_time` suficiente para geracao de PDF, envio de e-mail e uploads
- `memory_limit` suficiente para PDF, QR Code e consultas de dashboard
- `session.cookie_secure = On` em HTTPS
- `session.cookie_httponly = On`
- `session.cookie_samesite = Lax`
- `session.gc_maxlifetime` compativel com a politica de login

### Valores praticos sugeridos

- `post_max_size = 16M`
- `upload_max_filesize = 16M`
- `max_execution_time = 120`
- `memory_limit = 256M`

Se o host exigir mais margem para PDF e upload, aumente com criterio.

## 4. Storage fora da public_html

O projeto usa arquivos privados para comprovantes, certificados e logs. Isso e critico porque parte do fluxo depende de comprovantes PIX e PDFs privados.

Pastas que precisam existir fora da area publica e com escrita habilitada:

- `storage/app`
- `storage/cache`
- `storage/logs`
- `storage/private_uploads`
- `storage/private_uploads/certificados`
- `storage/tmp`
- `storage/trash`
- `storage/uploads`

### Teste minimo de storage

- [ ] escrever arquivo em `storage/logs`
- [ ] escrever arquivo em `storage/tmp`
- [ ] escrever arquivo em `storage/private_uploads`
- [ ] escrever arquivo em `storage/private_uploads/certificados`
- [ ] testar upload real de comprovante
- [ ] testar leitura de arquivo por rota protegida

## 5. Ordem das migrations

### Regra operacional

Nao trate "rodar todas as migrations" como procedimento padrao de producao recorrente.

Isso serve para:

- ambiente novo
- restauracao de homologaçao limpa

Em producao ja existente, rode apenas migrations pendentes.

Motivos:

- migrations podem nao ser idempotentes
- `ALTER TABLE` repetido pode falhar
- inserts e seeds podem ser reexecutados indevidamente

### Controle recomendado

Use um controle de versao de banco. Recomendacao pratica:

- tabela `schema_migrations`
- ou checklist manual documentado no deploy, com registro do que ja foi aplicado

### Sequencia ativa do projeto

1. `sql/001_auth_module.sql`
2. `sql/002_rbac_access.sql`
3. `sql/003_catalogo.sql`
4. `sql/004_catalogo_refino.sql`
5. `sql/005_pedidos_inscricoes.sql`
6. `sql/006_pedidos_refino.sql`
7. `sql/007_status_and_coupon_prep.sql`
8. `sql/008_cupons.sql`
9. `sql/009_email_transacional.sql`
10. `sql/010_certificados.sql`
11. `sql/011_area_curso.sql`
12. `sql/012_presenca_avaliacao.sql`
13. `sql/013_configuracoes_globais.sql`
14. `sql/015_financeiro.sql`

### Nota sobre a migration 014

A migration `014` foi descartada/absorvida e nao faz parte da sequencia ativa.

### Ambiente novo

Em ambiente novo, aplique a cadeia completa, do `001` ao `015`, respeitando a sequencia ativa.

### Producao existente

Em producao existente, aplique somente o que estiver pendente na versao atual do banco.

## 6. Aplicacao operacional das migrations

### Passo previo

- [ ] backup do banco concluido
- [ ] modo manutencao ativado
- [ ] versao de codigo confirmada
- [ ] versao do schema confirmada
- [ ] `APP_URL` validado

### Ambiente novo

```bash
for file in $(ls sql/*.sql | sort); do
  mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < "$file"
done
```

### Producao existente

1. Consulte o controle de migrations.
2. Liste apenas os arquivos pendentes.
3. Execute um por um.
4. Registre horario, responsavel e resultado.
5. Valide rotas criticas antes de sair da manutencao.

### Se houver controle manual

Documente no deploy:

- migration aplicada
- data e hora
- responsavel
- resultado

## 7. SMTP

Configure o provedor SMTP no `.env` e valide antes do go-live:

- `MAIL_ENABLED=true` somente apos teste real
- `SMTP_HOST` correto
- `SMTP_PORT` correto
- `SMTP_USERNAME` e `SMTP_PASSWORD` corretos
- `SMTP_ENCRYPTION=tls` na maioria dos provedores
- `MAIL_FROM_EMAIL` e `MAIL_REPLY_TO` existentes e autorizados

### Teste minimo de e-mail

- cadastro
- recuperacao de senha
- pedido criado
- comprovante enviado
- pedido aprovado
- certificado disponivel

## 8. Logs

Os logs ficam em `storage/logs`.

Validacoes:

- diretorio gravavel pelo PHP
- log gerado em acao sensivel
- log gerado em tentativa negada
- log gerado em falha de integridade

## 9. Sessao

O front controller inicia a sessao automaticamente.

Validar:

- HTTPS ativo
- cookie seguro
- cookie HttpOnly
- SameSite configurado
- tempo de sessao compativel com login e seguranca

## 10. Modo manutencao

Antes de:

- aplicar migration
- restaurar banco
- trocar codigo

ative modo manutencao.

Objetivo:

- bloquear novos acessos sensiveis
- evitar gravacao parcial
- impedir checkout ou alteracao administrativa durante a janela de deploy

Forma pratica:

- exibir tela temporaria de manutencao no webroot
- ou ativar bloqueio operacional via servidor/painel

## 11. Checklist final de deploy

- [ ] backup do banco realizado
- [ ] backup dos arquivos realizado
- [ ] modo manutencao ativo
- [ ] PHP e extensoes conferidos
- [ ] storage fora da area publica
- [ ] permissao de escrita em storage validada
- [ ] migrations pendentes aplicadas
- [ ] SMTP validado
- [ ] logs gravando
- [ ] upload privado validado
- [ ] checkout validado
- [ ] certificado validado
- [ ] dashboard admin validado
- [ ] dashboard professor validado

## 12. Snapshot pos go-live

Registre no fechamento do deploy:

- commit publicado
- horario do deploy
- migrations executadas
- responsavel pelo deploy
- resultado do smoke test
- resultado da homologacao rapida
- observacoes ou pendencias

## 13. Saida da manutencao

Somente depois de:

- rotas criticas respondendo
- e-mail funcionando
- upload privado validado
- dashboard admin e professor abrindo
- certificado validando publicamente

remova o modo manutencao.

