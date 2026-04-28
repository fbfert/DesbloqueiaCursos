<?php
use App\Core\Helpers;
use App\Core\Session;
use App\Services\ConfiguracaoGlobalService;

$pageTitle = isset($title) ? $title : 'Polo Rainbow';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = $requestPath ?: '/';
$globalConfigService = new ConfiguracaoGlobalService();
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
$isAuthenticated = Session::get('usuario_id') !== null;
$minhaAreaHref = '/meus-cursos';
if ($isProfessor) {
    $minhaAreaHref = '/professor/dashboard';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php if ($isAdmin): ?>
        <link rel="stylesheet" href="/assets/css/admin.css">
    <?php endif; ?>
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
                        <?php if ($isAuthenticated): ?>
                            <div class="public-header__actions">
                                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($minhaAreaHref); ?>">Minha Área</a>
                                <a class="button-link button-link--ghost button-link--icon" href="/minha-conta" aria-label="Editar dados da conta" title="Editar dados da conta">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"></path>
                                    </svg>
                                </a>
                                <form method="post" action="/logout" class="header-logout-form">
                                    <button type="submit" class="button-link button-link--ghost button-link--icon" aria-label="Sair" title="Sair">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4v-2H6V6h4V4Z"></path>
                                            <path d="M13 8l1.41 1.41L12.83 11H20v2h-7.17l1.58 1.59L13 16l-4-4 4-4Z"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
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
                        <?php if ($isAuthenticated): ?>
                            <div class="public-header__actions">
                                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($minhaAreaHref); ?>">Minha Área</a>
                                <a class="button-link button-link--ghost button-link--icon" href="/minha-conta" aria-label="Editar dados da conta" title="Editar dados da conta">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"></path>
                                    </svg>
                                </a>
                                <form method="post" action="/logout" class="header-logout-form">
                                    <button type="submit" class="button-link button-link--ghost button-link--icon" aria-label="Sair" title="Sair">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4v-2H6V6h4V4Z"></path>
                                            <path d="M13 8l1.41 1.41L12.83 11H20v2h-7.17l1.58 1.59L13 16l-4-4 4-4Z"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
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
                            <?php if ($isAuthenticated): ?>
                                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($minhaAreaHref); ?>">Minha Área</a>
                                <a class="button-link button-link--ghost button-link--icon" href="/minha-conta" aria-label="Editar dados da conta" title="Editar dados da conta">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"></path>
                                    </svg>
                                </a>
                                <form method="post" action="/logout" class="header-logout-form">
                                    <button type="submit" class="button-link button-link--ghost button-link--icon" aria-label="Sair" title="Sair">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4v-2H6V6h4V4Z"></path>
                                            <path d="M13 8l1.41 1.41L12.83 11H20v2h-7.17l1.58 1.59L13 16l-4-4 4-4Z"></path>
                                        </svg>
                                    </button>
                                </form>
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
<?php if (!empty($oldInput) && is_array($oldInput)): ?>
<script>
(function () {
    var oldInput = <?php echo json_encode($oldInput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function parseName(name) {
        var tokens = [];
        var hasArraySuffix = /\[\]$/.test(name);
        name.replace(/\[([^\]]*)\]|([^[\]]+)/g, function (_, bracket, plain) {
            tokens.push(typeof plain === 'string' ? plain : bracket);
            return '';
        });
        return { tokens: tokens, hasArraySuffix: hasArraySuffix };
    }

    function getValueByName(source, name) {
        var parsed = parseName(name);
        var current = source;

        for (var i = 0; i < parsed.tokens.length; i++) {
            var token = parsed.tokens[i];
            if (token === '') {
                continue;
            }
            if (current == null || typeof current !== 'object' || !(token in current)) {
                return undefined;
            }
            current = current[token];
        }

        if (parsed.hasArraySuffix && !Array.isArray(current) && typeof current !== 'undefined') {
            return [current];
        }

        return current;
    }

    function stringValue(value) {
        if (value == null) {
            return '';
        }
        return String(value);
    }

    function shouldSkipField(field) {
        if (!field || !field.name) {
            return true;
        }

        if (field.getAttribute('data-skip-old-input') === '1') {
            return true;
        }

        var name = field.name.toLowerCase();
        return name === '_token' || name === 'csrf_token';
    }

    var fields = document.querySelectorAll('input[name], textarea[name], select[name]');
    for (var i = 0; i < fields.length; i++) {
        var field = fields[i];
        if (shouldSkipField(field)) {
            continue;
        }

        if (field.type === 'file') {
            continue;
        }

        var value = getValueByName(oldInput, field.name);
        if (typeof value === 'undefined') {
            continue;
        }

        if (field.tagName === 'SELECT') {
            if (field.multiple) {
                var values = Array.isArray(value) ? value.map(stringValue) : [stringValue(value)];
                for (var opt = 0; opt < field.options.length; opt++) {
                    field.options[opt].selected = values.indexOf(field.options[opt].value) >= 0;
                }
            } else {
                field.value = stringValue(value);
            }
            field.classList.add('is-restored-input');
            continue;
        }

        if (field.type === 'checkbox') {
            if (Array.isArray(value)) {
                field.checked = value.map(stringValue).indexOf(field.value) >= 0;
            } else {
                var normalized = stringValue(value).toLowerCase();
                field.checked = field.value === stringValue(value) || normalized === '1' || normalized === 'true' || normalized === 'on';
            }
            field.classList.add('is-restored-input');
            continue;
        }

        if (field.type === 'radio') {
            field.checked = field.value === stringValue(value);
            if (field.checked) {
                field.classList.add('is-restored-input');
            }
            continue;
        }

        field.value = stringValue(value);
        field.classList.add('is-restored-input');
    }
})();
</script>
<?php endif; ?>
</html>


