<?php
use App\Core\Helpers;

$pedido = isset($pedido) && is_array($pedido) ? $pedido : array();
$quantidade = isset($quantidade) ? max(1, (int) $quantidade) : 1;
$participantePrefill = isset($participantePrefill) && is_array($participantePrefill) ? $participantePrefill : array();
$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;
$loggedIn = !empty($loggedIn);
$pedidoId = (int) ($pedido['id'] ?? 0);
$pedidoItemId = !empty($pedido['itens'][0]['id']) ? (int) $pedido['itens'][0]['id'] : '';
$etapaAtual = 2;
?>
<script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>

<section class="v2-container v2-checkout">
  <header class="v2-checkout-hero">
    <span class="v2-badge v2-badge-novo">Checkout</span>
    <h1 class="v2-h2" style="margin:6px 0;">Participantes</h1>
    <p class="v2-muted">Pedido <?php echo Helpers::e((string) ($pedido['codigo'] ?? '')); ?></p>
  </header>

  <?php require BASE_PATH . '/resources/views/v2/partials/checkout-steps.php'; ?>

  <div id="v2-checkout-feedback" tabindex="-1" aria-live="assertive">
    <?php if (!empty($success)): ?>
      <div class="v2-callout v2-callout-success" role="status"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e(is_array($success) ? (string) ($success['message'] ?? '') : (string) $success); ?></span></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="v2-callout v2-callout-danger" role="alert"><i class="ti ti-alert-triangle"></i><span><?php foreach ($errors as $erro): ?><?php echo Helpers::e((string) $erro); ?><br><?php endforeach; ?></span></div>
    <?php endif; ?>
  </div>

  <section class="v2-block">
    <h2 class="v2-h3" style="margin:0 0 8px;">Resumo do pedido</h2>
    <dl class="v2-sumlist">
      <div class="v2-sumrow"><dt>Pagador</dt><dd><?php echo Helpers::e((string) ($pedido['pagador_nome'] ?? '')); ?></dd></div>
      <div class="v2-sumrow"><dt>Status</dt><dd><?php echo Helpers::e((string) ($pedido['status'] ?? '')); ?></dd></div>
      <div class="v2-sumrow"><dt>Total</dt><dd><?php echo Helpers::e((string) ($pedido['total'] ?? '')); ?></dd></div>
    </dl>
  </section>

  <?php if (!$loggedIn): ?>
    <section class="v2-block">
      <h2 class="v2-h3" style="margin:0 0 6px;">Entre para concluir</h2>
      <p class="v2-muted">O cadastro dos participantes continua disponível depois do login.</p>
      <div class="v2-quiz-actions">
        <a class="v2-btn v2-btn-primary" href="/v2/login?origem=v2_aluno">Entrar</a>
        <a class="v2-btn v2-btn-ghost" href="/v2/cadastro">Criar conta</a>
      </div>
    </section>
  <?php else: ?>
    <form class="v2-checkout-form" method="post" action="/v2/checkout/participantes?pedido_id=<?php echo $pedidoId; ?>" data-native-submit id="v2-checkout-participantes-form">
      <?php for ($i = 0; $i < $quantidade; $i++): ?>
        <?php
        $nome = $i === 0 ? (string) ($pedido['pagador_nome'] ?? '') : '';
        $cpf = $i === 0 && !empty($pedido['pagador_cpf']) ? (string) $pedido['pagador_cpf'] : '';
        $email = $i === 0 ? (string) ($pedido['pagador_email'] ?? '') : '';
        $tel = $i === 0 && !empty($pedido['pagador_telefone']) ? (string) $pedido['pagador_telefone'] : '';
        if ($i === 0 && !empty($participantePrefill)) {
            $nome = isset($participantePrefill['nome']) && $participantePrefill['nome'] !== '' ? (string) $participantePrefill['nome'] : $nome;
            if (isset($participantePrefill['cpf']) && trim((string) $participantePrefill['cpf']) !== '') { $cpf = (string) $participantePrefill['cpf']; }
            $email = isset($participantePrefill['email']) && $participantePrefill['email'] !== '' ? (string) $participantePrefill['email'] : $email;
            if (isset($participantePrefill['telefone']) && trim((string) $participantePrefill['telefone']) !== '') { $tel = (string) $participantePrefill['telefone']; }
        }
        ?>
        <fieldset class="v2-block v2-checkout-part">
          <legend class="v2-h3">Participante <?php echo $i + 1; ?></legend>
          <input type="hidden" name="participantes[<?php echo $i; ?>][pedido_item_id]" value="<?php echo Helpers::e((string) $pedidoItemId); ?>">
          <div class="v2-field">
            <label for="cp-nome-<?php echo $i; ?>">Nome</label>
            <input class="v2-input" type="text" id="cp-nome-<?php echo $i; ?>" name="participantes[<?php echo $i; ?>][nome]" value="<?php echo Helpers::e($nome); ?>" autocomplete="off">
          </div>
          <div class="v2-field">
            <label for="cp-cpf-<?php echo $i; ?>">CPF</label>
            <input class="v2-input" type="text" id="cp-cpf-<?php echo $i; ?>" name="participantes[<?php echo $i; ?>][cpf]" value="<?php echo Helpers::e($cpf); ?>" placeholder="000.000.000-00" inputmode="numeric" data-mask-cpf autocomplete="off">
          </div>
          <div class="v2-field">
            <label for="cp-email-<?php echo $i; ?>">E-mail</label>
            <input class="v2-input" type="email" id="cp-email-<?php echo $i; ?>" name="participantes[<?php echo $i; ?>][email]" value="<?php echo Helpers::e($email); ?>" autocomplete="off">
          </div>
          <div class="v2-field">
            <label for="cp-tel-<?php echo $i; ?>">Telefone</label>
            <input class="v2-input" type="text" id="cp-tel-<?php echo $i; ?>" name="participantes[<?php echo $i; ?>][telefone]" value="<?php echo Helpers::e($tel); ?>" inputmode="tel" autocomplete="off">
          </div>
        </fieldset>
      <?php endfor; ?>

      <div class="v2-quiz-actions">
        <button type="submit" class="v2-btn v2-btn-primary" data-checkout-btn data-loading-label="Salvando…"><i class="ti ti-arrow-right"></i> Salvar participantes</button>
        <a class="v2-btn v2-btn-ghost" href="/v2/checkout/resumo?pedido_id=<?php echo $pedidoId; ?>">Ver resumo</a>
      </div>
    </form>
  <?php endif; ?>
</section>
