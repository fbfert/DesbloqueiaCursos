# Limpeza controlada de pedidos de teste (até PR-20260516144927-EA5D35)

## Objetivo
Remover do banco **o pedido** `PR-20260516144927-EA5D35` **e todos os pedidos anteriores a ele** (testes), além de remover **comprovantes PIX vinculados** a esses pedidos.

Restrições:
- Não apagar usuários/alunos (`usuarios`).
- Não alterar cursos, turmas ou configurações.
- Preservar todos os pedidos **posteriores** ao corte.
- Não apagar arquivos físicos de comprovantes automaticamente (apenas listar caminhos).

## Estrutura encontrada (tabelas e relações)
Baseado nas migrations do projeto:
- `pedidos` (`id` PK, `codigo` UNIQUE, `created_at` DATETIME, `deleted_at`)
- `pedido_itens` (`pedido_id` → `pedidos.id` **ON DELETE CASCADE**)
- `participantes_pedido` (`pedido_id` → `pedidos.id` **ON DELETE CASCADE**)
- `inscricoes` (`pedido_id` → `pedidos.id` **ON DELETE CASCADE**)
- `status_pedidos_historico` (`pedido_id` → `pedidos.id` **ON DELETE CASCADE**)
- `status_inscricoes_historico` (`inscricao_id` → `inscricoes.id` **ON DELETE CASCADE**)
- `comprovantes_pix` (`pedido_id` → `pedidos.id` **ON DELETE CASCADE**)

Observação:
- O módulo de certificados (`certificados`) referencia `pedidos` por `pedido_id` com **ON DELETE SET NULL** (portanto, certificados eventualmente existentes de pedidos de teste podem permanecer, mas sem vínculo financeiro).

## Critério de corte
1. Localizar o pedido por `pedidos.codigo = 'PR-20260516144927-EA5D35'`.
2. Usar `pedidos.created_at` como `@data_corte`.
3. Considerar teste tudo que satisfaz `pedidos.created_at <= @data_corte`.

Fallback (somente se o pedido não for encontrado):
- Usar `2026-05-16 14:49:27` (timestamp embutido no código).

## Script gerado
- `sql/maintenance/limpeza_pedidos_teste_ate_PR-20260516144927-EA5D35.sql`

O script contém, nesta ordem:
1. Identificação do pedido de corte e `@data_corte`.
2. Diagnóstico (lista de pedidos afetados + contagens).
3. Backup lógico em tabelas `backup_*` (via `CREATE TABLE ... AS SELECT`).
4. Execução de deletes dentro de transação.
5. Conferência pós-delete.
6. `COMMIT` **comentado** e `ROLLBACK` ativo (modo seguro).

## Backups criados
O script cria tabelas (sem índices) com sufixo `20260516`, por exemplo:
- `backup_pedidos_teste_20260516`
- `backup_comprovantes_pix_teste_20260516`
- `backup_pedido_itens_teste_20260516`
- `backup_participantes_pedido_teste_20260516`
- `backup_inscricoes_teste_20260516`
- `backup_status_pedidos_historico_teste_20260516`
- `backup_status_inscricoes_historico_teste_20260516`

## Como executar com segurança
1. Execute o script **como está** (ele termina em `ROLLBACK`).
2. Revise:
   - Se o pedido de corte foi encontrado.
   - Se existe pelo menos 1 pedido posterior ao corte.
   - As contagens de pedidos/PIX/inscrições afetados.
3. Se estiver tudo correto:
   - Comente o `ROLLBACK;`
   - Descomente o `COMMIT;`
   - Execute novamente.

## Confirmação de preservação dos pedidos posteriores
O critério de exclusão é **por data real**: `created_at <= @data_corte`.
Pedidos com `created_at > @data_corte` são preservados.

## ALERTA
Não executar em produção sem validação prévia do diagnóstico e sem janela de manutenção.

