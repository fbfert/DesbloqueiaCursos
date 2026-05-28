# Ajuste do editor de conteúdo na área do curso

Data: 2026-05-27

## Contexto

A aba **Conteúdo** da área do curso, no admin e no professor, precisava exibir um editor visual no campo **Conteúdo** do item do tipo **Texto**, preservando HTML seguro no salvamento e mantendo a sanitização no backend.

## O que foi ajustado

- Mantida a renderização da área do curso pelo layout base do projeto.
- Criado um editor local leve em `public_html/assets/js/conteudo-editor.js`.
- Criado o estilo do editor em `public_html/assets/css/conteudo-editor.css`.
- O campo `textarea` do conteúdo passou a usar a classe `js-conteudo-rich-editor`.
- O layout do admin/professor passou a carregar os assets do editor com cache-busting.
- A sanitização em `app/Support/HtmlSanitizer.php` foi preservada e reforçada para HTML seguro.

## Recursos do editor

- Negrito
- Itálico
- Título
- Parágrafo
- Lista
- Lista numerada
- Link
- Limpar formatação
- Alternância para edição HTML

## Segurança

Continuam bloqueados:

- `script`
- `iframe` inseguro
- `object`
- `embed`
- atributos `on*`
- `javascript:` em `href` e `src`

## Validação

- `php -l` executado nos arquivos PHP alterados.
- `node --check public_html/assets/js/conteudo-editor.js` executado com sucesso.
- Assets publicados por FTP.

## Arquivos principais

- `resources/views/layout.php`
- `resources/views/admin/area-curso/_conteudo.php`
- `resources/views/professor/area-curso/_conteudo.php`
- `public_html/assets/js/conteudo-editor.js`
- `public_html/assets/css/conteudo-editor.css`
- `app/Support/HtmlSanitizer.php`
