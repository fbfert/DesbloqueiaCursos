<?php use App\Core\Helpers; ?>
<?php if (!empty($preFooterModulo) || !empty($preFooterMenuItems)): ?>
    <section class="pre-footer menu-antes-rodape">
        <div class="site-footer menu-antes-rodape__container">
            <?php if (!empty($preFooterModulo)): ?>
                <article class="menu-antes-rodape__panel menu-antes-rodape__panel--content">
                    <?php if (!empty($preFooterModulo['imagem_caminho'])): ?>
                        <?php $altPreFooter = !empty($preFooterModulo['imagem_alt']) ? (string) $preFooterModulo['imagem_alt'] : (!empty($preFooterModulo['titulo']) ? (string) $preFooterModulo['titulo'] : 'Imagem do módulo'); ?>
                        <div class="module-public-image module-public-image--pre-footer"><img src="<?php echo Helpers::e($preFooterModulo['imagem_caminho']); ?>" alt="<?php echo Helpers::e($altPreFooter); ?>"></div>
                    <?php endif; ?>
                    <?php if (!empty($preFooterModulo['titulo'])): ?>
                        <strong class="pre-footer__title menu-antes-rodape__panel-title"><?php echo Helpers::e($preFooterModulo['titulo']); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($preFooterModulo['subtitulo'])): ?>
                        <p class="pre-footer__text menu-antes-rodape__panel-text"><?php echo Helpers::e($preFooterModulo['subtitulo']); ?></p>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
            <?php if (!empty($preFooterMenuItems)): ?>
                <nav class="menu-antes-rodape__panel menu-antes-rodape__panel--menu" aria-label="Links institucionais">
                    <div class="menu-antes-rodape__grid">
                        <?php foreach ($preFooterMenuItems as $item): ?>
                            <?php
                            $target = isset($item['target']) ? (string) $item['target'] : '_self';
                            $rel = isset($item['rel']) ? trim((string) $item['rel']) : '';
                            if ($target === '_blank' && $rel === '') {
                                $rel = 'noopener noreferrer';
                            }
                            ?>
                            <a class="pre-footer__link menu-antes-rodape__link" href="<?php echo Helpers::e($item['url']); ?>" target="<?php echo Helpers::e($target); ?>"<?php echo $rel !== '' ? ' rel="' . Helpers::e($rel) . '"' : ''; ?>>
                                <span class="menu-antes-rodape__link-label"><?php echo Helpers::e($item['rotulo']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
