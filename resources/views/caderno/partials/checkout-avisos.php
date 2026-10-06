<?php
/**
 * Avisos do checkout (retorno do servidor após o PRG), como post-its.
 *
 * Espera:
 *   $success      o flash de sucesso da V2 (string ou array com 'message');
 *   $ckErrosLista textos de erro já passados por caderno_ck_erros().
 *
 * O contêiner é o mesmo da V2 (foco programático após erro, aria-live):
 * o módulo `checkout` de caderno.js leva o foco para cá quando há erro.
 */

use App\Core\Helpers;

$ckAvisoOk = '';
if (!empty($success)) {
    $ckAvisoOk = is_array($success) ? (string) ($success['message'] ?? '') : (string) $success;
    $ckAvisoOk = caderno_ck_erro($ckAvisoOk)[0];
}
$ckErrosLista = isset($ckErrosLista) && is_array($ckErrosLista) ? $ckErrosLista : array();
?>
<div class="ck-avisos" id="ck-avisos" tabindex="-1" aria-live="assertive">
  <?php if ($ckAvisoOk !== ''): ?>
  <div class="postit ok largo" role="status"><?= Helpers::e($ckAvisoOk) ?></div>
  <?php endif; ?>
  <?php if (!empty($ckErrosLista)): ?>
  <div class="postit erro largo" role="alert" data-ck-erro>
    <b><?= count($ckErrosLista) > 1 ? 'Confira estes pontos:' : 'Confira este ponto:' ?></b>
    <?php if (count($ckErrosLista) === 1): ?>
    <?= Helpers::e($ckErrosLista[0]) ?>
    <?php else: ?>
    <ul>
      <?php foreach ($ckErrosLista as $ckErro): ?>
      <li><?= Helpers::e($ckErro) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
