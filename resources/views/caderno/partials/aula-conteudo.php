<?php
/**
 * Conteúdo da aula por tipo. Segurança idêntica a v2/pages/aula.php (não mudar
 * sem mudar lá):
 *   - texto: Helpers::renderSafeHtml(..., 'reading'); arquivo/link: 'basic';
 *     video_embed: 'full' (o sanitizador remove iframes);
 *   - html: iframe sandbox="allow-scripts allow-popups" (NUNCA allow-same-origin)
 *     com srcdoc = HtmlEmbedRenderer::wrap(...) escapado por Helpers::e(); a
 *     altura vem por postMessage (assets/js/conteudo-html-embed.js);
 *   - video_incorporado: o mesmo sandbox, srcdoc escapado;
 *   - arquivo_icone: impresso como vem (lista fechada de Helpers::iconeArquivo).
 *
 * Espera: $item (AulaController::montarItem).
 */

use App\Core\Helpers;
use App\Support\HtmlEmbedRenderer;

$acTipo = (string) $item['tipo'];
$acTitulo = (string) $item['titulo'];
?>
<?php if ($acTipo === 'video'): ?>
  <?php if (trim((string) $item['video_embed']) !== ''): ?>
  <div class="aula-video aula-video-livre"><?= Helpers::renderSafeHtml((string) $item['video_embed'], 'full') ?></div>
  <?php elseif (!empty($item['video_embed_resolvido'])): ?>
  <div class="aula-video">
    <iframe
      src="<?= Helpers::e($item['video_embed_resolvido']['embedUrl']) ?>"
      title="<?= Helpers::e($acTitulo) ?>"
      loading="lazy"
      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
      referrerpolicy="strict-origin-when-cross-origin"
      allowfullscreen
    ></iframe>
  </div>
  <?php if (trim((string) $item['video_url']) !== ''): ?>
  <p class="aula-nota"><a class="link" href="<?= Helpers::e((string) $item['video_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir no site original ↗</a></p>
  <?php endif; ?>
  <?php elseif (trim((string) $item['video_url']) !== ''): ?>
  <p class="aula-acao"><a class="btn" href="<?= Helpers::e((string) $item['video_url']) ?>" target="_blank" rel="noopener noreferrer">Assistir vídeo <?= caderno_icone('seta-dir') ?></a></p>
  <?php else: ?>
  <p class="aula-nota">Não há vídeo disponível para este conteúdo.</p>
  <?php endif; ?>

<?php elseif ($acTipo === 'video_incorporado'): ?>
  <?php if (trim((string) $item['video_incorporado_conteudo']) !== ''): ?>
  <?php $acSrcdoc = '<style>html,body{margin:0;padding:0;height:100%;overflow:hidden}iframe,video,embed,object{width:100%;height:100%;border:0;display:block}</style>' . $item['video_incorporado_conteudo']; ?>
  <div class="aula-video">
    <iframe
      id="conteudo-video-incorporado-frame-<?= (int) $item['id'] ?>"
      sandbox="allow-scripts allow-popups"
      title="<?= Helpers::e($acTitulo) ?>"
      loading="lazy"
      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
      allowfullscreen
      srcdoc="<?= Helpers::e($acSrcdoc) ?>"
    ></iframe>
  </div>
  <?php else: ?>
  <p class="aula-nota">Não há vídeo disponível para este conteúdo.</p>
  <?php endif; ?>

<?php elseif ($acTipo === 'texto' && trim((string) $item['texto_html']) !== ''): ?>
  <div class="aula-texto"><?= Helpers::renderSafeHtml((string) $item['texto_html'], 'reading') ?></div>

<?php elseif ($acTipo === 'html' && trim((string) $item['texto_html']) !== ''): ?>
  <?php $acFrameId = 'conteudo-html-frame-' . (int) $item['id']; ?>
  <article class="conteudo-item-html">
    <iframe
      id="<?= Helpers::e($acFrameId) ?>"
      class="js-conteudo-html-frame conteudo-item-html__frame"
      sandbox="allow-scripts allow-popups"
      title="<?= Helpers::e($acTitulo) ?>"
      loading="lazy"
      srcdoc="<?= Helpers::e(HtmlEmbedRenderer::wrap((string) $item['texto_html'], $acFrameId)) ?>"
    ></iframe>
  </article>

