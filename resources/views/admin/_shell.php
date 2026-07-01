<?php
use App\Core\Helpers;
use App\Core\Session;
use App\Services\RbacService;

$adminPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH);
$adminPath = $adminPath ?: '/admin';
$userName = Session::get('usuario_nome', 'Usuário');
$usuarioId = Session::get('usuario_id');
$rbacService = new RbacService();
$moduleTitle = !empty($title) ? (string) $title : 'Painel administrativo';
$menu = array(
    array('group' => 'Painel', 'items' => array(
        array('label' => 'Dashboard', 'href' => '/admin/dashboard', 'icon' => '◼'),
        array('label' => 'Catálogo', 'href' => '/admin/catalogo', 'icon' => '▣', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Páginas', 'href' => '/admin/paginas', 'icon' => '▤', 'permissions_any' => array('conteudo.ver')),
    )),
    array('group' => 'Conteudo', 'items' => array(
        array('label' => 'Categorias', 'href' => '/admin/categorias', 'icon' => '◦', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Cursos', 'href' => '/admin/cursos', 'icon' => '◧', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Turmas', 'href' => '/admin/turmas', 'icon' => '◨', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Área do curso', 'href' => '/admin/area-curso', 'icon' => '▤', 'permissions_any' => array('area_curso.gerenciar')),
    )),
    array('group' => 'Operação', 'items' => array(
        array('label' => 'Pedidos', 'href' => '/admin/pedidos', 'icon' => '⟡', 'permissions_any' => array('pedidos.ver')),
        array('label' => 'Inscrições', 'href' => '/admin/inscricoes', 'icon' => '⟢', 'permissions_any' => array('pedidos.ver')),
        array('label' => 'Comprovantes PIX', 'href' => '/admin/comprovantes-pix', 'icon' => '◉', 'permissions_any' => array('pedidos.ver')),
        array('label' => 'Avisos', 'href' => '/admin/avisos', 'icon' => '✦', 'permissions_any' => array('avisos.visualizar')),
        array('label' => 'Cupons', 'href' => '/admin/cupons', 'icon' => '⌘', 'permissions_any' => array('cupons.ver')),
        array('label' => 'Certificados', 'href' => '/admin/certificados', 'icon' => '⬚', 'permissions_any' => array('certificados.ver')),
        array('label' => 'Templates de certificados', 'href' => '/admin/certificados/templates', 'icon' => '✎', 'permissions_any' => array('certificados.ver')),
    )),
    array('group' => 'Promocionais', 'items' => array(
        array('label' => 'Presentes', 'href' => '/admin/promocionais/presentes', 'icon' => '🎁', 'permissions_any' => array('promocionais.presentes.ver')),
    )),
    array('group' => 'Acadêmico', 'items' => array(
        array('label' => 'Acadêmico', 'href' => '/admin/academico', 'icon' => '✦', 'permissions_any' => array('academico.ver')),
    )),
    array('group' => 'Financeiro', 'items' => array(
        array('label' => 'Financeiro', 'href' => '/admin/financeiro', 'icon' => '₪', 'permissions_any' => array('financeiro.ver')),
        array('label' => 'Repasses', 'href' => '/admin/financeiro/repasses', 'icon' => '↻', 'permissions_any' => array('financeiro.ver')),
        array('label' => 'Professores fiscais', 'href' => '/admin/professores-fiscais', 'icon' => '⧉', 'permissions_any' => array('financeiro.ver')),
        array('label' => 'Rateios', 'href' => '/admin/rateios', 'icon' => '≋', 'permissions_any' => array('financeiro.ver')),
    )),
    array('group' => 'Configurações', 'items' => array(
        array('label' => 'Frontend · Módulos', 'href' => '/admin/frontend/modulos', 'icon' => '▦', 'permissions_any' => array('frontend.modulos.ver')),
        array('label' => 'Frontend · Menus', 'href' => '/admin/frontend/menus', 'icon' => '☷', 'permissions_any' => array('frontend.menus.ver')),
        array('label' => 'Norminha', 'href' => '/admin/tutor-norminha', 'icon' => '✦', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Norminha · Configurações', 'href' => '/admin/tutor-norminha/configuracoes', 'icon' => '⚙', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Globais', 'href' => '/admin/configuracoes-globais', 'icon' => '⚙', 'permissions_any' => array('configuracoes_globais.ver')),
        array('label' => 'Pagamento', 'href' => '/admin/configuracoes-pagamento', 'icon' => '₿', 'permissions_any' => array('configuracoes_globais.gerenciar')),
        array('label' => 'Usuários', 'href' => '/admin/usuarios', 'icon' => '👤', 'permissions_any' => array('usuarios.ver')),
        array('label' => 'Permissões', 'href' => '/admin/permissoes', 'icon' => '🛡', 'permissions_any' => array('rbac.permissoes.ver')),
        array('label' => 'E-mails', 'href' => '/admin/emails', 'icon' => '✉', 'permissions_any' => array('emails.ver')),
        array('label' => 'E-mails · Modelos', 'href' => '/admin/emails/modelos', 'icon' => '✎', 'permissions_any' => array('emails.ver')),
        array('label' => 'RBAC', 'href' => '/admin/rbac', 'icon' => '☰', 'permissions_any' => array('rbac.dashboard.ver')),
    )),
);

$menu = array_values(array_filter(array_map(function ($group) use ($rbacService, $usuarioId) {
    $group['items'] = array_values(array_filter($group['items'], function ($item) use ($rbacService, $usuarioId) {
        if (empty($item['permissions_any'])) {
            return true;
        }

        if (!$usuarioId) {
            return false;
        }

        return $rbacService->userHasAnyPermission($usuarioId, $item['permissions_any']);
    }));

    return empty($group['items']) ? null : $group;
}, $menu)));

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
<div class="admin-shell" id="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="admin-sidebar__brand">
            <a class="brand" href="/admin"><?php echo Helpers::e($brandName); ?></a>
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
            <div class="admin-topbar__left">
                <button class="admin-topbar__menu-toggle" id="admin-menu-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Abrir menu administrativo">☰</button>
                <div class="admin-topbar__module">
                    <small><?php echo Helpers::e($brandName); ?></small>
                    <strong><?php echo Helpers::e($moduleTitle); ?></strong>
                </div>
            </div>
            <div>
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
                <span class="muted"><?php echo Helpers::e($userName); ?></span>
                <a class="button-link button-link--ghost" href="/">Portal</a>
                <a class="button-link button-link--ghost" href="/logout">Sair</a>
            </div>
        </header>

        <main class="site-main site-main--admin">
            <?php echo $content; ?>
        </main>
    </div>
</div>
<script>
    (function () {
        var shell = document.getElementById('admin-shell');
        var toggle = document.getElementById('admin-menu-toggle');
        if (!shell || !toggle) {
            return;
        }

        toggle.addEventListener('click', function () {
            var opened = shell.classList.toggle('sidebar-open');
            toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
        });

        window.addEventListener('resize', function () {
        if (window.innerWidth > 1200 && shell.classList.contains('sidebar-open')) {
            shell.classList.remove('sidebar-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    window.confirmarAcaoCritica = function (opcoes) {
        opcoes = opcoes || {};
        var palavra = String(opcoes.palavra || 'CONFIRMAR').trim().toUpperCase();
        var pergunta = String(opcoes.pergunta || 'Você conferiu esta ação?');
        var mensagem = pergunta + ' Digite ' + palavra + ' para confirmar.';
        var resposta = window.prompt(mensagem);

        if (resposta === null) {
            return false;
        }

        if (resposta.trim().toUpperCase() !== palavra) {
            window.alert('Confirmação inválida. Digite ' + palavra + ' para continuar.');
            return false;
        }

        return true;
    };
})();
</script>

