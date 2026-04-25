<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Resumo do pedido</h1>
    <p><?php echo Helpers::e($pedido['codigo']); ?></p>
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

<section class="checkout-grid">
    <article class="checkout-panel">
        <h2>Dados do pedido</h2>
        <dl class="summary-list">
            <dt>Pagador</dt>
            <dd><?php echo Helpers::e($pedido['pagador_nome']); ?></dd>
            <dt>Status</dt>
            <dd><?php echo Helpers::e($pedido['status']); ?></dd>
            <dt>Subtotal</dt>
            <dd><?php echo Helpers::e($pedido['subtotal']); ?></dd>
            <dt>Desconto</dt>
            <dd><?php echo Helpers::e($pedido['desconto_total']); ?></dd>
            <dt>Total</dt>
            <dd><?php echo Helpers::e($pedido['total']); ?></dd>
        </dl>
    </article>

    <article class="checkout-panel">
        <h2>Itens</h2>
        <?php foreach ($pedido['itens'] as $item): ?>
            <div class="status-card">
                <strong><?php echo Helpers::e($item['curso_nome']); ?></strong>
                <span><?php echo Helpers::e($item['turma_nome']); ?></span>
                <span><?php echo (int) $item['quantidade']; ?> vaga(s)</span>
                <span><?php echo Helpers::e($item['valor_total']); ?></span>
            </div>
        <?php endforeach; ?>
    </article>
</section>

<section class="checkout-grid">
    <article class="checkout-panel">
        <h2>Participantes</h2>
        <?php foreach ($pedido['participantes'] as $participante): ?>
            <div class="status-card">
                <strong><?php echo Helpers::e($participante['nome']); ?></strong>
                <span><?php echo Helpers::e($participante['email']); ?></span>
                <span><?php echo Helpers::e($participante['status']); ?></span>
            </div>
        <?php endforeach; ?>
    </article>

    <article class="checkout-panel">
        <h2>Cupom</h2>
        <?php if (!empty($pedido['cupom'])): ?>
            <p class="muted">Cupom aplicado: <?php echo Helpers::e($pedido['cupom']['cupom_codigo']); ?></p>
        <?php else: ?>
            <p class="muted">Nenhum cupom aplicado.</p>
        <?php endif; ?>

        <?php if (!empty($loggedIn)): ?>
            <form class="admin-form" method="post" action="/checkout/cupom">
                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                <label>
                    Codigo do cupom
                    <input type="text" name="cupom_codigo" placeholder="Codigo do cupom">
                </label>
                <button type="submit">Aplicar cupom</button>
            </form>
        <?php else: ?>
            <p class="muted">Entre na sua conta para aplicar um cupom.</p>
        <?php endif; ?>
    </article>
</section>

<section class="checkout-grid">
    <article class="checkout-panel">
        <h2>Comprovante PIX</h2>
        <?php if ($canSeePix): ?>
            <?php if (!empty($pedido['comprovante_atual'])): ?>
                <p class="muted">Status atual: <?php echo Helpers::e($pedido['comprovante_atual']['status']); ?></p>
            <?php endif; ?>
            <div class="cta-group">
                <a class="button-link" href="/checkout/comprovante?pedido_id=<?php echo (int) $pedido['id']; ?>">Enviar comprovante</a>
            </div>
        <?php else: ?>
            <p class="muted">Comprovante ainda não disponivel para este acesso.</p>
        <?php endif; ?>
    </article>

    <article class="checkout-panel">
        <h2>Proxima etapa</h2>
        <?php if (!empty($loggedIn)): ?>
            <div class="cta-group">
                <a class="button-link" href="/checkout/comprovante?pedido_id=<?php echo (int) $pedido['id']; ?>">Enviar comprovante</a>
                <a class="button-link button-link--ghost" href="/meus-cursos">Ir para Meus Cursos</a>
            </div>
        <?php else: ?>
            <p class="muted">Entre ou crie conta para concluir a compra e enviar o comprovante.</p>
            <div class="cta-group">
                <a class="button-link" href="/login">Entrar</a>
                <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
            </div>
        <?php endif; ?>
    </article>
</section>

