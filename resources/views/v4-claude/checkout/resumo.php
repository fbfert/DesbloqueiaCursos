<?php use App\Core\Helpers; ?>
<?php
// Incluído por checkout/resumo.php quando template = v4-claude.
$pedido  = isset($pedido) && is_array($pedido) ? $pedido : array();
$errors  = isset($errors) && is_array($errors) ? $errors : array();
$success = isset($success) ? (string) $success : '';
$loggedIn = !empty($loggedIn);

$pedidoStatusNormalizado = strtolower((string) ($pedido['status'] ?? ''));
$pedidoCancelado = $pedidoStatusNormalizado === 'cancelado';
$pedidoTemCheckoutOnline = !empty($pedido['payment_provider_payment_url']) || !empty($pedido['payment_provider_checkout_id']);
$primeiroItem = !empty($pedido['itens'][0]) && is_array($pedido['itens'][0]) ? $pedido['itens'][0] : array();
$urlNovaInscricao = !empty($primeiroItem['curso_evento_id'])
    ? '/inscricao?curso_id=' . (int) $primeiroItem['curso_evento_id'] . (!empty($primeiroItem['turma_id']) ? '&turma_id=' . (int) $primeiroItem['turma_id'] : '')
    : '/cursos';
$tituloProximaEtapa = $pedidoCancelado ? 'Pedido cancelado' : 'Próxima etapa';
$abacatepayPodeGerarCheckout = !empty($abacatepayEnabled)
    && $loggedIn
    && !empty($pedido['id'])
    && empty($pedidoSemCobranca)
    && !in_array($pedidoStatusNormalizado, array('pago', 'aprovado', 'cancelado', 'reembolsado'), true);
?>

<?php $stepAtual = 3; include BASE_PATH . '/resources/views/partials/dc-stepper.php'; ?>

