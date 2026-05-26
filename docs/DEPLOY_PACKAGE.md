# Pacote limpo de deploy

## O que entra no ZIP
- `index.php`
- `.htaccess`
- `.env.example`
- `app/`
- `config/`
- `public_html/`
- `resources/`
- `routes/`
- `sql/`

## O que fica fora
- `.git/`
- `.codex/`
- `_cleanup_quarantine/`
- `deploy/_backup_local/`
- `deploy/releases/`
- cookies
- logs
- backups
- temporários
- arquivos de conflito
- dumps não oficiais
- documentação interna que não precise ir para produção
- `docs/`
- `tests/`
- `scripts/`
- `node_modules/`
- `vendor/`
- diretórios de runtime em `storage/`

## Como gerar o ZIP
1. Abra um terminal Bash na raiz do projeto.
2. Execute:
   ```bash
   bash deploy/build_release.sh
   ```
3. O pacote será criado em `deploy/releases/` com nome no formato `portal-cursos-release-YYYYmmdd-HHMMSS.zip`.

## Como conferir o conteúdo antes de enviar ao servidor
- Listar o conteúdo:
  ```bash
  unzip -l deploy/releases/portal-cursos-release-*.zip
  ```
- Ou inspecionar nomes:
  ```bash
  zipinfo -1 deploy/releases/portal-cursos-release-*.zip
  ```
- Confirmar que não há `.git/`, `.codex/`, cookies, logs, backups, temporários, conflitos ou dumps.

## Pastas que devem existir no servidor
- `storage/cache/`
- `storage/logs/`
- `storage/tmp/`
- `storage/backups/`
- `storage/uploads/`
- `storage/private_uploads/`
- `storage/sessions/`
- `storage/trash/`

## Permissões
- O usuário do web server precisa de escrita nas pastas de `storage/` usadas pelo aplicativo.
- `public_html/` deve permanecer legível para o servidor web.
- Não publique arquivos de runtime, logs ou backups dentro do pacote.

## Observação
- O script foi desenhado para ser conservador: ele empacota apenas os diretórios de aplicação e as migrations oficiais de `sql/`.
