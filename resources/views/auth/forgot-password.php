<section class="auth-shell">
    <h1>Recuperar senha</h1>
    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <?php if (!empty($reset_token)): ?>
        <div class="auth-message auth-message-info">
            <p>Token gerado: <?php echo htmlspecialchars($reset_token, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="/recuperar-senha" class="auth-form">
        <label>
            E-mail ou CPF
            <input type="text" name="login" value="<?php echo htmlspecialchars(isset($old['login']) ? $old['login'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>

        <button type="submit">Gerar token</button>
    </form>

    <p><a href="/login">Voltar ao login</a></p>
</section>
