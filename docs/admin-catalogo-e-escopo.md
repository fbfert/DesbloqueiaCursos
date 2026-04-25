# Admin Catalogo e Escopo do Professor

Registro tecnico da evolucao recente do portal no backend administrativo e no endurecimento do escopo do professor.

## 1. Escopo academico do professor

Foi aplicado endurecimento de escopo para impedir acesso, edicao, exclusao ou recalculo por troca manual de IDs no request.

### Cobertura aplicada

- area do professor interna
- academico do professor
- inscricoes, avaliacoes, perguntas, presencas e aulas ligadas ao contexto

### Controles implementados

- validacao de pertencimento entre curso, turma e registro academico
- consistencia entre curso, turma, modulo, aula, material, avaliacao e pergunta
- retorno `403` quando o professor tenta operar fora do escopo autorizado
- falha explicita para registros inexistentes
- teste automatizado simples em `tests/Unit/professor_academic_scope.php`

### Arquivos principais

- `app/Controllers/Professor/AreaCursoController.php`
- `app/Controllers/Professor/AcademicoController.php`
- `app/Services/AreaCursoService.php`
- `app/Services/ProfessorAcademicScopeService.php`
- `app/Services/AptidaoCertificadoService.php`
- `app/Services/PresencaService.php`
- `app/Services/AvaliacaoService.php`
- `app/Services/ModuloService.php`
- `app/Services/AulaService.php`
- `app/Services/MaterialService.php`

## 2. Backend administrativo

O painel admin foi organizado com menu filtrado por permissao e com a base de catalogo preparada para alimentar portal publico, professor e aluno.

### Menu principal

O shell administrativo passa a exibir somente grupos e itens compatíveis com as permissoes do usuario autenticado.

Grupos atuais:

- Painel
- Conteudo
- Operacao
- Academico
- Financeiro
- Configuracoes

### CRUD de cursos

Cobertura atual:

- listar
- criar
- editar
- visualizar
- ativar/inativar por `POST`
- excluir com bloqueio quando houver turmas vinculadas
- definir professor responsavel do curso
- sincronizar professor responsavel em `curso_pessoas_vinculadas`
- sincronizar professor responsavel em `usuario_cursos`

### CRUD de turmas

Cobertura atual:

- listar
- criar
- editar
- visualizar
- abrir/encerrar por `POST`
- excluir com bloqueio quando houver inscricoes vinculadas
- vincular turma a curso existente
- definir professor responsavel da turma
- sincronizar professor responsavel em `usuario_turmas`

## 3. Permissoes usadas no admin

- `conteudo.ver`
- `conteudo.gerenciar`
- `area_curso.gerenciar`
- `pedidos.ver`
- `cupons.ver`
- `certificados.ver`
- `academico.ver`
- `financeiro.ver`
- `configuracoes_globais.ver`
- `emails.ver`
- `rbac.dashboard.ver`

## 4. Rotas adicionadas

- `POST /admin/cursos/status`
- `POST /admin/turmas/status`

## 5. Validacoes relevantes

- categoria do curso deve existir
- professor responsavel deve existir e ter perfil de professor
- turma deve apontar para curso existente
- data final da turma nao pode ser menor que a data inicial
- update de curso ou turma inexistente falha explicitamente
- exclusao de curso com turma vinculada e bloqueada
- exclusao de turma com inscricoes vinculadas e bloqueada
- acoes sensiveis exigem `POST`
- CSRF continua centralizado em middleware e injecao de token nas views

## 6. Validacao executada

Foram executados `php -l` nos arquivos alterados de controllers, services, models, views e rotas.

Tambem foi executado:

```bash
php tests/Unit/professor_academic_scope.php
```

Resultado esperado:

- sem erros de sintaxe
- teste do escopo academico do professor concluido com sucesso
