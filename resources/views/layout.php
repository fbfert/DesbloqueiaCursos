<?php
use App\Core\Helpers;

$pageTitle = isset($title) ? $title : 'Polo Rainbow';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/">Polo Rainbow</a>
    </header>

    <main class="site-main">
        <?php echo $content; ?>
    </main>

    <footer class="site-footer">
        <span><?php echo Helpers::e(date('Y')); ?> Polo Rainbow.</span>
    </footer>
</body>
</html>
