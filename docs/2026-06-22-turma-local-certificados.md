# Local da turma nos certificados

Implementação pontual para suportar o campo opcional `local` em turmas e alimentar o placeholder existente `{turma_local}`.

## Arquivos alterados

- `app/Models/Turma.php`
- `app/Services/TurmaService.php`
- `resources/views/admin/turmas/form.php`
- `sql/060_turmas_local.sql`

## Coluna criada

- `turmas.local VARCHAR(255) NULL`

## Regra aplicada

- O valor do formulário é salvo na tabela `turmas`.
- O placeholder `{turma_local}` continua sendo alimentado a partir de `turma.local`.
- Turmas sem local continuam funcionando com valor vazio.

## Validação real

- `php -l` executado com sucesso nos arquivos PHP alterados.
- Banco ao vivo atualizado com a coluna `turmas.local`.
- Criação temporária de turma validou gravação de `local`.
- Edição da turma `id=60` validou gravação de `local`.
- O certificado real `DESX9GASEW` foi gerado novamente via `pdfBytesByCodigo`.
- O placeholder `{turma_local}` renderizou o texto `Sala 101 - teste live` no serviço de placeholders.
- Nenhum resíduo foi deixado no banco após o teste.
