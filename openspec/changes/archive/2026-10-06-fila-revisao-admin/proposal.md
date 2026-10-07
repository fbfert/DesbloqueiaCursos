# Proposal

## Why

O Perfil Revisor (spec antiga `specs/0002-perfil-revisor`, fases 1 a 3 em produção desde 21/08/2026) já permite que especialistas externos registrem apontamentos nos cursos, mas **ninguém no admin consegue vê-los nem respondê-los**. A regra de triagem existe (`RevisaoComentarioService::triar()`), sem tela que a chame. Sem fila, os apontamentos se acumulam sem resposta, e a pendência "revisão por professor da área antes de divulgar" dos cursos 118 a 125 continua sem caminho para ser fechada — o risco de "fila abandonada" já registrado na spec original.

## What Changes

- Nova tela `/admin/revisoes`: fila de apontamentos de revisão de **todos** os cursos, com filtros por curso, severidade e situação, ordenada por situação (abertos primeiro) e gravidade (erro no topo).
- Cada apontamento mostra curso, alvo (item de conteúdo, módulo, pergunta ou alternativa de quiz) com descrição legível, trecho citado, comentário, autor, datas e, se já triado, quem triou, quando e a resposta.
- Ações de triagem pelo gestor: **aceitar**, **recusar** (resposta obrigatória) e **marcar como resolvido**, reaproveitando a regra e a auditoria já existentes em `triar()`.
- Contador de apontamentos `erro` em aberto na tela do curso no admin (`/admin/cursos/editar` e `/admin/cursos/show`), com link para a fila filtrada por aquele curso.
- Item "Revisões" no menu lateral do admin, visível só para quem tem `conteudo.gerenciar`.

**Fora de escopo:**
- Exportação da fila para planilha ou PDF (já excluída na spec original).
- Notificação por e-mail ao revisor quando o apontamento é triado.
- Reabrir apontamento já triado ou alterar a triagem depois de feita.
- Edição do conteúdo a partir da fila — a correção continua sendo feita nas telas de conteúdo existentes.
- Escrever retroativamente os requisitos das fases 1 a 3 do revisor (acesso, escopo, comentários); ficam para quando aquela parte for tocada.

## Capabilities

### New Capabilities
- `revisao-conteudo`: revisão de conteúdo de cursos por especialistas externos e triagem desses apontamentos pelo gestor de conteúdo. Esta mudança introduz os requisitos da triagem no admin.

### Modified Capabilities
(nenhuma — não há specs em `openspec/specs/` ainda)

## Impact

- **Código novo:** `app/Controllers/Admin/RevisoesController.php`, `resources/views/admin/revisoes/index.php`.
- **Código alterado:** `app/Models/RevisaoComentario.php` (listagem multi-curso), `app/Services/RevisaoComentarioService.php` (fila e descrição do alvo), `routes/web.php`, `resources/views/admin/_shell.php` (menu), `app/Controllers/Admin/CursosController.php` e views de `admin/cursos` (contador), `tests/Smoke` (rota nova).
- **Banco:** nenhuma migration nova. A tabela `revisao_comentarios` (migration `072_perfil_revisor_comentarios.sql`) já tem todas as colunas de triagem e o índice `idx_revisao_curso_status`.
- **Permissões:** usa `conteudo.gerenciar`, já existente. Nenhuma permissão nova; o perfil Revisor continua sem acesso a `/admin/*`. **Diverge da spec antiga**, que previa `area_curso.gerenciar`: pelas migrations essa permissão só pertence ao superadmin, enquanto o perfil `conteudo` (o gestor de conteúdo da spec) tem `conteudo.gerenciar` — a mesma exigida por `/admin/cursos/editar`, onde o contador aparece. Decisão do responsável pelo produto em 05/10/2026.
- **Deploy/rollback:** só arquivos PHP, sem migration — deploy por FTP comum; rollback é reenviar as versões anteriores dos arquivos alterados e remover os novos.
