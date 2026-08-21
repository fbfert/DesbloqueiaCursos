# Tarefas: Perfil Revisor com comentários de revisão

## Fase 1: Base de dados e RBAC

- [x] Criar `sql/072_perfil_revisor_comentarios.sql`
- [x] Acrescentar `revisor` ao enum `tipo_pessoa` de `curso_pessoas_vinculadas`
- [x] Criar a tabela `revisao_comentarios` com os índices previstos no plano
- [x] Inserir o perfil `revisor` em `perfis` (`sistema = 1`)
- [x] Inserir as permissões `area_curso.revisor.ver` e `area_curso.revisor.comentar` em `permissoes`
- [x] Vincular as duas permissões ao perfil em `perfil_permissoes`, e nenhuma outra
- [x] Aplicar a migration e conferir com `SHOW COLUMNS` e `SHOW TABLES`

## Fase 2: Escopo e regras

- [x] Criar `app/Models/RevisaoComentario.php`
- [x] Criar `app/Services/RevisorAcademicScopeService.php`, filtrando `tipo_pessoa = 'revisor'` e `status = 'ativo'`
- [x] Criar `app/Services/RevisaoComentarioService.php` com criação, edição enquanto `aberto`, triagem e exclusão via `TrashService`
- [x] Validar no Service que `alvo_tipo` e `alvo_id` existem e pertencem ao curso do vínculo
- [x] Registrar criação e triagem em `AuditService`
- [x] Criar `tests/Unit/revisor_academic_scope.php`
- [x] Criar `tests/Unit/revisor_permissoes.php`

## Fase 3: Área do revisor

- [x] Criar `app/Controllers/Revisor/DashboardController.php` — cursos com vínculo ativo
- [x] Criar `app/Controllers/Revisor/RevisaoController.php` — árvore, aula, questões, comentário
- [x] Exibir a aula pelo `HtmlEmbedRenderer`, no mesmo iframe sandbox do aluno
- [x] Exibir o banco de questões com enunciado, quatro alternativas, gabarito e explicação
- [x] Criar as views do revisor, incluindo o painel de comentários
- [x] Registrar as rotas `/revisor/*` em `routes/web.php` com as permissões corretas
- [x] Conferir que nenhum controller de revisor injeta Service de gravação de conteúdo

### Acrescentado durante a Fase 3, fora do plano original

- [x] `app/Services/RevisorLeituraService.php` — consultas somente-leitura, para
      os controllers ficarem finos sem injetar `ConteudoCursoService`
- [x] `resources/views/revisor/_shell.php` — shell próprio. O do admin não serve:
      seu item "Dashboard" não exige permissão, então o revisor veria um link
      para `/admin/dashboard` que receberia 403
- [x] `resources/views/layout.php` — escopo `$isRevisor`, carregando `admin.css`
      e o par `conteudo-html-embed.css/js`, que é o que dá altura real ao iframe

## Fase 4: Fila no admin

- [ ] Criar `app/Controllers/Admin/RevisoesController.php`
- [ ] Criar `resources/views/admin/revisoes/index.php` com filtro por curso, severidade e status
- [ ] Implementar aceitar, recusar e resolver, com resposta obrigatória ao recusar
- [ ] Exibir na tela do curso no admin o contador de comentários `erro` em aberto
- [ ] Registrar as rotas `/admin/revisoes/*` com `permission:area_curso.gerenciar`

## Fase 5: Validação

- [ ] Rodar `php -l` em todos os arquivos criados ou alterados
- [ ] Rodar os dois testes unitários novos
- [ ] Rodar `php tests/Unit/professor_academic_scope.php` para confirmar que nada do professor regrediu
- [ ] Percurso manual completo: vincular revisor ao curso 125, comentar uma questão, triar no admin, conferir o desfecho
- [ ] Tentar `/admin/area-curso` e `/professor/area-curso` com o usuário revisor, esperando 403
- [ ] Revisar textos de interface e mensagens em português brasileiro com acentuação
- [ ] Registrar a entrega em `docs/` no padrão dos demais documentos do projeto
