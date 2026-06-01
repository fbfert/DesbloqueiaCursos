<?php use App\Core\Helpers; ?>
<?php
$pedidoStatusNormalizado = strtolower((string) ($pedido['status'] ?? ''));
$pedidoGateway = strtolower(trim((string) ($pedidoGateway ?? ($pedido['payment_gateway'] ?? ''))));
$pedidoTemCheckoutOnline = !empty($pedido['payment_provider_payment_url']) || !empty($pedido['payment_provider_checkout_id']);
$pedidoCancelado = $pedidoStatusNormalizado === 'cancelado';
$primeiroItem = !empty($pedido['itens'][0]) && is_array($pedido['itens'][0]) ? $pedido['itens'][0] : array();
$urlNovaInscricao = !empty($primeiroItem['curso_evento_id'])
    ? '/inscricao?curso_id=' . (int) $primeiroItem['curso_evento_id'] . (!empty($primeiroItem['turma_id']) ? '&turma_id=' . (int) $primeiroItem['turma_id'] : '')
    : '/cursos';
$tituloProximaEtapa = $pedidoCancelado ? 'Pedido cancelado' : 'Próxima etapa';
$abacatepayPodeGerarCheckout = !empty($abacatepayEnabled)
    && !empty($loggedIn)
    && !empty($pedido['id'])
    && empty($pedidoSemCobranca)
    && !in_array($pedidoStatusNormalizado, array('pago', 'aprovado', 'cancelado', 'reembolsado'), true);
?>

