<?php
use App\Core\Helpers;
use App\Core\Session;
use App\Services\ConfiguracaoGlobalService;
use App\Services\FrontendModuloService;
use App\Services\FrontendMenuService;
use App\Services\PlaceholderService;
use App\Services\TutorVirtualService;

$appConfig = require BASE_PATH . '/config/app.php';
$pageTitle = isset($title) ? $title : (!empty($appConfig['name']) ? $appConfig['name'] : 'Desbloqueia Cursos');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = $requestPath ?: '/';
$globalConfigService = new ConfiguracaoGlobalService();
$institucional = $globalConfigService->institucional();
$faviconPublico = $globalConfigService->faviconPublico();
$frontend = $globalConfigService->frontend();
$frontendTemplate = $globalConfigService->templateVisualPortal();
$frontendCardGap = $globalConfigService->frontendCardGap();
$frontendSectionGap = $globalConfigService->frontendSectionGap();
// Versao de asset por filemtime, para o navegador buscar o arquivo novo depois
// de um deploy.
//
// Ate 22/08/2026 todos estes caminhos apontavam so para BASE_PATH/public_html/assets/.
// Acontece que apenas v4-claude.css e dc-main.* moram la; frontend.css,
// home-v3.css, orientacao-usuario.css e os arquivos da Norminha estao em
// /assets. Para esses, is_file() falhava calado, o parametro &f= nunca era
// emitido, e a unica chave de cache era a constante literal ?v=20260610-4 —
// que so muda quando alguem lembra de edita-la a mao. Na pratica: CSS e JS
// antigos servidos de cache depois do deploy.
//
// A funcao tenta os dois locais e devolve o filemtime do que existir.
$assetVersion = function ($relativo) {
    foreach (array(BASE_PATH . '/' . ltrim($relativo, '/'),
                   BASE_PATH . '/public_html/' . ltrim($relativo, '/')) as $caminho) {
        if (is_file($caminho)) {
            return filemtime($caminho);
        }
    }
    return null;
};

$frontendCssVersion = $assetVersion('assets/css/frontend.css');
$frontendV2CssVersion = $assetVersion('assets/css/frontend-v2.css');
$homeV3CssVersion = $assetVersion('assets/css/home-v3.css');
$orientacaoUsuarioCssVersion = $assetVersion('assets/css/orientacao-usuario.css');
$tutorNorminhaCssVersion = $assetVersion('assets/css/tutor-norminha.css');
$tutorNorminhaJsVersion = $assetVersion('assets/js/tutor-norminha.js');
$v4ClaudeCssVersion = $assetVersion('assets/css/v4-claude.css');
$v4ClaudeJsVersion = $assetVersion('assets/js/v4-claude.js');
$brandName = !empty($institucional['nome_fantasia']) ? $institucional['nome_fantasia'] : (!empty($appConfig['name']) ? $appConfig['name'] : 'Desbloqueia Cursos');
$isAdmin = strpos($requestPath, '/admin') === 0;
$isProfessor = strpos($requestPath, '/professor') === 0;
$isRevisor = strpos($requestPath, '/revisor') === 0;
$isAluno = in_array($requestPath, array('/meus-cursos', '/area-curso', '/area-curso/modulo', '/area-curso/material'), true)
    || strpos($requestPath, '/aluno/curso') === 0
    || strpos($requestPath, '/aluno/cursos') === 0;
$isPublicHome = !$isAdmin && !$isProfessor && !$isAluno && $requestPath === '/';
$useFrontendTheme = !$isAdmin && !$isProfessor;
$isV4Theme = $useFrontendTheme && $frontendTemplate === 'v4-claude';
$frontendTemplateAllowedRoutes = array('/login', '/cadastro', '/recuperar-senha', '/inscricao', '/checkout', '/logout');
$frontendTemplateIsPublic = !$isAdmin && !$isProfessor && !$isAluno;
$frontendTemplateIsAllowed = $frontendTemplateIsPublic;
foreach ($frontendTemplateAllowedRoutes as $routePrefix) {
    if ($requestPath === $routePrefix || strpos($requestPath, rtrim($routePrefix, '/') . '/') === 0) {
        $frontendTemplateIsAllowed = false;
        break;
    }
}
if ($frontendTemplateIsAllowed && $frontendTemplate === 'v4-claude') {
    $frontendTemplateVersion = 'v4-claude';
} elseif ($frontendTemplateIsAllowed && $frontendTemplate === 'v2') {
    $frontendTemplateVersion = 'v2';
} else {
    $frontendTemplateVersion = 'v1';
}
// (as versoes de v4-claude ja foram resolvidas por $assetVersion acima; este
// bloco duplicado, que so olhava public_html/assets, foi removido em 22/08/2026)
$shouldLoadConteudoAudio = strpos($requestPath, '/aluno/cursos/conteudo/item') === 0
    || strpos($requestPath, '/area-curso/conteudo/item') === 0
    || strpos($requestPath, '/aluno/curso/') === 0
    || (strpos($requestPath, '/aluno/cursos') === 0 && !empty($_GET['aula_id']))
    || (strpos($requestPath, '/area-curso') === 0 && !empty($_GET['aula_id']));
