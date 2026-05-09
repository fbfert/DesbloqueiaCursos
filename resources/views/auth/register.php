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
            <input type="text" name="cpf" value="<?php echo htmlspecialchars(isset($old['cpf']) ? $old['cpf'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="000.000.000-00" maxlength="14" inputmode="numeric" pattern="^\d{3}\.\d{3}\.\d{3}-\d{2}$|^\d{11}$" required>
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

        <label class="auth-check form-check-row">
            <input type="checkbox" name="aceite_termos" value="1" <?php echo !empty($old['aceite_termos']) ? 'checked' : ''; ?>>
            <span>
                Aceito os termos de uso
                <a class="consent-link" href="/termos-de-uso" target="_blank" rel="noopener">Ler termos</a>
            </span>
        </label>

        <label class="auth-check form-check-row">
            <input type="checkbox" name="aceite_privacidade" value="1" <?php echo !empty($old['aceite_privacidade']) ? 'checked' : ''; ?>>
            <span>
                Aceito a política de privacidade
                <a class="consent-link" href="/politica-de-privacidade" target="_blank" rel="noopener">Ler política</a>
            </span>
        </label>

        <label class="auth-check form-check-row">
            <input type="checkbox" name="aceite_marketing" value="1" <?php echo !empty($old['aceite_marketing']) ? 'checked' : ''; ?>>
            <span>Quero receber comunicacoes</span>
        </label>

        <button type="submit">Criar conta</button>
    </form>

    <p><a href="/login">Ja tenho conta</a></p>
</section>

<script>
(function () {
    var cpfInput = document.querySelector('input[name="cpf"]');
    if (!cpfInput) {
        return;
    }

    function formatCpf(value) {
        var digits = String(value || '').replace(/\D+/g, '').slice(0, 11);

        if (digits.length <= 3) {
            return digits;
        }

        if (digits.length <= 6) {
            return digits.slice(0, 3) + '.' + digits.slice(3);
        }

        if (digits.length <= 9) {
            return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6);
        }

        return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9);
    }

    cpfInput.addEventListener('input', function (event) {
        event.target.value = formatCpf(event.target.value);
    });

    cpfInput.value = formatCpf(cpfInput.value);
})();
</script>