<?php elseif ($acTipo === 'arquivo'): ?>
  <?php if (trim((string) $item['texto_html']) !== ''): ?>
  <div class="aula-texto"><?= Helpers::renderSafeHtml((string) $item['texto_html'], 'basic') ?></div>
  <?php endif; ?>
  <?php if (trim((string) $item['acao_url']) !== ''): ?>
  <div class="aula-anexo">
    <span class="aula-anexo-folha" aria-hidden="true"><?= $item['arquivo_icone'] ?><?php if ($item['arquivo_extensao'] !== ''): ?><small><?= Helpers::e(strtoupper($item['arquivo_extensao'])) ?></small><?php endif; ?></span>
    <div class="aula-anexo-info">
      <b><?= Helpers::e($item['arquivo_nome'] !== '' ? $item['arquivo_nome'] : $acTitulo) ?></b>
      <?php $acMeta = array_filter(array($item['arquivo_extensao'] !== '' ? strtoupper($item['arquivo_extensao']) : '', (string) $item['arquivo_tamanho'])); ?>
      <?php if (!empty($acMeta)): ?><span><?= Helpers::e(implode(' · ', $acMeta)) ?></span><?php endif; ?>
    </div>
    <a class="btn" href="<?= Helpers::e((string) $item['acao_url']) ?>">Baixar material</a>
  </div>
  <?php else: ?>
  <p class="aula-nota">O material não está disponível no momento.</p>
  <?php endif; ?>

<?php elseif ($acTipo === 'link'): ?>
  <?php $acDesc = trim((string) $item['texto_html']) !== '' ? (string) $item['texto_html'] : (string) $item['descricao_curta']; ?>
  <?php if (trim($acDesc) !== ''): ?>
  <div class="aula-texto"><?= Helpers::renderSafeHtml($acDesc, 'basic') ?></div>
  <?php endif; ?>
  <?php if (trim((string) $item['acao_url']) !== ''): ?>
  <div class="aula-anexo">
    <span class="aula-anexo-folha" aria-hidden="true">🔗</span>
    <div class="aula-anexo-info">
      <b><?= Helpers::e($acTitulo) ?></b>
      <?php if ($item['link_host'] !== ''): ?><span><?= Helpers::e($item['link_host']) ?></span><?php endif; ?>
    </div>
    <a class="btn" href="<?= Helpers::e((string) $item['acao_url']) ?>">Abrir link <?= caderno_icone('seta-dir') ?></a>
  </div>
  <?php else: ?>
  <p class="aula-nota">O link não está disponível no momento.</p>
  <?php endif; ?>

<?php elseif ($acTipo === 'quiz' && trim((string) ($item['quiz_url'] ?? '')) !== ''): ?>
  <div class="postit largo aula-tarefa">
    <p>Este é um quiz objetivo. Responda diretamente aqui na sua área.</p>
    <a class="btn" href="<?= Helpers::e((string) $item['quiz_url']) ?>">Responder quiz <?= caderno_icone('seta-dir') ?></a>
  </div>

<?php elseif ($acTipo === 'avaliacao_textual' && trim((string) ($item['atividade_url'] ?? '')) !== ''): ?>
  <div class="postit largo aula-tarefa">
    <p>Esta é uma atividade avaliativa discursiva. Responda diretamente aqui na sua área.</p>
    <a class="btn" href="<?= Helpers::e((string) $item['atividade_url']) ?>">Responder atividade <?= caderno_icone('seta-dir') ?></a>
  </div>

<?php elseif (!empty($item['eh_interativo'])): ?>
  <div class="postit largo aula-tarefa">
    <p>Esta é uma atividade <?= Helpers::e((string) $item['tipo_label']) ?>. Para responder, abra a atividade no ambiente de aprendizagem.</p>
    <a class="btn" href="<?= Helpers::e((string) $item['oficial_url']) ?>">Abrir atividade <?= caderno_icone('seta-dir') ?></a>
  </div>

<?php elseif ($acTipo === 'texto' || $acTipo === 'html'): ?>
  <p class="aula-nota">Conteúdo sem texto disponível.</p>
<?php endif; ?>
