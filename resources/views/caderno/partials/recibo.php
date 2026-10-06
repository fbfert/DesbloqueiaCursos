<?php
/**
 * Recibo serrilhado do pedido (resumo do checkout no tema caderno).
 *
 * Espera:
 *   $pedido          o pedido no formato do resumo V2 (detalharCheckout): codigo,
 *                    status, pagador_nome, subtotal, desconto_total, total,
 *                    itens[] (curso_nome, turma_nome, quantidade, valor_total)
 *                    e cupom (cupom_codigo) quando houver;
 *   $slotCupom       opcional: HTML já escapado posto no lugar do cupom, entre o
 *                    desconto e o total (a tarefa 10 põe aqui o campo de cupom).
 *                    Vazio: mostra o cupom já aplicado, como a V2;
 *   $reciboDobravel  opcional: true embrulha o recibo num <details> com o total
 *                    no resumo (etapas de pagamento e comprovante: no celular
 *                    fica recolhido; o módulo `checkout` abre a partir de 900 px);
 *   $reciboTitulo    opcional: título do recibo (padrão "Resumo do pedido").
 */

use App\Core\Helpers;

require_once __DIR__ . '/checkout-util.php';

$rcPedido = isset($pedido) && is_array($pedido) ? $pedido : array();
$rcSlot = isset($slotCupom) ? (string) $slotCupom : '';
$rcDobravel = !empty($reciboDobravel);
$rcTitulo = isset($reciboTitulo) && (string) $reciboTitulo !== '' ? (string) $reciboTitulo : 'Resumo do pedido';
$rcItens = isset($rcPedido['itens']) && is_array($rcPedido['itens']) ? $rcPedido['itens'] : array();
$rcCupom = isset($rcPedido['cupom']) && is_array($rcPedido['cupom']) ? $rcPedido['cupom'] : array();
$rcCodigo = (string) ($rcPedido['codigo'] ?? '');
$rcDesconto = (float) ($rcPedido['desconto_total'] ?? 0);
$rcTotal = caderno_ck_dinheiro($rcPedido['total'] ?? 0);
$rcId = 'rc-' . (int) ($rcPedido['id'] ?? 0);
?>
<?php if ($rcDobravel): ?>
<details class="recibo-dobra" data-recibo-dobra>
  <summary><span><?= Helpers::e($rcTitulo) ?></span><b><?= Helpers::e($rcTotal) ?></b></summary>
<?php endif; ?>
<section class="recibo" aria-labelledby="<?= $rcId ?>-tit">
  <h2 id="<?= $rcId ?>-tit"><?= Helpers::e($rcTitulo) ?></h2>
  <?php if ($rcCodigo !== ''): ?>
  <p class="recibo-cod">Pedido <b><?= Helpers::e($rcCodigo) ?></b></p>
  <?php endif; ?>

  <?php if (!empty($rcItens)): ?>
  <ul class="linhas recibo-itens">
    <?php foreach ($rcItens as $rcItem):
        $rcQtd = (int) ($rcItem['quantidade'] ?? 0);
        $rcTurma = trim((string) ($rcItem['turma_nome'] ?? ''));
    ?>
    <li class="linha">
      <span><b><?= Helpers::e((string) ($rcItem['curso_nome'] ?? 'Curso')) ?></b>
        <small><?= $rcTurma !== '' ? Helpers::e($rcTurma) . ', ' : '' ?><?= $rcQtd ?> <?= $rcQtd === 1 ? 'vaga' : 'vagas' ?></small></span>
      <span class="valor"><?= Helpers::e(caderno_ck_dinheiro($rcItem['valor_total'] ?? 0)) ?></span>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <dl>
    <?php if (trim((string) ($rcPedido['pagador_nome'] ?? '')) !== ''): ?>
    <div><dt>Pagador</dt><dd><?= Helpers::e((string) $rcPedido['pagador_nome']) ?></dd></div>
    <?php endif; ?>
    <div><dt>Situação</dt><dd><?= Helpers::e(caderno_ck_status($rcPedido['status'] ?? '')) ?></dd></div>
    <div><dt>Subtotal</dt><dd><?= Helpers::e(caderno_ck_dinheiro($rcPedido['subtotal'] ?? 0)) ?></dd></div>
    <?php if ($rcDesconto > 0): ?>
    <div class="desconto"><dt>Desconto</dt><dd>− <?= Helpers::e(caderno_ck_dinheiro($rcDesconto)) ?></dd></div>
    <?php endif; ?>
    <?php if ($rcSlot === '' && !empty($rcCupom['cupom_codigo'])): ?>
    <div><dt>Cupom</dt><dd class="recibo-cupom"><?= Helpers::e((string) $rcCupom['cupom_codigo']) ?></dd></div>
    <?php endif; ?>
  </dl>

  <?php if ($rcSlot !== ''): ?>
  <div class="recibo-slot"><?= $rcSlot ?></div>
  <?php endif; ?>

  <div class="total"><span>Total</span><strong><?= Helpers::e($rcTotal) ?></strong></div>
</section>
<?php if ($rcDobravel): ?>
</details>
<?php endif; ?>
