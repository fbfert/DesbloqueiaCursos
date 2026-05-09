<?php use App\Core\Helpers; ?>

<section class="auth-shell auth-shell--logout">
    <h1>Sair da conta</h1>
    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <p>Você está prestes a sair da sua conta.</p>
    <p>Você poderá entrar novamente usando seu e-mail, CPF ou senha cadastrada.</p>

    <div class="cta-group">
        <a class="button-link button-link--ghost" href="<?php echo Helpers::e(isset($cancelUrl) && $cancelUrl ? $cancelUrl : '/'); ?>">Cancelar</a>
        <form method="post" action="/logout" class="logout-confirm-form">
            <?php echo $csrfField; ?>
            <button type="submit">Sair da conta</button>
        </form>
    </div>
</section>
