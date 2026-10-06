# Fila de revisões no admin: os apontamentos do revisor ganham quem os responda

Data: 2026-10-05
Mudança OpenSpec: `openspec/changes/fila-revisao-admin/` (proposta, spec, design e tarefas)
Spec de origem: fase 4 de `specs/0002-perfil-revisor/`
Migration: **nenhuma**
Estado: **implementado e validado em ambiente local**; aguardando deploy

Até aqui o revisor registrava apontamentos e nada do outro lado os via. A regra
de triagem já existia (`RevisaoComentarioService::triar()`), sem tela que a
chamasse. Esta entrega fecha o fluxo e é a primeira mudança do projeto feita no
OpenSpec.

## O que muda para quem usa

- **`/admin/revisoes`** — fila com os apontamentos de todos os cursos: abertos
  primeiro e, dentro deles, o mais grave no topo (erro, impreciso, dúvida,
  sugestão). Filtros por curso, severidade e situação.
- Cada linha diz **onde** está o problema: curso, tipo do alvo e o título do
  item ou módulo, ou o início do enunciado da pergunta. Alvo excluído depois do
  apontamento aparece como "Alvo removido", sem quebrar a tela.
- **Triagem**: aceitar, marcar como resolvido ou recusar. Recusar exige
  resposta; a resposta aparece para o revisor na área dele.
- **Alerta na tela do curso** (`/admin/cursos/editar` e `/admin/cursos/show`):
  "Há N erros apontados pela revisão aguardando triagem", com link para a fila
  já filtrada.
- Item **Revisões** no menu lateral, grupo Cursos.

## Decisões

**Permissão: `conteudo.gerenciar`, e não `area_curso.gerenciar`.** A spec antiga
previa a segunda, mas pelas migrations ela só pertence ao superadmin; o perfil
`conteudo` — o gestor de conteúdo da spec — tem a primeira, que é também a
exigida pela tela de edição do curso, onde o alerta aparece. Decisão do
responsável pelo produto. O perfil Revisor continua sem nenhuma permissão
`*.gerenciar`: a garantia central da feature não mudou.

**Apontamento triado não é triado de novo.** A regra antiga aceitava triar duas
vezes, e dois gestores ao mesmo tempo sobrescreviam um ao outro. Agora a
checagem está no Service e no próprio `UPDATE` (`WHERE status = 'aberto'`): o
segundo gestor recebe "Este apontamento já foi triado."

**Descrição do alvo em lote.** Uma consulta por tipo de alvo, não uma por linha:
no máximo cinco consultas por página, qualquer que seja o tamanho da fila. A
fila mostra até 200 apontamentos e avisa quando há mais.

**Volta à fila preservando filtros sem confiar no navegador.** A URL de retorno
é remontada no servidor a partir dos filtros já validados.

## Arquivos

| Camada | Arquivos |
|---|---|
| Model | `app/Models/RevisaoComentario.php` — `listar()` multi-curso, `descricoesDeAlvos()`, `cursosComComentarios()`, `updateTriagem()` só em aberto |
| Service | `app/Services/RevisaoComentarioService.php` — `fila()`, normalização de filtros, `triar()` sem retriagem |
| Controller | `app/Controllers/Admin/RevisoesController.php` (novo), `app/Controllers/Admin/CursosController.php` (alerta) |
| Views | `resources/views/admin/revisoes/index.php` (nova), `resources/views/admin/cursos/_alerta_revisao.php` (novo), `form.php`, `show.php`, `admin/_shell.php` (menu) |
| Rotas | `routes/web.php` — `GET /admin/revisoes`, `POST /admin/revisoes/triar` |
| Testes | `tests/Unit/revisao_fila.php` (novo, 16 casos), `tests/Smoke/rotas.php` |

## Verificação

- `php -l` nos 12 arquivos PHP criados ou alterados.
- `tests/Unit/revisao_fila.php`: 16 passou. Teste de mutação: removidas as duas
  travas de retriagem, 2 casos falham — os testes de fato as cobrem.
- `tests/Unit/revisor_permissoes.php` (8) e `revisor_academic_scope.php` (24):
  sem regressão no revisor.
- Smoke: `/admin/revisoes` sem sessão termina no login. As 2 falhas restantes
  da suíte (`/v2/quem-somos`, `/v2/onde-estamos`) são páginas que só existem no
  banco de produção.
- Percurso HTTP completo em ambiente local (PHP 8.3 + MariaDB 10.5): revisora
  registra 4 apontamentos pela própria interface; fila acessível para gestor de
  conteúdo e superadmin, 403 para revisor e professor; filtros, inclusive com
  valor inválido e em formato de array, sem erro; aceitar, recusar (com e sem
  resposta), resolver e retriagem; POST sem CSRF rejeitado e registrado em
  `auditoria_logs`; triagens auditadas como `revisao.comentario.triado`;
  resposta visível para a revisora; alerta presente para quem gerencia
  conteúdo e ausente para perfil só de leitura e para curso sem erros.

## Antes do deploy

1. Conferir no admin de produção **quais perfis têm `conteudo.gerenciar`** — é
   a lista de quem passa a triar.
2. Subir por FTP os arquivos da tabela acima. Sem migration.
3. Validar com um apontamento de teste em curso não publicado.

Rollback: reenviar as versões anteriores dos arquivos alterados e remover os
três novos (`RevisoesController.php`, `admin/revisoes/index.php`,
`admin/cursos/_alerta_revisao.php`).

## Achado colateral

A trilha de navegação do admin (`resources/views/admin/_shell.php`, linha 157)
monta o rótulo a partir do segmento da URL com `ucfirst`, então mostra
"Revisoes", "Area curso", "Configuracoes globais" — sem acento, contra o padrão
editorial. É anterior a esta mudança e afeta todo o admin; fica registrado para
uma correção própria.
