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
/**
 * Menu lateral do admin.
 *
 * REORGANIZADO EM 23/08/2026. O que estava errado antes:
 *
 *   - "Configurações" tinha 14 dos 34 itens. Não era uma categoria, era o lugar
 *     onde tudo que não coubera nas outras foi parar: usuários, permissões,
 *     RBAC, e-mails, os quatro itens da Norminha, dois de frontend, gateway de
 *     pagamento e checkout. Quem procurava "Usuários" não tinha por que
 *     adivinhar que estava em Configurações.
 *   - "Promocionais" e "Acadêmico" eram títulos para um único link cada. Um
 *     cabeçalho que anuncia um item só é ruído.
 *   - "Painel" misturava o Dashboard com Catálogo e Páginas, que são conteúdo.
 *   - Certificados morava em "Operação", junto de Pedidos e Inscrições.
 *
 * O critério agora é o que a pessoa está tentando fazer, e não a semelhança
 * técnica entre as telas. Nenhum destino e NENHUMA PERMISSÃO mudaram: os 34
 * itens são os mesmos, com as mesmas chaves de RBAC. Só o agrupamento e a
 * ordem são novos.
 *
 * Os rótulos perderam o prefixo repetido — dentro do grupo "Norminha" não é
 * preciso escrever "Norminha ·" quatro vezes.
 */
