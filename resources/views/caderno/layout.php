<?php
/**
 * Layout principal do tema caderno (vitrine e checkout).
 *
 * Contrato: o shim da página define $contentView (caminho absoluto de
 * resources/views/caderno/pages/<pagina>.php) e $paginaTema (vira
 * <body data-pagina>), e depois faz require deste arquivo. As variáveis de
 * navegação são as mesmas do layout V2.
 */

use App\Core\Helpers;

require __DIR__ . '/partials/layout-dados.php';

$contentView = isset($contentView) ? (string) $contentView : '';
$cadernoEmbedConteudo = true;
// Páginas do aluno: $cadernoAluno carrega caderno-aluno.css/js; $cadernoEstudo
// (aula, quiz, atividade) também tira a bnav geral e marca <body data-estudo>.
$cadernoEstudo = !empty($cadernoEstudo);
$cadernoAluno = !empty($cadernoAluno) || $cadernoEstudo;
?>
<!doctype html>
<html lang="pt-BR">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body data-pagina="<?= Helpers::e($paginaTema) ?>"<?= $cadernoEstudo ? ' data-estudo' : '' ?>>
  <a class="pular" href="#conteudo">Pular para o conteúdo</a>
  <?php require __DIR__ . '/partials/icones.php'; ?>
  <?php require __DIR__ . '/partials/aviso-previa.php'; ?>
  <div class="folha">
    <div class="furos" aria-hidden="true"></div>
    <?php require __DIR__ . '/partials/topo.php'; ?>
    <main id="conteudo" class="miolo" tabindex="-1">
      <?php if ($cadernoAluno) { require_once BASE_PATH . '/resources/views/partials/aviso-cpf.php'; echo aviso_cpf_pendente('caderno'); } require $contentView; ?>
    </main>
    <?php require __DIR__ . '/partials/rodape.php'; ?>
  </div>
  <?php if (!$cadernoEstudo) { require __DIR__ . '/partials/bnav.php'; } ?>

  <script src="/assets/js/conteudo-html-embed.js?v=20260717" defer></script>
  <?php if ($tutorNorminha): ?>
  <?php require BASE_PATH . '/resources/views/components/tutor_norminha.php'; ?>
  <script src="/assets/js/tutor-norminha.js<?= $tutorNorminhaJsVersion ? '?v=' . (int) $tutorNorminhaJsVersion : '' ?>" defer></script>
  <?php endif; ?>
</body>
</html>
