<?php use App\Core\Helpers; ?>
<?php if (!empty($preFooterModulo) || !empty($preFooterMenuItems)): ?>
    <section class="pre-footer">
        <div class="site-footer">
            <?php if (!empty($preFooterModulo)): ?>
                <div class="pre-footer__content">
                    <?php if (!empty($preFooterModulo['imagem_caminho'])): ?>
                        <?php $altPreFooter = !empty($preFooterModulo['imagem_alt']) ? (string) $preFooterModulo['imagem_alt'] : (!empty($preFooterModulo['titulo']) ? (string) $preFooterModulo['titulo'] : 'Imagem do módulo'); ?>
                        <div class="module-public-image module-public-image--pre-footer"><img src="<?php echo Helpers::e($preFooterModulo['imagem_caminho']); ?>" alt="<?php echo Helpers::e($altPreFooter); ?>"></div>
                    <?php endif; ?>
                    <?php if (!empty($preFooterModulo['titulo'])): ?>
                        <strong class="pre-footer__title"><?php echo Helpers::e($preFooterModulo['titulo']); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($preFooterModulo['subtitulo'])): ?>
                        <p class="pre-footer__text"><?php echo Helpers::e($preFooterModulo['subtitulo']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($preFooterMenuItems)): ?>
                <nav class="pre-footer__menu" aria-label="Links institucionais">
                    <?php foreach ($preFooterMenuItems as $item): ?>
                        <?php
                        $target = isset($item['target']) ? (string) $item['target'] : '_self';
                        $rel = isset($item['rel']) ? trim((string) $item['rel']) : '';
                        if ($target === '_blank' && $rel === '') {
                            $rel = 'noopener noreferrer';
                        }
                        ?>
                        <a class="pre-footer__link" href="<?php echo Helpers::e($item['url']); ?>" target="<?php echo Helpers::e($target); ?>"<?php echo $rel !== '' ? ' rel="' . Helpers::e($rel) . '"' : ''; ?>>
                            <?php echo Helpers::e($item['rotulo']); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
