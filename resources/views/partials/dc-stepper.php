<?php
/**
 * Stepper do checkout (template v4-claude).
 * Uso: definir $stepAtual (1–4) antes do include.
 */
$stepAtual = isset($stepAtual) ? (int) $stepAtual : 1;
$steps = array(
    1 => 'Inscrição',
    2 => 'Participantes',
    3 => 'Resumo',
    4 => 'Pagamento',
);
?>
<div class="dc-stepper" aria-label="Progresso do checkout">
  <?php foreach ($steps as $n => $label): ?>
    <?php
    $cls = 'pending';
    if ($n < $stepAtual) {
        $cls = 'done';
    } elseif ($n === $stepAtual) {
        $cls = 'active';
    }
    ?>
    <div class="dc-step <?php echo $cls; ?>" data-dc-step="<?php echo $n; ?>">
      <div class="dc-step-dot">
        <?php if ($cls === 'done'): ?>
          <i class="ti ti-check"></i>
        <?php else: ?>
          <?php echo $n; ?>
        <?php endif; ?>
      </div>
      <span class="dc-step-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <?php if ($n < count($steps)): ?>
      <div class="dc-step-line <?php echo $n < $stepAtual ? 'done' : ''; ?>"></div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
