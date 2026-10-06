<?php
/**
 * Checkout no tema caderno — comprovante enviado. Mesmas variáveis de
 * resources/views/v2/pages/checkout-comprovante-enviado.php (pedidoCodigo,
 * cursos, totalFormatado, statusLabel, enviadoEm, minhaAreaHref). Somente
 * leitura: o comprovante aparece grampeado na folha e o carimbo
 * "COMPROVANTE EM ANÁLISE" cai sobre ele (cena do módulo `checkout`). O carimbo
 * é ornamento (aria-hidden); a situação está em texto na lista ao lado.
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';

$pedidoCodigo = isset($pedidoCodigo) ? (string) $pedidoCodigo : '';
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$totalFormatado = isset($totalFormatado) ? (string) $totalFormatado : '';
$statusLabel = isset($statusLabel) ? (string) $statusLabel : 'Em análise';
$enviadoEm = isset($enviadoEm) ? (string) $enviadoEm : '';
$minhaAreaHref = isset($minhaAreaHref) ? (string) $minhaAreaHref : '/v2/aluno/?aba=pedidos';
$success = null; // a V2 não exibe o flash nesta tela: a mensagem fixa abaixo já confirma o envio
$ckErrosLista = array();
$etapaAtual = 'comprovante';
$checklistConcluido = true;
?>
<div class="ck">
  <header class="ck-cab">
    <h1 class="t2">Comprovante enviado</h1>
    <?php if ($pedidoCodigo !== ''): ?>
    <p class="lead">Pedido <b><?= Helpers::e($pedidoCodigo) ?></b></p>
    <?php endif; ?>
  </header>

  <?php require BASE_PATH . '/resources/views/caderno/partials/checklist-checkout.php'; ?>

  <div class="ck-grade ck-enviado">
    <div class="ck-corpo">
      <div class="ck-avisos" id="ck-avisos" tabindex="-1" aria-live="polite">
        <div class="postit ok largo" role="status">
          <b>Aguarde a aprovação do seu comprovante.</b>
          Assim que a equipe concluir a análise, a situação do pedido muda em Minha área e o curso é liberado.
        </div>
      </div>
      <div class="form-acoes">
        <a class="btn" href="<?= Helpers::e($minhaAreaHref) ?>">Continuar para Minha área <?= caderno_icone('seta-dir') ?></a>
      </div>
    </div>

    <aside class="ck-lado">
      <section class="comprovante-folha" aria-labelledby="ce-dados" data-carimbar>
        <span class="grampeado-grampo" aria-hidden="true"></span>
        <h2 class="t3" id="ce-dados">Dados do pedido</h2>
        <?php if (!empty($cursos)): ?>
        <ul class="comprovante-cursos">
          <?php foreach ($cursos as $nomeCurso): ?>
          <li><?= Helpers::e((string) $nomeCurso) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <dl class="ficha-linhas">
          <?php if ($pedidoCodigo !== ''): ?>
          <div><dt>Pedido</dt><dd><?= Helpers::e($pedidoCodigo) ?></dd></div>
          <?php endif; ?>
          <div><dt>Situação</dt><dd><span class="selo tinta"><?= Helpers::e($statusLabel) ?></span></dd></div>
          <?php if ($enviadoEm !== ''): ?>
          <div><dt>Enviado em</dt><dd><?= Helpers::e($enviadoEm) ?></dd></div>
          <?php endif; ?>
          <?php if ($totalFormatado !== ''): ?>
          <div class="ck-valor"><dt>Total</dt><dd><b><?= Helpers::e($totalFormatado) ?></b></dd></div>
          <?php endif; ?>
        </dl>
        <div class="carimbo ret tinta" aria-hidden="true">COMPROVANTE EM ANÁLISE</div>
      </section>
    </aside>
  </div>
</div>
