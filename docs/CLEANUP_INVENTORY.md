# Inventário de limpeza

## Resumo
- Arquivos em quarentena: 565
- Conflitos fora da quarentena: apenas os três metadados da árvore Git, mantidos por cautela.
- Nenhum arquivo sensível, temporário ou de backup permaneceu no pacote de deploy.
- O arquivo `.env` foi mantido localmente por cautela operacional.

## Itens movidos para quarentena

### Diretórios movidos integralmente
- `.codex/`
- `deploy/_backup_local/`
- `storage/backups/`
- `storage/logs/`
- `storage/scripts/`
- `storage/sessions/`
- `storage/tmp/`
- `storage/test_uploads/`

### Arquivos de topo movidos
- `admin.cookies`
- `admin1.cookies`
- `deploy/portal_cursos_patch_2026-05-10.zip`
- `public_html/assets/css/app.css.bak`
- `_cleanup_quarantine_moved_files.txt`

### Duplicados de conflito movidos
- `app/` — cópias com sufixo de conflito em controllers, models e services
- `docs/` — cópias de documentação operacional e especificações internas
- `public_html/` — cópias de CSS e ícones de administração
- `resources/` — cópias de e-mails e views
- `routes/` — cópia de rotas
- `sql/` — cópias de migrations e scripts de manutenção

### Artefatos de storage movidos
- `storage/` continha logs, HTMLs de captura, dumps, CSVs, binários de teste, respostas HTTP, scripts temporários e arquivos de validação.
- Esses itens foram preservados na quarentena com a estrutura original para rastreabilidade.

## Itens mantidos por cautela
- `.env`
- `.env.example`
- `storage/private_uploads/`
- `storage/uploads/`
- `storage/trash/`
- `storage/cache/.gitkeep`

## Revisão manual necessária
- `.git/COMMIT_EDITMSG (Cópia em conflito de URSS4 2026-05-19)`
- `.git/index (Cópia em conflito de URSS4 2026-05-19)`
- `.git/ORIG_HEAD (Cópia em conflito de URSS4 2026-05-19)`

## Observação
- A quarentena preserva a origem dos itens movidos.
