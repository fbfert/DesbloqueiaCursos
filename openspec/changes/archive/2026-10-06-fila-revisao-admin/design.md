# Design

## Context

As fases 1 a 3 do revisor (ver `specs/0002-perfil-revisor/plan.md`) deixaram pronta quase toda a camada de dados e regra:

- `revisao_comentarios` (migration 072) já tem `status`, `resposta`, `triado_por`, `triado_em` e o índice `idx_revisao_curso_status (curso_evento_id, status)`.
- `RevisaoComentario::listarPorCurso($cursoId, $filtros)` já aplica filtros de status/severidade/autor/alvo e a ordem de tratamento exigida pela spec — mas **exige** um curso.
- `RevisaoComentario::contarErrosAbertos($cursoId)` já existe e é usado pelo painel do revisor.
- `RevisaoComentarioService::triar()` valida a situação, exige resposta na recusa, grava e audita — mas **não** impede triar de novo um apontamento já triado.
- `RevisorLeituraService` resolve conteúdo para leitura do revisor, e é deliberadamente injetado só em controllers do revisor.

O que falta é a superfície do admin e dois ajustes de regra.

## Goals / Non-Goals

**Goals:**
- Fila multi-curso com a mesma ordem e filtros já usados por curso, sem duplicar SQL de ordenação.
- Triagem reaproveitando `triar()`, que passa a recusar apontamento já triado.
- Descrição legível do alvo sem N+1 consultas por linha.

**Non-Goals:**
- Paginação: o volume esperado é de dezenas a poucas centenas de apontamentos por curso; a fila mostra no máximo 200 linhas por vez com aviso para refinar o filtro. Paginação entra se o volume real pedir.
- Reaproveitar `RevisorLeituraService` no admin — ele existe para o revisor e lê corpo inteiro de conteúdo, que a fila não precisa.

## Decisions

### 1. Generalizar `listarPorCurso` em vez de criar um segundo método de listagem
`RevisaoComentario::listar(array $filtros)` passa a ter `curso_evento_id` como filtro **opcional**, e `listarPorCurso($cursoId, $filtros)` vira um atalho que chama `listar()` com o curso preenchido — os chamadores atuais (área do revisor) não mudam. A consulta ganha `JOIN cursos_eventos` para trazer o nome do curso e um `LIMIT` (200 + 1, para saber se há mais).

*Alternativa descartada:* método `listarFila()` separado — duplicaria o `ORDER BY FIELD(...)`, e as duas ordens divergiriam com o tempo.

*MySQL 5.7:* `FIELD()` e `LIMIT` com inteiro concatenado após cast já são usados no projeto; nada novo.

### 2. Descrição do alvo resolvida em lote no Service
`RevisaoComentarioService::fila(array $filtros)` busca as linhas e, para cada `alvo_tipo` presente, faz **uma** consulta `WHERE id IN (...)` na tabela correspondente:

| alvo_tipo | tabela | campo exibido |
|---|---|---|
| `conteudo_item` | `conteudo_itens` | título |
| `conteudo_modulo` | `conteudo_modulos` | título |
| `quiz_pergunta` | `conteudo_quiz_perguntas` | enunciado, sem tags, cortado em 160 caracteres |
| `quiz_alternativa` | `conteudo_quiz_alternativas` | texto, sem tags, cortado em 160 caracteres |

No máximo 5 consultas por página (lista + 4 tipos), independentemente do número de linhas. Alvo ausente ou com `deleted_at` preenchido vira o rótulo "Alvo removido". O texto é reduzido com `strip_tags` + `html_entity_decode` e escapado na view com `Helpers::e()` — nunca renderizado como HTML, então não passa pelo sanitizer.

Os `SELECT`s de alvo ficam num método novo do Model `RevisaoComentario` (`descricoesDeAlvos($tipo, array $ids)`), para manter o SQL fora do Service.

### 3. Triagem só de apontamento aberto
`triar()` ganha a checagem `status === 'aberto'` antes de gravar, retornando erro "Este apontamento já foi triado." Além da checagem no PHP, `updateTriagem` passa a usar `WHERE id = :id AND status = 'aberto'` e o Service verifica `rowCount()`: dois gestores triando o mesmo item ao mesmo tempo não sobrescrevem um ao outro.

### 4. Controller e rotas
`App\Controllers\Admin\RevisoesController`, fino:
- `GET /admin/revisoes` → `index`: normaliza filtros contra `SEVERIDADES`/`STATUS` (valor inválido vira vazio), chama `fila()`, lista de cursos para o filtro via `CursoService` existente.
- `POST /admin/revisoes/triar` → `triar`: lê `id`, `status`, `resposta`, usa `Session::get('usuario_id')` como gestor, chama `triar()`, devolve flash e redireciona para a fila **preservando os filtros** (querystring repassada num campo oculto, validada por `SafeRedirect`).

Ambas com `array('auth', 'permission:conteudo.gerenciar')`; o POST recebe `csrf` automaticamente por `$app->post()`.

### 5. Alerta na tela do curso
`CursosController::edit()` e `show()` passam `errosRevisaoAbertos` para a view: o valor de `contarErrosAbertos()` quando o usuário tem `conteudo.gerenciar` (via `RbacService::userHasPermission`), senão `0`. Um partial `resources/views/admin/cursos/_alerta_revisao.php` é incluído nas duas views e não renderiza nada com `0`.

### 6. Menu
Item `array('label' => 'Revisões', 'href' => '/admin/revisoes', 'permissions_any' => array('conteudo.gerenciar'))` no mesmo grupo de "Cursos" em `_shell.php`. `AdminMenu` já resolve o item aceso pelo prefixo mais longo, sem ajuste.

## Risks / Trade-offs

- [Permissão diferente da spec antiga] → registrado na proposta; o perfil Revisor continua sem nenhuma permissão `*.gerenciar`, então a garantia central da feature (revisor não edita nem tria) não muda.
- [Permissões de produção podem diferir das migrations] → verificar no admin de produção, antes do deploy, quais perfis têm `conteudo.gerenciar`; essa é a lista de quem passa a triar.
- [Limite de 200 linhas esconde itens antigos] → a ordem coloca os abertos e graves primeiro, e a tela avisa quando há mais linhas que o limite.
- [Mudança em `updateTriagem` afeta outro chamador] → hoje o único chamador é `triar()`; confirmado por busca.

## Ajustes feitos na implementação

- **`LIMIT` opcional em `listar()`**: aplicado só pela fila do admin. Aplicá-lo em `listarPorCurso()` cortaria a lista do revisor, que hoje não tem limite.
- **Volta à fila sem `SafeRedirect`**: `SafeRedirect` só trata caminhos da V2. A URL de retorno é remontada no servidor (`RevisaoComentarioService::queryStringFila()`) a partir dos filtros normalizados — nenhuma URL vinda do navegador é usada como destino.
- **Filtro de curso**: lista só os cursos que têm apontamentos (`RevisaoComentario::cursosComComentarios()`), com a contagem, em vez de todos os cursos via `CursoService`.
- **Filtro em formato de array** (`?severidade[]=x`) é tratado como ausente: o cast de array para string emitiria warning, que o `ErrorHandler` do projeto transforma em 500.
- **`mb_*` com fallback**, no padrão de `AreaCursoService`, para servidores sem mbstring.

## Migration Plan

Sem migration. Deploy por FTP dos arquivos novos e alterados; rollback reenviando as versões anteriores. Validar em produção com um apontamento de teste em curso não publicado.
