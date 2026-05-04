# Menu de topo dinâmico + separação de cursos ativos/inativos

## Data
- 2026-05-04

## Escopo desta entrega
- Tornar o menu superior público administrável pelo backend já existente de menus.
- Suportar dois menus de topo:
  - `menu_topo_publico` (`topo_publico`)
  - `menu_topo_logado` (`topo_logado`)
- Renderizar automaticamente o menu conforme autenticação.
- Manter botão de destaque no topo:
  - visitante: `Entrar`
  - logado: `Minha Página`
- Implementar menu mobile com hambúrguer para o topo dinâmico.
- Ajustar `/admin/cursos` para duas tabelas:
  - ativos (aberta)
  - inativos (colapsada)

## Arquivos criados
- `sql/018_menus_topo.sql`
- `resources/views/partials/public/header.php`
- `docs/menu-topo-dinamico-e-cursos-ativos-inativos-2026-05-04.md`

## Arquivos alterados
- `app/Services/FrontendMenuService.php`
- `resources/views/layout.php`
- `public_html/assets/css/app.css`
- `app/Controllers/AuthController.php`
- `routes/web.php`
- `resources/views/admin/cursos/index.php`

## Regras e segurança aplicadas
- Reuso da estrutura existente:
  - `frontend_menus`
  - `frontend_menu_itens`
  - `FrontendMenuService`
  - `FrontendMenuController`
- Sem exclusão física de menus/itens.
- Escape de saída com `Helpers::e`.
- Sanitização de itens de menu:
  - bloqueio de `javascript:`, `data:`, `vbscript:`
  - bloqueio de URL vazia
  - `target` restrito a `_self` e `_blank`
  - `rel="noopener noreferrer"` quando `target="_blank"`
  - bloqueio de HTML em rótulo de menu
- Fallback silencioso se menu/tabela não existir:
  - público: Início, Cursos, Como funciona, Sobre, Contato, Entrar
  - logado: Minha Página, Meus Cursos, Certificados, Cursos, Sair

## Decisão público x logado
- Baseado em `Session::get('usuario_id')`.
- Não logado: tenta `menu_topo_publico` / `topo_publico`.
- Logado: tenta `menu_topo_logado` / `topo_logado`.
- Se não encontrar menu/itens válidos, usa fallback.

## Observações operacionais
- Em produção foi necessário sincronizar arquivos nos dois espelhos detectados no FTP:
  - raiz da conta
  - `/public_html`
- Resultado validado pelo usuário após publicação.
