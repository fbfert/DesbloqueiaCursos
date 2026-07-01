<?php
use App\Core\Helpers;

$usuarioPrimeiroNome = isset($usuarioPrimeiroNome) ? (string) $usuarioPrimeiroNome : '';
$loggedIn = !empty($loggedIn);
$areaHref = isset($areaHref) ? (string) $areaHref : '/meus-cursos';
$loginHref = isset($loginHref) ? (string) $loginHref : '/login';
$registerHref = isset($registerHref) ? (string) $registerHref : '/cadastro';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentPath = $currentPath ?: '/';
$homeNavClass = isset($homeNavClass) ? (string) $homeNavClass : (($currentPath === '/v2' || $currentPath === '/v2/') ? ' is-active' : '');
$catalogNavClass = isset($catalogNavClass) ? (string) $catalogNavClass : (strpos($currentPath, '/cursos') === 0 ? ' is-active' : '');
$categoriesNavClass = isset($categoriesNavClass) ? (string) $categoriesNavClass : (strpos($currentPath, '/categorias') === 0 ? ' is-active' : '');
$certNavClass = isset($certNavClass) ? (string) $certNavClass : (strpos($currentPath, '/certificados/validar') !== false ? ' is-active' : '');
?>
<nav class="v2-navbar" aria-label="Menu principal">
  <div class="v2-container v2-navbar-inner">
    <a href="<?php echo Helpers::e(isset($homeHref) ? (string) $homeHref : '/v2/'); ?>" class="v2-logo" aria-label="Página inicial Desbloqueia Cursos">
      <img src="/v2/assets/img/logo-v2.svg" alt="Desbloqueia Cursos">
    </a>
    <div class="v2-nav-links">
      <a href="<?php echo Helpers::e(isset($homeHref) ? (string) $homeHref : '/v2/'); ?>" class="v2-nav-link<?php echo Helpers::e($homeNavClass); ?>">Início</a>
      <a href="<?php echo Helpers::e(isset($catalogoHref) ? (string) $catalogoHref : '/cursos'); ?>" class="v2-nav-link<?php echo Helpers::e($catalogNavClass); ?>">Cursos</a>
      <a href="<?php echo Helpers::e(isset($categoriesHref) ? (string) $categoriesHref : '/categorias'); ?>" class="v2-nav-link<?php echo Helpers::e($categoriesNavClass); ?>">Categorias</a>
      <a href="<?php echo Helpers::e(isset($certificadosHref) ? (string) $certificadosHref : '/v2/certificados/validar'); ?>" class="v2-nav-link<?php echo Helpers::e($certNavClass); ?>">Certificados</a>
    </div>
    <div class="v2-nav-actions">
      <?php if ($loggedIn): ?>
        <a href="<?php echo Helpers::e($areaHref); ?>" class="v2-btn v2-btn-ghost v2-btn-sm">
          <span class="v2-avatar" aria-hidden="true"><?php echo Helpers::e(function_exists('mb_substr') ? mb_substr($usuarioPrimeiroNome !== '' ? $usuarioPrimeiroNome : 'A', 0, 1, 'UTF-8') : substr($usuarioPrimeiroNome !== '' ? $usuarioPrimeiroNome : 'A', 0, 1)); ?></span>
          Minha área
        </a>
      <?php else: ?>
        <a href="<?php echo Helpers::e($loginHref); ?>" class="v2-btn v2-btn-ghost v2-btn-sm">Entrar</a>
        <a href="<?php echo Helpers::e($registerHref); ?>" class="v2-btn v2-btn-primary v2-btn-sm">Cadastrar</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
