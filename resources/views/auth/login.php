<?php
if (!isset($frontend_template)) {
    try { $frontend_template = (new \App\Services\ConfiguracaoGlobalService())->templateVisualPortal(); } catch (\Throwable $e) { $frontend_template = 'v1'; }
}
if ((string) $frontend_template === 'v4-claude') { require BASE_PATH . '/resources/views/v4-claude/login.php'; return; }
?>
<section class="auth-shell">
    <h1>Login</h1>
    <section class="auth-info-card front-card" aria-labelledby="login-orientacao-title">
        <h2 id="login-orientacao-title">Já tem cadastro?</h2>
        <p>Entre com seu e-mail e senha para acessar seus cursos, acompanhar inscrições ou continuar sua compra.</p>
        <div class="auth-info-card__actions cta-group">
            <a class="button-link button-link--ghost" href="/cadastro">Ainda não tenho cadastro</a>
            <a class="button-link button-link--ghost" href="/recuperar-senha">Esqueci minha senha</a>
        </div>
    </section>
    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>

    <?php if (!empty($accountCreated) && is_array($accountCreated)): ?>
        <div class="login-success-card">
            <div class="login-success-card__icon">✓</div>
            <div class="login-success-card__content">
                <strong>Conta criada com sucesso</strong>
                <p>
                    A conta de
                    <?php echo htmlspecialchars(isset($accountCreated['nome']) ? (string) $accountCreated['nome'] : '', ENT_QUOTES, 'UTF-8'); ?>
                    foi criada com o e-mail
                    <?php echo htmlspecialchars(isset($accountCreated['email']) ? (string) $accountCreated['email'] : '', ENT_QUOTES, 'UTF-8'); ?>.
                </p>
                <p>Agora acesse com seus dados para começar.</p>
            </div>
        </div>
    <?php else: ?>
        <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>
    <?php endif; ?>

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

</section>