$hidePublicChrome = !empty($hide_public_chrome) || !empty($hidePublicChrome);
$hidePreFooterMenu = !empty($hide_pre_footer_menu) || !empty($hidePreFooterMenu);
$scopeClass = $isAdmin ? 'app-admin' : ($isRevisor ? 'app-admin app-revisor' : ($isProfessor ? 'app-professor' : ($isAluno ? 'app-aluno' : 'app-public')));
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
$tutorNorminha = null;
$tutorNorminhaTtlHoras = 24;
$loadOrientationUiAssets = $useFrontendTheme && !$isAdmin && !$isProfessor && !$isAluno && (
    $requestPath === '/'
    || strpos($requestPath, '/cursos') === 0
    || strpos($requestPath, '/cadastro') === 0
    || strpos($requestPath, '/login') === 0
    || strpos($requestPath, '/recuperar-senha') === 0
    || strpos($requestPath, '/esqueci-minha-senha') === 0
    || strpos($requestPath, '/inscricao') === 0
    || strpos($requestPath, '/checkout') === 0
);

if (!$isAdmin) {
    try {
        $frontendModuloService = new FrontendModuloService();
        $frontendMenuService = new FrontendMenuService();
        $placeholderService = new PlaceholderService();
        $tutorVirtualService = new TutorVirtualService();

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

        $tutorConfiguracoes = $tutorVirtualService->configuracoes();
        $tutorNorminhaTtlHoras = isset($tutorConfiguracoes['tutor_ttl_fechamento_horas']) ? (int) $tutorConfiguracoes['tutor_ttl_fechamento_horas'] : 24;

        $tutorNorminha = $tutorVirtualService->componenteParaLayout($requestPath, $_GET);
    } catch (\Throwable $exception) {
        $brandModulo = null;
        $preFooterModulo = null;
        $preFooterMenuItems = array();
        $footerModulo = null;
        $footerText = '';
        $headerMenu = array('menu' => null, 'itens' => $publicMenu, 'from_fallback' => true);
        $tutorNorminha = null;
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if (!empty($faviconPublico['href'])): ?>
        <link rel="icon" href="<?php echo Helpers::e($faviconPublico['href']); ?>" type="<?php echo Helpers::e($faviconPublico['mime']); ?>" sizes="any">
        <link rel="shortcut icon" href="<?php echo Helpers::e($faviconPublico['href']); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php if ($useFrontendTheme): ?>
        <link rel="stylesheet" href="/assets/css/frontend.css<?php echo $frontendCssVersion ? '?v=' . (int) $frontendCssVersion : ''; ?>">
        <?php if ($frontendTemplateVersion === 'v2'): ?>
            <link rel="stylesheet" href="/assets/css/frontend-v2.css<?php echo $frontendV2CssVersion ? '?v=' . (int) $frontendV2CssVersion : ''; ?>">
        <?php endif; ?>
        <?php if ($loadOrientationUiAssets): ?>
            <link rel="stylesheet" href="/assets/css/orientacao-usuario.css<?php echo $orientacaoUsuarioCssVersion ? '?v=' . (int) $orientacaoUsuarioCssVersion : ''; ?>">
        <?php endif; ?>
        <?php /* CSS e script de preferencia so quando o componente vai existir:
                 antes eles eram servidos em toda pagina de tema frontend,
                 inclusive onde a Norminha nao aparece. */ ?>
        <?php if (!empty($tutorNorminha)): ?>
            <?php require BASE_PATH . '/resources/views/components/tutor_norminha_head.php'; ?>
        <?php endif; ?>
        <?php if ($isPublicHome && $frontendTemplate === 'v3'): ?>
            <link rel="stylesheet" href="/assets/css/home-v3.css<?php echo $homeV3CssVersion ? '?v=' . (int) $homeV3CssVersion : ''; ?>">
        <?php endif; ?>
        <?php if ($isV4Theme): ?>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="/public_html/assets/css/v4-claude.css<?php echo $v4ClaudeCssVersion ? '?v=' . (int) $v4ClaudeCssVersion : ''; ?>">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
            <link rel="stylesheet" href="/public_html/assets/css/dc-main.css?v=<?php echo $v4ClaudeCssVersion ?? time(); ?>">
        <?php endif; ?>
    <?php if ($shouldLoadConteudoAudio): ?>
        <link rel="stylesheet" href="/assets/css/conteudo-audio.css?v=20260529">
        <link rel="stylesheet" href="/assets/css/conteudo-html-embed.css?v=20260717">
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($isAdmin || $isRevisor): ?>
        <link rel="stylesheet" href="/assets/css/admin.css">
    <?php endif; ?>
    <?php if ($isRevisor): ?>
        <link rel="stylesheet" href="/assets/css/conteudo-html-embed.css?v=20260717">
        <script src="/assets/js/conteudo-html-embed.js?v=20260717" defer></script>
    <?php endif; ?>
    <?php if ($isAdmin || $isProfessor): ?>
        <link rel="stylesheet" href="/assets/css/conteudo-editor.css?v=20260529">
        <link rel="stylesheet" href="/assets/css/email-modelo-editor.css?v=20260707">
        <script src="/assets/vendor/ckeditor5/ckeditor.js?v=41.4.2" defer></script>
        <script src="/assets/vendor/ckeditor5/translations/pt-br.js?v=41.4.2" defer></script>
        <script src="/assets/js/conteudo-editor.js?v=20260529-ckeditor5" defer></script>
        <script src="/assets/js/email-modelo-editor.js?v=20260707" defer></script>
        <script src="/assets/js/email-doc-editor.js?v=20260707b" defer></script>
    <?php endif; ?>
    <?php if ($useFrontendTheme && $shouldLoadConteudoAudio): ?>
        <script src="/assets/js/conteudo-audio.js?v=20260529" defer></script>
        <script src="/assets/js/conteudo-html-embed.js?v=20260717" defer></script>
    <?php endif; ?>
    <?php if ($useFrontendTheme && !empty($tutorNorminha)): ?>
        <script src="/assets/js/tutor-norminha.js<?php echo $tutorNorminhaJsVersion ? '?v=' . (int) $tutorNorminhaJsVersion : ''; ?>" defer></script>
    <?php endif; ?>
    <?php if ($isV4Theme): ?>
        <script src="/public_html/assets/js/v4-claude.js<?php echo $v4ClaudeJsVersion ? '?v=' . (int) $v4ClaudeJsVersion : ''; ?>" defer></script>
    <?php endif; ?>
    <?php if ($useFrontendTheme && $scopeClass === 'app-public'): ?>
        <style>
            <?php
            // A variável global do frontend público vem da configuração administrativa.
            // O valor é sanitizado no backend e recebe fallback seguro caso esteja ausente.
            ?>
            .app-public.frontend-theme {
                --frontend-card-gap: <?php echo htmlspecialchars($frontendCardGap, ENT_QUOTES, 'UTF-8'); ?>;
                --frontend-section-gap: <?php echo htmlspecialchars($frontendSectionGap, ENT_QUOTES, 'UTF-8'); ?>;
            }
        </style>
    <?php endif; ?>
</head>
<body class="<?php echo Helpers::e($scopeClass); ?><?php echo $useFrontendTheme ? ' frontend-theme frontend-template-' . Helpers::e($frontendTemplateVersion) : ''; ?> theme-<?php echo Helpers::e($frontendTemplateVersion); ?><?php echo $isV4Theme ? ' theme-v4-claude dc-v4' : ''; ?>">
    <?php if ($isAdmin): ?>
        <?php require BASE_PATH . '/resources/views/admin/_shell.php'; ?>
    <?php elseif ($isRevisor): ?>
        <?php require BASE_PATH . '/resources/views/revisor/_shell.php'; ?>
    <?php else: ?>
        <div class="site-shell">
            <?php if ($isV4Theme): ?>
                <?php // ===== Chrome V4 (navbar desktop + footer + bottom nav mobile) ===== ?>
                <?php
                    $v4UsuarioNome = (string) (Session::get('usuario_nome') ?? '');
                    $v4Inicial = $v4UsuarioNome !== '' ? mb_strtoupper(mb_substr($v4UsuarioNome, 0, 1)) : 'U';
                    $v4AtivoCursos = strpos($requestPath, '/cursos') === 0;
                    $v4AtivoCategorias = strpos($requestPath, '/categorias') === 0;
                    $v4AtivoComo = $requestPath === '/como-funciona';
                ?>
                <?php if (!$hidePublicChrome): ?>
                <nav class="dc-navbar" role="navigation" aria-label="Menu principal">
                    <div class="dc-container dc-navbar-inner">
                        <a href="/" class="dc-logo" aria-label="<?php echo Helpers::e($brandName); ?> — início">
                            <span class="dc-logo-fallback">
                                <span class="dc-logo-dot"><i class="ti ti-lock-open"></i></span>
                                <?php echo Helpers::e($brandName); ?>
                            </span>
                        </a>
                        <div class="dc-navbar-links">
                            <a href="/cursos" class="dc-navbar-link<?php echo $v4AtivoCursos ? ' dc-nav-active' : ''; ?>">Cursos</a>
                            <a href="/categorias" class="dc-navbar-link<?php echo $v4AtivoCategorias ? ' dc-nav-active' : ''; ?>">Categorias</a>
                            <a href="/como-funciona" class="dc-navbar-link<?php echo $v4AtivoComo ? ' dc-nav-active' : ''; ?>">Como funciona</a>
                        </div>
                        <div class="dc-navbar-actions">
                            <?php if ($isAuthenticated): ?>
                                <a href="/meus-cursos" class="dc-btn dc-btn-ghost dc-btn-sm"><i class="ti ti-book"></i> Minha área</a>
                                <a href="/minha-conta" class="dc-avatar" aria-label="Perfil"><?php echo Helpers::e($v4Inicial); ?></a>
                            <?php else: ?>
                                <a href="/login" class="dc-btn dc-btn-ghost dc-btn-sm">Entrar</a>
                                <a href="/cadastro" class="dc-btn dc-btn-primary dc-btn-sm">Cadastrar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </nav>
                <?php endif; ?>

                <main class="dc-main" id="dc-main">
                    <?php echo $content; ?>
                </main>

                <?php if (!$hidePublicChrome): ?>
                <footer class="dc-footer">
                    <div class="dc-container">
                        <div class="dc-footer-grid">
                            <div class="dc-footer-brand">
                                <span class="dc-footer-logo"><?php echo Helpers::e($brandName); ?></span>
                                <p>Quando aprende de verdade, desbloqueia.</p>
                            </div>
                            <div class="dc-footer-links">
                                <a href="/cursos">Cursos</a>
                                <a href="/como-funciona">Como funciona</a>
                                <a href="/sobre">Sobre</a>
                                <a href="/contato">Contato</a>
                                <a href="/certificados/validar">Validar certificado</a>
                            </div>
                        </div>
                        <div class="dc-footer-bottom">
                            <p>&copy; <?php echo date('Y'); ?> <?php echo Helpers::e($brandName); ?><?php echo !empty($institucional['razao_social']) ? ' — ' . Helpers::e($institucional['razao_social']) : ''; ?><?php echo !empty($institucional['cnpj']) ? ' — CNPJ ' . Helpers::e($institucional['cnpj']) : ''; ?></p>
                        </div>
                    </div>
                </footer>
                <?php endif; ?>

                <?php if ($useFrontendTheme && !empty($tutorNorminha)): ?>
                    <?php require BASE_PATH . '/resources/views/components/tutor_norminha.php'; ?>
                <?php endif; ?>
                <?php if (!$hidePublicChrome): ?>
                    <?php require BASE_PATH . '/resources/views/partials/public/bottom_nav_v4.php'; ?>
                <?php endif; ?>
            <?php else: ?>
                <?php if (!$hidePublicChrome): ?>
                    <?php require BASE_PATH . '/resources/views/partials/public/header.php'; ?>
                <?php endif; ?>

                <main class="site-main">
                    <?php echo $content; ?>
                </main>

                <?php if (!$hidePublicChrome && !$hidePreFooterMenu): ?>
                    <?php require BASE_PATH . '/resources/views/partials/public/pre_footer.php'; ?>
                <?php endif; ?>
                <?php if (!$hidePublicChrome): ?>
                    <?php require BASE_PATH . '/resources/views/partials/public/footer.php'; ?>
                <?php endif; ?>
                <?php if ($useFrontendTheme && !empty($tutorNorminha)): ?>
                    <?php require BASE_PATH . '/resources/views/components/tutor_norminha.php'; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($isV4Theme): ?>
        <script src="/public_html/assets/js/dc-main.js?v=<?php echo $v4ClaudeJsVersion ?? time(); ?>"></script>
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
