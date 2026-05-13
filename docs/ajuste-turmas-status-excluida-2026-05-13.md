# Ajuste de status das turmas — `excluida`

Data: 2026-05-13

## Contexto

Havia inconsistência entre a tabela `turmas`, a tela administrativa e a regra de exclusão das turmas.
O banco ainda aceitava `cancelada`, enquanto a interface e a operação administrativa precisavam trabalhar com `excluida`.

## Alterações aplicadas

- Padronização do enum de `turmas.status` para `planejada`, `aberta`, `encerrada` e `excluida`.
- Migração SQL para converter registros antigos `cancelada` para `excluida`.
- Ajuste do service de turmas para aceitar o novo status.
- Ajuste do model para não tratar `excluida` como registro ativo.
- Ajuste do formulário administrativo para exibir `Excluída`.
- Ajuste da listagem administrativa para exibir `Excluída` com acentuação correta.

## Arquivos alterados

- `sql/003_catalogo.sql`
- `sql/032_atualizar_status_turmas.sql`
- `app/Services/TurmaService.php`
- `app/Models/Turma.php`
- `resources/views/admin/turmas/form.php`
- `resources/views/admin/turmas/index.php`

## Validação

- `php -l app/Services/TurmaService.php`
- `php -l app/Models/Turma.php`
- `php -l resources/views/admin/turmas/form.php`
- `php -l resources/views/admin/turmas/index.php`

Resultado: sem erros de sintaxe.

## Publicação

A publicação deve ser feita por FTP com upload pontual dos arquivos alterados no projeto.
O arquivo de documentação permanece no repositório para rastreabilidade da mudança.
