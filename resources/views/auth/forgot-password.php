<section class="auth-shell">
    <h1>Recuperar senha</h1>
    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <form method="post" action="/recuperar-senha" class="auth-form">
        <label>
            E-mail ou CPF
            <input type="text" name="login" value="<?php echo htmlspecialchars(isset($old['login']) ? $old['login'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>

        <button type="submit">Enviar link de recuperação</button>
    </form>

    <p><a href="/login">Voltar ao login</a></p>
</section>
