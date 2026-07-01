<?php
use App\Core\Helpers;

$pageTitle = isset($pageTitle) && trim((string) $pageTitle) !== '' ? (string) $pageTitle : 'Desbloqueia Cursos';
$pageDescription = isset($pageDescription) ? (string) $pageDescription : '';
$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$catalogoHref = isset($catalogoHref) ? (string) $catalogoHref : '/cursos';
$categoriasHref = isset($categoriasHref) ? (string) $categoriasHref : '/categorias';
$certificadosHref = isset($certificadosHref) ? (string) $certificadosHref : '/v2/certificados/validar';
$sobreHref = isset($sobreHref) ? (string) $sobreHref : '/sobre';
$contatoHref = isset($contatoHref) ? (string) $contatoHref : '/contato';
$loginHref = isset($loginHref) ? (string) $loginHref : '/login';
$registerHref = isset($registerHref) ? (string) $registerHref : '/cadastro';
$areaHref = isset($areaHref) ? (string) $areaHref : '/meus-cursos';
$loggedIn = !empty($loggedIn);
$usuarioPrimeiroNome = isset($usuarioPrimeiroNome) ? (string) $usuarioPrimeiroNome : '';
$heroStats = isset($heroStats) && is_array($heroStats) ? $heroStats : null;
$disableV2AutoRenderHome = !empty($disableV2AutoRenderHome);
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentPath = $currentPath ?: '/';
$contentView = isset($contentView) ? (string) $contentView : BASE_PATH . '/resources/views/v2/pages/home.php';
$featuredCount = isset($featuredCourses) && is_array($featuredCourses) ? count($featuredCourses) : 0;
$categoriesCount = isset($categories) && is_array($categories) ? count($categories) : 0;
$topCount = isset($topCourses) && is_array($topCourses) ? count($topCourses) : 0;
$homeNavClass = $currentPath === '/v2' || $currentPath === '/v2/' ? ' is-active' : '';
$catalogNavClass = strpos($currentPath, '/cursos') === 0 ? ' is-active' : '';
$categoriesNavClass = strpos($currentPath, '/categorias') === 0 ? ' is-active' : '';
$certNavClass = strpos($currentPath, 'certificados/validar') !== false ? ' is-active' : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo Helpers::e($pageTitle); ?></title>
  <?php if ($pageDescription !== ''): ?>
  <meta name="description" content="<?php echo Helpers::e($pageDescription); ?>">
  <?php endif; ?>
  <meta name="theme-color" content="#FF6A00">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <link rel="stylesheet" href="/v2/assets/css/v2-main.css">
  <link rel="icon" href="/v2/assets/img/logo-v2.svg" type="image/svg+xml">
</head>
<body class="v2-app">
  <?php require BASE_PATH . '/resources/views/v2/partials/navbar.php'; ?>

  <main class="v2-main">
    <?php require $contentView; ?>
  </main>

  <?php require BASE_PATH . '/resources/views/v2/partials/footer.php'; ?>

  <?php if ($disableV2AutoRenderHome): ?>
  <script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>
  <?php endif; ?>
  <script src="/v2/assets/js/v2-main.js" defer></script>
</body>
</html>