<div class="dc-checkout-body dc-container">

  <?php if ($success !== ''): ?>
    <div class="dc-callout dc-callout-success"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e($success); ?></span></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="dc-callout dc-callout-danger"><i class="ti ti-alert-triangle"></i><span><?php foreach ($errors as $err): ?><?php echo Helpers::e((string) $err); ?><br><?php endforeach; ?></span></div>
  <?php endif; ?>

  <!-- Dados do pedido -->
  <div class="dc-section-card">
    <div class="dc-section-card-title">Resumo do pedido <?php echo !empty($pedido['codigo']) ? '· ' . Helpers::e((string) $pedido['codigo']) : ''; ?></div>
    <div class="dc-resumo-row"><span>Pagador</span><span><?php echo Helpers::e((string) ($pedido['pagador_nome'] ?? '')); ?></span></div>
    <div class="dc-resumo-row"><span>Status</span><span><?php echo Helpers::e((string) ($pedido['status'] ?? '')); ?></span></div>
    <div class="dc-resumo-row"><span>Subtotal</span><span><?php echo Helpers::e((string) ($pedido['subtotal'] ?? '')); ?></span></div>
    <div class="dc-resumo-row"><span>Desconto</span><span><?php echo Helpers::e((string) ($pedido['desconto_total'] ?? '')); ?></span></div>
    <div class="dc-resumo-row dc-resumo-total"><span>Total</span><span class="dc-price-val" style="font-size:1.1rem;"><?php echo Helpers::e((string) ($pedido['total'] ?? '')); ?></span></div>
  </div>

  <!-- Itens e participantes -->
  <?php if (empty($pedidoPagoOuAprovado) && (!empty($pedido['itens']) || !empty($pedido['participantes']))): ?>
  <div class="dc-section-card">
    <div class="dc-section-card-title">Itens e participantes</div>
    <?php foreach (($pedido['itens'] ?? array()) as $item): ?>
      <div class="dc-resumo-row">
        <span><?php echo Helpers::e((string) ($item['curso_nome'] ?? '')); ?><?php echo !empty($item['turma_nome']) ? ' · ' . Helpers::e((string) $item['turma_nome']) : ''; ?> (<?php echo (int) ($item['quantidade'] ?? 1); ?>)</span>
        <span class="dc-price-val">R$ <?php echo number_format((float) ($item['valor_total'] ?? 0), 2, ',', '.'); ?></span>
      </div>
    <?php endforeach; ?>
    <?php foreach (($pedido['participantes'] ?? array()) as $participante): ?>
      <div class="dc-resumo-row">
        <span><?php echo Helpers::e((string) ($participante['nome'] ?? '')); ?></span>
        <span class="dc-text-sm dc-text-muted"><?php echo Helpers::e((string) ($participante['status'] ?? '')); ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Cupom -->
  <?php if (empty($comprovanteAguardandoAprovacao) && empty($pedidoPagoOuAprovado)): ?>
  <div class="dc-section-card">
    <div class="dc-section-card-title">Cupom de desconto</div>
    <?php if (!empty($pedido['cupom']['cupom_codigo'])): ?>
      <p class="dc-text-sm dc-text-muted" style="margin-bottom:10px;">Cupom aplicado: <strong><?php echo Helpers::e((string) $pedido['cupom']['cupom_codigo']); ?></strong></p>
    <?php endif; ?>
    <?php if ($loggedIn): ?>
      <form method="post" action="/checkout/cupom" class="dc-cupom-row">
        <input type="hidden" name="pedido_id" value="<?php echo (int) ($pedido['id'] ?? 0); ?>">
        <input class="dc-input" type="text" name="cupom_codigo" placeholder="Código do cupom" value="<?php echo Helpers::e((string) ($cupomPromocional ?? '')); ?>">
        <button type="submit" class="dc-btn dc-btn-outline">Aplicar</button>
      </form>
    <?php else: ?>
      <p class="dc-text-sm dc-text-muted">Entre na sua conta para aplicar um cupom.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Próxima etapa -->
  <div class="dc-section-card">
    <div class="dc-section-card-title"><?php echo Helpers::e($tituloProximaEtapa); ?></div>

    <?php if (!empty($pedidoSemCobranca)): ?>
      <div class="dc-callout dc-callout-success"><i class="ti ti-circle-check"></i><span>Pedido gratuito confirmado. O valor final ficou em R$ 0,00 — não há pagamento nem envio de comprovante.</span></div>
    <?php elseif (!empty($comprovanteAguardandoAprovacao)): ?>
      <div class="dc-callout dc-callout-warning"><i class="ti ti-clock"></i><span>Comprovante enviado. Aguardando aprovação da equipe para liberar o curso.</span></div>
    <?php elseif (!empty($pedidoPagoOuAprovado)): ?>
      <div class="dc-callout dc-callout-success"><i class="ti ti-circle-check"></i><span>Pagamento confirmado. O curso será liberado em breve na área do aluno.</span></div>
    <?php elseif ($pedidoCancelado): ?>
      <div class="dc-callout dc-callout-warning"><i class="ti ti-alert-triangle"></i><span>Este pedido foi cancelado e não pode ser pago. Para gerar um novo pedido, faça uma nova inscrição.</span></div>
    <?php else: ?>
      <p class="dc-text-sm dc-text-muted" style="margin-bottom:12px;"><?php echo !empty($abacatepayEnabled) ? 'Após concluir o pagamento, siga para a confirmação online.' : 'Após concluir o pagamento, siga para o envio do comprovante PIX.'; ?></p>
    <?php endif; ?>

    <?php if ($abacatepayPodeGerarCheckout && !$pedidoCancelado): ?>
      <?php if ($pedidoTemCheckoutOnline && !empty($pedido['payment_provider_payment_url'])): ?>
        <a class="dc-btn dc-btn-primary dc-btn-block" href="<?php echo Helpers::e((string) $pedido['payment_provider_payment_url']); ?>" target="_blank" rel="noopener">Continuar no pagamento online</a>
      <?php else: ?>
        <form method="post" action="/aluno/pedidos/pagar/abacatepay">
          <?php echo $csrfField ?? ''; ?>
          <input type="hidden" name="pedido_id" value="<?php echo (int) ($pedido['id'] ?? 0); ?>">
          <button type="submit" class="dc-btn dc-btn-primary dc-btn-block">Pagar com Abacate Pay</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($loggedIn && empty($pedidoSemCobranca) && empty($comprovanteAguardandoAprovacao) && empty($pedidoPagoOuAprovado)): ?>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;">
        <?php if ($pedidoCancelado): ?>
          <a class="dc-btn dc-btn-primary dc-btn-block" href="<?php echo Helpers::e($urlNovaInscricao); ?>">Nova inscrição</a>
          <a class="dc-btn dc-btn-ghost dc-btn-block" href="/meus-cursos">Ir para Meus Cursos</a>
        <?php elseif (empty($abacatepayEnabled)): ?>
          <a class="dc-btn dc-btn-primary dc-btn-block" href="/checkout/comprovante?pedido_id=<?php echo (int) ($pedido['id'] ?? 0); ?>">Prosseguir para pagamento <i class="ti ti-arrow-right"></i></a>
          <a class="dc-btn dc-btn-ghost dc-btn-block" href="/meus-cursos">Ir para Meus Cursos</a>
        <?php elseif ($abacatepayPodeGerarCheckout): ?>
          <a class="dc-btn dc-btn-primary dc-btn-block" href="/checkout/comprovante?pedido_id=<?php echo (int) ($pedido['id'] ?? 0); ?>">Pagar com Pix</a>
          <a class="dc-btn dc-btn-ghost dc-btn-block" href="/meus-cursos">Ir para Meus Cursos</a>
        <?php else: ?>
          <a class="dc-btn dc-btn-ghost dc-btn-block" href="/meus-cursos">Ir para Meus Cursos</a>
        <?php endif; ?>
      </div>
    <?php elseif ($loggedIn && !empty($pedidoSemCobranca)): ?>
      <a class="dc-btn dc-btn-primary dc-btn-block" style="margin-top:12px;" href="/minha-pagina">Ir para Minha Página</a>
    <?php elseif ($loggedIn): ?>
      <a class="dc-btn dc-btn-ghost dc-btn-block" style="margin-top:12px;" href="/meus-cursos">Ir para Meus Cursos</a>
    <?php else: ?>
      <p class="dc-text-sm dc-text-muted" style="margin:12px 0;">Entre ou crie conta para concluir a compra.</p>
      <div style="display:flex;gap:8px;">
        <a class="dc-btn dc-btn-primary" href="/login">Entrar</a>
        <a class="dc-btn dc-btn-ghost" href="/cadastro">Criar conta</a>
      </div>
    <?php endif; ?>
  </div>
</div>
