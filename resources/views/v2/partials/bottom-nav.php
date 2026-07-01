<?php
use App\Core\Helpers;

$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$catalogoHref = isset($catalogoHref) ? (string) $catalogoHref : '/cursos';
$categoriesHref = isset($categoriesHref) ? (string) $categoriesHref : '/categorias';
$areaHref = isset($areaHref) ? (string) $areaHref : '/meus-cursos';
$loginHref = isset($loginHref) ? (string) $loginHref : '/login';
$loggedIn = !empty($loggedIn);
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentPath = $currentPath ?: '/';
$homeActive = ($currentPath === '/v2' || $currentPath === '/v2/') ? ' is-active' : '';
$catalogActive = strpos($currentPath, '/cursos') === 0 ? ' is-active' : '';
$categoryActive = strpos($currentPath, '/categorias') === 0 ? ' is-active' : '';
$areaActive = (!$loggedIn && strpos($currentPath, '/login') === 0) || ($loggedIn && (strpos($currentPath, '/meus-cursos') === 0 || strpos($currentPath, '/minha-conta') === 0 || strpos($currentPath, '/admin') === 0 || strpos($currentPath, '/professor') === 0)) ? ' is-active' : '';
$areaHrefFinal = $loggedIn ? $areaHref : $loginHref;
$areaLabel = $loggedIn ? 'Área' : 'Entrar';
$areaIcon = $loggedIn ? 'ti-user-circle' : 'ti-login';
?>
<nav class="v2-bnav" aria-label="Navegação rápida">
  <a href="<?php echo Helpers::e($homeHref); ?>" class="v2-bnav-item<?php echo Helpers::e($homeActive); ?>"><i class="ti ti-home"></i><span>Início</span></a>
  <a href="<?php echo Helpers::e($catalogoHref); ?>" class="v2-bnav-item<?php echo Helpers::e($catalogActive); ?>"><i class="ti ti-compass"></i><span>Cursos</span></a>
  <a href="<?php echo Helpers::e($categoriesHref); ?>" class="v2-bnav-item<?php echo Helpers::e($categoryActive); ?>"><i class="ti ti-category"></i><span>Categorias</span></a>
  <a href="<?php echo Helpers::e($areaHrefFinal); ?>" class="v2-bnav-item<?php echo Helpers::e($areaActive); ?>"><i class="ti <?php echo Helpers::e($areaIcon); ?>"></i><span><?php echo Helpers::e($areaLabel); ?></span></a>
</nav>
