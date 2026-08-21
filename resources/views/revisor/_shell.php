<?php
use App\Core\Helpers;
use App\Core\Session;

/**
 * Shell da area do revisor (spec 0002-perfil-revisor).
 *
 * Reaproveita o CSS do admin (admin.css) e as mesmas classes, mas tem menu
 * proprio e curto. O shell do admin nao serve aqui: o item "Dashboard" dele nao
 * exige permissao, entao um revisor veria um link para /admin/dashboard que
 * receberia 403 ao clicar. Menu com link morto e pior que menu separado.
 */

$revisorPath = parse_url($_SERVER['REQUEST_URI'] ?? '/revisor', PHP_URL_PATH) ?: '/revisor';
$userName = Session::get('usuario_nome', 'Revisor');
$brandName = isset($brandName) ? $brandName : 'Desbloqueia Cursos';
$moduleTitle = !empty($title) ? (string) $title : 'Área de revisão';

$breadcrumbs = array(array('label' => 'Revisão', 'href' => '/revisor'));
if (!empty($cursoAtual['nome'])) {
    $breadcrumbs[] = array(
        'label' => $cursoAtual['nome'],
        'href' => '/revisor/curso?curso_id=' . (int) $cursoAtual['id'],
    );
}
if (!empty($title) && $title !== 'Área de revisão') {
    $breadcrumbs[] = array('label' => $title, 'href' => null);
}
?>
<div class="admin-shell" id="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="admin-sidebar__brand">
            <a class="brand" href="/revisor"><?php echo Helpers::e($brandName); ?></a>
            <p><?php echo Helpers::e($userName); ?></p>
        </div>

        <section class="admin-nav-group">
            <h2>Revisão</h2>
            <nav class="admin-nav">
                <a class="admin-nav__link<?php echo $revisorPath === '/revisor' ? ' is-active' : ''; ?>" href="/revisor">
                    <span class="admin-nav__icon">▤</span>
                    <span>Meus cursos</span>
                </a>
            </nav>
        </section>

        <?php if (!empty($cursosDoRevisor)): ?>
            <section class="admin-nav-group">
                <h2>Cursos atribuídos</h2>
                <nav class="admin-nav">
                    <?php foreach ($cursosDoRevisor as $cursoMenu): ?>
                        <?php $ativo = !empty($cursoAtual['id']) && (int) $cursoAtual['id'] === (int) $cursoMenu['id']; ?>
                        <a class="admin-nav__link<?php echo $ativo ? ' is-active' : ''; ?>"
                           href="/revisor/curso?curso_id=<?php echo (int) $cursoMenu['id']; ?>">
                            <span class="admin-nav__icon">◧</span>
                            <span><?php echo Helpers::e($cursoMenu['nome']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </section>
        <?php endif; ?>
    </aside>

    <div class="admin-shell__main">
        <header class="admin-topbar">
            <div class="admin-topbar__left">
                <button class="admin-topbar__menu-toggle" id="admin-menu-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Abrir menu de revisão">☰</button>
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
                <a class="button-link button-link--ghost" href="/logout">Sair</a>
            </div>
        </header>

        <main class="site-main site-main--admin">
            <?php echo $content; ?>
        </main>
    </div>
</div>
<script>
// Mesmo comportamento do menu lateral do admin, em versao minima.
(function () {
    var toggle = document.getElementById('admin-menu-toggle');
    var sidebar = document.getElementById('admin-sidebar');
    var shell = document.getElementById('admin-shell');
    if (!toggle || !sidebar || !shell) { return; }
    toggle.addEventListener('click', function () {
        var aberto = shell.classList.toggle('is-menu-open');
        toggle.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    });
})();
</script>