<div class="front-section-stack">
    <section class="page-header front-section">
        <h1>Resumo do pedido</h1>
        <p><?php echo Helpers::e($pedido['codigo']); ?></p>
    </section>

    <?php if (!empty($success)): ?>
        <section class="auth-message auth-message-success front-section">
            <p><?php echo Helpers::e($success); ?></p>
        </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error front-section">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="checkout-grid front-card-grid front-section">
        <article class="checkout-panel front-card">
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

        <?php if (empty($pedidoPagoOuAprovado)): ?>
            <article class="checkout-panel front-card">
                <h2>Itens e participantes</h2>
                <?php foreach ($pedido['itens'] as $item): ?>
                    <div class="status-card front-card">
                        <strong><?php echo Helpers::e($item['curso_nome']); ?></strong>
                        <span><?php echo Helpers::e($item['turma_nome']); ?></span>
                        <span><?php echo (int) $item['quantidade']; ?> vaga(s)</span>
                        <span>R$ <?php echo number_format((float) $item['valor_total'], 2, ',', '.'); ?></span>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($pedido['participantes'] as $participante): ?>
                    <div class="status-card front-card">
                        <strong><?php echo Helpers::e($participante['nome']); ?></strong>
                        <span><?php echo Helpers::e($participante['email']); ?></span>
                        <span><?php echo Helpers::e($participante['status']); ?></span>
                    </div>
                <?php endforeach; ?>
            </article>
        <?php endif; ?>
    </section>

    <section class="checkout-grid front-card-grid front-section">
        <?php if (empty($comprovanteAguardandoAprovacao) && empty($pedidoPagoOuAprovado)): ?>
            <article class="checkout-panel front-card">
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
                            Código do cupom
                            <input type="text" name="cupom_codigo" placeholder="Código do cupom" value="<?php echo Helpers::e((string) ($cupomPromocional ?? '')); ?>">
                        </label>
                        <button type="submit">Aplicar cupom</button>
                    </form>
                <?php else: ?>
                    <p class="muted">Entre na sua conta para aplicar um cupom.</p>
                <?php endif; ?>
            </article>
        <?php endif; ?>

        <article class="checkout-panel checkout-next-step-card front-card">
            <h2><?php echo Helpers::e($tituloProximaEtapa); ?></h2>

            <?php if (!empty($pedidoSemCobranca)): ?>
                <div class="checkout-status-alert checkout-status-alert--success">
                    <strong class="checkout-status-alert__title">Pedido gratuito confirmado</strong>
                    <p class="checkout-status-alert__text">O valor final ficou em R$ 0,00. Não há pagamento nem envio de comprovante PIX.</p>
                </div>
            <?php elseif (!empty($comprovanteAguardandoAprovacao)): ?>
                <div class="checkout-status-alert checkout-status-alert--warning">
                    <strong class="checkout-status-alert__title">Comprovante enviado</strong>
                    <p class="checkout-status-alert__text">Aguardando aprovação do comprovante. Um funcionário irá confirmar o pagamento e liberar o curso em breve.</p>
                </div>
            <?php elseif (!empty($pedidoPagoOuAprovado)): ?>
                <div class="checkout-status-alert checkout-status-alert--success">
                    <strong class="checkout-status-alert__title">Pagamento confirmado</strong>
                    <p class="checkout-status-alert__text">Seu pedido já foi confirmado. O curso será liberado em breve na área do aluno.</p>
                </div>
            <?php elseif (!empty($pedidoCancelado)): ?>
                <div class="checkout-status-alert checkout-status-alert--warning">
                    <strong class="checkout-status-alert__title">Pedido cancelado</strong>
                    <p class="checkout-status-alert__text">Este pedido foi cancelado e não pode ser pago. Para gerar um novo pedido, faça uma nova inscrição no curso.</p>
                </div>
            <?php elseif (!empty($abacatepayPodeGerarCheckout)): ?>
                <div class="checkout-status-alert checkout-status-alert--info">
                    <strong class="checkout-status-alert__title">Pagamento online disponível</strong>
                    <p class="checkout-status-alert__text">Após o pagamento, a liberação pode levar alguns instantes.</p>
                </div>
            <?php else: ?>
                <p class="muted checkout-next-step-card__text"><?php echo !empty($abacatepayEnabled) ? 'Após concluir o pagamento, siga para a confirmação online.' : 'Após concluir o pagamento, siga para o envio do comprovante PIX.'; ?></p>
            <?php endif; ?>

            <?php if (!empty($abacatepayPodeGerarCheckout) && empty($pedidoCancelado)): ?>
                <?php if (!empty($pedidoTemCheckoutOnline) && !empty($pedido['payment_provider_payment_url'])): ?>
                    <a class="button-link button-link--primary" href="<?php echo Helpers::e($pedido['payment_provider_payment_url']); ?>" target="_blank" rel="noopener">Continuar no pagamento online</a>
                <?php else: ?>
                    <form class="admin-form checkout-abacatepay-form" method="post" action="/aluno/pedidos/pagar/abacatepay">
                        <?php echo $csrfField; ?>
                        <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                        <button type="submit" class="button-link button-link--primary">Pagar agora</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($loggedIn) && empty($pedidoSemCobranca) && empty($comprovanteAguardandoAprovacao) && empty($pedidoPagoOuAprovado)): ?>
                <div class="cta-group">
                    <?php if (!empty($pedidoCancelado)): ?>
                        <a class="button-link button-link--primary" href="<?php echo Helpers::e($urlNovaInscricao); ?>">Nova inscrição</a>
                        <a class="button-link button-link--ghost" href="/meus-cursos">Ir para Meus Cursos</a>
                    <?php elseif (empty($abacatepayEnabled)): ?>
                        <a class="button-link button-link--primary" href="/checkout/comprovante?pedido_id=<?php echo (int) $pedido['id']; ?>">Prosseguir para pagamento</a>
                        <a class="button-link button-link--ghost" href="/meus-cursos">Ir para Meus Cursos</a>
                    <?php elseif (!empty($abacatepayPodeGerarCheckout)): ?>
                        <?php if (!empty($pedidoTemCheckoutOnline) && !empty($pedido['payment_provider_payment_url'])): ?>
                            <a class="button-link button-link--primary" href="<?php echo Helpers::e($pedido['payment_provider_payment_url']); ?>" target="_blank" rel="noopener">Continuar no pagamento online</a>
                        <?php else: ?>
                            <form class="admin-form checkout-abacatepay-form" method="post" action="/aluno/pedidos/pagar/abacatepay">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                                <button type="submit" class="button-link button-link--primary">Pagar agora</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($pedidoGateway === 'pix_manual'): ?>
                            <a class="button-link button-link--ghost" href="/checkout/comprovante?pedido_id=<?php echo (int) $pedido['id']; ?>">Pagamento manual</a>
                        <?php endif; ?>
                        <a class="button-link button-link--ghost" href="/meus-cursos">Ir para Meus Cursos</a>
                    <?php else: ?>
                        <a class="button-link button-link--ghost" href="/meus-cursos">Ir para Meus Cursos</a>
                    <?php endif; ?>
                </div>
            <?php elseif (!empty($loggedIn) && !empty($pedidoSemCobranca)): ?>
                <div class="cta-group">
                    <a class="button-link button-link--primary" href="/minha-pagina">Ir para Minha Página</a>
                </div>
            <?php elseif (!empty($loggedIn)): ?>
                <div class="cta-group">
                    <a class="button-link button-link--ghost" href="/meus-cursos">Ir para Meus Cursos</a>
                </div>
            <?php else: ?>
                <p class="muted"><?php echo !empty($pedidoSemCobranca) ? 'Entre ou crie conta para acessar o pedido gratuito e seus cursos.' : (!empty($abacatepayEnabled) ? 'Entre ou crie conta para concluir a compra e efetuar o pagamento online.' : 'Entre ou crie conta para concluir a compra e enviar o comprovante.'); ?></p>
                <div class="cta-group">
                    <a class="button-link" href="/login">Entrar</a>
                    <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
                </div>
            <?php endif; ?>
        </article>
    </section>
</div>
