<?php
use App\Core\Helpers;

$menuItens = isset($headerMenu['itens']) && is_array($headerMenu['itens']) ? $headerMenu['itens'] : array();
$buttonLabel = $isAuthenticated ? 'Minha Página' : 'Entrar';
$buttonHref = $isAuthenticated ? '/minha-pagina' : '/login';
$buttonLabelLower = function_exists('mb_strtolower') ? mb_strtolower($buttonLabel, 'UTF-8') : strtolower($buttonLabel);

$desktopItens = array();
foreach ($menuItens as $item) {
    $label = trim((string) ($item['rotulo'] ?? ''));
    $url = trim((string) ($item['url'] ?? ''));
    if ($label === '' || $url === '') {
        continue;
    }

    $labelLower = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
    if ($labelLower === $buttonLabelLower) {
        continue;
    }

    $desktopItens[] = $item;
}
?>
<header class="public-header">
    <div class="site-header site-header__inner">
        <a class="brand site-header__brand" href="/" aria-label="Página inicial do Polo Rainbow"><?php echo Helpers::e($brandName); ?></a>

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
            <a class="button-link site-header__button" href="<?php echo Helpers::e($buttonHref); ?>"><?php echo Helpers::e($buttonLabel); ?></a>
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
                <?php foreach ($menuItens as $item): ?>
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
