# Plano técnico: Perfil Revisor com comentários de revisão

## Estratégia

Não construir nada que já exista. O perfil sai do RBAC atual (`perfis` + `perfil_permissoes` + `usuario_perfis`), o escopo por curso sai de `curso_pessoas_vinculadas` e a proteção de rota sai do `PermissionMiddleware`. O que é realmente novo é uma tabela de comentários, um Service de escopo e um conjunto pequeno de telas de leitura.

A área do revisor é **somente leitura sobre conteúdo** e **escrita apenas em `revisao_comentarios`**. Nenhum controller do revisor toca em `ConteudoCursoService` ou em qualquer Service de gravação de conteúdo — a separação é física, não convencional.

## Artefatos a criar ou ajustar

**Migration**

- `sql/072_perfil_revisor_comentarios.sql` — cria `revisao_comentarios`, acrescenta `revisor` ao enum `tipo_pessoa` de `curso_pessoas_vinculadas`, insere o perfil `revisor` em `perfis`, insere as duas permissões em `permissoes` e as vincula ao perfil

**Models**

- `app/Models/RevisaoComentario.php`

**Services**

- `app/Services/RevisorAcademicScopeService.php` — espelha o `ProfessorAcademicScopeService`, filtrando `tipo_pessoa = 'revisor'`
- `app/Services/RevisaoComentarioService.php` — regras de criação, edição enquanto `aberto`, triagem pelo gestor, exclusão via `TrashService`

**Controllers**

- `app/Controllers/Revisor/DashboardController.php` — cursos atribuídos
- `app/Controllers/Revisor/RevisaoController.php` — árvore do curso, leitura de aula, leitura de banco de questões, criação e edição de comentário
- `app/Controllers/Admin/RevisoesController.php` — fila, triagem e resposta

**Views**

- `resources/views/revisor/dashboard.php`
- `resources/views/revisor/curso.php`
- `resources/views/revisor/conteudo.php`
- `resources/views/revisor/questoes.php`
- `resources/views/revisor/_painel_comentarios.php`
- `resources/views/admin/revisoes/index.php`

**Rotas**

- `routes/web.php` — bloco `/revisor/*` com `auth` + `permission:area_curso.revisor.ver` (leitura) e `permission:area_curso.revisor.comentar` (gravação); bloco `/admin/revisoes/*` com `permission:area_curso.gerenciar`

**Testes**

- `tests/Unit/revisor_academic_scope.php` — massa própria em transação revertida, no molde de `quiz_simulado_pnd.php`
- `tests/Unit/revisor_permissoes.php` — afirma exatamente quais permissões o perfil tem

> Correção em relação à primeira versão deste plano: ele dizia "no molde de
> `professor_academic_scope.php`". **Esse arquivo não existe.** O `CLAUDE.md` o
> cita como exemplo de comando, mas `tests/Unit/` tem apenas `_bootstrap.php`,
> `checkout_rapido_*`, `quiz_rascunho`, `quiz_simulado_pnd`, `quiz_sorteio` e
> `quiz_system`. O escopo de professor, na prática, não é coberto por teste — o
> que reforça a decisão de cobrir o do revisor.

## Modelagem

`revisao_comentarios`

| Coluna | Tipo | Papel |
|---|---|---|
| `id` | bigint unsigned PK | |
| `curso_evento_id` | bigint unsigned | escopo e consulta da fila |
| `alvo_tipo` | varchar(30) | `conteudo_item`, `quiz_pergunta`, `quiz_alternativa`, `conteudo_modulo` |
| `alvo_id` | bigint unsigned | |
| `trecho` | text NULL | texto colado pelo revisor |
| `comentario` | text | |
| `severidade` | varchar(20) | `erro`, `impreciso`, `sugestao`, `duvida` |
| `status` | varchar(20) | `aberto`, `aceito`, `recusado`, `resolvido` |
| `resposta` | text NULL | o que o gestor fez |
| `autor_id` | bigint unsigned | revisor |
| `triado_por` | bigint unsigned NULL | |
| `triado_em` | datetime NULL | |
| `created_at` / `updated_at` / `deleted_at` | datetime NULL | |

Índices: `(curso_evento_id, status)`, `(alvo_tipo, alvo_id)`, `(autor_id)`, `(deleted_at)`.

`alvo_tipo` e `alvo_id` formam alvo polimórfico sem chave estrangeira — o mesmo padrão de `emails_envios` (`entidade_tipo` / `entidade_id`). Sem FK porque os alvos moram em quatro tabelas diferentes; a integridade é validada no Service.

## Decisões de arquitetura

- **VARCHAR em `severidade`, `status` e `alvo_tipo`**, não ENUM. É a regra que a migration 070 já adotou onde há evolução prevista
- **Sem chave estrangeira no alvo polimórfico**, com validação no Service, seguindo `emails_envios`
- **O perfil Revisor não recebe nenhuma permissão terminada em `gerenciar`** exceto `area_curso.revisor.comentar`, que só grava em `revisao_comentarios`
- **A aula é exibida no mesmo `HtmlEmbedRenderer` do aluno**, com o iframe `sandbox` sem `allow-same-origin`. O revisor vê exatamente o que o aluno verá, e o isolamento continua o mesmo
- **O escopo é por curso, não por turma.** O revisor revisa o conteúdo, que é do curso; turma não altera conteúdo
- **A triagem é exclusiva de quem tem `area_curso.gerenciar`.** O revisor nunca muda o status do próprio apontamento
- **Nenhuma rota do revisor recebe o middleware `permission:area_curso.gerenciar`**, e nenhum controller de revisor injeta `ConteudoCursoService`

## Validação

- `php -l` em todos os arquivos criados ou alterados
- Migration aplicada e conferida com `SHOW COLUMNS` em `curso_pessoas_vinculadas` (enum com `revisor`) e `SHOW TABLES LIKE 'revisao_comentarios'`
- `php tests/Unit/revisor_academic_scope.php` — revisor vinculado enxerga o curso; não vinculado recebe negativa
- `php tests/Unit/revisor_permissoes.php` — o conjunto de permissões do perfil é exatamente o esperado, e o teste falha se crescer
- Percurso manual: criar usuário revisor, vincular ao curso 125, acessar `/revisor`, abrir uma aula, abrir o banco do quiz 31, comentar uma questão, triar no admin e conferir o desfecho na tela do revisor
- Tentativa deliberada de acesso a `/admin/area-curso` e `/professor/area-curso` com o usuário revisor, esperando 403
- Textos de interface e mensagens em português brasileiro com acentuação, conforme `docs/padrao-editorial-ptbr.md`
- Compatibilidade MySQL 5.7 na migration
