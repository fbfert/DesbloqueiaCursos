<section class="auth-shell">
    <h1>Redefinir senha</h1>
    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <form method="post" action="/recuperar-senha/redefinir" class="auth-form">
        <label>
            Token
            <input type="text" name="token" value="<?php echo htmlspecialchars(isset($token) ? $token : '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>

        <label>
            Nova senha
            <input type="password" name="senha" required>
        </label>

        <label>
            Confirmar nova senha
            <input type="password" name="senha_confirmacao" required>
        </label>

        <button type="submit">Salvar nova senha</button>
    </form>

    <p><a href="/login">Voltar ao login</a></p>
</section>
