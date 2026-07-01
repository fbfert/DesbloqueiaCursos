<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Nao foi possivel localizar a raiz do projeto.\n");
    exit(1);
}

$viewsDir = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin';
$routesDir = $root . DIRECTORY_SEPARATOR . 'routes';

if (!is_dir($viewsDir)) {
    fwrite(STDERR, "Diretorio de views admin nao encontrado: {$viewsDir}\n");
    exit(1);
}

$postRoutes = loadPostRoutes($routesDir);
$files = collectPhpFiles($viewsDir);

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false || stripos($content, '<form') === false) {
        continue;
    }

    $warnings = array();
    $forms = extractForms($content);

    foreach ($forms as $index => $form) {
        $warnings = array_merge($warnings, auditForm($file, $form, $postRoutes, $index + 1));
    }

    if (empty($warnings)) {
        echo '[OK] ' . relativePath($root, $file) . PHP_EOL;
        continue;
    }

    foreach ($warnings as $warning) {
        echo '[WARN] ' . relativePath($root, $file) . ' - ' . $warning . PHP_EOL;
    }
}

exit(0);

function collectPhpFiles(string $directory): array
{
    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        if (strtolower($fileInfo->getExtension()) !== 'php') {
            continue;
        }

        $files[] = $fileInfo->getPathname();
    }

    sort($files);
    return $files;
}

function extractForms(string $content): array
{
    $forms = array();
    if (!preg_match_all('/<form\b[^>]*>.*?<\/form>/is', $content, $matches)) {
        return $forms;
    }

    foreach ($matches[0] as $formHtml) {
        $forms[] = $formHtml;
    }

    return $forms;
}

function auditForm(string $file, string $formHtml, array $postRoutes, int $formNumber): array
{
    $warnings = array();
    $openingTag = '';
    if (preg_match('/<form\b[^>]*>/i', $formHtml, $match)) {
        $openingTag = $match[0];
    }

    $isPost = (bool) preg_match('/\bmethod\s*=\s*([\'"])?post\1/i', $openingTag);
    $action = '';
    if (preg_match('/\baction\s*=\s*([\'"])(.*?)\1/i', $openingTag, $match)) {
        $action = trim($match[2]);
    }

    $hasCsrf = (stripos($formHtml, 'name="_token"') !== false)
        || (stripos($formHtml, "name='_token'") !== false)
        || (stripos($formHtml, 'Csrf::field') !== false)
        || (stripos($formHtml, 'csrfField') !== false);

    $hasHiddenId = (bool) preg_match('/<input\b[^>]*type\s*=\s*([\'"])hidden\1[^>]*name\s*=\s*([\'"])id\2/i', $formHtml)
        || (bool) preg_match('/<input\b[^>]*name\s*=\s*([\'"])id\1[^>]*type\s*=\s*([\'"])hidden\2/i', $formHtml)
        || (stripos($formHtml, 'name="id"') !== false)
        || (stripos($formHtml, "name='id'") !== false);

    $hasFormAction = (bool) preg_match('/name\s*=\s*([\'"])(form_action|submit_action)\1/i', $formHtml);

    if ($isPost && !$hasCsrf) {
        $warnings[] = 'POST sem CSRF';
    }

    if ($isPost && $action === '') {
        $warnings[] = 'POST sem action explícita';
    }

    if (isEditLikeForm($file, $formHtml) && !$hasHiddenId) {
        $warnings[] = 'formulário de edição sem hidden id';
    }

    if ($isPost && !$hasFormAction) {
        $warnings[] = 'POST sem form_action ou submit_action';
    }

    if ($action !== '' && $action[0] === '/' && stripos($action, '/admin') === 0) {
        $actionPath = normalizeActionPath($action);
        if ($actionPath !== '' && !isset($postRoutes[$actionPath])) {
            $warnings[] = 'action aponta para rota POST inexistente: ' . $actionPath;
        }
    }

    if (empty($warnings)) {
        return array();
    }

    return array_map(function ($warning) use ($formNumber) {
        return 'form #' . $formNumber . ' - ' . $warning;
    }, $warnings);
}

function isEditLikeForm(string $file, string $formHtml): bool
{
    $fileLower = strtolower($file);
    if (strpos($fileLower, 'editar') !== false) {
        return true;
    }

    $content = strtolower($formHtml);
    return strpos($content, 'atualizar') !== false
        || strpos($content, 'editar') !== false
        || strpos($content, 'save_copy') !== false;
}

function normalizeActionPath(string $action): string
{
    $parts = parse_url($action);
    if (!$parts || empty($parts['path'])) {
        return '';
    }

    return $parts['path'];
}

function loadPostRoutes(string $routesDir): array
{
    $routes = array();
    if (!is_dir($routesDir)) {
        return $routes;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($routesDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
            continue;
        }

        $content = file_get_contents($fileInfo->getPathname());
        if ($content === false) {
            continue;
        }

        if (!preg_match_all('/->post(?:WithoutCsrf)?\(\s*([\'"])(\/[^\'"]+)\1/i', $content, $matches)) {
            continue;
        }

        foreach ($matches[2] as $route) {
            $routes[$route] = true;
        }
    }

    return $routes;
}

function relativePath(string $root, string $path): string
{
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $normalizedPath = str_replace('\\', '/', $path);
    if (strpos($normalizedPath, $normalizedRoot) === 0) {
        return substr($normalizedPath, strlen($normalizedRoot));
    }

    return $normalizedPath;
}
