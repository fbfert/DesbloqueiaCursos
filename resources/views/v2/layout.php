<?php
use App\Core\Helpers;
use App\Support\V2Nav;

$pageTitle = isset($pageTitle) && trim((string) $pageTitle) !== '' ? (string) $pageTitle : 'Desbloqueia Cursos';
$pageDescription = isset($pageDescription) ? (string) $pageDescription : '';

// Cache busting pelo mtime, mesmo padrão de resources/views/layout.php. Sem
// isso o aluno que já visitou a V2 continua com o CSS/JS antigos em cache, e
// mudanças de comportamento (como a navegação do simulado) não chegam nele.
$v2CssPath = BASE_PATH . '/v2/assets/css/v2-main.css';
$v2JsPath  = BASE_PATH . '/v2/assets/js/v2-main.js';
$v2CssVersion = is_file($v2CssPath) ? filemtime($v2CssPath) : null;
$v2JsVersion  = is_file($v2JsPath) ? filemtime($v2JsPath) : null;

// ---------------------------------------------------------------------------
// Norminha (22/08/2026)
//
// Ate aqui o componente existia SO no layout legado. Como HOME_VERSION=v2 e a
// area do aluno viva e a V2, na pratica a Norminha nao aparecia para ninguem.
// Este e o unico ponto de montagem da V2 — nao replicar em view de pagina, sob
// pena de o widget aparecer duas vezes (a guarda do smoke reprova isso).
// ---------------------------------------------------------------------------
$tutorNorminha = null;
$tutorNorminhaTtlHoras = 24;
$tutorNorminhaCssVersion = null;
$tutorNorminhaJsVersion = null;

$norminhaAssetVersion = function ($relativo) {
    foreach (array(BASE_PATH . '/' . ltrim($relativo, '/'),
                   BASE_PATH . '/public_html/' . ltrim($relativo, '/')) as $caminho) {
        if (is_file($caminho)) {
            return filemtime($caminho);
        }
    }
    return null;
};

try {
    $tutorVirtualService = new App\Services\TutorVirtualService();
    $tutorConfiguracoes = $tutorVirtualService->configuracoes();
    $tutorNorminhaTtlHoras = isset($tutorConfiguracoes['tutor_ttl_fechamento_horas'])
        ? (int) $tutorConfiguracoes['tutor_ttl_fechamento_horas'] : 24;

    $norminhaPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $tutorNorminha = $tutorVirtualService->componenteParaLayout($norminhaPath, $_GET);
} catch (\Throwable $e) {
    // A Norminha nunca pode derrubar a pagina do aluno. Falhou, nao aparece.
    $tutorNorminha = null;
    App\Core\Logger::error('norminha.layout.falha', array('message' => $e->getMessage()));
}

if ($tutorNorminha) {
    $tutorNorminhaCssVersion = $norminhaAssetVersion('assets/css/tutor-norminha.css');
    $tutorNorminhaJsVersion = $norminhaAssetVersion('assets/js/tutor-norminha.js');
}

// Pistas de contexto. O controller publica o que RESOLVEU; onde ele ainda nao
// publica, cai-se na query string, que e mais fraca de proposito. Ver
// App\Support\NorminhaHints.
if (!isset($norminhaContexto) || !is_array($norminhaContexto)) {
    $norminhaContexto = App\Support\NorminhaHints::daQuery($_GET, $norminhaPath ?? null);
}

// Fase 2.13 — navegação V2 centralizada (V2Nav). Sobrescreve quaisquer hrefs
// herdados que apontariam ao V1 (ex.: catalogo/categorias/login). `areaHref`
// (papel-dependente) e `homeHref` são preservados quando já informados.
$homeHref = isset($homeHref) && (string) $homeHref !== '' ? (string) $homeHref : V2Nav::HOME;
$areaHref = isset($areaHref) && (string) $areaHref !== '' ? (string) $areaHref : V2Nav::ALUNO;
$loggedIn = !empty($loggedIn);
$v2Nav = V2Nav::links($areaHref, $loggedIn);
$catalogoHref = $v2Nav['catalogoHref'];
$categoriasHref = $v2Nav['categoriasHref'];
$categoriesHref = $v2Nav['categoriesHref'];
$certificadosHref = $v2Nav['certificadosHref'];
$sobreHref = $v2Nav['sobreHref'];
$contatoHref = $v2Nav['contatoHref'];
$comoFuncionaHref = $v2Nav['comoFuncionaHref'];
$loginHref = $v2Nav['loginHref'];
$registerHref = $v2Nav['registerHref'];
$pedidosHref = $v2Nav['pedidosHref'];
$contaHref = $v2Nav['contaHref'];
$usuarioPrimeiroNome = isset($usuarioPrimeiroNome) ? (string) $usuarioPrimeiroNome : '';
$heroStats = isset($heroStats) && is_array($heroStats) ? $heroStats : null;
$disableV2AutoRenderHome = !empty($disableV2AutoRenderHome);
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentPath = $currentPath ?: '/';
// Auto-referencial (path + querystring atuais): evita conteúdo duplicado
// entre variações de URL, sem mapear cada página ao seu par V1.
$currentQuery = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
$canonicalUrl = Helpers::url(ltrim($currentPath, '/')) . ($currentQuery !== '' ? '?' . $currentQuery : '');
$contentView = isset($contentView) ? (string) $contentView : BASE_PATH . '/resources/views/v2/pages/home.php';
$featuredCount = isset($featuredCourses) && is_array($featuredCourses) ? count($featuredCourses) : 0;
$categoriesCount = isset($categories) && is_array($categories) ? count($categories) : 0;
$topCount = isset($topCourses) && is_array($topCourses) ? count($topCourses) : 0;
$homeNavClass = $currentPath === '/v2' || $currentPath === '/v2/' ? ' is-active' : '';
$catalogNavClass = strpos($currentPath, '/cursos') === 0 ? ' is-active' : '';
$categoriesNavClass = strpos($currentPath, '/v2/categorias') === 0 ? ' is-active' : '';
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
  <link rel="canonical" href="<?php echo Helpers::e($canonicalUrl); ?>">
  <meta name="theme-color" content="#FF6A00">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <link rel="stylesheet" href="/v2/assets/css/v2-main.css<?php echo $v2CssVersion ? "?v=" . (int) $v2CssVersion : ""; ?>">
  <link rel="stylesheet" href="/assets/css/conteudo-html-embed.css?v=20260717">
  <link rel="icon" href="/v2/assets/img/logo-v2.svg" type="image/svg+xml">
  <?php if ($tutorNorminha): ?>
  <?php require BASE_PATH . '/resources/views/components/tutor_norminha_head.php'; ?>
  <?php endif; ?>
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
  <script src="/v2/assets/js/v2-main.js<?php echo $v2JsVersion ? "?v=" . (int) $v2JsVersion : ""; ?>" defer></script>
  <script src="/assets/js/conteudo-html-embed.js?v=20260717" defer></script>
  <?php if ($tutorNorminha): ?>
  <?php require BASE_PATH . '/resources/views/components/tutor_norminha.php'; ?>
  <script src="/assets/js/tutor-norminha.js<?php echo $tutorNorminhaJsVersion ? '?v=' . (int) $tutorNorminhaJsVersion : ''; ?>" defer></script>
  <?php endif; ?>
</body>
</html>
