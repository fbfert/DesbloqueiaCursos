# LMS — Aba “Conteúdo” (Admin e Professor) — 2026-05-19

Este documento descreve a implementação da nova aba **“Conteúdo”** na área interna do curso para **Admin** e **Professor**, criada para validar em paralelo a nova base de Conteúdo Unificado (módulos + itens) sem remover as abas antigas.

## Objetivo

- Adicionar uma nova aba **Conteúdo** em:
  - Admin: `/admin/area-curso?curso_id=ID&turma_id=ID&aba=conteudo`
  - Professor: `/professor/area-curso?curso_id=ID&turma_id=ID&aba=conteudo`
- Manter as abas antigas **Módulos e aulas / Materiais / Atividades** funcionando e visíveis.
- Não alterar a área do aluno, certificados e relatórios nesta etapa.

## O que a aba permite (Admin e Professor)

- Listar módulos do curso (curso_evento_id).
- Criar/editar/arquivar/duplicar módulo.
- Reordenar módulos (botões “Subir/Descer”).
- Listar itens dentro de cada módulo.
- Criar/editar/arquivar/duplicar item.
- Mover item para outro módulo.
- Reordenar itens (botões “Subir/Descer”).

Tipos de item suportados:
- `etiqueta`
- `texto`
- `arquivo`
- `link`
- `avaliacao_textual`
- `video`

Status suportados:
- `rascunho`
- `publicado`
- `oculto`
- `arquivado`

## Rotas (POST)

### Admin

- `POST /admin/area-curso/conteudo/modulo/salvar`
- `POST /admin/area-curso/conteudo/modulo/arquivar`
- `POST /admin/area-curso/conteudo/modulo/duplicar`
- `POST /admin/area-curso/conteudo/modulos/ordenar`
- `POST /admin/area-curso/conteudo/item/salvar`
- `POST /admin/area-curso/conteudo/item/arquivar`
- `POST /admin/area-curso/conteudo/item/duplicar`
- `POST /admin/area-curso/conteudo/item/mover`
- `POST /admin/area-curso/conteudo/itens/ordenar`

### Professor

- `POST /professor/area-curso/conteudo/modulo/salvar`
- `POST /professor/area-curso/conteudo/modulo/arquivar`
- `POST /professor/area-curso/conteudo/modulo/duplicar`
- `POST /professor/area-curso/conteudo/modulos/ordenar`
- `POST /professor/area-curso/conteudo/item/salvar`
- `POST /professor/area-curso/conteudo/item/arquivar`
- `POST /professor/area-curso/conteudo/item/duplicar`
- `POST /professor/area-curso/conteudo/item/mover`
- `POST /professor/area-curso/conteudo/itens/ordenar`

## Segurança / Permissões

- Todas as rotas POST são protegidas pelo middleware de CSRF já existente (`csrfField` nos formulários).
- Professor:
  - Toda escrita é bloqueada se o contexto não for autorizado pelo fluxo existente: `AreaCursoService->contextoProfessorAutorizado(...)` (via `Professor\\AreaCursoController::contextoAutorizado()`).

## WYSIWYG e sanitização

- Campos ricos usam o padrão do projeto (Quill):
  - `<textarea class="js-wysiwyg" data-wysiwyg="basic|full">`
- HTML é sanitizado no backend (para evitar scripts/iframes etc.).
  - Implementado em: `app/Support/HtmlSanitizer.php`
  - Usado em: `app/Services/ConteudoCursoService.php` ao salvar etiqueta/texto/avaliação textual.

## Upload de arquivo (fase atual)

- O item do tipo `arquivo` já aceita upload no formulário.
- O upload é salvo fora da `public_html` em `storage/private_uploads` usando `FileStorageService`.
- Ao enviar arquivo, o sistema grava:
  - metadados em `conteudo_arquivos`
  - uma nova versão em `conteudo_arquivos_versoes`
  - atualiza `versao_atual_id`

Limites / validação:
- Tamanho máximo: **10 MB**
- Extensões permitidas: `pdf`, `jpg`, `jpeg`, `png`, `webp`, `doc`, `docx`, `odt`, `xls`, `xlsx`, `ods`, `ppt`, `pptx`, `odp`, `txt`, `csv`.

Observação:
- Ainda não existe tela de “download/preview” específica do Conteúdo; isso fica para a próxima etapa.

## Principais arquivos

Controllers:
- `app/Controllers/Admin/AreaCursoController.php`
- `app/Controllers/Professor/AreaCursoController.php`

Service:
- `app/Services/ConteudoCursoService.php`

Views:
- `resources/views/admin/area-curso/_conteudo.php`
- `resources/views/professor/area-curso/_conteudo.php`
- `resources/views/admin/area-curso/index.php`
- `resources/views/professor/area-curso/index.php`

Rotas:
- `routes/web.php`

## Checklist de validação manual

- Admin:
  - Abrir `/admin/area-curso?curso_id=ID&turma_id=ID&aba=conteudo`
  - Criar/editar/duplicar/arquivar módulo
  - Criar/editar itens de todos os tipos
  - Reordenar e mover itens
- Professor:
  - Abrir `/professor/area-curso?curso_id=ID&turma_id=ID&aba=conteudo`
  - Confirmar que professor sem vínculo não consegue salvar
- Geral:
  - Abas antigas continuam funcionando
  - Área do aluno (`/area-curso`) não foi alterada

