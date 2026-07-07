<?php
use App\Core\Helpers;

// -----------------------------------------------------------------------------
// Fase 2.12C — Casca V2 do COMPROVANTE PIX.
// Apresenta apenas o que o backend já autoriza (pedido carregado por
// PedidoService::detalharCheckout com checagem de propriedade). Não calcula
// valores, não gera PIX/QR, não cria transação e não aprova pagamento. O envio
// é um POST HTML nativo (multipart) à rota V2 dedicada, que delega ao mesmo
// ComprovantePixService do fluxo legado; o CSRF é injetado automaticamente por
// Csrf::injectIntoHtml no formulário POST. Funciona sem JavaScript.
// -----------------------------------------------------------------------------

$pedido = isset($pedido) && is_array($pedido) ? $pedido : array();
$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;

$canSeePix = !empty($canSeePix);
$pedidoPago = !empty($pedidoPago);
$pedidoBloqueado = !empty($pedidoBloqueado);
$comprovanteAguardando = !empty($comprovanteAguardando);
$podeEnviar = !empty($podeEnviarComprovante);
$exigeMotivoReenvio = !empty($exigeMotivoReenvio);
$estadoReenvio = !empty($estadoReenvio);

$pixKey = isset($pixKey) ? (string) $pixKey : '';
$enviarUrl = isset($enviarUrl) ? (string) $enviarUrl : '/v2/checkout/comprovante/enviar';
$pagamentoUrl = isset($pagamentoUrl) ? (string) $pagamentoUrl : '';

$pedidoId = (int) ($pedido['id'] ?? 0);
$statusRaw = strtolower(trim((string) ($pedido['status'] ?? '')));
$itens = isset($pedido['itens']) && is_array($pedido['itens']) ? $pedido['itens'] : array();
$comprovantes = isset($pedido['comprovantes']) && is_array($pedido['comprovantes']) ? $pedido['comprovantes'] : array();
$totalNumerico = (float) ($pedido['total'] ?? 0);
$valorPrefill = number_format($totalNumerico, 2, '.', '');

// Rótulos de status apenas para APRESENTAÇÃO (derivados do status real do
// backend; não constituem lista paralela de autorização).
$statusLabels = array(
    'rascunho' => 'Rascunho',
    'aguardando_pagamento' => 'Aguardando pagamento',
    'pendencia' => 'Pendência',
    'aguardando_reenvio' => 'Aguardando reenvio do comprovante',
    'comprovante_enviado' => 'Comprovante enviado',
    'em_analise' => 'Em análise',
    'aprovado' => 'Aprovado',
    'pago' => 'Pago',
    'cancelado' => 'Cancelado',
    'expirado' => 'Expirado',
    'reembolsado' => 'Reembolsado',
);
$statusLabel = isset($statusLabels[$statusRaw]) ? $statusLabels[$statusRaw] : ($statusRaw !== '' ? ucfirst(str_replace('_', ' ', $statusRaw)) : '—');

$comprovanteStatusLabels = array(
    'pendente' => 'Em análise',
    'aprovado' => 'Aprovado',
    'reprovado' => 'Devolvido para reenvio',
);

$etapas = array(1 => 'Inscrição', 2 => 'Participantes', 3 => 'Resumo', 4 => 'Pagamento');
$etapaAtual = 4;
?>
<script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>

