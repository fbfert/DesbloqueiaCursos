# Área interna do curso — refatoração de workspace

Data: 2026-05-16

## Objetivo

Transformar `/admin/area-curso` em um ambiente interno de trabalho por curso, com seleção inicial separada da área operacional do curso selecionado.

## Mudanças já entregues

### Seleção inicial
- A página sem `curso_id` passou a exibir somente a lista de cursos para escolha.
- A lista foi separada por estado:
  - cursos ativos
  - cursos em rascunho
  - cursos inativos
- A seção de rascunhos ficou visível por padrão, sem accordion fechado.

### Workspace do curso selecionado
- Com `curso_id`, a lista de cursos deixa de aparecer.
- O topo passou a exibir um cabeçalho compacto com:
  - nome do curso
  - ID
  - status
  - tipo
  - modalidade
  - categoria
  - professor responsável
- O botão `Trocar curso` retorna para a tela inicial de seleção.
- A navegação interna deixou de usar âncoras como mecanismo principal e passou a usar o parâmetro `aba`.
- A aba padrão passou a ser `visao-geral`.
- Foi incluído suporte visual para menu de abas no desktop e mobile.

### Abas e conteúdo
- A área foi separada em painéis de aba com exibição exclusiva da aba ativa.
- As seções principais passaram a respeitar `aba` na URL.
- O fallback para URLs antigas com hash foi mantido apenas como compatibilidade.
- A nomenclatura `Visão geral` foi corrigida.
- Os blocos legados visíveis foram removidos da interface principal.

### Dados resumidos
- A visão geral passou a exibir contadores de:
  - turmas
  - módulos
  - aulas
  - materiais
  - atividades
  - alunos inscritos
  - certificados

## Arquivos já alterados

- `app/Controllers/Admin/AreaCursoController.php`
- `app/Services/AreaCursoService.php`
- `resources/views/admin/area-curso/index.php`
- `resources/views/admin/area-curso/_materiais.php`
- `resources/views/admin/area-curso/_atividades.php`
- `resources/views/admin/area-curso/_relatorios.php`
- `public_html/assets/css/admin.css`

## Estado atual

O workspace administrativo do curso já opera como área interna por abas, com separação clara entre seleção de curso e edição operacional.

## Diagnóstico posterior

Foi identificado um vazamento de renderização em algumas seções da área do curso:

- blocos de `módulos`, `aulas`, `links externos` e `participantes` estavam sendo impressos fora do painel da aba `modulos-aulas`;
- o mesmo padrão ocorria na área de `atividades`, com listas e detalhes sendo renderizados fora do painel da aba `atividades`.

### Causa

O problema estava na `view` principal (`resources/views/admin/area-curso/index.php`) e em `resources/views/admin/area-curso/_atividades.php`, onde alguns blocos foram deixados sem a classe `area-curso-tab-panel` depois da refatoração para abas.

### Correção

Os blocos soltos foram recolocados sob a lógica de aba:

- `modulos-aulas` passou a englobar os blocos de instruções, módulos, aulas, links externos e participantes;
- `atividades` passou a englobar também a lista de atividades e o detalhe de entregas.

Com isso, a aba `Visão geral` volta a exibir apenas o resumo.

### Padronização final

Os identificadores internos foram uniformizados para reduzir ambiguidade:

- `area-curso-modulos-aulas-instrucoes`
- `area-curso-modulos-aulas-modulos`
- `area-curso-modulos-aulas-aulas`
- `area-curso-modulos-aulas-links`
- `area-curso-modulos-aulas-participantes`
- `area-curso-atividades-lista`
- `area-curso-atividades-formulario`
- `area-curso-atividades-entregas`
- `area-curso-atividades-detalhe`
- `area-curso-relatorios-aptos-certificado`
