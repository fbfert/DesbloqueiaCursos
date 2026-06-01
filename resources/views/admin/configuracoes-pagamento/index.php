<?php
$configuracao = isset($configuracao) && is_array($configuracao) ? $configuracao : array();
$oldInput = isset($oldInput) && is_array($oldInput) ? $oldInput : array();
$ativoSelecionado = isset($oldInput['ativo']) ? (string) $oldInput['ativo'] : (string) ($configuracao['ativo'] ?? 0);
$ambienteSelecionado = isset($oldInput['ambiente']) ? (string) $oldInput['ambiente'] : (string) ($configuracao['ambiente'] ?? 'sandbox');
$webhookUrlPublica = isset($configuracao['webhook_url_publica']) ? (string) $configuracao['webhook_url_publica'] : '';
$apiKeyMask = isset($configuracao['api_key_masked']) ? (string) $configuracao['api_key_masked'] : '';
$webhookHmacMask = isset($configuracao['webhook_hmac_secret_masked']) ? (string) $configuracao['webhook_hmac_secret_masked'] : '';
$webhookUrlSecretMask = isset($configuracao['webhook_url_secret_masked']) ? (string) $configuracao['webhook_url_secret_masked'] : '';
$ultimoTesteStatus = isset($configuracao['ultimo_teste_status']) ? (string) $configuracao['ultimo_teste_status'] : '';
$ultimoTesteMensagem = isset($configuracao['ultimo_teste_mensagem']) ? (string) $configuracao['ultimo_teste_mensagem'] : '';
$ultimoTesteEm = isset($configuracao['ultimo_teste_em']) ? (string) $configuracao['ultimo_teste_em'] : '';
?>
<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Configurações de Pagamento</h1>
            <p class="admin-page__subtitle">Configure e teste o gateway AbacatePay sem expor segredos no navegador.</p>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <div class="panel-header">
            <div>
                <h2>AbacatePay</h2>
                <p class="muted">Deixe em branco para manter o valor atual.</p>
            </div>
            <div class="admin-page__actions" style="display:flex;gap:12px;flex-wrap:wrap;">
                <form method="post" action="/admin/configuracoes-pagamento/testar-abacatepay">
                    <?php echo $csrfField; ?>
                    <button type="submit" class="button-link button-link--ghost">Testar conexão</button>
                </form>
                <form method="post" action="/admin/configuracoes-pagamento/gerar-webhook-secret">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="ativo" value="<?php echo htmlspecialchars($ativoSelecionado, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="ambiente" value="<?php echo htmlspecialchars($ambienteSelecionado, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="button-link">Gerar novo secret de URL</button>
                </form>
            </div>
        </div>

        <form method="post" action="/admin/configuracoes-pagamento/salvar" class="form-grid">
            <?php echo $csrfField; ?>

            <label>
                Ativo
                <select name="ativo">
                    <option value="0" <?php echo $ativoSelecionado === '0' ? 'selected' : ''; ?>>Não</option>
                    <option value="1" <?php echo $ativoSelecionado === '1' ? 'selected' : ''; ?>>Sim</option>
                </select>
            </label>

            <label>
                Ambiente
                <select name="ambiente">
                    <?php
                    foreach (array('sandbox' => 'Sandbox / teste', 'producao' => 'Produção') as $value => $label):
                    ?>
                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $ambienteSelecionado === $value ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                API Key
                <input type="password" name="api_key" value="" autocomplete="new-password" placeholder="Deixe em branco para manter o valor atual">
                <small class="muted">Atual: <?php echo htmlspecialchars($apiKeyMask !== '' ? $apiKeyMask : 'não configurada', ENT_QUOTES, 'UTF-8'); ?></small>
            </label>

            <label>
                Webhook HMAC Secret
                <input type="password" name="webhook_hmac_secret" value="" autocomplete="new-password" placeholder="Deixe em branco para manter o valor atual">
                <small class="muted">Atual: <?php echo htmlspecialchars($webhookHmacMask !== '' ? $webhookHmacMask : 'não configurado', ENT_QUOTES, 'UTF-8'); ?></small>
            </label>

            <label>
                Webhook URL Secret
                <input type="password" name="webhook_url_secret" value="" autocomplete="new-password" placeholder="Deixe em branco para manter o valor atual">
                <small class="muted">Atual: <?php echo htmlspecialchars($webhookUrlSecretMask !== '' ? $webhookUrlSecretMask : 'não configurado', ENT_QUOTES, 'UTF-8'); ?></small>
            </label>

            <label style="grid-column:1 / -1;">
                URL pública do webhook
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <input type="text" id="webhook-url-publica" readonly value="<?php echo htmlspecialchars($webhookUrlPublica !== '' ? $webhookUrlPublica : 'Configure um secret para gerar a URL pública.', ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="button" class="button-link button-link--ghost" id="copy-webhook-url">Copiar URL do webhook</button>
                </div>
            </label>

            <label style="grid-column:1 / -1;">
                Resultado do último teste
                <div class="status-card" style="margin-top:8px;">
                    <strong>Status:</strong> <?php echo htmlspecialchars($ultimoTesteStatus !== '' ? $ultimoTesteStatus : 'não testado', ENT_QUOTES, 'UTF-8'); ?><br>
                    <strong>Mensagem:</strong> <?php echo htmlspecialchars($ultimoTesteMensagem !== '' ? $ultimoTesteMensagem : 'Sem testes anteriores.', ENT_QUOTES, 'UTF-8'); ?><br>
                    <strong>Executado em:</strong> <?php echo htmlspecialchars($ultimoTesteEm !== '' ? $ultimoTesteEm : '-', ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </label>

            <?php
            $cancel_url = '/admin/configuracoes-pagamento';
            $show_save_and_new = false;
            $show_save_and_exit = false;
            $show_save_as_copy = false;
            $save_label = 'Salvar';
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </form>
    </section>
</div>

<script>
(function () {
    var copyButton = document.getElementById('copy-webhook-url');
    var webhookInput = document.getElementById('webhook-url-publica');

    if (!copyButton || !webhookInput) {
        return;
    }

    copyButton.addEventListener('click', function () {
        var value = webhookInput.value || '';
        if (!value || value.indexOf('Configure um secret') === 0) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value);
            copyButton.textContent = 'URL copiada';
            setTimeout(function () {
                copyButton.textContent = 'Copiar URL do webhook';
            }, 1500);
            return;
        }

        webhookInput.focus();
        webhookInput.select();

        try {
            document.execCommand('copy');
            copyButton.textContent = 'URL copiada';
            setTimeout(function () {
                copyButton.textContent = 'Copiar URL do webhook';
            }, 1500);
        } catch (error) {
            // Sem ação adicional.
        }
    });
})();
</script>
