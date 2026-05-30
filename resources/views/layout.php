<?php
use App\Core\Helpers;
use App\Core\Session;
use App\Services\ConfiguracaoGlobalService;
use App\Services\FrontendModuloService;
use App\Services\FrontendMenuService;
use App\Services\PlaceholderService;

$appConfig = require BASE_PATH . '/config/app.php';
$pageTitle = isset($title) ? $title : (!empty($appConfig['name']) ? $appConfig['name'] : 'Desbloqueia Cursos');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = $requestPath ?: '/';
$globalConfigService = new ConfiguracaoGlobalService();
$institucional = $globalConfigService->institucional();
$frontend = $globalConfigService->frontend();
$brandName = !empty($institucional['nome_fantasia']) ? $institucional['nome_fantasia'] : (!empty($appConfig['name']) ? $appConfig['name'] : 'Desbloqueia Cursos');
$isAdmin = strpos($requestPath, '/admin') === 0;
$isProfessor = strpos($requestPath, '/professor') === 0;
$isAluno = in_array($requestPath, array('/meus-cursos', '/area-curso', '/area-curso/modulo', '/area-curso/material'), true);
$useFrontendTheme = !$isAdmin && !$isProfessor;
$shouldLoadConteudoAudio = strpos($requestPath, '/aluno/cursos/conteudo/item') === 0
    || strpos($requestPath, '/area-curso/conteudo/item') === 0
    || (strpos($requestPath, '/aluno/cursos') === 0 && !empty($_GET['aula_id']))
    || (strpos($requestPath, '/area-curso') === 0 && !empty($_GET['aula_id']));
$scopeClass = $isAdmin ? 'app-admin' : ($isProfessor ? 'app-professor' : ($isAluno ? 'app-aluno' : 'app-public'));
$publicMenu = array(
    array('label' => 'Início', 'href' => '/', 'active' => $requestPath === '/'),
    array('label' => 'Cursos', 'href' => '/cursos', 'active' => strpos($requestPath, '/cursos') === 0 || $requestPath === '/inscricao'),
    array('label' => 'Como funciona', 'href' => '/como-funciona', 'active' => $requestPath === '/como-funciona'),
    array('label' => 'Validar certificado', 'href' => '/certificados/validar', 'active' => strpos($requestPath, '/certificados/validar') === 0),
    array('label' => 'Sobre', 'href' => '/sobre', 'active' => $requestPath === '/sobre'),
    array('label' => 'Contato', 'href' => '/contato', 'active' => $requestPath === '/contato'),
);
$isAuthenticated = Session::get('usuario_id') !== null;
$minhaAreaHref = '/meus-cursos';
if ($isProfessor) {
    $minhaAreaHref = '/professor/dashboard';
}
$sessionPerfis = Session::get('usuario_perfis', array());
$hasAdminAccess = Session::get('usuario_admin') || Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
$hasProfessorAccess = Session::get('usuario_professor') || Session::get('is_professor') || in_array('professor', $sessionPerfis, true);
$preFooterModulo = null;
$preFooterMenuItems = array();
$footerModulo = null;
$footerText = '';
$headerMenu = array('menu' => null, 'itens' => $publicMenu, 'from_fallback' => true);
$brandModulo = null;

