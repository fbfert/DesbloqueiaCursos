# Conteúdo/LMS: novo tipo de item "HTML"

Novo tipo de conteúdo de módulo (ao lado de `texto`, `arquivo`, `link`, `video`, `avaliacao_textual`, `quiz`): o autor do curso cola uma página HTML completa (CSS/JS próprios, ex.: algo produzido no Claude Desktop) e ela é exibida ao aluno como está, dentro de uma área isolada.

## Decisão central: sem sanitização, isolamento por iframe

Todo texto rico do projeto (`texto`, `etiqueta`, `avaliacao_textual`) passa por `HtmlSanitizer::clean()`, que remove `<script>`, `<style>`, `class`, `style` livre etc. Isso é incompatível com o objetivo do tipo `html`: preservar CSS/JS autorais de uma página pronta.

Por isso o tipo `html` é o único no projeto cujo conteúdo **não** passa pelo `HtmlSanitizer`. O risco de XSS/quebra de layout é isolado de outra forma: o HTML é renderizado ao aluno dentro de

```html
<iframe sandbox="allow-scripts allow-popups" srcdoc="...">
```

sem `allow-same-origin` — o documento do iframe fica em origem opaca, sem acesso a cookies/sessão do aluno nem ao DOM da página pai. Autoria fica disponível tanto para admin quanto para professor (mesmo modelo de confiança dos demais tipos; nenhum outro tipo hoje é admin-only).

## Arquivos criados

- `sql/065_conteudo_tipo_html.sql`
- `app/Models/ConteudoHtml.php`
- `app/Support/HtmlEmbedRenderer.php` — envolve o HTML bruto e injeta um `<script>` mínimo (`ResizeObserver` + `postMessage`) para o iframe avisar sua altura real ao documento pai.
- `assets/css/conteudo-html-embed.css`
- `assets/js/conteudo-html-embed.js` — listener de `postMessage` no documento pai que redimensiona o iframe (valida a origem pela igualdade `frame.contentWindow === event.source`, não por `event.origin`, porque a origem do iframe sandboxed é opaca).

## Arquivos alterados

- `app/Services/ConteudoCursoService.php` — tipo `html` adicionado a `TIPOS_ITEM_VALIDOS` e a todos os pontos de despacho por tipo: salvar (sem `HtmlSanitizer`), carregar, duplicar, rótulo ("HTML") e ação pública ao aluno ("Abrir conteúdo").
- `app/Controllers/Admin/AreaCursoController.php` — `previewConteudoHtml()`, devolve o HTML colado tal como está (sem wrapper), para conferência visual antes de salvar.
- `app/Controllers/AreaCursoController.php` (aluno) — `html` tratado como `texto` para ação registrada (`abriu_html`) e conclusão automática ao abrir a página.
- `routes/web.php` — `POST /admin/area-curso/conteudo/html/preview`.
- `resources/views/admin/area-curso/conteudo_item_form.php` — opção "HTML" no seletor de tipo, campo `<textarea name="html_conteudo">` **sem** CKEditor (o WYSIWYG destruiria `<script>`/`<style>`), botão "Pré-visualizar em nova aba".
- `resources/views/admin/area-curso/_conteudo.php` e `resources/views/professor/area-curso/_conteudo.php` — rótulo/dica do tipo na listagem (aproveitado para também corrigir "Quiz", que faltava nos dois).
- `app/Core/Helpers.php` — `tipoConteudoLms()` também ganhou `html` e `quiz`.
- `resources/views/aluno/curso/conteudo.php` **e** `resources/views/v4-claude/aluno/curso/conteudo.php` — os dois templates ativos (a escolha é uma configuração runtime, `ConfiguracaoGlobalService::templateVisualPortal()`); ambos precisaram do bloco do iframe e do ajuste na condição de conclusão automática.
- `resources/views/v4-claude/aluno/curso/modulo.php` — ícone do tipo `html` no mapa `$tipoIcone` da listagem do módulo.
- `resources/views/layout.php` — inclui `conteudo-html-embed.css/js` apenas nas páginas de conteúdo do aluno (mesma condição `$shouldLoadConteudoAudio` já usada por `conteudo-audio.css/js`).

## Tabela criada

`conteudo_htmls` — espelha `conteudo_textos` (`id`, `item_id` único com FK `ON DELETE CASCADE` para `conteudo_itens`, `conteudo LONGTEXT`, `created_at`, `updated_at`).

## Rotas

| Rota | Permissão |
|---|---|
| `POST /admin/area-curso/conteudo/html/preview` | `area_curso.gerenciar` |

Sem rota equivalente para professor: o controller de professor não tem tela de criar/editar item (`criarConteudoItem`/`editarConteudoItem` só existem no admin), então não há de onde chamar a pré-visualização.

## Validação real

- `php -l` e `node --check` sem erros em todos os arquivos criados/alterados.
- Migration aplicada diretamente no banco configurado em `.env` (script avulso reaproveitando `App\Core\Database::connection()`), com verificação pós-aplicação: `SHOW COLUMNS FROM conteudo_itens LIKE 'tipo'` confirmando o novo valor `html` no `ENUM`, e `SHOW TABLES LIKE 'conteudo_htmls'` confirmando a tabela.

**Não exercitado:** o fluxo completo em navegador (criar item HTML pelo admin, pré-visualizar, salvar, abrir como aluno e conferir o auto-resize do iframe). Recomendado antes de considerar a feature pronta para uso real.