$menu = array(
    array('group' => 'Início', 'items' => array(
        array('label' => 'Dashboard', 'href' => '/admin/dashboard', 'icon' => '◼'),
    )),

    // O que se vende e se ensina, do catálogo à sala de aula.
    array('group' => 'Cursos', 'items' => array(
        array('label' => 'Catálogo', 'href' => '/admin/catalogo', 'icon' => '▣', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Categorias', 'href' => '/admin/categorias', 'icon' => '◦', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Cursos', 'href' => '/admin/cursos', 'icon' => '◧', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Turmas', 'href' => '/admin/turmas', 'icon' => '◨', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Área interna do curso', 'href' => '/admin/area-curso', 'icon' => '▤', 'permissions_any' => array('area_curso.gerenciar')),
        array('label' => 'Área acadêmica', 'href' => '/admin/academico', 'icon' => '◈', 'permissions_any' => array('academico.ver')),
    )),

    // Do pedido até a matrícula ativa.
    array('group' => 'Vendas', 'items' => array(
        array('label' => 'Pedidos', 'href' => '/admin/pedidos', 'icon' => '⟡', 'permissions_any' => array('pedidos.ver')),
        array('label' => 'Inscrições', 'href' => '/admin/inscricoes', 'icon' => '⟢', 'permissions_any' => array('pedidos.ver')),
        array('label' => 'Comprovantes PIX', 'href' => '/admin/comprovantes-pix', 'icon' => '◉', 'permissions_any' => array('pedidos.ver')),
        array('label' => 'Cupons', 'href' => '/admin/cupons', 'icon' => '⌘', 'permissions_any' => array('cupons.ver')),
        array('label' => 'Presentes', 'href' => '/admin/promocionais/presentes', 'icon' => '❖', 'permissions_any' => array('promocionais.presentes.ver')),
    )),

    // Grupo próprio: emitir e desenhar certificado sao tarefas de quem cuida da
    // conclusao, nao de quem cuida de pedido.
    array('group' => 'Certificados', 'items' => array(
        array('label' => 'Emissão', 'href' => '/admin/certificados', 'icon' => '⬚', 'permissions_any' => array('certificados.ver')),
        array('label' => 'Modelos', 'href' => '/admin/certificados/templates', 'icon' => '✎', 'permissions_any' => array('certificados.ver')),
    )),

    array('group' => 'Financeiro', 'items' => array(
        array('label' => 'Visão geral', 'href' => '/admin/financeiro', 'icon' => '₪', 'permissions_any' => array('financeiro.ver')),
        array('label' => 'Repasses', 'href' => '/admin/financeiro/repasses', 'icon' => '↻', 'permissions_any' => array('financeiro.ver')),
        array('label' => 'Rateios', 'href' => '/admin/rateios', 'icon' => '≋', 'permissions_any' => array('financeiro.ver')),
        array('label' => 'Professores fiscais', 'href' => '/admin/professores-fiscais', 'icon' => '⧉', 'permissions_any' => array('financeiro.ver')),
    )),

    // Quatro telas espalhadas em "Configurações" viraram um grupo. A Norminha
    // tem credencial, custo e telemetria proprios; nao e um ajuste entre outros.
    array('group' => 'Norminha', 'items' => array(
        array('label' => 'Falas', 'href' => '/admin/tutor-norminha', 'icon' => '✦', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Configurações', 'href' => '/admin/tutor-norminha/configuracoes', 'icon' => '⚙', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Inteligência artificial', 'href' => '/admin/tutor-norminha/ia', 'icon' => '✧', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Telemetria', 'href' => '/admin/tutor-norminha/telemetria', 'icon' => '◫', 'permissions_any' => array('conteudo.ver')),
    )),

    // O que o visitante vê.
    array('group' => 'Site', 'items' => array(
        array('label' => 'Páginas', 'href' => '/admin/paginas', 'icon' => '▥', 'permissions_any' => array('conteudo.ver')),
        array('label' => 'Módulos da home', 'href' => '/admin/frontend/modulos', 'icon' => '▦', 'permissions_any' => array('frontend.modulos.ver')),
        array('label' => 'Menus', 'href' => '/admin/frontend/menus', 'icon' => '☷', 'permissions_any' => array('frontend.menus.ver')),
    )),

    // Tudo que sai daqui e chega no aluno.
    array('group' => 'Comunicação', 'items' => array(
        array('label' => 'Avisos', 'href' => '/admin/avisos', 'icon' => '◬', 'permissions_any' => array('avisos.visualizar')),
        array('label' => 'E-mails', 'href' => '/admin/emails', 'icon' => '✉', 'permissions_any' => array('emails.ver')),
        array('label' => 'Modelos de e-mail', 'href' => '/admin/emails/modelos', 'icon' => '✎', 'permissions_any' => array('emails.ver')),
    )),

    // Quem entra e o que cada um pode fazer.
    array('group' => 'Acessos', 'items' => array(
        array('label' => 'Usuários', 'href' => '/admin/usuarios', 'icon' => '◍', 'permissions_any' => array('usuarios.ver')),
        array('label' => 'Permissões', 'href' => '/admin/permissoes', 'icon' => '⊞', 'permissions_any' => array('rbac.permissoes.ver')),
        array('label' => 'Papéis e RBAC', 'href' => '/admin/rbac', 'icon' => '☰', 'permissions_any' => array('rbac.dashboard.ver')),
    )),

    // Ajuste de sistema, mexido raramente. Fica por ultimo de proposito.
    array('group' => 'Ajustes', 'items' => array(
        array('label' => 'Globais', 'href' => '/admin/configuracoes-globais', 'icon' => '⚙', 'permissions_any' => array('configuracoes_globais.ver')),
        array('label' => 'Pagamento', 'href' => '/admin/configuracoes-pagamento', 'icon' => '₿', 'permissions_any' => array('configuracoes_globais.gerenciar')),
        array('label' => 'Checkout rápido', 'href' => '/admin/checkout-rapido', 'icon' => '⚡', 'permissions_any' => array('configuracoes_globais.gerenciar')),
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

/**
 * Qual item fica aceso.
 *
 * Ate 23/08/2026 a regra era `strpos($adminPath, $item['href']) === 0`, e ela
 * errava de dois jeitos:
 *
 *   - em /admin/certificados/templates acendiam DOIS itens, porque
 *     /admin/certificados tambem e prefixo. O mesmo valia para Norminha (quatro
 *     itens), E-mails, Financeiro e Frontend;
 *   - /admin/cursos ficaria aceso em /admin/cursos-antigos, porque prefixo de
 *     texto nao respeita fronteira de caminho.
 *
 * Agora: casa quem for igual ao caminho ou for pasta dele (com a barra), e
 * entre os que casam vence o MAIS LONGO — o filho, nao o pai.
 */
$hrefsDoMenu = array();
foreach ($menu as $grupoDoMenu) {
    foreach ($grupoDoMenu['items'] as $itemDoMenu) {
        $hrefsDoMenu[] = $itemDoMenu['href'];
    }
}
$hrefAtivo = \App\Support\AdminMenu::hrefAtivo($adminPath, $hrefsDoMenu);

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
                        <?php $active = rtrim((string) $item['href'], '/') === $hrefAtivo; ?>
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

