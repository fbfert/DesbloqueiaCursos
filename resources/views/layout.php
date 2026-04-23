<?php
use App\Core\Helpers;
use App\Services\ConfiguracaoGlobalService;

$pageTitle = isset($title) ? $title : 'Polo Rainbow';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = $requestPath ?: '/';
$globalConfigService = new ConfiguracaoGlobalService();
$institucional = $globalConfigService->institucional();
$frontend = $globalConfigService->frontend();
$brandName = !empty($institucional['nome_fantasia']) ? $institucional['nome_fantasia'] : 'Polo Rainbow';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="theme-<?php echo htmlspecialchars((string) (isset($frontend['template_visual_portal']) ? $frontend['template_visual_portal'] : 'padrao'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (strpos($requestPath, '/admin') === 0): ?>
        <?php require BASE_PATH . '/resources/views/admin/_shell.php'; ?>
    <?php else: ?>
        <header class="site-header">
            <a class="brand" href="/"><?php echo Helpers::e($brandName); ?></a>
        </header>

        <main class="site-main">
            <?php echo $content; ?>
        </main>

        <footer class="site-footer">
            <span><?php echo Helpers::e(date('Y')); ?> <?php echo Helpers::e($brandName); ?>.</span>
        </footer>
    <?php endif; ?>
</body>
</html>
