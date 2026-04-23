# Deploy Runbook

Roteiro operacional curto para executar o go-live controlado do Polo Rainbow em Linux/cPanel.

## Objetivo

Publicar o sistema com risco controlado, validando o essencial antes de sair da manutencao.

## O que conferir antes de iniciar

Ausencias obvias para producao:

- `.env` nao pode faltar no servidor
- `storage/private_uploads/certificados` precisa existir e ser gravavel
- o controle de migrations precisa existir no processo, mesmo que seja manual

### Comandos locais de pre-checagem

```powershell
git status --short
Get-ChildItem 'sql' -Filter *.sql | Sort-Object Name | Select-Object -ExpandProperty Name
Test-Path '.env'
Test-Path 'storage/logs'
Test-Path 'storage/private_uploads'
Test-Path 'storage/private_uploads/certificados'
Test-Path 'storage/tmp'
```

### Conferencia rapida de ambiente PHP

```powershell
php -v
php -m
php -i | Select-String -Pattern 'date.timezone|post_max_size|upload_max_filesize|max_execution_time|memory_limit'
```

### Rodar o smoke local

O smoke depende de um servidor PHP ativo e de ambiente funcional.

```powershell
php -S 127.0.0.1:8000 -t public_html
php tests/Smoke/smoke.php http://127.0.0.1:8000
```

Se o smoke retornar `500` em rotas publicas, pare e verifique:

- `.env`
- conexao com o banco
- bootstrap do front controller
- logs em `storage/logs`

## Ordem de deploy

1. Ativar manutencao.
2. Fazer backup do banco.
3. Fazer backup dos arquivos.
4. Confirmar `APP_URL`, PHP e extensoes.
5. Confirmar storage fora de `public_html`.
6. Confirmar SMTP.
7. Publicar o codigo.
8. Aplicar migrations pendentes apenas.
9. Rodar smoke.
10. Validar SMTP real.
11. Validar upload privado.
12. Validar checkout.
13. Validar certificado.
14. Validar dashboard admin.
15. Validar dashboard professor.
16. Registrar snapshot.
17. Sair da manutencao.

## Checkpoints obrigatorios

- [ ] modo manutencao ativo antes de mexer
- [ ] backup do banco concluido
- [ ] backup dos arquivos concluido
- [ ] `.env` conferido no host
- [ ] PHP e extensoes conferidos
- [ ] storage privado conferido
- [ ] migrations pendentes mapeadas
- [ ] migrations pendentes aplicadas
- [ ] smoke executado
- [ ] SMTP validado
- [ ] upload privado validado
- [ ] checkout validado
- [ ] certificado validado
- [ ] dashboard admin validado
- [ ] dashboard professor validado
- [ ] snapshot final preenchido

## Critérios para abortar

Pare o go-live se ocorrer qualquer um destes pontos:

- erro 500 em rota critica
- falha de login
- falha de upload privado
- falha de SMTP
- falha de certificado
- falha de checkout
- dashboard admin ou professor indisponivel
- permissao incorreta por perfil
- acao sensivel sem log ou auditoria
- migration com erro ou schema desalinhado

## Saida da manutencao

Somente saia da manutencao depois de confirmar:

- rotas criticas respondendo
- e-mail funcionando
- upload privado funcionando
- dashboard admin e professor abrindo
- certificado validando publicamente

