# Deploy — LMS Conteúdo Unificado

## Ordem de aplicação SQL
1. `sql/038_conteudo_unificado_lms.sql`
2. `sql/039_conteudo_unificado_lms_hardening.sql`
3. `sql/040_conteudo_migracao_legado.sql`

## Comandos de migração legada
Exemplos:

```bash
php storage/scripts/migrar_conteudo_legado.php --diagnosticar
php storage/scripts/migrar_conteudo_legado.php --curso=1 --dry-run
php storage/scripts/migrar_conteudo_legado.php --curso=1 --executar
```

## Permissões de storage
- Garantir escrita/leitura em `storage/private_uploads`.
- Validar proteção de execução de scripts em diretórios de upload.

## Cuidados com `.env`
- Revisar `APP_ENV`, `APP_DEBUG`, `APP_URL`.
- Revisar credenciais DB e sessão.
- Garantir configuração correta de caminhos de storage.

## Cuidados com SMTP
- Validar `MAIL_*` e `SMTP_*`.
- Testar envio real de e-mails de avaliação textual em homologação.

## Cuidados com backup
- Backup banco antes de migração.
- Backup arquivos antes de migração.
- Registrar timestamp e hash do pacote publicado.

## Testes imediatos pós-deploy
- Login admin/professor/aluno.
- Área do curso admin/professor com aba `Conteúdo`.
- Redirecionamento de abas antigas para `aba=conteudo`.
- Criação/edição de módulo e item.
- Fluxo de avaliação textual.
- Aptos/certificados (sem alterar regras).

## Rollback visual para legado (se necessário)
- Reverter somente alterações de navegação (tabs/redirecionamentos) para reexibir abas antigas.
- Não apagar dados migrados.
- Não remover tabela `conteudo_migracao_legado`.
- Manter trilha de auditoria do rollback (quem, quando, motivo).

