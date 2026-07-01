<?php
use App\Core\Helpers;

// Layout mínimo para telas de autenticação V2 (sem navbar pública, footer
// público ou bottom navigation, conforme padrão já adotado na V2).
$pageTitle = isset($pageTitle) && trim((string) $pageTitle) !== '' ? (string) $pageTitle : 'Desbloqueia Cursos';
$pageDescription = isset($pageDescription) ? (string) $pageDescription : '';
$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$contentView = isset($contentView) ? (string) $contentView : BASE_PATH . '/resources/views/v2/pages/login.php';
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
  <meta name="robots" content="noindex, follow">
  <meta name="theme-color" content="#FF6A00">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <link rel="stylesheet" href="/v2/assets/css/v2-main.css">
  <link rel="icon" href="/v2/assets/img/logo-v2.svg" type="image/svg+xml">
</head>
<body class="v2-app">
  <script>
    /* Páginas de auth não usam renderizações demonstrativas da V2. */
    window.V2_DISABLE_AUTORENDER_HOME = true;
    window.V2_DISABLE_AUTORENDER_CATALOGO = true;
    window.V2_DISABLE_AUTORENDER_CURSO = true;
  </script>

  <header class="v2-auth-header">
    <div class="v2-container v2-auth-header-inner">
      <a href="<?php echo Helpers::e($homeHref); ?>" class="v2-logo" aria-label="Página inicial Desbloqueia Cursos">
        <img src="/v2/assets/img/logo-v2.svg" alt="Desbloqueia Cursos">
      </a>
      <a href="<?php echo Helpers::e($homeHref); ?>" class="v2-btn v2-btn-ghost v2-btn-sm"><i class="ti ti-arrow-left"></i> Voltar ao início</a>
    </div>
  </header>

  <main class="v2-main v2-authpage">
    <div class="v2-container">
      <?php require $contentView; ?>
    </div>
  </main>

  <script src="/v2/assets/js/v2-main.js" defer></script>
</body>
</html>
