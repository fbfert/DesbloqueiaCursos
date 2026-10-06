<?php
/**
 * Página institucional do tema caderno. Recebe as mesmas variáveis da V2
 * (V2\InstitucionalController): institTitulo, institResumo, institBreadcrumb,
 * institHtml (já sanitizado no controller, impresso como na V2),
 * institMapEmbedUrl e institMapLinkUrl.
 *
 * O texto longo usa .texto (line-height de 32 px, a pauta do caderno).
 */

use App\Core\Helpers;

$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$institTitulo = isset($institTitulo) ? (string) $institTitulo : '';
$institResumo = isset($institResumo) ? (string) $institResumo : '';
$institBreadcrumb = isset($institBreadcrumb) ? (string) $institBreadcrumb : $institTitulo;
$institHtml = isset($institHtml) ? (string) $institHtml : '';
$institMapEmbedUrl = isset($institMapEmbedUrl) && $institMapEmbedUrl !== null ? (string) $institMapEmbedUrl : '';
$institMapLinkUrl = isset($institMapLinkUrl) && $institMapLinkUrl !== null ? (string) $institMapLinkUrl : '';
?>
<section class="sec instit" aria-labelledby="instit-titulo">
  <nav class="migalha" aria-label="Caminho">
    <a href="<?= Helpers::e($homeHref) ?>">Início</a>
    <span aria-hidden="true">/</span>
    <span aria-current="page"><?= Helpers::e($institBreadcrumb) ?></span>
  </nav>

  <h1 class="t1" id="instit-titulo"><?= Helpers::e($institTitulo) ?></h1>
  <?php if ($institResumo !== ''): ?>
  <p class="lead"><?= Helpers::e($institResumo) ?></p>
  <?php endif; ?>

  <article class="texto instit-texto">
    <?php if (trim($institHtml) !== ''): ?>
    <?= $institHtml /* sanitizado em V2InstitucionalContent */ ?>
    <?php else: ?>
    <p>Conteúdo em atualização.</p>
    <?php endif; ?>
  </article>

  <?php if ($institMapEmbedUrl !== ''): ?>
  <div class="instit-mapa">
    <iframe src="<?= Helpers::e($institMapEmbedUrl) ?>" title="Mapa da localização" width="100%" height="352"
            loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
  </div>
  <?php endif; ?>

  <?php if ($institMapLinkUrl !== ''): ?>
  <p class="instit-mapa-link"><a class="link" href="<?= Helpers::e($institMapLinkUrl) ?>" target="_blank" rel="noopener noreferrer">Ver no mapa</a></p>
  <?php endif; ?>
</section>
