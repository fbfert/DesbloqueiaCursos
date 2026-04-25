<?php
use App\Core\Helpers;
use App\Services\ConfiguraçãoGlobalService;

$pageTitle = isset($title) ? $title : 'Polo Rainbow';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = $requestPath ?: '/';
$globalConfigService = new ConfiguraçãoGlobalService();
$institucional = $globalConfigService->institucional();
$frontend = $globalConfigService->frontend();
$brandName = !empty($institucional['nome_fantasia']) ? $institucional['nome_fantasia'] : 'Polo Rainbow';
$isAdmin = strpos($requestPath, '/admin') === 0;
$isProfessor = strpos($requestPath, '/professor') === 0;
$isAluno = in_array($requestPath, array('/meus-cursos', '/area-curso', '/area-curso/modulo', '/area-curso/material'), true);
$scopeClass = $isAdmin ? 'app-admin' : ($isProfessor ? 'app-professor' : ($isAluno ? 'app-aluno' : 'app-public'));
$publicMenu = array(
    array('label' => 'Inicio', 'href' => '/', 'active' => $requestPath === '/'),
    array('label' => 'Cursos', 'href' => '/cursos', 'active' => strpos($requestPath, '/cursos') === 0 || $requestPath === '/inscricao'),
    array('label' => 'Como funciona', 'href' => '/como-funciona', 'active' => $requestPath === '/como-funciona'),
    array('label' => 'Sobre', 'href' => '/sobre', 'active' => $requestPath === '/sobre'),
    array('label' => 'Contato', 'href' => '/contato', 'active' => $requestPath === '/contato'),
);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="<?php echo Helpers::e($scopeClass); ?> theme-<?php echo htmlspecialchars((string) (isset($frontend['template_visual_portal']) ? $frontend['template_visual_portal'] : 'padrao'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($isAdmin): ?>
        <?php require BASE_PATH . '/resources/views/admin/_shell.php'; ?>
    <?php else: ?>
        <div class="site-shell">
            <?php if ($isProfessor): ?>
                <header class="public-header">
                    <div class="site-header">
                        <a class="brand" href="/professor">Painel do professor</a>
                        <nav class="public-nav" aria-label="Menu do professor">
                            <a class="public-nav__link<?php echo $requestPath === '/professor' || $requestPath === '/professor/dashboard' ? ' is-active' : ''; ?>" href="/professor/dashboard">Inicio</a>
                            <a class="public-nav__link<?php echo strpos($requestPath, '/professor/catalogo') === 0 ? ' is-active' : ''; ?>" href="/professor/catalogo">Catálogo</a>
                            <a class="public-nav__link<?php echo strpos($requestPath, '/professor/area-curso') === 0 ? ' is-active' : ''; ?>" href="/professor/area-curso">Area do curso</a>
                            <a class="public-nav__link<?php echo strpos($requestPath, '/professor/academico') === 0 ? ' is-active' : ''; ?>" href="/professor/academico">Acadêmico</a>
                        </nav>
                    </div>
                </header>
            <?php elseif ($isAluno): ?>
                <header class="public-header">
                    <div class="site-header">
                        <a class="brand" href="/meus-cursos">Area do aluno</a>
                        <nav class="public-nav" aria-label="Menu do aluno">
                            <a class="public-nav__link<?php echo $requestPath === '/meus-cursos' ? ' is-active' : ''; ?>" href="/meus-cursos">Meus cursos</a>
                            <a class="public-nav__link<?php echo strpos($requestPath, '/area-curso') === 0 ? ' is-active' : ''; ?>" href="/area-curso">Conteudo</a>
                            <a class="public-nav__link" href="/cursos">Catálogo</a>
                        </nav>
                    </div>
                </header>
            <?php else: ?>
                <header class="public-header">
                    <div class="site-header">
                        <a class="brand" href="/"><?php echo Helpers::e($brandName); ?></a>
                        <nav class="public-nav" aria-label="Menu principal">
                            <?php foreach ($publicMenu as $item): ?>
                                <a class="public-nav__link<?php echo $item['active'] ? ' is-active' : ''; ?>" href="<?php echo Helpers::e($item['href']); ?>">
                                    <?php echo Helpers::e($item['label']); ?>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                        <div class="public-header__actions">
                            <?php if (!empty($usuarioNome)): ?>
                                <a class="button-link button-link--ghost" href="/meus-cursos">Minha area</a>
                            <?php else: ?>
                                <a class="button-link button-link--ghost" href="/login">Entrar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>
            <?php endif; ?>

            <main class="site-main">
                <?php echo $content; ?>
            </main>

            <footer class="public-footer">
                <div class="site-footer">
                    <div>
                        <strong><?php echo Helpers::e($brandName); ?></strong>
                        <p>Portal publico para cursos, turmas e inscricoes iniciais.</p>
                    </div>
                    <div class="public-footer__links">
                        <a href="/cursos">Cursos</a>
                        <a href="/como-funciona">Como funciona</a>
                        <a href="/sobre">Sobre</a>
                        <a href="/contato">Contato</a>
                    </div>
                    <span><?php echo Helpers::e(date('Y')); ?> <?php echo Helpers::e($brandName); ?>.</span>
                </div>
            </footer>
        </div>
    <?php endif; ?>
</body>
</html>

