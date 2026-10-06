<?php
/**
 * Preparação comum aos dois layouts do tema caderno: título, descrição,
 * canonical, versões dos assets (filemtime), navegação (V2Nav) e a montagem
 * da Norminha. Lê as mesmas variáveis que resources/views/v2/layout.php.
 */

use App\Core\Helpers;
use App\Support\V2Nav;

$pageTitle = isset($pageTitle) && trim((string) $pageTitle) !== '' ? (string) $pageTitle : 'Desbloqueia Cursos';
$pageDescription = isset($pageDescription) ? (string) $pageDescription : '';
$paginaTema = isset($paginaTema) ? (string) $paginaTema : '';

// Cache busting pelo mtime, no padrão do layout V2. caderno.js só entra na
// página se existir (o núcleo de movimento é opcional: sem ele, tudo aparece).
$cadernoCssPath = BASE_PATH . '/assets/caderno/caderno.css';
$cadernoJsPath = BASE_PATH . '/assets/caderno/caderno.js';
$cadernoCssVersion = is_file($cadernoCssPath) ? (int) filemtime($cadernoCssPath) : 0;
$cadernoJsVersion = is_file($cadernoJsPath) ? (int) filemtime($cadernoJsPath) : 0;

// Norminha: a mesma montagem dos layouts V2 (ponto único; nunca na view da página).
require BASE_PATH . '/resources/views/v2/partials/norminha_montagem.php';

// Navegação centralizada (V2Nav), como no layout V2: `areaHref` (depende do
// papel) e `homeHref` são preservados quando já informados pelo controller.
$homeHref = isset($homeHref) && (string) $homeHref !== '' ? (string) $homeHref : V2Nav::HOME;
$areaHref = isset($areaHref) && (string) $areaHref !== '' ? (string) $areaHref : V2Nav::ALUNO;
$loggedIn = !empty($loggedIn);
$cadernoNav = V2Nav::links($areaHref, $loggedIn);
$catalogoHref = $cadernoNav['catalogoHref'];
$categoriasHref = $cadernoNav['categoriasHref'];
$categoriesHref = $cadernoNav['categoriesHref'];
$certificadosHref = $cadernoNav['certificadosHref'];
$loginHref = $cadernoNav['loginHref'];
$registerHref = $cadernoNav['registerHref'];
$sobreHref = $cadernoNav['sobreHref'];
$contatoHref = $cadernoNav['contatoHref'];
$comoFuncionaHref = $cadernoNav['comoFuncionaHref'];
$quemSomosHref = $cadernoNav['quemSomosHref'];
$comoFuncionaSalaHref = $cadernoNav['comoFuncionaSalaHref'];
$ondeEstamosHref = $cadernoNav['ondeEstamosHref'];
$termosHref = $cadernoNav['termosHref'];
$privacidadeHref = $cadernoNav['privacidadeHref'];
$removaMeHref = $cadernoNav['removaMeHref'];
$pedidosHref = $cadernoNav['pedidosHref'];
$contaHref = $cadernoNav['contaHref'];
$usuarioPrimeiroNome = isset($usuarioPrimeiroNome) ? (string) $usuarioPrimeiroNome : '';

// Canonical auto-referencial (caminho + query atuais), como na V2.
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentPath = $currentPath ?: '/';
$currentQuery = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
$canonicalUrl = Helpers::url(ltrim($currentPath, '/')) . ($currentQuery !== '' ? '?' . $currentQuery : '');

// Item de navegação marcado como atual, a partir da página do tema.
$cadernoNavPorPagina = array(
    'home' => 'inicio',
    'catalogo' => 'cursos',
    'curso' => 'cursos',
    'categorias' => 'categorias',
    'certificados-validar' => 'certificado',
    'login' => 'entrar',
    'cadastro' => 'entrar',
    'recuperar-senha' => 'entrar',
    'recuperar-senha-redefinir' => 'entrar',
);
$navAtual = isset($cadernoNavPorPagina[$paginaTema]) ? $cadernoNavPorPagina[$paginaTema] : '';
