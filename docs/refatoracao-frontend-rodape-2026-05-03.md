# Refatoração do pré-rodapé e rodapé públicos (admin gerenciável)

## Data
- 2026-05-03

## Objetivo
- Remover conteúdo fixo antes do rodapé e no rodapé do layout público.
- Tornar módulos e menus dessa área gerenciáveis no backoffice.
- Manter compatibilidade com PHP MVC, MySQL 5.7, mobile-first, RBAC, auditoria e lixeira.

## Escopo implementado

### 1) Migration e seed inicial
- Arquivo: `sql/017_frontend_modulos_menus.sql`
- Cria tabelas:
  - `frontend_modulos`
  - `frontend_menus`
  - `frontend_menu_itens`
- Inclui seed:
  - Módulo `antes_rodape`
  - Menu `menu_antes_rodape`
  - Itens iniciais: Cursos, Como funciona, Sobre, Contato
  - Módulo `rodape` com conteúdo `{ano} Polo Rainbow.`
- Inclui permissões RBAC:
  - `frontend.modulos.ver`
  - `frontend.modulos.gerenciar`
  - `frontend.menus.ver`
  - `frontend.menus.gerenciar`
- Vincula permissões para `superadmin`, `conteudo` e `marketing`.

### 2) Modelos
- `app/Models/FrontendModulo.php`
- `app/Models/FrontendMenu.php`
- `app/Models/FrontendMenuItem.php`
- Métodos para listar, buscar, criar, atualizar, exclusão lógica e listagem ordenada de itens ativos.

### 3) Services
- `app/Services/FrontendModuloService.php`
- `app/Services/FrontendMenuService.php`
- `app/Services/PlaceholderService.php`
- Regras implementadas:
  - validação de campos obrigatórios
  - bloqueio de código duplicado
  - validação de URL e `target` de itens
  - ajuste de `rel="noopener noreferrer"` quando `target="_blank"`
  - exclusão lógica com justificativa
  - auditoria em criar/editar/excluir/reordenar
  - placeholders suportados:
    - `{ano}`
    - `{nome_portal}`
    - `{url_site}`

### 4) Controllers admin
- `app/Controllers/Admin/FrontendModuloController.php`
- `app/Controllers/Admin/FrontendMenuController.php`
- Controllers finos, com uso de Services e redirecionamento pós-POST.

### 5) Rotas
- Arquivo: `routes/web.php`
- Adicionadas rotas para:
  - CRUD de módulos
  - CRUD de menus
  - CRUD de itens de menu
  - reordenação de itens

### 6) Views admin
- `resources/views/admin/frontend/modulos/index.php`
- `resources/views/admin/frontend/modulos/form.php`
- `resources/views/admin/frontend/menus/index.php`
- `resources/views/admin/frontend/menus/form.php`
- `resources/views/admin/frontend/menus/itens.php`
- `resources/views/admin/frontend/menus/item_form.php`

### 7) Menu lateral admin
- Arquivo: `resources/views/admin/_shell.php`
- Inclusos atalhos:
  - Frontend · Módulos
  - Frontend · Menus

### 8) Layout público dinâmico
- Arquivo alterado: `resources/views/layout.php`
- Partials criadas:
  - `resources/views/partials/public/pre_footer.php`
  - `resources/views/partials/public/footer.php`
- Removido hardcode do bloco pré-rodapé e rodapé.
- Renderização passa a consultar módulos/menus ativos.

### 9) CSS mobile-first
- Arquivo alterado: `public_html/assets/css/app.css`
- Adicionadas classes:
  - `.pre-footer`
  - `.pre-footer__title`
  - `.pre-footer__text`
  - `.pre-footer__menu`
  - `.pre-footer__link`
  - `.site-footer__content`

## Segurança aplicada
- Escape de saída com `Helpers::e` nas views.
- Validação de URL (`/`, `http://`, `https://`) para itens de menu.
- Restrição de `target` para `_self` e `_blank`.
- Complemento de `rel` para links externos com nova aba.
- Exclusão lógica com `deleted_at`, `excluido_por`, `justificativa_exclusao`.
- Registro em auditoria e lixeira.
- `permite_html` mantido desabilitado por padrão no fluxo de negócio.

## Deploy
- Arquivos publicados via FTP em `ftp.polorainbow.com.br`.
- Pendente operacional:
  - executar `sql/017_frontend_modulos_menus.sql` no banco de produção.

## Testes recomendados
1. Abrir página pública e validar pré-rodapé/rodapé.
2. Confirmar texto inicial com acentuação correta.
3. Alterar título/subtítulo em `/admin/frontend/modulos`.
4. Ativar/desativar módulo `antes_rodape`.
5. Adicionar item `Validar certificado` em `/certificados/validar`.
6. Reordenar itens em `/admin/frontend/menus/itens`.
7. Alterar rodapé para `© {ano} Polo Rainbow. Todos os direitos reservados.`
8. Validar responsividade em `360`, `390`, `768`, `1366` e `1920`.
