<section class="auth-shell">
    <h1>Login</h1>
    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <form method="post" action="/login" class="auth-form">
        <label>
            E-mail ou CPF
            <input type="text" name="login" value="<?php echo htmlspecialchars(isset($old['login']) ? $old['login'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>

        <label>
            Senha
            <input type="password" name="senha" required>
        </label>

        <button type="submit">Entrar</button>
    </form>

    <div class="cta-group login-secondary-actions">
        <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
        <a class="button-link button-link--ghost" href="/recuperar-senha">Recuperar senha</a>
    </div>
</section>
