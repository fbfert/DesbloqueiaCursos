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

## Etapa seguinte — aba Turmas

### Implementação

- A aba `Turmas` deixou de ser placeholder e passou a renderizar a gestão de turmas do curso selecionado.
- O conteúdo agora aparece no painel exclusivo da aba `turmas`, sem misturar visão geral ou outras seções.
- Foi adicionada uma seção própria com:
  - cabeçalho com tooltip explicativo
  - resumo operacional das turmas
  - tabela de turmas do curso
  - estado vazio com ação de criação da primeira turma

### Integração com o CRUD existente

- O fluxo reaproveita o CRUD administrativo de turmas já existente.
- A criação e a edição agora preservam o retorno para `/admin/area-curso?curso_id={ID}&aba=turmas`.
- As ações de `ver`, `editar`, `atualizar status` e `enviar para lixeira` retornam para a aba `Turmas` quando acionadas a partir do workspace.

### Dados usados

- O resumo da aba `Turmas` considera:
  - total de turmas
  - turmas abertas
  - turmas planejadas
  - turmas encerradas
  - vagas totais
  - inscritos ativos por turma
- Os inscritos são contados a partir das inscrições ativas vinculadas às turmas do curso.

### Arquivos desta etapa

- `app/Controllers/Admin/TurmasController.php`
- `app/Services/TurmaService.php`
- `app/Services/AreaCursoService.php`
- `app/Models/Inscricao.php`
- `resources/views/admin/area-curso/_turmas.php`
- `resources/views/admin/area-curso/index.php`
- `resources/views/admin/turmas/form.php`
- `resources/views/admin/turmas/show.php`
- `public_html/assets/css/admin.css`

### Estabilização da coluna `Ações`

- A coluna `Ações` da tabela da aba `Turmas` foi simplificada para evitar qualquer navegação acidental para telas completas dentro do workspace.
- O link de resumo foi removido da grade interna da aba.
- A ação inline de status/lixeira também foi retirada temporariamente da aba para manter a interface estável.
- A coluna passou a exibir apenas `Editar` quando o usuário tem permissão, ou `Sem ações disponíveis` quando não há permissão.

### Verificação adicional

- Foi conferido o arquivo publicado por FTP para garantir que a versão em produção contém a redução da coluna `Ações`.
- Não restou referência a `/admin/turmas/show` dentro da partial da aba `Turmas`.

## Etapa seguinte — aba `Módulos e aulas`

### Refatoração estrutural

- A aba `Módulos e aulas` foi extraída da `view` principal e passou a morar em uma partial própria:
  - `resources/views/admin/area-curso/_modulos_aulas.php`
- A `view` principal (`resources/views/admin/area-curso/index.php`) agora apenas inclui a partial, sem manter o bloco longo inline.
- A aba passou a ser organizada em blocos mais claros:
  - cabeçalho com tooltip
  - cards-resumo
  - ações rápidas
  - instruções pós-checkout
  - módulos
  - aulas
  - links externos

### Ajustes funcionais

- Os formulários de instruções e links passaram a respeitar o campo `aba` ao salvar, preservando o retorno para a aba correta.
- Os links internos da aba agora preservam `curso_id`, `turma_id` e a aba ativa.
- A aba deixou de misturar participantes dentro do conteúdo de `Módulos e aulas`.
- O formulário de aula passou a reutilizar seleção contextual de módulo e aula, quando disponível no `AreaCursoController`.

### Organização visual

- Foram adicionados estilos próprios para:
  - cards de módulo
  - itens de aula
  - detalhes recolhíveis dos formulários
  - blocos de ações rápidas
  - listas e estados vazios
- A estrutura principal deixou de depender da tabela linear longa do bloco anterior.

### Arquivos desta etapa

- `resources/views/admin/area-curso/_modulos_aulas.php`
- `resources/views/admin/area-curso/index.php`
- `app/Controllers/Admin/AreaCursoController.php`
- `public_html/assets/css/admin.css`

## Etapa seguinte — aba `Participantes`

### Implementação

- A aba `Participantes` deixou de ser placeholder e passou a renderizar uma listagem administrativa dos alunos vinculados ao curso e à turma selecionados.
- O conteúdo foi isolado em uma partial própria:
  - `resources/views/admin/area-curso/_participantes.php`
