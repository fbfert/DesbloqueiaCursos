<?php
use App\Core\Helpers;

// Fase 2.13B — página institucional com conteúdo real do backend.
// $institTitulo, $institResumo, $institHtml (JÁ sanitizado no controller),
// $institMapEmbedUrl (URL de mapa em whitelist ou null), $institMapLinkUrl,
// $institBreadcrumb.
$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$institTitulo = isset($institTitulo) ? (string) $institTitulo : '';
$institResumo = isset($institResumo) ? (string) $institResumo : '';
$institBreadcrumb = isset($institBreadcrumb) ? (string) $institBreadcrumb : $institTitulo;
$institHtml = isset($institHtml) ? (string) $institHtml : '';
$institMapEmbedUrl = isset($institMapEmbedUrl) && $institMapEmbedUrl !== null ? (string) $institMapEmbedUrl : '';
$institMapLinkUrl = isset($institMapLinkUrl) && $institMapLinkUrl !== null ? (string) $institMapLinkUrl : '';
?>
<script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>

<section class="v2-container v2-instit">
  <nav class="v2-breadcrumb" aria-label="Caminho">
    <a href="<?php echo Helpers::e($homeHref); ?>">Início</a>
    <i class="ti ti-chevron-right" aria-hidden="true"></i>
    <span aria-current="page"><?php echo Helpers::e($institBreadcrumb); ?></span>
  </nav>

  <header class="v2-instit-head">
    <h1 class="v2-h1"><?php echo Helpers::e($institTitulo); ?></h1>
    <?php if ($institResumo !== ''): ?>
      <p class="v2-instit-resumo v2-muted"><?php echo Helpers::e($institResumo); ?></p>
    <?php endif; ?>
  </header>

  <article class="v2-instit-card">
    <?php if (trim($institHtml) !== ''): ?>
      <div class="v2-prose"><?php echo $institHtml; /* sanitizado em V2InstitucionalContent */ ?></div>
    <?php else: ?>
      <p class="v2-muted">Conteúdo em atualização.</p>
    <?php endif; ?>

    <?php if ($institMapEmbedUrl !== ''): ?>
      <div class="v2-instit-map">
        <iframe
          src="<?php echo Helpers::e($institMapEmbedUrl); ?>"
          title="Mapa da localização"
          width="100%"
          height="360"
          style="border:0;"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          allowfullscreen></iframe>
      </div>
    <?php endif; ?>

    <?php if ($institMapLinkUrl !== ''): ?>
      <p class="v2-sm" style="margin:12px 0 0;"><a href="<?php echo Helpers::e($institMapLinkUrl); ?>" target="_blank" rel="noopener noreferrer">Ver no mapa</a></p>
    <?php endif; ?>
  </article>
</section>
