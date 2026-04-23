<?php
use App\Core\Helpers;
use App\Core\Session;

$adminPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH);
$adminPath = $adminPath ?: '/admin';
$userName = Session::get('usuario_nome', 'Usuario');
$menu = array(
    array('group' => 'Painel', 'items' => array(
        array('label' => 'Dashboard', 'href' => '/admin/dashboard', 'icon' => '◼'),
        array('label' => 'Catalogo', 'href' => '/admin/catalogo', 'icon' => '▣'),
    )),
    array('group' => 'Conteudo', 'items' => array(
        array('label' => 'Categorias', 'href' => '/admin/categorias', 'icon' => '◦'),
        array('label' => 'Cursos', 'href' => '/admin/cursos', 'icon' => '◧'),
        array('label' => 'Turmas', 'href' => '/admin/turmas', 'icon' => '◨'),
        array('label' => 'Area do curso', 'href' => '/admin/area-curso', 'icon' => '▤'),
    )),
    array('group' => 'Operacao', 'items' => array(
        array('label' => 'Pedidos', 'href' => '/admin/pedidos', 'icon' => '⟡'),
        array('label' => 'Inscricoes', 'href' => '/admin/inscricoes', 'icon' => '⟢'),
        array('label' => 'Comprovantes PIX', 'href' => '/admin/comprovantes-pix', 'icon' => '◉'),
        array('label' => 'Cupons', 'href' => '/admin/cupons', 'icon' => '⌘'),
        array('label' => 'Certificados', 'href' => '/admin/certificados', 'icon' => '⬚'),
    )),
    array('group' => 'Academico', 'items' => array(
        array('label' => 'Acadêmico', 'href' => '/admin/academico', 'icon' => '✦'),
    )),
    array('group' => 'Financeiro', 'items' => array(
        array('label' => 'Financeiro', 'href' => '/admin/financeiro', 'icon' => '₪'),
        array('label' => 'Repasses', 'href' => '/admin/financeiro/repasses', 'icon' => '↻'),
        array('label' => 'Professores fiscais', 'href' => '/admin/professores-fiscais', 'icon' => '⧉'),
        array('label' => 'Rateios', 'href' => '/admin/rateios', 'icon' => '≋'),
    )),
    array('group' => 'Configuracoes', 'items' => array(
        array('label' => 'Globais', 'href' => '/admin/configuracoes-globais', 'icon' => '⚙'),
        array('label' => 'E-mails', 'href' => '/admin/emails', 'icon' => '✉'),
        array('label' => 'RBAC', 'href' => '/admin/rbac', 'icon' => '☰'),
    )),
);

$breadcrumbs = array(
    array('label' => 'Admin', 'href' => '/admin'),
);

$segments = array_values(array_filter(explode('/', trim($adminPath, '/'))));
if (!empty($segments[1])) {
    $breadcrumbs[] = array('label' => ucfirst(str_replace('-', ' ', $segments[1])), 'href' => '/' . $segments[0] . '/' . $segments[1]);
}
if (!empty($title)) {
    $breadcrumbs[] = array('label' => $title, 'href' => null);
}
?>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-sidebar__brand">
            <a class="brand" href="/admin">Polo Rainbow</a>
            <p><?php echo Helpers::e($userName); ?></p>
        </div>

        <?php foreach ($menu as $group): ?>
            <section class="admin-nav-group">
                <h2><?php echo Helpers::e($group['group']); ?></h2>
                <nav class="admin-nav">
                    <?php foreach ($group['items'] as $item): ?>
                        <?php $active = strpos($adminPath, $item['href']) === 0; ?>
                        <a class="admin-nav__link<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo Helpers::e($item['href']); ?>">
                            <span class="admin-nav__icon"><?php echo Helpers::e($item['icon']); ?></span>
                            <span><?php echo Helpers::e($item['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </section>
        <?php endforeach; ?>
    </aside>

    <div class="admin-shell__main">
        <header class="admin-topbar">
            <div>
                <strong><?php echo Helpers::e($brandName); ?></strong>
                <div class="breadcrumbs">
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <?php if ($index > 0): ?><span>/</span><?php endif; ?>
                        <?php if (!empty($crumb['href']) && $index < count($breadcrumbs) - 1): ?>
                            <a href="<?php echo Helpers::e($crumb['href']); ?>"><?php echo Helpers::e($crumb['label']); ?></a>
                        <?php else: ?>
                            <span><?php echo Helpers::e($crumb['label']); ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="admin-topbar__actions">
                <a class="button-link button-link--ghost" href="/">Portal</a>
                <form method="post" action="/logout">
                    <button type="submit">Sair</button>
                </form>
            </div>
        </header>

        <main class="site-main site-main--admin">
            <?php echo $content; ?>
        </main>
    </div>
</div>