<section class="v2-container v2-checkout">
  <header class="v2-checkout-hero">
    <span class="v2-badge v2-badge-novo">Checkout</span>
    <h1 class="v2-h2" style="margin:6px 0;">Comprovante PIX</h1>
    <p class="v2-muted">Pedido <?php echo Helpers::e((string) ($pedido['codigo'] ?? '')); ?></p>
  </header>

  <?php require BASE_PATH . '/resources/views/v2/partials/checkout-steps.php'; ?>

  <div id="v2-checkout-feedback" tabindex="-1" aria-live="assertive">
    <?php if (!empty($success)): ?>
      <div class="v2-callout v2-callout-success" role="status"><i class="ti ti-circle-check" aria-hidden="true"></i><span><?php echo Helpers::e(is_array($success) ? (string) ($success['message'] ?? '') : (string) $success); ?></span></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="v2-callout v2-callout-danger" role="alert"><i class="ti ti-alert-triangle" aria-hidden="true"></i><span><?php foreach ($errors as $erro): ?><?php echo Helpers::e((string) $erro); ?><br><?php endforeach; ?></span></div>
    <?php endif; ?>
  </div>

  <section class="v2-block" aria-labelledby="v2-comp-resumo-titulo">
    <h2 id="v2-comp-resumo-titulo" class="v2-h3" style="margin:0 0 8px;">Resumo do pedido</h2>
    <?php if (!empty($itens)): ?>
      <?php foreach ($itens as $item): ?>
        <div class="v2-checkout-line">
          <strong><?php echo Helpers::e((string) ($item['curso_nome'] ?? 'Curso')); ?></strong>
          <?php if (trim((string) ($item['turma_nome'] ?? '')) !== ''): ?>
            <span class="v2-muted v2-sm"><?php echo Helpers::e((string) $item['turma_nome']); ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <dl class="v2-sumlist" style="margin-top:8px;">
      <div class="v2-sumrow"><dt>Status</dt><dd><?php echo Helpers::e($statusLabel); ?></dd></div>
      <div class="v2-sumrow v2-sumrow--total"><dt>Total</dt><dd><strong>R$ <?php echo number_format($totalNumerico, 2, ',', '.'); ?></strong></dd></div>
    </dl>
  </section>

  <?php if ($podeEnviar && $canSeePix && $pixKey !== ''): ?>
    <section class="v2-block" aria-labelledby="v2-comp-pix-titulo">
      <h2 id="v2-comp-pix-titulo" class="v2-h3" style="margin:0 0 4px;"><i class="ti ti-qrcode" aria-hidden="true"></i> Chave PIX</h2>
      <p class="v2-muted v2-sm" style="margin:0 0 10px;">Faça o PIX para a chave abaixo e depois anexe o comprovante. A análise segue o processo atual da equipe.</p>
      <div class="v2-field" data-pix-key-block>
        <label for="v2-comp-pixkey">Chave PIX</label>
        <input class="v2-input" type="text" id="v2-comp-pixkey" value="<?php echo Helpers::e($pixKey); ?>" readonly data-pix-key-input>
        <button type="button" class="v2-btn v2-btn-ghost v2-btn-sm" style="margin-top:8px;" data-pix-copy data-copy-label="Copiar chave" data-copied-label="Copiada!"><i class="ti ti-copy" aria-hidden="true"></i> Copiar chave</button>
        <span class="v2-muted v2-sm" aria-live="polite" data-pix-feedback></span>
      </div>
    </section>
  <?php endif; ?>

  <section class="v2-block" aria-labelledby="v2-comp-acao-titulo">
    <h2 id="v2-comp-acao-titulo" class="v2-h3" style="margin:0 0 8px;">Comprovante</h2>

    <?php if ($pedidoPago): ?>
      <div class="v2-callout v2-callout-success" role="status">
        <i class="ti ti-circle-check" aria-hidden="true"></i>
        <span><strong>Pagamento confirmado.</strong> Este pedido já está pago — não há comprovante a enviar. O curso será liberado na sua área do aluno.</span>
      </div>
      <div class="v2-quiz-actions"><a class="v2-btn v2-btn-primary" href="/v2/aluno">Ir para minha área</a></div>

    <?php elseif ($pedidoBloqueado): ?>
      <div class="v2-callout v2-callout-warning" role="status">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <span><strong>Pedido indisponível.</strong> Este pedido não aceita envio de comprovante no momento (status: <?php echo Helpers::e($statusLabel); ?>). Faça uma nova inscrição se precisar de um novo pedido.</span>
      </div>
      <div class="v2-quiz-actions"><a class="v2-btn v2-btn-ghost" href="/v2/catalogo/">Ver catálogo</a></div>

    <?php elseif ($comprovanteAguardando): ?>
      <div class="v2-callout v2-callout-info" role="status">
        <i class="ti ti-clock-hour-4" aria-hidden="true"></i>
        <span><strong>Comprovante em análise.</strong> Recebemos seu comprovante e ele está aguardando aprovação conforme o processo atual. Assim que for confirmado, o curso será liberado na sua área.</span>
      </div>
      <?php if (!empty($comprovantes)): ?>
        <div class="v2-block" style="margin:12px 0 0;">
          <h3 class="v2-h3" style="margin:0 0 6px;">Envios realizados</h3>
          <?php foreach ($comprovantes as $indice => $comprovante): ?>
            <?php $cs = strtolower(trim((string) ($comprovante['status'] ?? ''))); ?>
            <div class="v2-checkout-line">
              <strong>Envio <?php echo (int) ($indice + 1); ?></strong>
              <span class="v2-muted v2-sm"><?php echo Helpers::e(isset($comprovanteStatusLabels[$cs]) ? $comprovanteStatusLabels[$cs] : ($cs !== '' ? ucfirst($cs) : '—')); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="v2-quiz-actions">
        <a class="v2-btn v2-btn-ghost" href="/v2/aluno">Minha área</a>
        <a class="v2-btn v2-btn-ghost" href="/v2/aluno/?aba=pedidos">Meus pedidos</a>
      </div>

    <?php elseif ($podeEnviar): ?>
      <?php if ($estadoReenvio): ?>
        <div class="v2-callout v2-callout-warning" role="status" style="margin-bottom:12px;">
          <i class="ti ti-refresh" aria-hidden="true"></i>
          <span><strong>Reenvio necessário.</strong> Seu pedido está aguardando um novo comprovante. Anexe o arquivo correto e explique o motivo do reenvio abaixo.</span>
        </div>
      <?php endif; ?>

      <p class="v2-muted" style="margin:0 0 12px;">Anexe o arquivo do comprovante após concluir o PIX. Formatos aceitos: PDF, JPG, PNG ou WEBP (até 10 MB). O arquivo é validado e analisado pela equipe conforme o processo atual.</p>

      <form method="post" action="<?php echo Helpers::e($enviarUrl); ?>" enctype="multipart/form-data" class="v2-checkout-form" data-native-submit data-v2-single-submit novalidate>
        <input type="hidden" name="pedido_id" value="<?php echo $pedidoId; ?>">

        <div class="v2-field">
          <label for="v2-comp-arquivo">Arquivo do comprovante</label>
          <input class="v2-input" type="file" id="v2-comp-arquivo" name="comprovante" accept=".pdf,.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp,application/pdf" aria-describedby="v2-comp-arquivo-ajuda" required>
          <span id="v2-comp-arquivo-ajuda" class="v2-muted v2-sm">PDF, JPG, PNG ou WEBP, com no máximo 10 MB. A verificação final é feita no servidor.</span>
        </div>

        <div class="v2-field">
          <label for="v2-comp-valor">Valor informado</label>
          <input class="v2-input" type="text" id="v2-comp-valor" name="valor_informado" value="<?php echo Helpers::e($valorPrefill); ?>" inputmode="decimal" autocomplete="off">
        </div>

        <?php if ($exigeMotivoReenvio): ?>
          <div class="v2-field">
            <label for="v2-comp-motivo">Motivo do reenvio</label>
            <textarea class="v2-textarea" id="v2-comp-motivo" name="motivo_reenvio" rows="4" placeholder="Explique por que está reenviando o comprovante" aria-describedby="v2-comp-motivo-ajuda"></textarea>
            <span id="v2-comp-motivo-ajuda" class="v2-muted v2-sm">Obrigatório quando já existe um comprovante enviado para este pedido.</span>
          </div>
        <?php endif; ?>

        <div class="v2-quiz-actions" style="margin-top:6px;">
          <button type="submit" class="v2-btn v2-btn-primary" data-checkout-btn data-loading-label="Enviando...">
            <i class="ti ti-upload" aria-hidden="true"></i> Enviar comprovante
          </button>
        </div>
      </form>

    <?php else: ?>
      <div class="v2-callout v2-callout-info" role="status">
        <i class="ti ti-info-circle" aria-hidden="true"></i>
        <span>No momento este pedido não está aberto para envio de comprovante. Acompanhe o andamento na sua área do aluno.</span>
      </div>
      <div class="v2-quiz-actions"><a class="v2-btn v2-btn-ghost" href="/v2/aluno/?aba=pedidos">Meus pedidos</a></div>
    <?php endif; ?>
  </section>

  <div class="v2-quiz-actions" style="margin-top:6px;">
    <?php if ($pagamentoUrl !== ''): ?>
      <a class="v2-btn v2-btn-ghost" href="<?php echo Helpers::e($pagamentoUrl); ?>"><i class="ti ti-arrow-left" aria-hidden="true"></i> Voltar ao pagamento</a>
    <?php endif; ?>
  </div>
