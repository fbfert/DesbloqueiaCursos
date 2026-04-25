<section class="hero">
    <h1>Configurações de seguranca</h1>
    <p>Politica de login e reset de senha.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="/admin/configuracoes-globais/seguranca" class="form-grid">
        <label>
            Politica de login
            <select name="politica_login">
                <?php foreach (array('email_cpf' => 'E-mail e CPF', 'email' => 'Apenas e-mail', 'cpf' => 'Apenas CPF') as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (isset($configuracao['politica_login']) && $configuracao['politica_login'] === $value) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Validade do reset de senha (min)
            <input type="number" min="1" name="validade_reset_senha_minutos" value="<?php echo htmlspecialchars((string) (isset($configuracao['validade_reset_senha_minutos']) ? $configuracao['validade_reset_senha_minutos'] : 60), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Maximo de tentativas de login
            <input type="number" min="1" name="max_tentativas_login" value="<?php echo htmlspecialchars((string) (isset($configuracao['max_tentativas_login']) ? $configuracao['max_tentativas_login'] : 5), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Tempo de bloqueio (min)
            <input type="number" min="1" name="tempo_bloqueio_login_minutos" value="<?php echo htmlspecialchars((string) (isset($configuracao['tempo_bloqueio_login_minutos']) ? $configuracao['tempo_bloqueio_login_minutos'] : 15), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <button type="submit">Salvar seguranca</button>
    </form>
</section>

