<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Enviar comprovante PIX</h1>
    <p>Pedido <?php echo Helpers::e($pedido['codigo']); ?></p>
</section>

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

<?php if (empty($loggedIn)): ?>
    <section class="notice">
        <strong>Entre para enviar o comprovante</strong>
        <p>O comprovante pertence ao pagador e precisa ser enviado com a conta autenticada.</p>
        <div class="cta-group">
            <a class="button-link" href="/login">Entrar</a>
            <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
        </div>
    </section>
<?php endif; ?>

<section class="checkout-panel">
    <h2>Pedido</h2>
    <dl class="summary-list">
        <dt>Status</dt>
        <dd><?php echo Helpers::e($pedido['status']); ?></dd>
        <dt>Total</dt>
        <dd><?php echo Helpers::e($pedido['total']); ?></dd>
    </dl>
    <div class="status-card" style="margin-top:12px;">
        <strong>Chave Pix</strong>
        <span>cpeducacursos@gmail.com</span>
    </div>
</section>

<?php if (!empty($loggedIn)): ?>
    <form class="admin-form checkout-form" method="post" action="/checkout/comprovante?pedido_id=<?php echo (int) $pedido['id']; ?>" enctype="multipart/form-data">
        <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
        <label>
            Arquivo do comprovante
            <input type="file" name="comprovante" accept="image/*,application/pdf">
        </label>
        <label>
            Valor informado
            <input type="text" name="valor_informado" value="<?php echo Helpers::e($pedido['total']); ?>">
        </label>
        <label>
            Motivo do reenvio
            <textarea name="motivo_reenvio" rows="4" placeholder="Opcional para reenvio"></textarea>
        </label>
        <button type="submit">Enviar comprovante</button>
    </form>

    <?php if (!empty($pedido['comprovantes'])): ?>
        <section class="checkout-panel">
            <h2>Versões enviadas</h2>
            <div class="stack">
                <?php foreach ($pedido['comprovantes'] as $comprovante): ?>
                    <div class="status-card">
                        <strong>Versao <?php echo (int) $comprovante['versao']; ?></strong>
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
