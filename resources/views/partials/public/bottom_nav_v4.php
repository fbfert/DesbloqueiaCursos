<?php
use App\Core\Helpers;

// Barra de navegação inferior do template V4 - Claude (apenas mobile).
// Recebe $requestPath e $isAuthenticated do layout base.
$autenticado = !empty($isAuthenticated);
$caminho = isset($requestPath) ? (string) $requestPath : '/';

$ativoInicio = $caminho === '/';
$ativoExplorar = strpos($caminho, '/cursos') === 0
    || strpos($caminho, '/categorias') === 0
    || $caminho === '/inscricao'
    || strpos($caminho, '/checkout') === 0;
$ativoMeusCursos = strpos($caminho, '/meus-cursos') === 0
    || strpos($caminho, '/pedidos') === 0
    || strpos($caminho, '/aluno/') === 0
    || strpos($caminho, '/area-curso') === 0;
$ativoPerfil = strpos($caminho, '/minha-conta') === 0
    || strpos($caminho, '/minha-pagina') === 0
    || strpos($caminho, '/login') === 0
    || strpos($caminho, '/cadastro') === 0;

$hrefMeusCursos = $autenticado ? '/meus-cursos' : '/login';
$hrefPerfil = $autenticado ? '/minha-conta' : '/login';

$tabs = array(
    array(
        'label' => 'Início',
        'href' => '/',
        'ativo' => $ativoInicio,
        'icone' => '<path d="M5 12H3l9-9 9 9h-2M5 12v7a1 1 0 0 0 1 1h3v-5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v5h3a1 1 0 0 0 1-1v-7"/>',
    ),
    array(
        'label' => 'Explorar',
        'href' => '/cursos',
        'ativo' => $ativoExplorar,
        'icone' => '<circle cx="10" cy="10" r="7"/><path d="m21 21-6-6"/>',
    ),
    array(
        'label' => 'Meus Cursos',
        'href' => $hrefMeusCursos,
        'ativo' => $ativoMeusCursos,
        'icone' => '<path d="M3 19V5a1 1 0 0 1 1-1h6a3 3 0 0 1 2 1 3 3 0 0 1 2-1h6a1 1 0 0 1 1 1v14"/><path d="M12 5v14M3 19h6a3 3 0 0 1 3 3 3 3 0 0 1 3-3h6"/>',
    ),
    array(
        'label' => 'Perfil',
        'href' => $hrefPerfil,
        'ativo' => $ativoPerfil,
        'icone' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
    ),
);
?>
<nav class="dc-bnav" role="navigation" aria-label="Navegação rápida">
    <?php foreach ($tabs as $tab): ?>
        <a
            class="dc-bnav-item<?php echo $tab['ativo'] ? ' on' : ''; ?>"
            href="<?php echo Helpers::e($tab['href']); ?>"
            <?php echo $tab['ativo'] ? 'aria-current="page"' : ''; ?>
        >
            <svg class="dc-bnav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <?php echo $tab['icone']; ?>
            </svg>
            <span><?php echo Helpers::e($tab['label']); ?></span>
        </a>
    <?php endforeach; ?>
</nav>
