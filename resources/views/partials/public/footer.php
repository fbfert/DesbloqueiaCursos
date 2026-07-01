<?php use App\Core\Helpers; ?>
<?php if (!empty($footerModulo) && (!empty($footerText) || !empty($footerModulo['imagem_caminho']))): ?>
    <footer class="public-footer">
        <div class="site-footer">
            <?php if (!empty($footerModulo['imagem_caminho'])): ?>
                <?php $altFooter = !empty($footerModulo['imagem_alt']) ? (string) $footerModulo['imagem_alt'] : 'Imagem do rodapé'; ?>
                <span class="site-footer__image"><img src="<?php echo Helpers::e($footerModulo['imagem_caminho']); ?>" alt="<?php echo Helpers::e($altFooter); ?>"></span>
            <?php endif; ?>
            <?php if (!empty($footerText)): ?>
                <span class="site-footer__content"><?php echo Helpers::e($footerText); ?></span>
            <?php endif; ?>
        </div>
    </footer>
<?php endif; ?>
