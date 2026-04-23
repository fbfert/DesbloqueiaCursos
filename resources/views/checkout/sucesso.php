<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Pedido recebido</h1>
    <p>Seu fluxo inicial foi concluido.</p>
</section>

<section class="notice notice--success">
    <strong>Pedido</strong>
    <?php if (!empty($pedido)): ?>
        <p><?php echo Helpers::e($pedido['codigo']); ?> - <?php echo Helpers::e($pedido['status']); ?></p>
    <?php else: ?>
        <p>O pedido foi registrado com sucesso.</p>
    <?php endif; ?>
</section>

<section class="checkout-panel">
    <h2>Proxima ação</h2>
    <p>Agora acompanhe suas inscricoes em Meus Cursos.</p>
    <div class="cta-group">
        <a class="button-link" href="/meus-cursos">Ir para Meus Cursos</a>
        <?php if (!empty($pedido)): ?>
            <a class="button-link button-link--ghost" href="/checkout/resumo?pedido_id=<?php echo (int) $pedido['id']; ?>">Voltar ao resumo</a>
        <?php endif; ?>
    </div>
</section>
