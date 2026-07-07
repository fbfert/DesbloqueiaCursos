<?php
use App\Core\Helpers;

// -----------------------------------------------------------------------------
// Fase 2.12C — Confirmação V2 do envio de comprovante PIX.
// Somente leitura, a partir do pedido JÁ autorizado por detalharCheckout(). Não
// permite novo upload, não altera status e não expõe caminho de arquivo,
// gateway, PIX automático ou detalhe técnico de armazenamento.
// -----------------------------------------------------------------------------

$pedidoCodigo = isset($pedidoCodigo) ? (string) $pedidoCodigo : '';
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$totalFormatado = isset($totalFormatado) ? (string) $totalFormatado : '';
$statusLabel = isset($statusLabel) ? (string) $statusLabel : 'Em análise';
$enviadoEm = isset($enviadoEm) ? (string) $enviadoEm : '';
$minhaAreaHref = isset($minhaAreaHref) ? (string) $minhaAreaHref : '/v2/aluno/?aba=pedidos';

$etapas = array(1 => 'Inscrição', 2 => 'Participantes', 3 => 'Resumo', 4 => 'Pagamento');
$etapaAtual = 4;
?>
<script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>

<section class="v2-container v2-checkout">
  <header class="v2-checkout-hero">
    <span class="v2-badge v2-badge-novo">Checkout</span>
    <h1 class="v2-h2" style="margin:6px 0;">Comprovante enviado com sucesso</h1>
    <?php if ($pedidoCodigo !== ''): ?>
      <p class="v2-muted">Pedido <?php echo Helpers::e($pedidoCodigo); ?></p>
    <?php endif; ?>
  </header>

  <?php require BASE_PATH . '/resources/views/v2/partials/checkout-steps.php'; ?>

  <div id="v2-checkout-feedback" tabindex="-1" aria-live="polite">
    <div class="v2-callout v2-callout-success" role="status">
      <i class="ti ti-circle-check" aria-hidden="true"></i>
      <span>
        <strong>Aguarde a aprovação do seu comprovante pelo administrador.</strong>
        Assim que a análise for concluída, o status do seu pedido será atualizado em Minha Área.
      </span>
    </div>
  </div>

  <section class="v2-block" aria-labelledby="v2-comp-env-titulo">
    <h2 id="v2-comp-env-titulo" class="v2-h3" style="margin:0 0 8px;">Dados do pedido</h2>

    <?php if (!empty($cursos)): ?>
      <?php foreach ($cursos as $nomeCurso): ?>
        <div class="v2-checkout-line">
          <strong><?php echo Helpers::e((string) $nomeCurso); ?></strong>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <dl class="v2-sumlist" style="margin-top:<?php echo empty($cursos) ? '0' : '8px'; ?>;">
      <?php if ($pedidoCodigo !== ''): ?>
        <div class="v2-sumrow"><dt>Pedido</dt><dd><?php echo Helpers::e($pedidoCodigo); ?></dd></div>
      <?php endif; ?>
      <div class="v2-sumrow"><dt>Status</dt><dd><span class="v2-badge v2-badge-gratis"><i class="ti ti-clock-hour-4" aria-hidden="true"></i> <?php echo Helpers::e($statusLabel); ?></span></dd></div>
      <?php if ($enviadoEm !== ''): ?>
        <div class="v2-sumrow"><dt>Comprovante enviado em</dt><dd><?php echo Helpers::e($enviadoEm); ?></dd></div>
      <?php endif; ?>
      <?php if ($totalFormatado !== ''): ?>
        <div class="v2-sumrow v2-sumrow--total"><dt>Total</dt><dd><strong><?php echo Helpers::e($totalFormatado); ?></strong></dd></div>
      <?php endif; ?>
    </dl>
  </section>

  <div class="v2-quiz-actions" style="margin-top:6px;">
    <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e($minhaAreaHref); ?>">
      <i class="ti ti-arrow-right" aria-hidden="true"></i> Continuar para Minha Área
    </a>
  </div>
</section>
