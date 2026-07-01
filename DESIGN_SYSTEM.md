# Design System

Projeto: Portal de Cursos / Desbloqueia Cursos

Este documento consolida os padrões visuais reais já usados no projeto para orientar novas telas, ajustes de layout e evoluções de interface sem quebrar consistência.

## Objetivo

- Manter o visual coerente entre a área pública, a área do aluno, a área do professor e o backoffice/admin.
- Evitar criação de estilos globais desnecessários.
- Reaproveitar os componentes, classes e espaçamentos já existentes no código.

## Princípios

1. **Consistência acima de novidade**
   - Use os padrões que já existem no projeto antes de criar qualquer variação.

2. **Escopo local**
   - Ajustes visuais devem ficar limitados à tela/área afetada.
   - Evite mexer em `.card`, `.btn`, `.table` ou equivalentes sem classe de contexto.

3. **Admin compacto, público legível**
   - No admin, privilegie densidade, clareza e ação rápida.
   - No frontend público, privilegie leitura, hierarquia e conversão.

4. **Acessibilidade e clareza**
   - Links e botões devem continuar distinguíveis.
   - Ícones precisam de `title`, `aria-label` e texto auxiliar quando necessário.
   - Estados destrutivos exigem confirmação explícita.

## Tokens de Cor do Admin

Os seguintes tokens já existem em [public_html/assets/css/admin.css](public_html/assets/css/admin.css):

- `--admin-bg: #f3f5f9`
- `--admin-panel: #ffffff`
- `--admin-panel-muted: #f8faff`
- `--admin-border: #d8deea`
- `--admin-text: #1f2937`
- `--admin-muted: #5b6576`
- `--admin-primary: #4c1d95`
- `--admin-primary-strong: #3b1475`
- `--admin-sidebar-start: #101827`
- `--admin-sidebar-end: #1f2c3f`
- `--admin-sidebar-text: #d9e3f1`
- `--admin-sidebar-muted: #99a9bf`

### Leitura dos tokens

- Fundo geral do admin: cinza muito claro.
- Painéis: branco com borda suave.
- Destaque primário: roxo escuro.
- Sidebar: gradiente escuro com texto claro.
- Texto secundário: cinza médio.

## Estrutura Visual do Admin

Referências principais:

- [public_html/assets/css/admin.css](public_html/assets/css/admin.css)
- [resources/views/admin/area-curso/_conteudo.php](resources/views/admin/area-curso/_conteudo.php)
- [resources/views/admin/pedidos/index.php](resources/views/admin/pedidos/index.php)

### Padrões de composição

- `app-admin`
  - wrapper da área administrativa.
- `admin-shell`
  - layout principal em duas colunas: sidebar e conteúdo.
- `admin-topbar`
  - barra superior sticky com ações e breadcrumbs.
- `admin-page`
  - container de cada tela administrativa.
- `admin-page__header`
  - cabeçalho da página com título, descrição e ações.
- `status-card`
  - cartão base para blocos do admin.
- `panel`
  - bloco auxiliar com borda e espaçamento compacto.
- `admin-table`
  - tabela base do backoffice.

### Bordas e profundidade

- Radius padrão de navegação: `10px`
- Radius de headers/painéis: `14px`
- Shadow de cards principais: suave, por exemplo `0 12px 28px rgba(15, 23, 42, 0.05)`
- Shadow de CTA/ações especiais: sutil, sem volume excessivo

## Componentes do Admin

### Botões

Classes já usadas:

- `button-link`
- `button-link--primary`
- `button-link--ghost`
- `button-link--danger`
- `admin-icon-btn`

Uso esperado:

- `button-link--primary`
  - ação principal da tela.
- `button-link--ghost`
  - ações secundárias.
- `button-link--danger`
  - ações destrutivas.
- `admin-icon-btn`
  - botões compactos com ícone, usados principalmente em tabelas e barras de ação.

### Badges

Classes e estados já usados:

- `badge`
- `badge--success`
- `badge--warn`
- `badge--soft`

Uso esperado:

- `badge--success`
  - status positivo, por exemplo `Publicado` ou `Ativo`.
- `badge--warn`
  - rascunho, atenção ou condição intermediária.
- `badge--soft`
  - contadores e metadados discretos.

### Tabelas

Padrões observados:

- tabela com `table-wrap`
- colunas compactas para ações
- uso de `white-space: nowrap` em ações quando possível
- títulos clicáveis em células específicas

Regras:

- Evitar linhas muito altas sem necessidade.
- Reduzir quebras em colunas de ação.
- Priorizar legibilidade no desktop e quebra controlada no mobile.

## Estrutura Visual do Frontend Público

Referências principais:

- [resources/views/home.php](resources/views/home.php)
- [resources/views/cursos/index.php](resources/views/cursos/index.php)
- [resources/views/cursos/show.php](resources/views/cursos/show.php)

