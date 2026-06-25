<?php use App\Core\Helpers; ?>
<?php
// Incluído por checkout/comprovante.php quando template = v4-claude.
$pedido   = isset($pedido) && is_array($pedido) ? $pedido : array();
$errors   = isset($errors) && is_array($errors) ? $errors : array();
$success  = isset($success) ? (string) $success : '';
$loggedIn = !empty($loggedIn);
$pixKey   = 'cpeducacursos@gmail.com';
$totalTexto = isset($pedido['total']) ? (string) $pedido['total'] : '';
?>

<?php $stepAtual = 4; include BASE_PATH . '/resources/views/partials/dc-stepper.php'; ?>

<div class="dc-checkout-body dc-container">

  <?php if ($success !== ''): ?>
    <div class="dc-callout dc-callout-success"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e($success); ?></span></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="dc-callout dc-callout-danger"><i class="ti ti-alert-triangle"></i><span><?php foreach ($errors as $err): ?><?php echo Helpers::e((string) $err); ?><br><?php endforeach; ?></span></div>
  <?php endif; ?>

  <!-- PIX -->
  <div class="dc-section-card dc-pix-box">
    <?php if ($totalTexto !== ''): ?>
      <div class="dc-pix-valor"><?php echo Helpers::e($totalTexto); ?></div>
    <?php endif; ?>
    <div class="dc-pix-label">Pagamento via PIX</div>
    <div class="dc-pix-qr-placeholder"><i class="ti ti-qrcode"></i></div>
    <div class="dc-pix-chave">
      <i class="ti ti-key" style="color:var(--dc-laranja);"></i>
      <input type="text" readonly value="<?php echo Helpers::e($pixKey); ?>" aria-label="Chave PIX">
    </div>
    <button type="button" class="dc-btn dc-btn-outline dc-btn-block dc-pix-copy" data-dc-copy-pix="<?php echo Helpers::e($pixKey); ?>">
      <i class="ti ti-copy"></i> Copiar chave PIX
    </button>
  </div>

  <div class="dc-callout dc-callout-warning">
    <i class="ti ti-clock"></i>
    <span>Após pagar, envie o comprovante abaixo. O acesso ao curso é liberado após aprovação manual pela equipe.</span>
  </div>

  <?php if ($loggedIn): ?>
    <!-- Upload do comprovante -->
    <form method="post" action="/checkout/comprovante" enctype="multipart/form-data" id="dc-comprovante-form">
      <?php echo $csrfField ?? ''; ?>
      <input type="hidden" name="pedido_id" value="<?php echo (int) ($pedido['id'] ?? 0); ?>">
      <input type="file" id="dc-comprovante-input" name="comprovante" accept="image/png,image/jpeg,application/pdf" required style="display:none;">

      <div id="dc-upload-area" class="dc-upload-area">
        <i class="ti ti-upload"></i>
        <p>Toque para enviar o comprovante</p>
        <span>PNG, JPG ou PDF</span>
      </div>

      <div id="dc-upload-preview" class="dc-upload-preview" style="display:none;">
        <div class="dc-upload-preview-icon"><i class="ti ti-file-check"></i></div>
        <div class="dc-upload-preview-info">
          <span id="dc-upload-name">arquivo</span>
          <span id="dc-upload-size" class="dc-text-muted dc-text-sm"></span>
        </div>
        <button type="button" id="dc-upload-remove" class="dc-upload-remove"><i class="ti ti-x"></i></button>
      </div>

      <div class="dc-checkout-footer">
        <button type="submit" id="dc-submit-comprovante" class="dc-btn dc-btn-primary dc-btn-block" disabled>
          <i class="ti ti-send"></i> Enviar comprovante
        </button>
      </div>
    </form>
  <?php else: ?>
    <div class="dc-section-card" style="text-align:center;">
      <div class="dc-section-card-title">Entre para enviar o comprovante</div>
      <a href="/login" class="dc-btn dc-btn-primary dc-btn-block">Entrar</a>
    </div>
  <?php endif; ?>
</div>
