<?php
/**
 * Checkout no tema caderno — etapa 4, pagamento. Mesmas variáveis, mesmas
 * condições e os mesmos destinos de resources/views/v2/pages/checkout-pagamento.php:
 *  - pagamento online (AbacatePay): POST nativo em $abacatepayActionUrl com
 *    pedido_id e origem_v2=1 (ocultos) e o _token injetado pelo View::render,
 *    só quando o backend habilita e o pedido pode gerar checkout;
 *  - PIX com envio de comprovante: link para $comprovanteUrl (a chave PIX e o
 *    botão de copiar ficam na etapa do comprovante, onde a V2 recebe a chave);
 *  - estados: gratuito, comprovante em análise, pago, cancelado, sem login e
 *    sem forma de pagamento disponível.
 * O envio único (sem pedido duplicado no clique duplo) é do módulo `checkout`.
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';

$pedido = isset($pedido) && is_array($pedido) ? $pedido : array();
$ckErros = caderno_ck_erros(isset($errors) ? $errors : array());
$ckErrosLista = $ckErros['lista'];
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

// Mesmas condições da V2.
$abacatepayPodeGerarCheckout = $abacatepayEnabled
    && $loggedIn
    && $pedidoId > 0
    && !$pedidoSemCobranca
    && !in_array($statusNorm, array('pago', 'aprovado', 'cancelado', 'reembolsado'), true);
$temCheckoutOnline = !empty($pedido['payment_provider_payment_url']) || !empty($pedido['payment_provider_checkout_id']);
$rotuloAbacatepay = $temCheckoutOnline ? 'Continuar no pagamento online' : 'Pagar online';
$podePagarManual = $loggedIn
    && $pedidoId > 0
    && !$pedidoSemCobranca
    && !$pago
    && !$cancelado
    && !$comprovanteAguardando;
$mostrarPix = $podePagarManual && $comprovanteUrl !== '';

$slotCupom = '';
$reciboDobravel = true;
$reciboTitulo = 'Resumo do pedido';
$etapaAtual = 'pagamento';
?>
<div class="ck">
  <header class="ck-cab">
    <h1 class="t2">Pagamento</h1>
    <p class="lead">Pedido <b><?= Helpers::e((string) ($pedido['codigo'] ?? '')) ?></b>, total de <b><?= Helpers::e(caderno_ck_dinheiro($pedido['total'] ?? 0)) ?></b>.</p>
  </header>

  <?php require BASE_PATH . '/resources/views/caderno/partials/checklist-checkout.php'; ?>
  <?php require BASE_PATH . '/resources/views/caderno/partials/checkout-avisos.php'; ?>

  <div class="ck-grade">
    <aside class="ck-lado ck-lado-recibo">
      <?php require BASE_PATH . '/resources/views/caderno/partials/recibo.php'; ?>
    </aside>

    <section class="ck-corpo" aria-labelledby="cg-como">
      <h2 class="t3" id="cg-como">Como pagar</h2>

      <?php if ($pedidoSemCobranca): ?>
      <div class="postit ok largo" role="status"><b>Pedido gratuito.</b>O valor final ficou em R$ 0,00: não há pagamento nem comprovante a enviar. A liberação segue o fluxo gratuito.</div>
      <div class="form-acoes"><a class="btn" href="/v2/aluno">Ir para minha área <?= caderno_icone('seta-dir') ?></a></div>

      <?php elseif ($comprovanteAguardando): ?>
      <div class="postit largo" role="status"><b>Comprovante em análise.</b>Recebemos seu comprovante e ele aguarda aprovação. Assim que for confirmado, o curso é liberado na sua área.</div>
      <div class="form-acoes">
        <a class="btn-sec" href="/v2/aluno">Minha área<?= caderno_ck_contorno() ?></a>
        <a class="btn-sec" href="/v2/aluno/?aba=pedidos">Meus pedidos<?= caderno_ck_contorno() ?></a>
      </div>

      <?php elseif ($pago): ?>
      <div class="postit ok largo" role="status"><b>Pagamento confirmado.</b>Este pedido já está pago. O curso será liberado na sua área do aluno.</div>
      <div class="form-acoes"><a class="btn" href="/v2/aluno">Ir para minha área <?= caderno_icone('seta-dir') ?></a></div>

      <?php elseif ($cancelado): ?>
      <div class="postit erro largo" role="alert"><b>Pedido cancelado.</b>Este pedido foi cancelado e não pode ser pago. Faça uma nova inscrição para gerar outro pedido.</div>
      <div class="form-acoes"><a class="btn-sec" href="/v2/catalogo/">Ver catálogo<?= caderno_ck_contorno() ?></a></div>

      <?php elseif (!$loggedIn): ?>
      <div class="postit largo" role="status">Entre na sua conta para escolher a forma de pagamento deste pedido.</div>
      <div class="form-acoes"><a class="btn" href="/v2/login">Entrar</a></div>

      <?php else: ?>
      <p class="ck-texto">Escolha uma forma de pagamento. Nada é cobrado antes da sua confirmação.</p>

      <div class="ck-metodos">
        <?php if ($abacatepayPodeGerarCheckout): ?>
        <div class="ck-metodo">
          <h3 class="ck-metodo-tit"><?= caderno_icone('cartao') ?>Pagamento online, por PIX ou cartão</h3>
          <p class="ck-texto">Você vai para o ambiente seguro do provedor de pagamento. A liberação pode levar alguns instantes depois da confirmação.</p>
          <form method="post" action="<?= Helpers::e($abacatepayActionUrl) ?>" class="v2-pay-form" data-native-submit data-v2-single-submit data-ck-envio>
            <input type="hidden" name="pedido_id" value="<?= $pedidoId ?>">
            <input type="hidden" name="origem_v2" value="1">
            <button type="submit" class="btn btn-laranja" data-loading-label="Abrindo o pagamento…"><?= Helpers::e($rotuloAbacatepay) ?> <?= caderno_icone('seta-dir') ?></button>
          </form>
        </div>
        <?php endif; ?>

        <?php if ($mostrarPix): ?>
        <div class="ck-metodo ck-metodo-pix">
          <h3 class="ck-metodo-tit"><?= caderno_icone('pix') ?>PIX com envio de comprovante</h3>
          <div class="postit">
            <b>Como funciona</b>
            <ol class="ck-passos">
              <li>Na próxima tela, copie a chave PIX.</li>
              <li>Faça o PIX de <?= Helpers::e(caderno_ck_dinheiro($pedido['total'] ?? 0)) ?> no app do seu banco.</li>
              <li>Envie o comprovante ali mesmo. A equipe confere e libera o curso.</li>
            </ol>
          </div>
          <div class="form-acoes">
            <a class="<?= $abacatepayPodeGerarCheckout ? 'btn-sec' : 'btn btn-laranja' ?>" href="<?= Helpers::e($comprovanteUrl) ?>">Pagar com PIX<?= $abacatepayPodeGerarCheckout ? caderno_ck_contorno() : ' ' . caderno_icone('seta-dir') ?></a>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <?php if (!$abacatepayPodeGerarCheckout && !$mostrarPix): ?>
      <div class="postit largo" role="status">No momento não há forma de pagamento disponível para este pedido. Acompanhe o andamento em Meus pedidos.</div>
      <div class="form-acoes"><a class="btn-sec" href="/v2/aluno/?aba=pedidos">Meus pedidos<?= caderno_ck_contorno() ?></a></div>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($resumoUrl !== ''): ?>
      <p class="ck-voltar"><a class="link" href="<?= Helpers::e($resumoUrl) ?>"><?= caderno_icone('seta-esq') ?>Voltar ao resumo</a></p>
      <?php endif; ?>
    </section>
  </div>
</div>
