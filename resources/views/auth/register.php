<section class="auth-shell">
    <h1>Cadastro</h1>
    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <form method="post" action="/cadastro" class="auth-form">
        <label>
            Nome
            <input type="text" name="nome" value="<?php echo htmlspecialchars(isset($old['nome']) ? $old['nome'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>

        <label>
            E-mail
            <input type="email" name="email" value="<?php echo htmlspecialchars(isset($old['email']) ? $old['email'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>

        <label>
            CPF
            <input type="text" name="cpf" value="<?php echo htmlspecialchars(isset($old['cpf']) ? $old['cpf'] : '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>

        <label>
            Telefone
            <input type="text" name="telefone" value="<?php echo htmlspecialchars(isset($old['telefone']) ? $old['telefone'] : '', ENT_QUOTES, 'UTF-8'); ?>">
        </label>

        <label>
            Senha
            <input type="password" name="senha" required>
        </label>

        <label>
            Confirmar senha
            <input type="password" name="senha_confirmacao" required>
        </label>

        <label class="auth-check">
            <input type="checkbox" name="aceite_termos" value="1" <?php echo !empty($old['aceite_termos']) ? 'checked' : ''; ?>>
            Aceito os termos de uso
        </label>

        <label class="auth-check">
            <input type="checkbox" name="aceite_privacidade" value="1" <?php echo !empty($old['aceite_privacidade']) ? 'checked' : ''; ?>>
            Aceito a politica de privacidade
        </label>

        <label class="auth-check">
            <input type="checkbox" name="aceite_marketing" value="1" <?php echo !empty($old['aceite_marketing']) ? 'checked' : ''; ?>>
            Quero receber comunicacoes
        </label>

        <button type="submit">Criar conta</button>
    </form>

    <p><a href="/login">Ja tenho conta</a></p>
</section>