### Classes recorrentes

- `front-section`
- `front-section-stack`
- `front-card`
- `front-card-grid`
- `front-card-list`
- `status-card`
- `hero`
- `button-link`
- `cta-group`
- `pill`
- `pill-row`

### Direção visual

- Visual mais aberto e de leitura confortável.
- Cards com identidade de vitrine e CTA claro.
- Uso de `pill` para metadados curtos como tipo, modalidade e quantidade de turmas abertas.
- Destaques da home devem equilibrar imagem, título e CTA sem poluição visual.

## Capa/Home

Referência:

- [resources/views/home.php](resources/views/home.php)

### Padrões atuais

- Hero principal com bloco de conteúdo e eventual imagem do módulo.
- Seção de destaques com cards de curso.
- Seção de ranking/top cursos.
- Seção de depoimentos em carrossel/slider.
- Estado vazio amigável quando não há conteúdo configurado.

### Regras de conteúdo

- A capa deve mostrar apenas cursos públicos elegíveis.
- Destaques da home seguem a configuração de limite em `home_destaques_limite`.
- O espaçamento entre cards e seções é controlado por configuração frontend quando disponível.

## Catálogo Público

Referência:

- [resources/views/cursos/index.php](resources/views/cursos/index.php)

### Padrões atuais

- Grid de cards de curso.
- CTA de detalhes e CTA de inscrição.
- Meta curta em linha, com contador de turmas abertas e preço/desconto quando aplicável.

### Diretrizes

- Manter o catálogo legível e escaneável.
- Não expor dados administrativos.
- Evitar excesso de texto em cards.

## Área do Curso / Conteúdo

Referência:

- [resources/views/admin/area-curso/_conteudo.php](resources/views/admin/area-curso/_conteudo.php)

### Direção visual

- Tela compacta e operacional.
- Tabelas densas com colunas de ação em linha.
- Separação clara entre módulos ativos e arquivados.
- Separação clara entre conteúdos ativos e arquivados.

### Padrões de ação

- Botões de ação devem virar ícones sempre que possível.
- Ações destrutivas devem pedir confirmação.
- O contexto de navegação deve manter `curso_id`, `turma_id`, `aba=conteudo` e, quando aplicável, `modulo_id`.

## Pedidos do Admin

Referência:

- [resources/views/admin/pedidos/index.php](resources/views/admin/pedidos/index.php)

### Direção visual

- Cards mais densos no desktop.
- Menos padding vertical.
- Uso mais forte de alinhamento horizontal.
- Filtros, tabela e ações devem permanecer acessíveis.

## Configurações de Frontend

Referências:

- [app/Services/ConfiguracaoGlobalService.php](app/Services/ConfiguracaoGlobalService.php)
- [app/Models/ConfiguracaoFrontend.php](app/Models/ConfiguracaoFrontend.php)

### Tokens configuráveis

- `home_destaques_limite`
  - quantidade de cards da capa.
- `frontend_card_gap`
  - espaço entre cards.
- `frontend_section_gap`
  - espaço entre seções.

### Padrão

- O frontend pode receber ajustes de espaçamento via configuração.
- Sempre validar se o valor está sanitizado antes de aplicar no CSS.

## Espaçamento e Ritmo

- O admin deve usar blocos mais compactos.
- O frontend público pode usar mais respiro, desde que a leitura continue clara.
- Evite espaçamento excessivo entre blocos internos de tabelas e cards.

## Tipografia

O projeto não padroniza uma família de fonte customizada neste documento; o comportamento visual atual depende do CSS já existente e de herança do sistema.

Diretrizes:

- títulos curtos e claros;
- subtítulos sempre mais sutis;
- textos longos com largura confortável;
- em tabelas, reduzir o uso de quebras desnecessárias.

## Estado vazio, aviso e erro

Padrões visuais existentes:

- `status-card`
- `notice`
- `alert-danger`

Diretrizes:

- Estados vazios devem orientar o próximo passo.
- Erros devem ser claros e localizados.
- Avisos não devem competir com a ação principal.

## Regras para novas telas

1. Escolha primeiro o padrão de página existente mais próximo.
2. Reaproveite a mesma linguagem visual.
3. Limite o CSS ao contexto da tela.
4. Não mude o visual global se o problema for local.
5. Valide a experiência em desktop e mobile.

## Arquivos de referência

- [AGENTS.md](AGENTS.md)
- [public_html/assets/css/admin.css](public_html/assets/css/admin.css)
- [resources/views/home.php](resources/views/home.php)
- [resources/views/cursos/index.php](resources/views/cursos/index.php)
- [resources/views/cursos/show.php](resources/views/cursos/show.php)
- [resources/views/admin/area-curso/_conteudo.php](resources/views/admin/area-curso/_conteudo.php)
- [resources/views/admin/pedidos/index.php](resources/views/admin/pedidos/index.php)

