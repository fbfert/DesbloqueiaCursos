<?php
/**
 * <head> comum dos layouts do tema caderno. Espera as variáveis de
 * partials/layout-dados.php e, opcionalmente, $cadernoRobots.
 *
 * O gate de movimento é inline e roda antes do CSS: só põe html.anima quando
 * o sistema não pede movimento reduzido, e a trava (window.CADERNO_TRAVA)
 * tira .anima em 2,5 s caso caderno.js não assuma — nada fica invisível.
 * Sem Google Fonts e sem Tabler: fontes e ícones são do próprio site.
 */

use App\Core\Helpers;
?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= Helpers::e($pageTitle) ?></title>
  <?php if ($pageDescription !== ''): ?>
  <meta name="description" content="<?= Helpers::e($pageDescription) ?>">
  <?php endif; ?>
  <?php if (!empty($cadernoRobots)): ?>
  <meta name="robots" content="<?= Helpers::e($cadernoRobots) ?>">
  <?php endif; ?>
  <link rel="canonical" href="<?= Helpers::e($canonicalUrl) ?>">
  <meta name="theme-color" content="#FCFCFA">
  <link rel="preload" href="/assets/caderno/fontes/geist-vf.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/caderno/fontes/instrument-serif-400.woff2" as="font" type="font/woff2" crossorigin>
  <script>if(!matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('anima');window.CADERNO_TRAVA=setTimeout(function(){document.documentElement.classList.remove('anima')},2500)</script>
  <link rel="stylesheet" href="/assets/caderno/caderno.css<?= $cadernoCssVersion ? '?v=' . $cadernoCssVersion : '' ?>">
  <?php if ($cadernoJsVersion): ?>
  <script src="/assets/caderno/caderno.js?v=<?= $cadernoJsVersion ?>" defer></script>
  <?php endif; ?>
  <?php if (!empty($cadernoEmbedConteudo)): ?>
  <link rel="stylesheet" href="/assets/css/conteudo-html-embed.css?v=20260717">
  <?php endif; ?>
  <link rel="icon" href="/v2/assets/img/logo-v2.svg" type="image/svg+xml">
  <?php if ($tutorNorminha): ?>
  <?php require BASE_PATH . '/resources/views/components/tutor_norminha_head.php'; ?>
  <?php endif; ?>
