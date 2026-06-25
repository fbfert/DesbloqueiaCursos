<?php
$dcPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$dcLoggedIn = isset($loggedIn) ? !empty($loggedIn) : (\App\Core\Session::get('usuario_id') !== null);
$dcNav = array(
    array('href' => '/',            'label' => 'Início',      'match' => array('/')),
    array('href' => '/cursos',      'label' => 'Explorar',    'match' => array('/cursos', '/categorias')),
    array('href' => $dcLoggedIn ? '/meus-cursos' : '/login', 'label' => 'Meus Cursos', 'match' => array('/meus-cursos', '/aluno/')),
    array('href' => $dcLoggedIn ? '/minha-conta' : '/login', 'label' => 'Perfil',      'match' => array('/minha-conta', '/minha-pagina', '/login')),
);

function dcNavIsActive($item, $path) {
    foreach ($item['match'] as $prefix) {
        if ($prefix === '/' ? $path === '/' : strpos($path, $prefix) === 0) {
            return true;
        }
    }
    return false;
}
?>
<nav class="dc-bottom-nav" aria-label="Navegação principal">
    <?php foreach ($dcNav as $navItem): ?>
        <?php $active = dcNavIsActive($navItem, $dcPath); ?>
        <a href="<?php echo \App\Core\Helpers::e($navItem['href']); ?>"
           class="dc-bottom-nav__item<?php echo $active ? ' dc-bottom-nav__item--active' : ''; ?>"
           <?php echo $active ? 'aria-current="page"' : ''; ?>>
            <?php if ($navItem['label'] === 'Início'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <?php elseif ($navItem['label'] === 'Explorar'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <?php elseif ($navItem['label'] === 'Meus Cursos'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <?php endif; ?>
            <span><?php echo \App\Core\Helpers::e($navItem['label']); ?></span>
        </a>
    <?php endforeach; ?>
</nav>
