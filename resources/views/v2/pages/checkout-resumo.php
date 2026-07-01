<?php
use App\Core\Helpers;

$pedido = isset($pedido) && is_array($pedido) ? $pedido : array();
$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;
$loggedIn = !empty($loggedIn);
$pedidoSemCobranca = !empty($pedidoSemCobranca);
$comprovanteAguardando = !empty($comprovanteAguardandoAprovacao);
$pago = !empty($pedidoPagoOuAprovado);
$statusNorm = strtolower((string) ($pedido['status'] ?? ''));
$cancelado = $statusNorm === 'cancelado';
$continuarUrl = isset($continuarPagamentoUrl) ? (string) $continuarPagamentoUrl : '';
$itens = isset($pedido['itens']) && is_array($pedido['itens']) ? $pedido['itens'] : array();
$participantes = isset($pedido['participantes']) && is_array($pedido['participantes']) ? $pedido['participantes'] : array();
$cupom = isset($pedido['cupom']) && is_array($pedido['cupom']) ? $pedido['cupom'] : array();
$etapaAtual = 3;
?>
<script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>

<section class="v2-container v2-checkout">
  <header class="v2-checkout-hero">
    <span class="v2-badge v2-badge-novo">Checkout</span>
    <h1 class="v2-h2" style="margin:6px 0;">Resumo do pedido</h1>
    <p class="v2-muted">Pedido <?php echo Helpers::e((string) ($pedido['codigo'] ?? '')); ?></p>
  </header>

  <?php require BASE_PATH . '/resources/views/v2/partials/checkout-steps.php'; ?>

  <div id="v2-checkout-feedback" tabindex="-1" aria-live="assertive">
    <?php if (!empty($success)): ?>
      <div class="v2-callout v2-callout-success" role="status"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e(is_array($success) ? (string) ($success['message'] ?? '') : (string) $success); ?></span></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="v2-callout v2-callout-danger" role="alert"><i class="ti ti-alert-triangle"></i><span><?php foreach ($errors as $erro): ?><?php echo Helpers::e((string) $erro); ?><br><?php endforeach; ?></span></div>
    <?php endif; ?>
  </div>

  <section class="v2-block">
    <h2 class="v2-h3" style="margin:0 0 8px;">Dados do pedido</h2>
    <dl class="v2-sumlist">
      <div class="v2-sumrow"><dt>Pagador</dt><dd><?php echo Helpers::e((string) ($pedido['pagador_nome'] ?? '')); ?></dd></div>
      <div class="v2-sumrow"><dt>Status</dt><dd><?php echo Helpers::e((string) ($pedido['status'] ?? '')); ?></dd></div>
      <div class="v2-sumrow"><dt>Subtotal</dt><dd><?php echo Helpers::e((string) ($pedido['subtotal'] ?? '')); ?></dd></div>
      <div class="v2-sumrow"><dt>Desconto</dt><dd><?php echo Helpers::e((string) ($pedido['desconto_total'] ?? '')); ?></dd></div>
      <?php if (!empty($cupom['cupom_codigo'])): ?>
        <div class="v2-sumrow"><dt>Cupom</dt><dd><?php echo Helpers::e((string) $cupom['cupom_codigo']); ?></dd></div>
      <?php endif; ?>
      <div class="v2-sumrow v2-sumrow--total"><dt>Total</dt><dd><strong><?php echo Helpers::e((string) ($pedido['total'] ?? '')); ?></strong></dd></div>
    </dl>
  </section>

  <?php if (!$pago): ?>
    <section class="v2-block">
      <h2 class="v2-h3" style="margin:0 0 8px;">Itens e participantes</h2>
      <?php foreach ($itens as $item): ?>
        <div class="v2-checkout-line">
          <strong><?php echo Helpers::e((string) ($item['curso_nome'] ?? '')); ?></strong>
          <span class="v2-muted v2-sm"><?php echo Helpers::e((string) ($item['turma_nome'] ?? '')); ?></span>
          <span class="v2-muted v2-sm"><?php echo (int) ($item['quantidade'] ?? 0); ?> vaga(s)</span>
          <span>R$ <?php echo number_format((float) ($item['valor_total'] ?? 0), 2, ',', '.'); ?></span>
        </div>
      <?php endforeach; ?>
      <?php foreach ($participantes as $p): ?>
        <div class="v2-checkout-line">
          <strong><?php echo Helpers::e((string) ($p['nome'] ?? '')); ?></strong>
          <span class="v2-muted v2-sm"><?php echo Helpers::e((string) ($p['email'] ?? '')); ?></span>
          <span class="v2-muted v2-sm"><?php echo Helpers::e((string) ($p['status'] ?? '')); ?></span>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <section class="v2-block">
    <h2 class="v2-h3" style="margin:0 0 8px;">Próxima etapa</h2>
    <?php if ($pago): ?>
      <p class="v2-callout v2-callout-success" role="status"><i class="ti ti-circle-check"></i><span>Pagamento confirmado. O curso será liberado na sua área do aluno.</span></p>
      <div class="v2-quiz-actions"><a class="v2-btn v2-btn-primary" href="/v2/aluno">Ir para minha área</a></div>
    <?php elseif ($cancelado): ?>
      <p class="v2-callout v2-callout-warning" role="status"><i class="ti ti-alert-triangle"></i><span>Este pedido foi cancelado. Faça uma nova inscrição para gerar um novo pedido.</span></p>
      <div class="v2-quiz-actions"><a class="v2-btn v2-btn-ghost" href="/v2/catalogo/">Ver catálogo</a></div>
    <?php else: ?>
      <p class="v2-muted">Revise os dados acima. O pagamento segue <strong>temporariamente</strong> no fluxo oficial atual — você será levado à etapa de pagamento existente.</p>
      <div class="v2-quiz-actions">
        <?php if (trim($continuarUrl) !== ''): ?>
          <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e($continuarUrl); ?>"><i class="ti ti-arrow-right"></i> Continuar para o pagamento</a>
        <?php endif; ?>
        <a class="v2-btn v2-btn-ghost" href="/v2/aluno">Minha área</a>
      </div>
    <?php endif; ?>
  </section>
</section>
