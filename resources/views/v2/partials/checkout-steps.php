<?php
use App\Core\Helpers;
$etapaAtual = isset($etapaAtual) ? (int) $etapaAtual : 1;
// As páginas que precisam de etapas adicionais (ex.: Pagamento) injetam
// $etapas antes de incluir este partial. Sem injeção, mantém as 3 etapas
// originais do pré-pagamento — comportamento inalterado para as telas 2.12A.
$etapas = isset($etapas) && is_array($etapas) ? $etapas : array(1 => 'Inscrição', 2 => 'Participantes', 3 => 'Resumo');
?>
<ol class="v2-steps" aria-label="Etapas do checkout">
  <?php foreach ($etapas as $num => $rotulo): ?>
    <?php
    $estado = $num < $etapaAtual ? 'feito' : ($num === $etapaAtual ? 'atual' : 'pendente');
    $rotuloEstado = $estado === 'feito' ? 'concluída' : ($estado === 'atual' ? 'etapa atual' : 'pendente');
    ?>
    <li class="v2-step v2-step--<?php echo Helpers::e($estado); ?>"<?php echo $estado === 'atual' ? ' aria-current="step"' : ''; ?>>
      <span class="v2-step-num" aria-hidden="true"><?php echo $estado === 'feito' ? '✓' : (int) $num; ?></span>
      <span class="v2-step-label"><?php echo Helpers::e($rotulo); ?></span>
      <span class="v2-sr-only"> (<?php echo Helpers::e($rotuloEstado); ?>)</span>
    </li>
  <?php endforeach; ?>
</ol>
