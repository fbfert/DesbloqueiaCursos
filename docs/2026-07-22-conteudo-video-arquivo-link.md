# Conteúdo/LMS: embed de vídeo, novo tipo "Vídeo Incorporado", visual de Arquivo/Link

Conjunto de melhorias na área do curso (aluno) para os tipos de conteúdo `video`, `arquivo`, `link`, e um tipo novo, `video_incorporado`.

## Vídeo (`video`): embed automático por URL

Antes, `video` só exibia um botão "Assistir vídeo" abrindo a URL em nova aba — o campo `embed_html` nunca é preenchido pelo formulário admin (sempre grava `null`), e mesmo que fosse, `HtmlSanitizer::clean()` remove `<iframe>` de qualquer HTML.

Criado `App\Support\VideoEmbedResolver::resolve($url)`: identifica o provedor pelo host (YouTube, Vimeo, Google Drive) e extrai o ID por regex (whitelist, sem depender de HTML livre), devolvendo uma URL de embed segura:

- YouTube (`watch?v=`, `youtu.be/`, `/embed/`, `/shorts/`) → `youtube-nocookie.com/embed/{id}`
- Vimeo → `player.vimeo.com/video/{id}`
- Google Drive (`/file/d/{id}/...`) → `drive.google.com/file/d/{id}/preview`
- Qualquer outro provedor (ex.: link de reunião do Google Meet) continua caindo no botão externo — não dá pra embutir uma reunião ao vivo.

O iframe é construído diretamente na view a partir dessa URL validada — não passa pelo `HtmlSanitizer` porque não é HTML livre, é uma URL nossa, montada por nós.

## Novo tipo "Vídeo Incorporado" (`video_incorporado`)

Para quando o autor já tem o código de embed pronto (de qualquer provedor, inclusive os que a detecção automática do tipo `video` não cobre — ex. Panda, Wistia). Mesma filosofia do tipo `html` (ver `docs/2026-07-17-conteudo-tipo-html.md`): o conteúdo é gravado **sem sanitização** e exibido num iframe sandboxed (`allow-scripts allow-popups`, sem `allow-same-origin`).

Diferença para o tipo `html`: aqui o resultado é sempre um player de vídeo em caixa 16:9 (não uma "página" de altura variável), então não usa `HtmlEmbedRenderer::wrap()` (que injeta o script de auto-resize) — em vez disso injeta um `<style>` mínimo (`iframe,video,embed,object{width:100%;height:100%}`) para o embed colado preencher o player, independentemente do `width`/`height` fixo que o snippet original tragа.

### Tabela criada

`conteudo_videos_incorporados` — espelha `conteudo_htmls` (`id`, `item_id` único com FK `ON DELETE CASCADE` para `conteudo_itens`, `conteudo LONGTEXT`, `created_at`, `updated_at`). Migration: `sql/066_conteudo_tipo_video_incorporado.sql` (ALTER do ENUM `conteudo_itens.tipo` + CREATE TABLE, ambos idempotentes). **Aplicada diretamente no banco de produção nesta sessão.**

## Arquivo (`arquivo`): card com ícone e tamanho

Antes: card genérico, só um botão "Baixar arquivo", sem nenhuma informação do arquivo em si. Agora mostra ícone por extensão (`Helpers::iconeArquivo()`), nome original e tamanho formatado (`Helpers::formatarTamanhoArquivo()`, KB/MB/GB).

## Link (`link`): descrição e domínio visíveis

A `descricao_curta` do item (campo já existente, mas nunca exibido nessa tela) e o domínio do link (`parse_url` na URL real armazenada, não na rota intermediária de redirecionamento) agora aparecem num card com ícone 🔗, no mesmo padrão visual do card de Arquivo.

## Unificação com o template `v2`

O template `v2` (rota `/v2/aula`, `App\Controllers\V2\AulaController`) tem sua própria lógica de renderização (array `$item` achatado, não o par `$conteudo_item`/`$conteudo_detalhe` dos outros dois templates) e estava desatualizado em relação a tudo acima — inclusive sem nenhum suporte a `video_incorporado`. `montarItem()` ganhou os mesmos campos (`video_embed_resolvido`, `video_incorporado_conteudo`, dados de arquivo, host do link, `descricao_curta`); a view (`resources/views/v2/pages/aula.php`) ganhou os blocos correspondentes; CSS novo em `v2/assets/css/v2-main.css` (`.v2-lms-video-player`, `.v2-lms-file-card`).

