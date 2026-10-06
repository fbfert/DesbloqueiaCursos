<?php
/**
 * Checklist riscado do checkout (5 etapas). No celular vira "Etapa N de 5"
 * com a barra de marca-texto; a partir de 720 px, a lista inteira.
 *
 * Espera:
 *   $etapaAtual         'inscricao' | 'participantes' | 'resumo' | 'pagamento' | 'comprovante';
 *   $checklistConcluido opcional: true marca as cinco como feitas (comprovante já enviado).
 *
 * A etapa imediatamente anterior à atual leva .recente: o módulo `checkout`
 * de caderno.js risca essa etapa à caneta ao carregar a página (sem JS ou com
 * movimento reduzido, ela já vem riscada).
 */

use App\Core\Helpers;

$ckEtapas = array(
    'inscricao' => 'Inscrição',
    'participantes' => 'Participantes',
    'resumo' => 'Resumo',
    'pagamento' => 'Pagamento',
    'comprovante' => 'Comprovante',
);
$ckChaves = array_keys($ckEtapas);
$ckAtualIdx = array_search(isset($etapaAtual) ? (string) $etapaAtual : '', $ckChaves, true);
$ckAtualIdx = $ckAtualIdx === false ? 0 : (int) $ckAtualIdx;
$ckTudo = !empty($checklistConcluido);
$ckTotal = count($ckChaves);
$ckFeitas = $ckTudo ? $ckTotal : $ckAtualIdx;
$ckRecenteIdx = $ckTudo ? $ckTotal - 1 : $ckAtualIdx - 1;
$ckPct = $ckTudo ? 100 : (int) round(($ckAtualIdx + 1) * 100 / $ckTotal);
$ckPct0 = $ckTudo ? (int) round(($ckTotal - 1) * 100 / $ckTotal) : (int) round($ckAtualIdx * 100 / $ckTotal);
?>
<nav class="checklist" aria-label="Etapas do checkout" data-checklist>
  <p class="checklist-movel">
    <?php if ($ckTudo): ?>
    Etapas concluídas: <b><?= $ckTotal ?></b> de <?= $ckTotal ?>
    <?php else: ?>
    Etapa <b><?= $ckAtualIdx + 1 ?></b> de <?= $ckTotal ?>: <?= Helpers::e($ckEtapas[$ckChaves[$ckAtualIdx]]) ?>
    <?php endif; ?>
    <span class="checklist-barra" aria-hidden="true"><i style="--p:<?= $ckPct ?>%;--p0:<?= $ckPct0 ?>%"></i></span>
  </p>
  <ol>
    <?php foreach ($ckChaves as $i => $chave):
        $feito = $i < $ckFeitas;
        $atual = !$ckTudo && $i === $ckAtualIdx;
        $classe = $feito ? 'feito' . ($i === $ckRecenteIdx ? ' recente' : '') : ($atual ? 'atual' : '');
        $estado = $feito ? 'concluída' : ($atual ? 'etapa atual' : 'pendente');
    ?>
    <li<?= $classe !== '' ? ' class="' . $classe . '"' : '' ?><?= $atual ? ' aria-current="step"' : '' ?>>
      <?php if ($feito): ?>
      <svg viewBox="0 0 26 26" fill="none" stroke="#1F3FA8" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M13 2.5a10.5 10.5 0 1 1-.9.05"/><path class="ck-ok" d="M8 13.5l3.5 3.5 7-8"/></svg>
      <?php elseif ($atual): ?>
      <svg viewBox="0 0 26 26" fill="none" stroke="#22104A" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M13 2.5a10.5 10.5 0 1 1-.9.05"/><circle cx="13" cy="13" r="4" fill="#FF6A00" stroke="none"/></svg>
      <?php else: ?>
      <svg viewBox="0 0 26 26" fill="none" stroke="#9B93B3" stroke-width="2.2" aria-hidden="true"><circle cx="13" cy="13" r="10.5"/></svg>
      <?php endif; ?>
      <span class="rot"><?= Helpers::e($ckEtapas[$chave]) ?></span><span class="vh"> (<?= $estado ?>)</span>
    </li>
    <?php endforeach; ?>
  </ol>
</nav>
