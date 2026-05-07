# Frontend público — refresh visual mobile-first (2026-05-07)

## Escopo aplicado
- Tema visual claro e vibrante no frontend público.
- Isolamento por classe raiz `frontend-theme`.
- Sem alterações em regras de negócio, rotas, controllers, models, services ou SQL.

## Arquivos alterados
- `resources/views/layout.php`
- `public_html/assets/css/frontend.css`

## O que foi implementado
- Novo CSS dedicado ao frontend público (`frontend.css`) com:
  - variáveis de tema;
  - paleta clara com gradientes controlados;
  - componentes mobile-first (header, hero, cards, badges, botões, formulários, footer);
  - responsividade em `768px` e `1024px`.
- Carregamento condicional do `frontend.css` no layout.
- Aplicação de `frontend-theme` para páginas não-admin e não-professor.
- Ajustes de contraste:
  - menu hambúrguer mobile;
  - textos em cards e blocos de destaque (hero stats);
  - CTA “Minha Página” e links do header no mesmo padrão da capa.

## Validação rápida
- Verificado carregamento de `frontend.css` e `frontend-theme` em:
  - `/`
  - `/cursos`
  - `/login`
  - `/cadastro`
  - `/meus-cursos`
  - `/area-curso`

## Observações
- Admin e professor permanecem fora do tema novo.
- Mudança estritamente visual.