## Correção de largura no mobile

O player de vídeo (tipo `video` e `video_incorporado`) ficava encolhido por dois paddings aninhados: o card `.conteudo-item-video` (16px) dentro do painel de conteúdo. `.conteudo-item-video__player` agora usa `width: calc(100% + 32px)` com `margin: 0 -16px`, sangrando para fora do padding do card e ocupando 100% da largura dele. Mesmo ajuste (inline) no template `v4-claude`. O template `v2` não precisou de ajuste — lá o player já não tem padding de card ao redor.

## Arquivos criados

- `sql/066_conteudo_tipo_video_incorporado.sql`
- `app/Models/ConteudoVideoIncorporado.php`
- `app/Support/VideoEmbedResolver.php`

## Arquivos alterados

- `app/Services/ConteudoCursoService.php` — `video_incorporado` em `TIPOS_ITEM_VALIDOS` e em todos os pontos de despacho por tipo (duplicar, carregar detalhe, rótulo, ação pública, salvar).
- `app/Core/Helpers.php` — `iconeArquivo()`, `formatarTamanhoArquivo()`, `video_incorporado` em `tipoConteudoLms()`.
- `app/Controllers/AreaCursoController.php` (aluno) — ação de log `abriu_video_incorporado`; conclusão manual (mesma regra do tipo `video`, não auto-completa como `texto`/`html`).
- `app/Controllers/V2/AulaController.php` — ver "Unificação com o template v2" acima.
- `resources/views/admin/area-curso/conteudo_item_form.php` — opção "Vídeo incorporado" no seletor, textarea `video_incorporado_conteudo`, botão de pré-visualização (reaproveita a rota `/admin/area-curso/conteudo/html/preview` já existente).
- `resources/views/admin/area-curso/_conteudo.php` e `resources/views/professor/area-curso/_conteudo.php` — rótulo/dica do tipo novo na listagem.
- `resources/views/aluno/curso/conteudo.php`, `resources/views/v4-claude/aluno/curso/conteudo.php`, `resources/views/v2/pages/aula.php` — blocos de vídeo (embed automático + `video_incorporado`), arquivo (ícone/tamanho) e link (descrição/host) nos três templates.
- `resources/views/v4-claude/aluno/curso/modulo.php` — ícone do tipo `video_incorporado` no mapa `$tipoIcone`.
- `assets/css/frontend.css` e `v2/assets/css/v2-main.css` — estilos novos citados acima.

## Validação real

- `php -l` sem erros em todos os arquivos criados/alterados.
- Migration aplicada diretamente no banco configurado em `.env`, com verificação pós-aplicação (`SHOW COLUMNS`/`SHOW TABLES`).
- Round-trip completo direto no service (`salvarItemComDetalhes` → `detalharItem` → `duplicarItem`) contra o banco real, com limpeza dos dados de teste (delete físico) ao final — sem deixar rastro.
- `VideoEmbedResolver` testado com as URLs reais já cadastradas no banco (YouTube, Google Meet, Google Drive) — cada uma resolvendo (ou não) como esperado.
- Views renderizadas fora do navegador (harness próprio: `extract()` de um `$data` mock + `require` direto do arquivo de view, capturando a saída via `ob_start()`) para os três templates, cobrindo vídeo com embed resolvido, vídeo sem URL, vídeo incorporado com/sem conteúdo, arquivo disponível/indisponível, link com/sem descrição.
- Largura no mobile: testada com Puppeteer (`page.setViewport({isMobile:true, hasTouch:true, deviceScaleFactor:2})`, não apenas a flag `--window-size` da CLI do Chromium, que se mostrou não confiável para emular viewport nesse ambiente) em três larguras (375px, 390px, 412px) — `document.documentElement.scrollWidth - clientWidth === 0` em todos os casos, nos dois templates com o fix (`v1` e `v4-claude`).

**Não exercitado:** o fluxo completo em navegador real autenticado como aluno (criar um item `video_incorporado` pelo admin, salvar, abrir como aluno). A validação foi toda via renderização isolada da view + round-trip direto no banco/service.
