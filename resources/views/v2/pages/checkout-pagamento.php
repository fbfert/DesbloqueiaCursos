<?php
use App\Core\Helpers;

// -----------------------------------------------------------------------------
// Fase 2.12B — Casca V2 de PAGAMENTO.
// Apresenta apenas o que o backend já autoriza no checkout atual. Não calcula
// valores, não gera PIX/QR, não cria transação e não confirma pagamento. O
// início do AbacatePay é um POST HTML nativo ao endpoint real existente; o CSRF
// é injetado automaticamente por Csrf::injectIntoHtml no formulário POST.
// -----------------------------------------------------------------------------

$pedido = isset($pedido) && is_array($pedido) ? $pedido : array();
$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;
$loggedIn = !empty($loggedIn);

$pedidoSemCobranca = !empty($pedidoSemCobranca);
$comprovanteAguardando = !empty($comprovanteAguardandoAprovacao);
$pago = !empty($pedidoPagoOuAprovado);
$abacatepayEnabled = !empty($abacatepayEnabled);

$statusNorm = strtolower((string) ($pedido['status'] ?? ''));
$cancelado = $statusNorm === 'cancelado';

$abacatepayActionUrl = isset($abacatepayActionUrl) ? (string) $abacatepayActionUrl : '/aluno/pedidos/pagar/abacatepay';
$comprovanteUrl = isset($comprovanteUrl) ? (string) $comprovanteUrl : '';
$resumoUrl = isset($resumoUrl) ? (string) $resumoUrl : '';
$pedidoId = (int) ($pedido['id'] ?? 0);

// Elegibilidade do AbacatePay: mesma condição real da tela de resumo legada.
$abacatepayPodeGerarCheckout = $abacatepayEnabled
    && $loggedIn
    && $pedidoId > 0
    && !$pedidoSemCobranca
    && !in_array($statusNorm, array('pago', 'aprovado', 'cancelado', 'reembolsado'), true);

// Existência de checkout online só é usada para o RÓTULO do botão; a URL do
// provider nunca é montada/exposta no cliente — quem decide reutilizar ou criar
// é o endpoint real do backend.
$temCheckoutOnline = !empty($pedido['payment_provider_payment_url']) || !empty($pedido['payment_provider_checkout_id']);
$rotuloAbacatepay = $temCheckoutOnline ? 'Continuar no pagamento online' : 'Pagar com AbacatePay';

// PIX/manual disponível quando o pedido ainda pode receber pagamento manual.
$podePagarManual = $loggedIn
    && $pedidoId > 0
    && !$pedidoSemCobranca
    && !$pago
    && !$cancelado
    && !$comprovanteAguardando;

// Estado geral: há alguma ação de pagamento a renderizar?
$pedidoElegivelPagamento = !$pedidoSemCobranca && !$comprovanteAguardando && !$pago && !$cancelado;

$etapas = array(1 => 'Inscrição', 2 => 'Participantes', 3 => 'Resumo', 4 => 'Pagamento');
$etapaAtual = 4;
?>
<script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>

