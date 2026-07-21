# Rollback

## 1. Reverter a Home de V2 para V1 (a ação mais provável de precisar reverter)

A troca é **um único flag**, sem migração de dados envolvida:

```
# no .env de produção
HOME_VERSION=v1
```

(ou remover a linha — `v1` é o padrão em `config/app.php` quando a variável
não existe). Nenhuma outra rota é afetada por esse flag — `/v2/...` continua
acessível normalmente, `/`, `/cursos`, `/login` etc. do V1 também. Não requer
deploy de código, só editar o `.env` do servidor e (se houver algum cache de
opcode/config) recarregar o PHP-FPM ou aguardar o próximo request.

## 2. Reverter uma alteração de código específica

Como não há CI/CD, um rollback de código é um `git revert` (ou reset seguido
de novo deploy manual dos arquivos afetados — nunca `git reset --hard` em
produção sem ter certeza do que está sobrescrevendo):

```bash
git log --oneline           # identifique o commit problemático
git revert <hash>           # cria um commit novo desfazendo aquele
git push origin frontend-v4
```

Depois, reenvie por FTP/SCP apenas os arquivos que o `git diff` do revert
alterou.

## 3. Reverter uma migration de banco (`sql/*.sql`)

Não há runner de migrations nem rollback automático. Cada arquivo em `sql/`
foi escrito manualmente; reverter significa escrever e aplicar manualmente o
SQL inverso (ex.: `ALTER TABLE ... DROP COLUMN` para desfazer um `ADD
COLUMN`). Sempre faça backup do(s) dado(s) afetado(s) antes de uma migration
em produção, especialmente se ela alterar ou remover colunas/linhas
existentes.

## 4. Reverter uma ação de dados (exclusões via `TrashService`)

Inscrições, pedidos e outras entidades excluídas pelos services do sistema
(`InscricaoService::excluir`, `PedidoService::excluir`, etc.) são **soft
delete** — a linha ganha `deleted_at`, mas continua no banco, e um snapshot
completo fica salvo em `lixeira` (`snapshot_dados`, em JSON) junto com a
justificativa e quem excluiu. Para restaurar:

1. Localize a linha em `lixeira` pelo `entidade_tipo`/`entidade_id`.
2. Restaure o `deleted_at` para `NULL` na tabela original (ex.:
   `UPDATE inscricoes SET deleted_at = NULL WHERE id = ...`).
3. Registre a restauração (`restaurado_por_usuario_id`, `restaurado_em`) na
   própria linha de `lixeira`, mantendo o rastro de auditoria.

Certificados **revogados** (`CertificadoService::revogar`) não são soft
delete — o registro nunca some, só o `status` muda para `revogado`. Para
reverter, usar o fluxo administrativo equivalente para voltar o status a
`emitido` (não há endpoint de "des-revogar" direto hoje; verificar com a
equipe antes de fazer isso manualmente no banco).

## 5. Se um deploy quebrar algo em produção agora

1. Não entre em pânico e não rode `git reset --hard`/`rm -rf` no servidor.
2. Confira `storage/logs/app-YYYY-MM-DD.log` (mais recente) e o log de erro
   do servidor web (`/home/desbloqueiacursos/logs/error_log`) para entender o
   que está acontecendo.
3. Se for algo pontual de código, prefira reverter só o(s) arquivo(s)
   específico(s) (reenviar a versão anterior por FTP) em vez de um rollback
   geral — o deploy é manual arquivo-a-arquivo, então o "raio de dano" de um
   deploy ruim normalmente também é pequeno.
4. Se envolver a Home V2 especificamente, o passo 1 deste documento
   (`HOME_VERSION=v1`) é o rollback mais rápido e seguro — reverte só a
   experiência padrão, sem mexer em código.
