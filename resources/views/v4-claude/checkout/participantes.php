<?php use App\Core\Helpers; ?>
<?php
// Incluído por checkout/participantes.php quando template = v4-claude.
$pedido    = isset($pedido) && is_array($pedido) ? $pedido : array();
$quantidade = isset($quantidade) ? max(1, (int) $quantidade) : 1;
$participantePrefill = isset($participantePrefill) && is_array($participantePrefill) ? $participantePrefill : array();
$errors    = isset($errors) && is_array($errors) ? $errors : array();
$loggedIn  = !empty($loggedIn);
?>

<?php $stepAtual = 2; include BASE_PATH . '/resources/views/partials/dc-stepper.php'; ?>

<div class="dc-checkout-body dc-container">

  <?php if (!empty($errors)): ?>
    <div class="dc-callout dc-callout-danger"><i class="ti ti-alert-triangle"></i><span><?php foreach ($errors as $err): ?><?php echo Helpers::e((string) $err); ?><br><?php endforeach; ?></span></div>
  <?php endif; ?>

  <!-- Resumo do pedido -->
  <div class="dc-section-card">
    <div class="dc-section-card-title">Resumo do pedido</div>
    <div class="dc-resumo-row"><span>Pagador</span><span><?php echo Helpers::e((string) ($pedido['pagador_nome'] ?? '')); ?></span></div>
    <div class="dc-resumo-row"><span>Status</span><span><?php echo Helpers::e((string) ($pedido['status'] ?? '')); ?></span></div>
    <div class="dc-resumo-row dc-resumo-total"><span>Total</span><span class="dc-price-val"><?php echo Helpers::e((string) ($pedido['total'] ?? '')); ?></span></div>
  </div>

  <?php if (!$loggedIn): ?>
    <div class="dc-section-card">
      <div class="dc-section-card-title">Entre para concluir</div>
      <p class="dc-text-sm dc-text-muted" style="margin-bottom:12px;">O cadastro dos participantes continua disponível depois do login.</p>
      <div style="display:flex;gap:8px;">
        <a href="/login" class="dc-btn dc-btn-primary">Entrar</a>
        <a href="/cadastro" class="dc-btn dc-btn-ghost">Criar conta</a>
      </div>
    </div>
  <?php else: ?>
    <form class="admin-form checkout-form" method="post" action="/checkout/participantes?pedido_id=<?php echo (int) ($pedido['id'] ?? 0); ?>">
      <?php for ($i = 0; $i < $quantidade; $i++): ?>
        <?php
        $nomeParticipante = $i === 0 ? (string) ($pedido['pagador_nome'] ?? '') : '';
        $cpfParticipante = $i === 0 && !empty($pedido['pagador_cpf']) ? (string) $pedido['pagador_cpf'] : '';
        $emailParticipante = $i === 0 ? (string) ($pedido['pagador_email'] ?? '') : '';
        $telefoneParticipante = $i === 0 && !empty($pedido['pagador_telefone']) ? (string) $pedido['pagador_telefone'] : '';

        if ($i === 0 && !empty($participantePrefill)) {
            $nomeParticipante = isset($participantePrefill['nome']) ? $participantePrefill['nome'] : $nomeParticipante;
            if (isset($participantePrefill['cpf']) && trim((string) $participantePrefill['cpf']) !== '') {
                $cpfParticipante = $participantePrefill['cpf'];
            }
            $emailParticipante = isset($participantePrefill['email']) ? $participantePrefill['email'] : $emailParticipante;
            if (isset($participantePrefill['telefone']) && trim((string) $participantePrefill['telefone']) !== '') {
                $telefoneParticipante = $participantePrefill['telefone'];
            }
        }
        ?>
        <div class="dc-section-card">
          <div class="dc-section-card-title">Participante <?php echo $i + 1; ?></div>
          <input type="hidden" name="participantes[<?php echo $i; ?>][pedido_item_id]" value="<?php echo !empty($pedido['itens'][0]['id']) ? (int) $pedido['itens'][0]['id'] : ''; ?>">
          <div class="dc-field">
            <label>Nome</label>
            <input class="dc-input" type="text" name="participantes[<?php echo $i; ?>][nome]" value="<?php echo Helpers::e($nomeParticipante); ?>">
          </div>
          <div class="dc-field">
            <label>CPF</label>
            <input class="dc-input" type="text" id="<?php echo $i === 0 ? 'participante-1-cpf' : ''; ?>" name="participantes[<?php echo $i; ?>][cpf]" value="<?php echo Helpers::e($cpfParticipante); ?>" <?php echo $i === 0 ? 'data-skip-old-input="1"' : ''; ?>>
          </div>
          <div class="dc-field">
            <label>E-mail</label>
            <input class="dc-input" type="email" name="participantes[<?php echo $i; ?>][email]" value="<?php echo Helpers::e($emailParticipante); ?>">
          </div>
          <div class="dc-field">
            <label>WhatsApp</label>
            <input class="dc-input" type="text" id="<?php echo $i === 0 ? 'participante-1-telefone' : ''; ?>" name="participantes[<?php echo $i; ?>][telefone]" value="<?php echo Helpers::e($telefoneParticipante); ?>" <?php echo $i === 0 ? 'data-skip-old-input="1"' : ''; ?>>
          </div>
        </div>
      <?php endfor; ?>

      <div class="dc-checkout-footer">
        <button type="submit" class="dc-btn dc-btn-primary dc-btn-block">Confirmar participantes <i class="ti ti-arrow-right"></i></button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php if ($loggedIn): ?>
<script>
(function () {
    try {
        var campoCpf = document.getElementById('participante-1-cpf');
        var campoTelefone = document.getElementById('participante-1-telefone');
        if (campoCpf && !campoCpf.value) {
            campoCpf.value = sessionStorage.getItem('checkout_pagador_cpf') || '';
        }
        if (campoTelefone && !campoTelefone.value) {
            campoTelefone.value = sessionStorage.getItem('checkout_pagador_telefone') || '';
        }
    } catch (e) {}
})();
</script>
<?php endif; ?>