</section>

<script>
// Progressive enhancement apenas: foca o primeiro erro após PRG e evita duplo
// clique no envio (sem impedir o submit nativo). Sem AJAX, sem upload no
// cliente, sem validação financeira, sem preview de arquivo, sem armazenamento.
(function () {
  var feedback = document.getElementById('v2-checkout-feedback');
  if (feedback && feedback.querySelector('.v2-callout-danger')) {
    try { feedback.focus(); } catch (e) {}
  }

  var forms = document.querySelectorAll('form[data-v2-single-submit]');
  Array.prototype.forEach.call(forms, function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type="submit"]');
      if (!btn) { return; }
      if (btn.getAttribute('data-submitting') === '1') { return; }
      btn.setAttribute('data-submitting', '1');
      var label = btn.getAttribute('data-loading-label');
      // Desabilita no próximo tick para não bloquear o envio nativo.
      window.setTimeout(function () {
        if (label) { btn.textContent = label; }
        btn.setAttribute('disabled', 'disabled');
      }, 0);
    });
  });

  var copyButtons = document.querySelectorAll('[data-pix-copy]');
  Array.prototype.forEach.call(copyButtons, function (button) {
    button.addEventListener('click', function () {
      var block = button.closest('[data-pix-key-block]');
      var input = block ? block.querySelector('[data-pix-key-input]') : null;
      var feedbackEl = block ? block.querySelector('[data-pix-feedback]') : null;
      var value = input ? input.value : '';
      var copiedLabel = button.getAttribute('data-copied-label') || 'Copiada!';
      var setFeedback = function (text) { if (feedbackEl) { feedbackEl.textContent = text ? ' ' + text : ''; } };
      var done = function () { setFeedback(copiedLabel); window.setTimeout(function () { setFeedback(''); }, 1800); };
      var fail = function () { if (input) { input.focus(); input.select(); } setFeedback('Selecione a chave e copie manualmente.'); };
      if (!value) { fail(); return; }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done).catch(fail);
        return;
      }
      try {
        if (input) { input.focus(); input.select(); }
        if (document.execCommand && document.execCommand('copy')) { done(); return; }
      } catch (e) {}
      fail();
    });
  });
})();
</script>
