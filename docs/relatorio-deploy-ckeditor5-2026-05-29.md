# Relatório de deploy e validação - CKEditor 5

Data: 2026-05-29

## Escopo

- Substituição do editor local por CKEditor 5 local, sem CDN.
- Compatibilidade mantida com:
  - `window.initConteudoRichEditors`
  - `window.initAreaCursoWysiwyg`
- Revisão de sanitização e renderização HTML no frontend do aluno.

## Revisão técnica

- O layout carrega os assets na ordem correta:
  - `public_html/assets/vendor/ckeditor5/ckeditor.js`
  - `public_html/assets/vendor/ckeditor5/translations/pt-br.js`
  - `public_html/assets/js/conteudo-editor.js`
- A toolbar final foi alinhada ao build Classic usado.
- `removeFormat` foi removido da configuração para evitar warning/erro, pois não está disponível no build verificado.
- A inicialização é idempotente e evita duplicação de instâncias.
- O submit global sincroniza os editores antes do envio.

## Frontend do aluno

- A view exata da rota `/aluno/cursos` é `resources/views/area-curso/index.php`.
- A descrição do módulo é renderizada de forma segura com:
  - `Helpers::renderSafeHtml(...)`
- O fluxo de dados editorial ficou:
  - `decodeEditorHtml()`
  - `HtmlSanitizer::clean()`
  - renderização segura na view

## Sanitização

- Remoção confirmada para:
  - `script`
  - `iframe`
  - `object`
  - `embed`
  - `form`
  - `input`
  - atributos `on*`
  - `href="javascript:"`
  - `href="data:"`
- Tags editoriais básicas mantidas:
  - `p`, `br`, `strong`, `b`, `em`, `i`, `u`, `ul`, `ol`, `li`, `a`, `h2`, `h3`, `h4`, `blockquote`, `table`, `thead`, `tbody`, `tr`, `th`, `td`

## Validações executadas

- `php -l` executado com sucesso nos arquivos de PHP alterados.
- `node --check public_html/assets/js/conteudo-editor.js` executado com sucesso.
- Teste de sanitização com payload malicioso executado com sucesso.

## Licença

- O build usado foi `@ckeditor/ckeditor5-build-classic` `41.4.2`.
- Licença do pacote: `GPL-2.0-or-later`.
- Não foi usada CDN.
- Não foram ativados recursos premium.
- Não foi inserida `licenseKey` falsa.

## Observação operacional

- Os arquivos de vendor do CKEditor foram mantidos em `public_html/assets/vendor/ckeditor5/`.
- A pasta está liberada no `.gitignore` para versionamento.

