# Relatório de limpeza

## Resumo das ações
- Foi feita uma inspeção geral antes de qualquer alteração.
- Foi criada a pasta `_cleanup_quarantine/`.
- Foram movidos para quarentena os artefatos claramente indevidos para deploy.
- Foram preservadas as pastas e arquivos de runtime que exigem cautela operacional.
- O `.gitignore` foi revisado para cobrir segredos, logs, backups, temporários, quarentena e nomes de conflito.
- Foi criado `deploy/build_release.sh` para gerar pacote ZIP limpo.

## Quantidade movida
- Arquivos em quarentena: 565
- Principais tipos removidos do pacote:
  - cookies de sessão
  - logs
  - backups locais
  - temporários e caches
  - HTMLs e artefatos de teste/captura
  - arquivos de conflito com nomes duplicados
  - pacote ZIP de operação local
  - manifesto temporário de limpeza

## Itens mantidos por cautela
- `.env`
- `.env.example`
- `storage/private_uploads/`
- `storage/uploads/`
- `storage/trash/`
- `storage/cache/.gitkeep`

## Revisão manual
- Os três arquivos com conflito dentro de `.git/` foram registrados, mas não mexidos.
- Nenhuma regra de negócio foi alterada.
- Nenhum banco de dados foi tocado.
- Nenhuma migration foi executada.

## Mudanças no `.gitignore`
- Adicionados padrões para `.env` e `.env.*`, mantendo `!.env.example`.
- Adicionados padrões para `*.log`, `*.tmp`, `*.bak`, `*.old`, `*.zip`, `*.tar`, `*.tar.gz`, `*.gz`, `*.cookies` e `*.sql`.
- Adicionados padrões para backups locais, quarentena e diretórios de runtime.
- Adicionados padrões para `.codex/`, `.vscode/`, `node_modules/`, `vendor/`, `storage/`, `var/` e nomes de conflito.
- Mantida exceção para migrations oficiais em `sql/`.

## Validações executadas
- `git status`
- buscas por conflitos, cookies, logs, backups e temporários
- busca por marcadores de merge (`<<<<<<<`, `=======`, `>>>>>>>`)
- verificação final de risco fora da quarentena

## Confirmações
- Nenhuma regra de negócio foi alterada.
- Nenhuma migration foi executada.
- Nenhuma credencial real foi alterada.
- O pacote limpo será gerado separadamente por `deploy/build_release.sh`.
