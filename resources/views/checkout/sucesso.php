<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Pedido recebido</h1>
    <p>Seu fluxo inicial foi concluído.</p>
</section>

<section class="notice notice--success">
    <strong>Pedido</strong>
    <?php if (!empty($pedido)): ?>
        <p><?php echo Helpers::e($pedido['codigo']); ?> - <?php echo Helpers::e($pedido['status']); ?></p>
    <?php else: ?>
        <p>O pedido foi registrado com sucesso.</p>
    <?php endif; ?>
</section>

<?php if (!empty($comprovanteAguardandoAprovacao)): ?>
    <section class="checkout-status-alert checkout-status-alert--warning">
        <strong class="checkout-status-alert__title">Comprovante enviado</strong>
        <p class="checkout-status-alert__text">Aguardando aprovação do comprovante. Um funcionário irá confirmar o pagamento e liberar o curso em breve.</p>
    </section>
<?php endif; ?>

<section class="checkout-panel">
    <h2>Próxima ação</h2>
    <?php if (!empty($proximas_acoes)): ?>
        <div class="detail-list">
            <?php foreach ($proximas_acoes as $acao): ?>
                <div>
                    <strong><?php echo Helpers::e($acao['titulo']); ?></strong>
                    <small><?php echo nl2br(Helpers::e((string) $acao['conteudo'])); ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Agora acompanhe suas inscrições em Meus Cursos.</p>
    <?php endif; ?>
    <div class="cta-group">
        <a class="button-link" href="/meus-cursos">Ir para Meus Cursos</a>
        <?php if (!empty($pedido)): ?>
            <a class="button-link button-link--ghost" href="/checkout/resumo?pedido_id=<?php echo (int) $pedido['id']; ?>">Voltar ao resumo</a>
        <?php endif; ?>
    </div>
</section>
