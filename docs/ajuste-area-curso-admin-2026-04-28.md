# Ajuste da Area do Curso no admin (2026-04-28)

## Contexto

No backend administrativo, a tela de Area do Curso exigia selecao de `curso` e `turma` para abrir o contexto de edicao.

Como o conteudo do curso passou a ser unico para todas as turmas, o fluxo foi simplificado para selecao apenas de `curso`.

## Alteracoes aplicadas

- Remocao do seletor de turma na tela admin da Area do Curso.
- Remocao de `turma_id` dos links de edicao e formularios dessa tela.
- Ajuste no controller admin para carregar o contexto por `curso_id` apenas.
- Ajuste de redirecionamentos pos-salvamento/exclusao para manter somente `curso_id`.
- Ajuste da listagem de participantes no endpoint admin para contexto de curso (sem filtro de turma).
- Limpeza do retorno `turmas` em `carregarAdmin()`, por nao ser mais utilizado nesse fluxo.
- Correcao editorial do titulo para `Area interna do curso` -> `Area interna do curso` com acentuacao correta na interface.

## Arquivos alterados

- `app/Controllers/Admin/AreaCursoController.php`
- `app/Services/AreaCursoService.php`
- `resources/views/admin/area-curso/index.php`

## Validacoes executadas

- `php -l app/Controllers/Admin/AreaCursoController.php`
- `php -l app/Services/AreaCursoService.php`
- `php -l resources/views/admin/area-curso/index.php`

Resultado: sem erros de sintaxe.

## Deploy

Publicacao realizada via FTP em `ftp.polorainbow.com.br`, com upload pontual dos arquivos alterados nos caminhos:

- `app/Controllers/Admin/AreaCursoController.php`
- `app/Services/AreaCursoService.php`
- `resources/views/admin/area-curso/index.php`

Confirmacao pos-upload realizada por listagem dos diretorios remotos correspondentes.