<section class="v2-container v2-checkout">
  <header class="v2-checkout-hero">
    <span class="v2-badge v2-badge-novo">Checkout</span>
    <h1 class="v2-h2" style="margin:6px 0;">Pagamento</h1>
    <p class="v2-muted">Pedido <?php echo Helpers::e((string) ($pedido['codigo'] ?? '')); ?></p>
  </header>

  <?php require BASE_PATH . '/resources/views/v2/partials/checkout-steps.php'; ?>

  <div id="v2-checkout-feedback" tabindex="-1" aria-live="assertive">
    <?php if (!empty($success)): ?>
      <div class="v2-callout v2-callout-success" role="status"><i class="ti ti-circle-check" aria-hidden="true"></i><span><?php echo Helpers::e(is_array($success) ? (string) ($success['message'] ?? '') : (string) $success); ?></span></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="v2-callout v2-callout-danger" role="alert"><i class="ti ti-alert-triangle" aria-hidden="true"></i><span><?php foreach ($errors as $erro): ?><?php echo Helpers::e((string) $erro); ?><br><?php endforeach; ?></span></div>
    <?php endif; ?>
  </div>

  <section class="v2-block" aria-labelledby="v2-pag-resumo-titulo">
    <h2 id="v2-pag-resumo-titulo" class="v2-h3" style="margin:0 0 8px;">Resumo do pedido</h2>
    <dl class="v2-sumlist">
      <div class="v2-sumrow"><dt>Pagador</dt><dd><?php echo Helpers::e((string) ($pedido['pagador_nome'] ?? '')); ?></dd></div>
      <div class="v2-sumrow"><dt>Status</dt><dd><?php echo Helpers::e((string) ($pedido['status'] ?? '')); ?></dd></div>
      <div class="v2-sumrow v2-sumrow--total"><dt>Total</dt><dd><strong><?php echo Helpers::e((string) ($pedido['total'] ?? '')); ?></strong></dd></div>
    </dl>
  </section>

  <section class="v2-block" aria-labelledby="v2-pag-metodos-titulo">
    <h2 id="v2-pag-metodos-titulo" class="v2-h3" style="margin:0 0 8px;">Como pagar</h2>

    <?php if ($pedidoSemCobranca): ?>
      <div class="v2-callout v2-callout-success" role="status">
        <i class="ti ti-gift" aria-hidden="true"></i>
        <span><strong>Pedido gratuito.</strong> O valor final ficou em R$ 0,00 — não há pagamento nem envio de comprovante. A liberação segue o fluxo gratuito.</span>
      </div>
      <div class="v2-quiz-actions"><a class="v2-btn v2-btn-primary" href="/v2/aluno">Ir para minha área</a></div>

    <?php elseif ($comprovanteAguardando): ?>
      <div class="v2-callout v2-callout-info" role="status">
        <i class="ti ti-clock-hour-4" aria-hidden="true"></i>
        <span><strong>Comprovante em análise.</strong> Recebemos seu comprovante e ele está aguardando aprovação. Assim que for confirmado, o curso será liberado na sua área.</span>
      </div>
      <div class="v2-quiz-actions">
        <a class="v2-btn v2-btn-ghost" href="/v2/aluno">Minha área</a>
        <a class="v2-btn v2-btn-ghost" href="/v2/aluno/?aba=pedidos">Meus pedidos</a>
      </div>

    <?php elseif ($pago): ?>
      <div class="v2-callout v2-callout-success" role="status">
        <i class="ti ti-circle-check" aria-hidden="true"></i>
        <span><strong>Pagamento confirmado.</strong> Este pedido já está pago. O curso será liberado na sua área do aluno.</span>
      </div>
      <div class="v2-quiz-actions"><a class="v2-btn v2-btn-primary" href="/v2/aluno">Ir para minha área</a></div>

    <?php elseif ($cancelado): ?>
      <div class="v2-callout v2-callout-warning" role="alert">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <span><strong>Pedido cancelado.</strong> Este pedido foi cancelado e não pode ser pago. Faça uma nova inscrição para gerar um novo pedido.</span>
      </div>
      <div class="v2-quiz-actions"><a class="v2-btn v2-btn-ghost" href="/v2/catalogo/">Ver catálogo</a></div>

    <?php elseif (!$loggedIn): ?>
      <div class="v2-callout v2-callout-info" role="status">
        <i class="ti ti-lock" aria-hidden="true"></i>
        <span>Entre na sua conta para escolher a forma de pagamento deste pedido.</span>
      </div>
      <div class="v2-quiz-actions"><a class="v2-btn v2-btn-primary" href="/v2/login">Entrar</a></div>

    <?php else: ?>
      <p class="v2-muted" style="margin:0 0 14px;">Escolha uma forma de pagamento. O pagamento é iniciado somente quando você confirma abaixo.</p>

      <?php if ($abacatepayPodeGerarCheckout): ?>
        <div class="v2-block" style="margin-bottom:12px;">
          <h3 class="v2-h3" style="margin:0 0 4px;"><i class="ti ti-credit-card" aria-hidden="true"></i> Pagamento online (PIX ou cartão)</h3>
          <p class="v2-muted v2-sm" style="margin:0 0 10px;">Você será levado ao ambiente seguro do provedor de pagamento para concluir. A liberação pode levar alguns instantes após a confirmação.</p>
          <form method="post" action="<?php echo Helpers::e($abacatepayActionUrl); ?>" class="v2-pay-form" data-v2-single-submit>
            <input type="hidden" name="pedido_id" value="<?php echo $pedidoId; ?>">
            <button type="submit" class="v2-btn v2-btn-primary">
              <i class="ti ti-arrow-right" aria-hidden="true"></i> <?php echo Helpers::e($rotuloAbacatepay); ?>
            </button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($podePagarManual && $comprovanteUrl !== ''): ?>
        <div class="v2-block" style="margin-bottom:0;">
          <h3 class="v2-h3" style="margin:0 0 4px;"><i class="ti ti-file-upload" aria-hidden="true"></i> PIX com envio de comprovante</h3>
          <p class="v2-muted v2-sm" style="margin:0 0 10px;">Pague por PIX com a chave informada na próxima etapa e envie o comprovante para aprovação manual, conforme o processo atual.</p>
          <a class="v2-btn v2-btn-outline" href="<?php echo Helpers::e($comprovanteUrl); ?>">
            <i class="ti ti-arrow-right" aria-hidden="true"></i> Continuar para envio do comprovante
          </a>
        </div>
      <?php endif; ?>

      <?php if (!$abacatepayPodeGerarCheckout && !($podePagarManual && $comprovanteUrl !== '')): ?>
        <div class="v2-callout v2-callout-warning" role="status">
          <i class="ti ti-info-circle" aria-hidden="true"></i>
          <span>No momento não há forma de pagamento disponível para este pedido. Acesse seus pedidos para acompanhar o andamento.</span>
        </div>
        <div class="v2-quiz-actions"><a class="v2-btn v2-btn-ghost" href="/v2/aluno/?aba=pedidos">Meus pedidos</a></div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <div class="v2-quiz-actions" style="margin-top:6px;">
    <?php if ($resumoUrl !== ''): ?>
      <a class="v2-btn v2-btn-ghost" href="<?php echo Helpers::e($resumoUrl); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Voltar ao resumo</a>
    <?php endif; ?>
  </div>
</section>

<script>
// Progressive enhancement apenas: evita duplo clique no envio do pagamento.
// Sem cálculo, sem AJAX, sem persistência — a página funciona sem JavaScript.
(function () {
  var forms = document.querySelectorAll('form[data-v2-single-submit]');
  Array.prototype.forEach.call(forms, function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        // Desabilita no próximo tick para não bloquear o envio nativo.
        window.setTimeout(function () { btn.setAttribute('disabled', 'disabled'); }, 0);
      }
    });
  });
})();
</script>
