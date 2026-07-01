<?php use App\Core\Helpers; ?>
<?php
$pedidoStatusNormalizado = strtolower((string) ($pedido['status'] ?? ''));
$pedidoConcluido = !empty($pedidoSemCobranca) || in_array($pedidoStatusNormalizado, array('aprovado', 'pago'), true);
$tituloSucesso = $pedidoConcluido ? 'Inscrição realizada com sucesso' : 'Pedido registrado com sucesso';
?>

<div class="front-section-stack">
    <section class="page-header front-section">
        <h1><?php echo Helpers::e($tituloSucesso); ?></h1>
        <p>Seu pedido foi registrado. Acompanhe o andamento e acesse seus cursos pela área do aluno.</p>
    </section>

    <div class="front-card-section front-section">
    <section class="notice notice--success front-card">
        <strong>Pedido</strong>
        <?php if (!empty($pedido)): ?>
            <p><?php echo Helpers::e($pedido['codigo']); ?> - <?php echo Helpers::e($pedido['status']); ?></p>
        <?php else: ?>
            <p>O pedido foi registrado com sucesso.</p>
        <?php endif; ?>
    </section>

    <?php if (!empty($pedidoSemCobranca)): ?>
        <section class="checkout-status-alert checkout-status-alert--success front-card">
            <strong class="checkout-status-alert__title">Pedido gratuito confirmado</strong>
            <p class="checkout-status-alert__text">O valor final ficou em R$ 0,00. O curso já foi liberado sem necessidade de comprovante PIX.</p>
        </section>
    <?php endif; ?>

    <?php if (!empty($comprovanteAguardandoAprovacao)): ?>
        <section class="checkout-status-alert checkout-status-alert--warning front-card">
            <strong class="checkout-status-alert__title">Comprovante enviado</strong>
            <p class="checkout-status-alert__text">Aguardando aprovação do comprovante. Um funcionário irá confirmar o pagamento e liberar o curso em breve.</p>
        </section>
    <?php endif; ?>

    <section class="checkout-panel front-card">
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
            <p>Agora acompanhe suas inscrições em Meus Cursos e continue o acesso na área do aluno.</p>
        <?php endif; ?>
        <div class="cta-group">
            <a class="button-link" href="/minha-pagina">Ir para a área do aluno</a>
            <a class="button-link button-link--ghost" href="/meus-cursos">Ver meus cursos</a>
            <a class="button-link button-link--ghost" href="/cursos">Conhecer outros cursos</a>
            <?php if (!empty($pedido)): ?>
                <a class="button-link button-link--ghost" href="/checkout/resumo?pedido_id=<?php echo (int) $pedido['id']; ?>">Voltar ao resumo</a>
            <?php endif; ?>
        </div>
    </section>
    </div>
</div>
