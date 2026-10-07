# Tasks

## 1. Model: listagem multi-curso e descrição de alvos

- [x] 1.1 Criar `RevisaoComentario::listar(array $filtros)` com `curso_evento_id` opcional, `JOIN cursos_eventos` para o nome do curso, a ordem `FIELD(...)` atual e `LIMIT 201`; reescrever `listarPorCurso()` como atalho para `listar()`. Verificar com `php -l` e abrindo `/revisor/questoes` no ambiente local (lista do revisor inalterada)
- [x] 1.2 Criar `RevisaoComentario::descricoesDeAlvos($tipo, array $ids)` com um `SELECT ... WHERE id IN (...)` por tipo (prepared statement com placeholders gerados), retornando `id => texto` e ignorando registros com `deleted_at`. Verificar com `php -l`
- [x] 1.3 Alterar `updateTriagem()` para `WHERE id = :id AND status = 'aberto'` e retornar `rowCount()`. Verificar com `php -l`

## 2. Service: fila e triagem só de abertos

- [x] 2.1 Criar `RevisaoComentarioService::fila(array $filtros)`: normaliza filtros contra `SEVERIDADES`/`STATUS`, busca as linhas, resolve descrições em lote (uma consulta por tipo), aplica `strip_tags` + `html_entity_decode` + corte em 160 caracteres, marca "Alvo removido" e devolve `array('itens' => ..., 'truncado' => bool)`
- [x] 2.2 Em `triar()`, recusar apontamento com `status` diferente de `aberto` ("Este apontamento já foi triado.") e tratar `rowCount() === 0` do Model como o mesmo erro
- [x] 2.3 Criar `tests/Unit/revisao_fila.php` (no padrão de `tests/Unit/revisor_permissoes.php`) cobrindo: ordem aberto/erro primeiro, filtro inválido ignorado, alvo removido, recusa sem resposta, triagem repetida. Verificar com `php tests/Unit/revisao_fila.php` dentro do container local (saída sem falhas)

## 3. Admin: fila e triagem

- [x] 3.1 Criar `app/Controllers/Admin/RevisoesController.php` com `index` e `triar` (gestor de `Session::get('usuario_id')`, redirect preservando filtros via `SafeRedirect`). Verificar com `php -l`
- [x] 3.2 Registrar em `routes/web.php` `GET /admin/revisoes` e `POST /admin/revisoes/triar` com `array('auth', 'permission:conteudo.gerenciar')`. Verificar que `/admin/revisoes` sem sessão redireciona ao login e, com o usuário revisor, é negado
- [x] 3.3 Criar `resources/views/admin/revisoes/index.php` (desktop-first, padrão visual de `admin/_shell.php`): filtros por curso/severidade/situação, cartão por apontamento com curso, alvo, trecho, comentário, autor, datas e triagem; formulário de triagem só para abertos, com resposta obrigatória indicada para "Recusar"; aviso quando `truncado`. Todo texto escapado com `Helpers::e()`. Verificar triando localmente um apontamento de cada tipo
- [x] 3.4 Adicionar o item "Revisões" em `resources/views/admin/_shell.php` com `permissions_any => array('conteudo.gerenciar')`. Verificar que aparece para o admin de homologação, fica aceso em `/admin/revisoes` e não aparece para o professor
- [x] 3.5 Adicionar `/admin/revisoes` ao smoke test (`tests/Smoke`), como rota protegida que deve terminar no login do admin. Verificar com `php tests/Smoke/smoke.php http://127.0.0.1:8010` no container

## 4. Alerta na tela do curso

- [x] 4.1 Em `CursosController::edit()` e `show()`, passar `errosRevisaoAbertos` (contagem quando o usuário tem `conteudo.gerenciar`, senão `0`). Verificar com `php -l`
- [x] 4.2 Criar o partial `resources/views/admin/cursos/_alerta_revisao.php` e incluí-lo em `form.php` (modo edição) e `show.php`; nada é renderizado com `0`. Verificar com um curso com 2 erros abertos (alerta e link filtrado) e um sem (nenhum alerta)

## 5. Validação integrada

- [x] 5.1 Rodar `php -l` em todos os arquivos PHP criados ou alterados e `php tests/Unit/revisor_permissoes.php` e `php tests/Unit/revisor_academic_scope.php` para confirmar que o revisor não regrediu
- [x] 5.2 Percurso completo local: vincular o revisor a um curso, registrar apontamentos `erro` e `sugestao`, ver o alerta no curso, triar na fila (aceitar, recusar com resposta, resolver), conferir a resposta na área do revisor e o registro em auditoria
- [x] 5.3 Revisar todos os textos novos de interface e mensagens em PT-BR com acentuação (`docs/padrao-editorial-ptbr.md`)
- [x] 5.4 Registrar a entrega em `docs/` no padrão dos demais documentos (data, escopo, decisões, verificação) e marcar a fase 4 como concluída em `docs/2026-08-21-perfil-revisor.md`

## Workflow follow-up

- Antes do deploy, conferir no admin de produção quais perfis têm `conteudo.gerenciar` — é a lista de quem passa a triar.
- Arquivar a mudança com `/opsx:archive` depois do deploy validado, criando `openspec/specs/revisao-conteudo/spec.md`.
