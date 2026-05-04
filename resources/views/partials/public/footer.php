<?php use App\Core\Helpers; ?>
<?php if (!empty($footerModulo) && !empty($footerText)): ?>
    <footer class="public-footer">
        <div class="site-footer">
            <span class="site-footer__content"><?php echo Helpers::e($footerText); ?></span>
        </div>
    </footer>
<?php endif; ?>