- A aba agora exibe:
  - cabeçalho com tooltip
  - resumo com contagens acadêmicas
  - filtros por termo e status
  - tabela organizada com links de apoio

### Integração com os dados existentes

- A listagem continua usando o modelo `ParticipantePedido`.
- O serviço `AreaCursoService::listarParticipantes()` passou a aceitar filtros opcionais.
- A consulta agora inclui dados úteis para o acompanhamento administrativo:
  - pedido
  - status da inscrição
  - curso
  - turma
  - dados do participante e do usuário vinculado

### Arquivos desta etapa

- `resources/views/admin/area-curso/index.php`
- `resources/views/admin/area-curso/_participantes.php`
- `app/Controllers/Admin/AreaCursoController.php`
- `app/Services/AreaCursoService.php`
- `app/Models/ParticipantePedido.php`
- `public_html/assets/css/admin.css`

## Etapa seguinte — aba `Presença`

### Implementação

- A aba `Presença` deixou de ser placeholder e passou a operar dentro do workspace do curso.
- Foi criada a partial própria:
  - `resources/views/admin/area-curso/_presenca.php`
- A aba agora oferece:
  - cabeçalho com tooltip
  - resumo por status
  - filtros por busca, data, aula e status
  - registro em lote por participantes selecionados
  - histórico de presença
  - exclusão individual e em lote com justificativa
  - exportação CSV

### Reaproveitamento da infraestrutura existente

- A implementação reutiliza:
  - `app/Models/Presenca.php`
  - `app/Services/PresencaService.php`
  - recálculo de aptidão já existente no projeto
- A listagem de presença passou a ser filtrável sem alterar a estrutura das demais abas.

### Ajustes técnicos

- O `AreaCursoController` passou a trazer contexto de presença quando a aba ativa é `presenca`.
- O `PresencaService` ganhou suporte a:
  - listagem filtrada
  - exportação CSV
  - registro em lote
  - exclusão em lote
- O `Presenca` model passou a devolver dados enriquecidos de participante, curso, turma, aula e marcador.

### Arquivos desta etapa

- `resources/views/admin/area-curso/index.php`
- `resources/views/admin/area-curso/_presenca.php`
- `app/Controllers/Admin/AreaCursoController.php`
- `app/Services/AreaCursoService.php`
- `app/Services/PresencaService.php`
- `app/Models/Presenca.php`
- `routes/web.php`
- `public_html/assets/css/admin.css`

---

## Avaliações e notas

### Objetivo

- A aba `Avaliações e notas` foi implementada para consolidar o acompanhamento acadêmico do curso/turma no workspace administrativo.
- O foco é reunir, em um único painel, nota final, progresso, presença, certificados, avaliações cadastradas e atividades avaliativas relacionadas.

### O que foi entregue

- Foi criada a partial própria:
  - `resources/views/admin/area-curso/_avaliacoes_notas.php`
- A aba passou a exibir:
  - cabeçalho com tooltip explicativo
  - cards-resumo com indicadores acadêmicos
  - formulário para lançar ou atualizar nota
  - cards das avaliações cadastradas
  - tabela de participantes com situação acadêmica
  - tabela de atividades avaliativas e entregas
  - exportação CSV preservando o contexto e os filtros aplicados

### Reaproveitamento da infraestrutura existente

- A implementação reutiliza:
  - `app/Services/AvaliacaoService.php`
  - `app/Services/AreaCursoService.php`
  - `app/Services/AptidaoCertificadoService.php`
  - `app/Models/Inscricao.php`
- O recálculo de aptidão para certificado continua sendo feito ao registrar ou atualizar nota.

### Ajustes técnicos

- O `AreaCursoController` passou a reconhecer a aba `avaliacoes-notas`, carregar os filtros específicos e encaminhar o POST de lançamento de nota.
- O `AreaCursoService` passou a consolidar:
  - inscrições no contexto
  - avaliações cadastradas
  - atividades vinculadas
  - resumo acadêmico da aba
  - exportação CSV
- O workspace preserva o contexto da aba ao navegar, filtrar e salvar.

### Arquivos desta etapa

- `resources/views/admin/area-curso/index.php`
- `resources/views/admin/area-curso/_avaliacoes_notas.php`
- `app/Controllers/Admin/AreaCursoController.php`
- `app/Services/AreaCursoService.php`
- `app/Services/AvaliacaoService.php`
- `routes/web.php`
- `public_html/assets/css/admin.css`
