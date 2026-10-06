<?php
/**
 * Layout das telas de autenticação do tema caderno (login, pós-login,
 * cadastro, recuperação e redefinição de senha): folha limpa e centralizada,
 * sem navegação principal nem barra inferior. Mesmo contrato do layout.php
 * ($contentView e $paginaTema).
 */

use App\Core\Helpers;

require __DIR__ . '/partials/layout-dados.php';

$contentView = isset($contentView) ? (string) $contentView : '';
$cadernoRobots = 'noindex, follow';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="auth" data-pagina="<?= Helpers::e($paginaTema) ?>">
  <a class="pular" href="#conteudo">Pular para o conteúdo</a>
  <?php require __DIR__ . '/partials/icones.php'; ?>
  <?php require __DIR__ . '/partials/aviso-previa.php'; ?>
  <div class="folha">
    <div class="furos" aria-hidden="true"></div>
    <header class="topo auth-topo">
      <div class="miolo">
        <a class="logo" href="<?= Helpers::e($homeHref) ?>" aria-label="Desbloqueia Cursos, página inicial">
          <svg width="22" height="24" viewBox="0 0 34 36" fill="none" stroke="#FF6A00" stroke-width="3" stroke-linecap="round" aria-hidden="true"><rect x="3" y="14" width="26" height="19" rx="4" fill="#FFF4EC"/><path d="M9 14v-4a7 7 0 0 1 14 0"/><circle cx="16" cy="23" r="2.6" fill="#FF6A00" stroke="none"/></svg>
          <span>Desbloqueia Cursos</span>
        </a>
        <a class="voltar" href="<?= Helpers::e($homeHref) ?>"><?= caderno_icone('seta-esq') ?><span>Voltar ao início</span></a>
      </div>
    </header>
    <main id="conteudo" class="miolo" tabindex="-1">
      <div class="auth-folha">
        <?php require $contentView; ?>
      </div>
    </main>
    <?php require __DIR__ . '/partials/rodape.php'; ?>
  </div>

  <?php if ($tutorNorminha): ?>
  <?php require BASE_PATH . '/resources/views/components/tutor_norminha.php'; ?>
  <script src="/assets/js/tutor-norminha.js<?= $tutorNorminhaJsVersion ? '?v=' . (int) $tutorNorminhaJsVersion : '' ?>" defer></script>
  <?php endif; ?>
</body>
</html>
