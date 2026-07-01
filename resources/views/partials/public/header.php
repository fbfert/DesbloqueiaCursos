<?php
use App\Core\Helpers;

$menuItens = isset($headerMenu['itens']) && is_array($headerMenu['itens']) ? $headerMenu['itens'] : array();
$buttonLabel = $isAuthenticated ? 'Minha Conta' : 'Entrar';
$buttonHref = $isAuthenticated ? '/minha-conta' : '/login';
$hiddenActionLabels = $isAuthenticated
    ? array('minha página', 'minha conta', 'sair', 'entrar')
    : array('entrar');

$brandModulo = isset($brandModulo) && is_array($brandModulo) ? $brandModulo : null;
$brandTexto = $brandName;
$brandImagem = null;
$brandImagemAlt = $brandName;

if (!empty($institucional['logo_caminho'])) {
    $brandImagem = (string) $institucional['logo_caminho'];
    $brandImagemAlt = $brandName;
}

if ($brandModulo) {
    if (!empty($brandModulo['titulo'])) {
        $brandTexto = (string) $brandModulo['titulo'];
    } elseif (!empty($brandModulo['subtitulo'])) {
        $brandTexto = (string) $brandModulo['subtitulo'];
    } elseif (!empty($brandModulo['conteudo'])) {
        $brandTexto = (string) $brandModulo['conteudo'];
    }

    if (empty($brandImagem) && !empty($brandModulo['imagem_caminho'])) {
        $brandImagem = (string) $brandModulo['imagem_caminho'];
        $brandImagemAlt = !empty($brandModulo['imagem_alt']) ? (string) $brandModulo['imagem_alt'] : $brandTexto;
    }
}

$desktopItens = array();
foreach ($menuItens as $item) {
    $label = trim((string) ($item['rotulo'] ?? ''));
    $url = trim((string) ($item['url'] ?? ''));
    if ($label === '' || $url === '') {
        continue;
    }

    $labelLower = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
    if (in_array($labelLower, $hiddenActionLabels, true)) {
        continue;
    }

    $desktopItens[] = $item;
}

$mobileItens = $desktopItens;
?>
<header class="public-header">
    <div class="site-header site-header__inner">
        <a class="brand site-header__brand<?php echo $brandImagem ? ' brand--image' : ' brand--text'; ?>" href="/" aria-label="Página inicial de <?php echo Helpers::e($brandTexto); ?>">
            <?php if ($brandImagem): ?>
                <img class="brand__image" src="<?php echo Helpers::e($brandImagem); ?>" alt="<?php echo Helpers::e($brandImagemAlt); ?>">
            <?php else: ?>
                <?php echo Helpers::e($brandTexto); ?>
            <?php endif; ?>
        </a>

        <nav class="site-header__nav" aria-label="Menu principal">
            <ul class="site-header__nav-list">
                <?php foreach ($desktopItens as $item): ?>
                    <?php
                    $url = (string) $item['url'];
                    $isActive = $url === '/' ? $requestPath === '/' : strpos($requestPath, rtrim($url, '/')) === 0;
                    $target = (string) ($item['target'] ?? '_self');
                    $rel = isset($item['rel']) ? trim((string) $item['rel']) : '';
                    ?>
                    <li>
                        <a
                            class="site-header__nav-link<?php echo $isActive ? ' is-active' : ''; ?>"
                            href="<?php echo Helpers::e($url); ?>"
                            target="<?php echo Helpers::e($target); ?>"
                            <?php if ($target === '_blank'): ?>
                                rel="<?php echo Helpers::e($rel !== '' ? $rel : 'noopener noreferrer'); ?>"
                            <?php endif; ?>
                        >
                            <?php echo Helpers::e((string) $item['rotulo']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="site-header__actions">
            <?php if ($isAuthenticated): ?>
                <a
                    class="site-header__icon-button"
                    href="<?php echo Helpers::e($buttonHref); ?>"
                    aria-label="Meu perfil"
                    title="Meu perfil"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M12 12c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5Zm0 2c-3.31 0-10 1.67-10 5v2h20v-2c0-3.33-6.69-5-10-5Z"></path>
                    </svg>
                </a>

                <form method="post" action="/logout" class="site-header__logout-form">
                    <?php echo $csrfField; ?>
                    <button
                        type="submit"
                        class="site-header__icon-button site-header__icon-button--logout"
                        aria-label="Sair"
                        title="Sair"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M16 13v-2H7.83l3.58-3.59L10 6l-6 6 6 6 1.41-1.41L7.83 13H16Zm3-10H9c-1.1 0-2 .9-2 2v3h2V5h10v14H9v-3H7v3c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2Z"></path>
                        </svg>
                    </button>
                </form>
            <?php else: ?>
                <a class="button-link site-header__button" href="<?php echo Helpers::e($buttonHref); ?>"><?php echo Helpers::e($buttonLabel); ?></a>
            <?php endif; ?>
            <button class="site-header__toggle" type="button" aria-label="Abrir menu" aria-controls="menu-mobile" aria-expanded="false" data-menu-toggle>
                <span class="menu-toggle__line" aria-hidden="true"></span>
                <span class="menu-toggle__line" aria-hidden="true"></span>
                <span class="menu-toggle__line" aria-hidden="true"></span>
            </button>
        </div>
    </div>

    <div class="mobile-menu__overlay" hidden data-menu-overlay></div>
    <nav class="mobile-menu" id="menu-mobile" aria-label="Menu principal mobile" hidden tabindex="-1" data-menu-mobile>
        <div class="mobile-menu__panel">
            <div class="mobile-menu__header">
                <strong>Menu</strong>
                <button class="mobile-menu__close" type="button" aria-label="Fechar menu" data-menu-close>&times;</button>
            </div>
            <div class="mobile-menu__links">
                <?php foreach ($mobileItens as $item): ?>
                    <?php
                    $url = (string) ($item['url'] ?? '');
                    if ($url === '') {
                        continue;
                    }
                    $target = (string) ($item['target'] ?? '_self');
                    $rel = isset($item['rel']) ? trim((string) $item['rel']) : '';
                    ?>
                    <a
                        class="mobile-menu__link"
                        href="<?php echo Helpers::e($url); ?>"
                        target="<?php echo Helpers::e($target); ?>"
                        <?php if ($target === '_blank'): ?>
                            rel="<?php echo Helpers::e($rel !== '' ? $rel : 'noopener noreferrer'); ?>"
                        <?php endif; ?>
                    >
                        <?php echo Helpers::e((string) ($item['rotulo'] ?? '')); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
</header>