if (!$isAdmin) {
    try {
        $frontendModuloService = new FrontendModuloService();
        $frontendMenuService = new FrontendMenuService();
        $placeholderService = new PlaceholderService();

        $brandModulo = $frontendModuloService->buscarAtivoPorPosicaoOuCodigo('topo_site', 'topo_site');
        $preFooterModulo = $frontendModuloService->buscarAtivoPorPosicaoOuCodigo('antes_rodape', 'antes_rodape');
        $preFooterMenu = $frontendMenuService->buscarMenuAtivoPorPosicao('antes_rodape', 'menu_antes_rodape');
        if ($preFooterMenu) {
            $preFooterMenuItems = $frontendMenuService->listarItensAtivos((int) $preFooterMenu['id']);
        }
        $headerMenu = $frontendMenuService->menuTopoPublico($isAuthenticated);

        $footerModulo = $frontendModuloService->buscarAtivoPorPosicaoOuCodigo('rodape', 'rodape');
        if ($footerModulo && !empty($footerModulo['conteudo'])) {
            $footerText = $placeholderService->render((string) $footerModulo['conteudo']);
        }
    } catch (\Throwable $exception) {
        $brandModulo = null;
        $preFooterModulo = null;
        $preFooterMenuItems = array();
        $footerModulo = null;
        $footerText = '';
        $headerMenu = array('menu' => null, 'itens' => $publicMenu, 'from_fallback' => true);
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php if ($useFrontendTheme): ?>
        <link rel="stylesheet" href="/assets/css/frontend.css">
    <?php if ($shouldLoadConteudoAudio): ?>
        <link rel="stylesheet" href="/assets/css/conteudo-audio.css?v=20260529">
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
        <link rel="stylesheet" href="/assets/css/admin.css">
    <?php endif; ?>
    <?php if ($isAdmin || $isProfessor): ?>
        <link rel="stylesheet" href="/assets/css/conteudo-editor.css?v=20260529">
        <script src="/assets/vendor/ckeditor5/ckeditor.js?v=41.4.2" defer></script>
        <script src="/assets/vendor/ckeditor5/translations/pt-br.js?v=41.4.2" defer></script>
        <script src="/assets/js/conteudo-editor.js?v=20260529-ckeditor5" defer></script>
    <?php endif; ?>
    <?php if ($useFrontendTheme && $shouldLoadConteudoAudio): ?>
        <script src="/assets/js/conteudo-audio.js?v=20260529" defer></script>
    <?php endif; ?>
</head>
<body class="<?php echo Helpers::e($scopeClass); ?><?php echo $useFrontendTheme ? ' frontend-theme' : ''; ?> theme-<?php echo htmlspecialchars((string) (isset($frontend['template_visual_portal']) ? $frontend['template_visual_portal'] : 'padrao'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($isAdmin): ?>
        <?php require BASE_PATH . '/resources/views/admin/_shell.php'; ?>
    <?php else: ?>
        <div class="site-shell">
            <?php require BASE_PATH . '/resources/views/partials/public/header.php'; ?>

            <main class="site-main">
                <?php echo $content; ?>
            </main>

            <?php require BASE_PATH . '/resources/views/partials/public/pre_footer.php'; ?>
            <?php require BASE_PATH . '/resources/views/partials/public/footer.php'; ?>
        </div>
    <?php endif; ?>
</body>
<script>
(function () {
    var body = document.body;
    var menu = document.querySelector('[data-menu-mobile]');
    var toggle = document.querySelector('[data-menu-toggle]');
    var overlay = document.querySelector('[data-menu-overlay]');
    var closeButton = document.querySelector('[data-menu-close]');

    if (!menu || !toggle || !overlay) {
        return;
    }

    function setMenuState(isOpen) {
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
        menu.hidden = !isOpen;
        overlay.hidden = !isOpen;
        body.classList.toggle('is-menu-open', isOpen);
        if (isOpen) {
            menu.focus();
        } else {
            toggle.focus();
        }
    }

    function closeMenu() {
        setMenuState(false);
    }

    toggle.addEventListener('click', function () {
        setMenuState(toggle.getAttribute('aria-expanded') !== 'true');
    });

    overlay.addEventListener('click', closeMenu);

    if (closeButton) {
        closeButton.addEventListener('click', closeMenu);
    }

    menu.addEventListener('click', function (event) {
        var target = event.target;
        if (target && target.tagName === 'A') {
            closeMenu();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            closeMenu();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768 && toggle.getAttribute('aria-expanded') === 'true') {
            setMenuState(false);
        }
    });
})();
</script>
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
