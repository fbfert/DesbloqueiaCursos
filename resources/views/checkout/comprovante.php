<?php use App\Core\Helpers; ?>
<?php
$pixKey = 'cpeducacursos@gmail.com';
$pedidoGateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? '')));
$pedidoUsaPagamentoOnline = $pedidoGateway === 'abacatepay' || !empty($pedido['payment_provider_payment_url']) || !empty($pedido['payment_provider_checkout_id']);
$mostraMotivoReenvio = !empty($pedido['comprovantes']);
if (!$mostraMotivoReenvio) {
    $statusPedido = isset($pedido['status']) ? (string) $pedido['status'] : '';
    $mostraMotivoReenvio = in_array($statusPedido, array('aguardando_reenvio', 'pendencia'), true);
}
?>

<div class="front-section-stack">
    <section class="page-header front-section">
        <h1>Enviar comprovante PIX</h1>
        <p>Pedido <?php echo Helpers::e($pedido['codigo']); ?></p>
        <?php if (!empty($pedidoUsaPagamentoOnline)): ?>
            <p class="muted">Este pedido está configurado para pagamento online; use o comprovante manual apenas se o atendimento orientar.</p>
        <?php endif; ?>
    </section>

    <div class="front-card-section front-section">
    <?php if (!empty($success)): ?>
        <section class="auth-message auth-message-success">
            <p><?php echo Helpers::e($success); ?></p>
        </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="checkout-panel checkout-pix-key front-card">
        <h2>Chave PIX</h2>
        <p class="muted checkout-pix-key__help">Copie a chave abaixo, faça o pagamento e depois envie o comprovante.</p>
        <div class="checkout-pix-key__row" data-pix-key-block>
            <input class="checkout-pix-key__value" type="text" readonly value="<?php echo Helpers::e($pixKey); ?>" data-pix-key-input aria-label="Chave PIX">
            <button type="button" class="button-link button-link--ghost checkout-pix-key__copy" data-pix-copy data-copy-label="Copiar" data-copied-label="Copiado!">Copiar</button>
        </div>
        <span class="checkout-pix-key__feedback" aria-live="polite"></span>
    </section>

    <?php if (empty($loggedIn)): ?>
        <section class="notice front-card">
            <strong>Entre para enviar o comprovante</strong>
            <p>O comprovante pertence ao pagador e precisa ser enviado com a conta autenticada.</p>
            <div class="cta-group">
                <a class="button-link" href="/login">Entrar</a>
                <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($loggedIn)): ?>
        <section class="checkout-panel checkout-upload-card front-card">
            <h2 class="checkout-upload-card__title">Enviar comprovante de pagamento</h2>
            <p class="checkout-upload-card__help">Anexe o arquivo do comprovante após concluir o PIX.</p>

            <form class="admin-form checkout-form checkout-upload-card__form" method="post" action="/checkout/comprovante?pedido_id=<?php echo (int) $pedido['id']; ?>" enctype="multipart/form-data">
                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                <label class="checkout-upload-card__field">
                    Arquivo do comprovante
                    <input type="file" name="comprovante" accept="image/*,application/pdf">
                </label>
                <label class="checkout-upload-card__field">
                    Valor informado
                    <input type="text" name="valor_informado" value="<?php echo Helpers::e($pedido['total']); ?>">
                </label>
                <?php if ($mostraMotivoReenvio): ?>
                    <label class="checkout-upload-card__field">
                        Motivo do reenvio
                        <textarea name="motivo_reenvio" rows="4" placeholder="Explique por que está reenviando o comprovante"></textarea>
                    </label>
                <?php endif; ?>
                <div class="checkout-upload-card__actions">
                    <button type="submit" class="button-link button-link--primary">Enviar comprovante</button>
                </div>
            </form>
        </section>

        <?php if (!empty($pedido['comprovantes'])): ?>
            <section class="checkout-panel front-card">
                <h2>Versões enviadas</h2>
                <div class="stack front-card-list">
                    <?php foreach ($pedido['comprovantes'] as $comprovante): ?>
                        <div class="status-card front-card">
                            <strong>Versão <?php echo (int) $comprovante['versao']; ?></strong>
                            <span><?php echo Helpers::e($comprovante['status']); ?></span>
                            <?php if (!empty($comprovante['motivo_reenvio'])): ?>
                                <span><?php echo Helpers::e($comprovante['motivo_reenvio']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var buttons = document.querySelectorAll('[data-pix-copy]');
    if (!buttons.length) {
        return;
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var block = button.closest('[data-pix-key-block]');
            var input = block ? block.querySelector('[data-pix-key-input]') : null;
            var feedback = block ? block.parentNode.querySelector('.checkout-pix-key__feedback') : null;
            var value = input ? input.value : '';
            var copyLabel = button.getAttribute('data-copy-label') || 'Copiar';
            var copiedLabel = button.getAttribute('data-copied-label') || 'Copiado!';

            var setFeedback = function (text) {
                if (feedback) {
                    feedback.textContent = text;
                }
            };

            var restoreButton = function () {
                button.textContent = copyLabel;
            };

            var markCopied = function () {
                button.textContent = copiedLabel;
                setFeedback(copiedLabel);
                window.clearTimeout(button._copyTimeout);
                button._copyTimeout = window.setTimeout(function () {
                    restoreButton();
                    setFeedback('');
                }, 1800);
            };

            var failCopy = function () {
                if (input) {
                    input.focus();
                    input.select();
                }
                setFeedback('Selecione a chave e copie manualmente.');
            };

            if (!value) {
                failCopy();
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(markCopied).catch(failCopy);
                return;
            }

            try {
                if (input) {
                    input.focus();
                    input.select();
                }
                if (document.execCommand && document.execCommand('copy')) {
                    markCopied();
                    return;
                }
            } catch (exception) {
                // Fallback below.
            }

            failCopy();
        });
    });
})();
</script>
