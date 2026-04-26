# Refatoração do backend administrativo (desktop-first)

## Contexto

Esta entrega separa e consolida o visual do backend administrativo em padrão **desktop-first**, priorizando produtividade em telas largas (com foco em **1920x1080**), sem alterar regras de negócio, permissões, rotas ou banco de dados.

O frontend público permanece com foco mobile-first.

## Objetivos atendidos

- Separação de estilos do admin em arquivo dedicado.
- Layout base administrativo reutilizável com sidebar e topbar.
- Padronização visual de listagens, filtros, formulários, cards e ações.
- Ajuste fino de densidade e largura de tabelas por módulo.
- Revisão de microtipografia em PT-BR (com acentuação).

## Principais commits desta frente

- `9cadb6d` — Refatora layout administrativo desktop-first com CSS isolado.
- `f942d6a` — Expande padrão desktop-first para módulos admin restantes.
- `54fd62f` — Padroniza forms e detalhes admin em desktop-first.
- `f8da313` — QA final admin: consistência visual e PT-BR.
- `2920341` — Otimiza densidade e largura do admin para Full HD.
- `231dc92` — Ajusta largura de colunas por módulo no admin.
- `4259e81` — Refina colunas e tabelas em cupons, certificados e área do curso.
- `4fbe23e` — Refina densidade de colunas em RBAC e Catálogo.
- `889049e` — Refina microtipografia final no admin.

## Arquivos-chave impactados

- `public_html/assets/css/admin.css`
- `resources/views/layout.php`
- `resources/views/admin/_shell.php`
- Views administrativas em:
  - `resources/views/admin/dashboard/`
  - `resources/views/admin/catalogo/`
  - `resources/views/admin/categorias/`
  - `resources/views/admin/cursos/`
  - `resources/views/admin/turmas/`
  - `resources/views/admin/area-curso/`
  - `resources/views/admin/pedidos/`
  - `resources/views/admin/inscricoes/`
  - `resources/views/admin/comprovantes_pix/`
  - `resources/views/admin/cupons/`
  - `resources/views/admin/certificados/`
  - `resources/views/admin/academico/`
  - `resources/views/admin/financeiro/`
  - `resources/views/admin/professores-fiscais/`
  - `resources/views/admin/rateios/`
  - `resources/views/admin/configuracoes-globais/`
  - `resources/views/admin/emails/`
  - `resources/views/admin/rbac/`

## Validações executadas

- Verificação de sintaxe (`php -l`) nas views alteradas em cada etapa.
- Varredura de rotas `/admin` para identificar erros `5xx` sem sessão:
  - Resultado: **nenhuma rota testada retornou 5xx** (respostas `302` esperadas para login).

## Deploy

Publicação realizada por FTP em `ftp.polorainbow.com.br`, diretório `public_html/`, em múltiplas etapas, sempre com validação após upload.

## Observações

- Não houve necessidade de alteração de banco de dados.
- Não houve remoção de funcionalidades existentes.
- Regras de negócio, serviços, permissões e autenticação foram preservados.
