<?php
/**
 * Checkout no tema caderno — etapa 3, resumo. Mesmas variáveis e os mesmos
 * estados de resources/views/v2/pages/checkout-resumo.php: dados do pedido
 * (no recibo serrilhado), itens e participantes (fora do estado pago) e a
 * próxima etapa (pago → minha área; cancelado → catálogo; senão, o link
 * $continuarPagamentoUrl montado no backend). Único formulário: o campo de cupom.
 *
 * $slotCupom: o recibo tem o lugar do cupom reservado; aqui ele recebe
 * o campo de cupom (pedido aberto). Vazio, o recibo mostra o cupom já aplicado.
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';

$pedido = isset($pedido) && is_array($pedido) ? $pedido : array();
$ckErros = caderno_ck_erros(isset($errors) ? $errors : array());
$ckErrosLista = $ckErros['lista'];
$success = isset($success) ? $success : null;
$loggedIn = !empty($loggedIn);
$pago = !empty($pedidoPagoOuAprovado);
$statusNorm = strtolower((string) ($pedido['status'] ?? ''));
$cancelado = $statusNorm === 'cancelado';
$continuarUrl = isset($continuarPagamentoUrl) ? (string) $continuarPagamentoUrl : '';
$itens = isset($pedido['itens']) && is_array($pedido['itens']) ? $pedido['itens'] : array();
$participantes = isset($pedido['participantes']) && is_array($pedido['participantes']) ? $pedido['participantes'] : array();
$slotCupom = isset($slotCupom) ? (string) $slotCupom : '';
// Campo de cupom (POST /v2/checkout/cupom): só com o pedido ainda aberto para pagamento.
if ($slotCupom === '' && empty($comprovanteAguardandoAprovacao) && empty($pedidoPagoOuAprovado)) {
    $cupomErro = isset($errors['cupom_codigo']) && !is_array($errors['cupom_codigo']) ? caderno_ck_erro($errors['cupom_codigo'])[0] : '';
    $cupomAplicado = isset($pedido['cupom']) && is_array($pedido['cupom']) ? $pedido['cupom'] : array();
    $cupomDesc = (float) ($pedido['desconto_total'] ?? 0);
    $cupomValor = isset($cupomPromocional) ? (string) $cupomPromocional : '';
    ob_start();
    ?>
    <div class="cupom-recorte">
      <form method="post" action="/v2/checkout/cupom" novalidate>
        <input type="hidden" name="pedido_id" value="<?= (int) ($pedido['id'] ?? 0) ?>">
        <div class="campo<?= $cupomErro !== '' ? ' erro' : '' ?>">
          <label for="cupom-codigo">Código do cupom</label>
          <input type="text" id="cupom-codigo" name="cupom_codigo" value="<?= Helpers::e($cupomValor) ?>" maxlength="80" autocomplete="off" autocapitalize="characters" spellcheck="false"<?= $cupomErro !== '' ? ' aria-invalid="true" aria-describedby="cupom-codigo-erro"' : '' ?>>
          <?php if ($cupomErro !== ''): ?><p class="erro-msg" id="cupom-codigo-erro"><?= Helpers::e($cupomErro) ?></p><?php endif; ?>
        </div>
        <button class="btn" type="submit">Aplicar cupom</button>
      </form>
      <?php if (!empty($cupomAplicado['cupom_codigo'])): ?>
      <p class="recibo-cupom-ok">Cupom aplicado: <b class="recibo-cupom"><?= Helpers::e((string) $cupomAplicado['cupom_codigo']) ?></b><?= $cupomDesc > 0 ? ' (desconto de ' . Helpers::e(caderno_ck_dinheiro($cupomDesc)) . ')' : '' ?></p>
      <?php endif; ?>
    </div>
    <?php
    $slotCupom = (string) ob_get_clean();
}
$reciboDobravel = false;
$reciboTitulo = 'Resumo do pedido';
$etapaAtual = 'resumo';

$rotulosParticipante = array(
    'pendente' => 'Pendente',
    'confirmado' => 'Confirmado',
    'ativo' => 'Ativo',
    'cancelado' => 'Cancelado',
);
?>
<div class="ck">
  <header class="ck-cab">
    <h1 class="t2">Resumo do pedido</h1>
    <p class="lead">Confira tudo antes de pagar.</p>
  </header>

  <?php require BASE_PATH . '/resources/views/caderno/partials/checklist-checkout.php'; ?>
  <?php require BASE_PATH . '/resources/views/caderno/partials/checkout-avisos.php'; ?>

  <div class="ck-grade">
    <aside class="ck-lado ck-lado-recibo">
      <?php require BASE_PATH . '/resources/views/caderno/partials/recibo.php'; ?>
    </aside>

    <div class="ck-corpo">
      <?php if (!$pago && !empty($participantes)): ?>
      <section class="ck-bloco" aria-labelledby="cr-part">
        <h2 class="t3" id="cr-part">Quem vai estudar</h2>
        <ol class="ck-chamada">
          <?php foreach ($participantes as $p):
              $ps = strtolower(trim((string) ($p['status'] ?? '')));
          ?>
          <li>
            <b><?= Helpers::e((string) ($p['nome'] ?? '')) ?></b>
            <?php if (trim((string) ($p['email'] ?? '')) !== ''): ?><small><?= Helpers::e((string) $p['email']) ?></small><?php endif; ?>
            <?php if ($ps !== ''): ?><span class="selo apagado"><?= Helpers::e(isset($rotulosParticipante[$ps]) ? $rotulosParticipante[$ps] : ucfirst(str_replace('_', ' ', $ps))) ?></span><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ol>
      </section>
      <?php endif; ?>

      <section class="ck-bloco" aria-labelledby="cr-prox">
        <h2 class="t3" id="cr-prox">Próxima etapa</h2>
        <?php if ($pago): ?>
        <div class="postit ok largo" role="status">Pagamento confirmado. O curso será liberado na sua área do aluno.</div>
        <div class="form-acoes"><a class="btn" href="/v2/aluno">Ir para minha área <?= caderno_icone('seta-dir') ?></a></div>
        <?php elseif ($cancelado): ?>
        <div class="postit largo" role="status">Este pedido foi cancelado. Faça uma nova inscrição para gerar outro pedido.</div>
        <div class="form-acoes"><a class="btn-sec" href="/v2/catalogo/">Ver catálogo<?= caderno_ck_contorno() ?></a></div>
        <?php else: ?>
        <p class="ck-texto">Se estiver tudo certo, siga para o pagamento e escolha como pagar.</p>
        <div class="form-acoes">
          <?php if (trim($continuarUrl) !== ''): ?>
          <a class="btn" href="<?= Helpers::e($continuarUrl) ?>">Continuar para o pagamento <?= caderno_icone('seta-dir') ?></a>
          <?php endif; ?>
          <a class="btn-sec" href="/v2/aluno">Minha área<?= caderno_ck_contorno() ?></a>
        </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>
