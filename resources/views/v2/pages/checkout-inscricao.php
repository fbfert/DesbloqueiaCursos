<?php
use App\Core\Helpers;

$coursePalette = isset($coursePalette) && is_array($coursePalette) ? $coursePalette : array(
    array('icon' => 'ti-scale', 'g1' => '#fff4ec', 'g2' => '#ffe4d3', 'cor' => '#cc5500'),
    array('icon' => 'ti-speakerphone', 'g1' => '#f0e8ff', 'g2' => '#e4d6ff', 'cor' => '#4B008E'),
    array('icon' => 'ti-chart-bar', 'g1' => '#d1faf5', 'g2' => '#b8f2ea', 'cor' => '#007a6a'),
    array('icon' => 'ti-device-laptop', 'g1' => '#e3f0ff', 'g2' => '#cfe4ff', 'cor' => '#1d4ed8'),
    array('icon' => 'ti-microphone', 'g1' => '#ffe9f0', 'g2' => '#ffd6e3', 'cor' => '#c00057'),
    array('icon' => 'ti-briefcase', 'g1' => '#eef7df', 'g2' => '#dcefc0', 'cor' => '#3b6d11'),
);
$curso = isset($curso) && is_array($curso) ? $curso : array();
$cursoThumb = !empty($curso['thumbnail']) ? (string) $curso['thumbnail'] : '';
$temaIndex = !empty($curso['id']) ? ((int) $curso['id'] % count($coursePalette)) : 0;
$tema = $coursePalette[$temaIndex];
$pagadorPrefill = isset($pagadorPrefill) && is_array($pagadorPrefill) ? $pagadorPrefill : array();
// Após um erro de validação, o formulário é reaberto com os dados que o aluno
// digitou (old_input), em vez de voltar a puxar só o que já está no cadastro.
$oldInput = isset($oldInput) && is_array($oldInput) ? $oldInput : array();
$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;
$situacao = isset($situacaoInscricao) && is_array($situacaoInscricao) ? $situacaoInscricao : array();
$statusFluxo = isset($situacao['status_fluxo']) ? (string) $situacao['status_fluxo'] : 'nao_inscrito';
$loggedIn = !empty($loggedIn);
$usuarioNome = isset($usuarioNome) ? (string) $usuarioNome : '';
$usuarioEmail = isset($usuarioEmail) ? (string) $usuarioEmail : '';
// Destino de continuidade de pagamento construído no backend (rota interna fixa).
$continuarPagamentoUrl = isset($continuarPagamentoUrl) ? (string) $continuarPagamentoUrl : '';
$etapaAtual = 1;

$pf = function ($chave, $fallback = '') use ($pagadorPrefill, $oldInput) {
    if (isset($oldInput['pagador_' . $chave]) && (string) $oldInput['pagador_' . $chave] !== '') {
        return (string) $oldInput['pagador_' . $chave];
    }
    return isset($pagadorPrefill[$chave]) && (string) $pagadorPrefill[$chave] !== '' ? (string) $pagadorPrefill[$chave] : $fallback;
};
$turmaSel = isset($curso['turma_selecionada']) && is_array($curso['turma_selecionada']) ? $curso['turma_selecionada'] : array();
$estadoAtual = strtoupper((string) $pf('estado'));
$tipoPedidoAtual = isset($oldInput['tipo_pedido']) ? (string) $oldInput['tipo_pedido'] : 'propria';
$quantidadeAtual = isset($oldInput['quantidade']) ? (int) $oldInput['quantidade'] : 1;
$observacoesAtual = isset($oldInput['observacoes_publicas']) ? (string) $oldInput['observacoes_publicas'] : '';
$mostrarForm = $loggedIn && !in_array($statusFluxo, array('matriculado', 'pendente_pagamento'), true);
?>
<script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>

<section class="v2-container v2-checkout">
  <header class="v2-checkout-hero">
    <div class="v2-checkout-hero-media" style="background:linear-gradient(135deg,<?php echo Helpers::e($tema['g1']); ?>,<?php echo Helpers::e($tema['g2']); ?>);">
      <?php if ($cursoThumb !== ''): ?>
        <img src="<?php echo Helpers::e($cursoThumb); ?>" alt="<?php echo Helpers::e((string) ($curso['nome'] ?? '')); ?>" style="width:100%;height:auto;display:block;">
      <?php else: ?>
        <i class="ti <?php echo Helpers::e($tema['icon']); ?>" style="color:<?php echo Helpers::e($tema['cor']); ?>;"></i>
      <?php endif; ?>
    </div>
    <div class="v2-checkout-hero-text">
      <span class="v2-badge v2-badge-novo">Checkout</span>
      <h1 class="v2-h2" style="margin:6px 0;">Inscrição</h1>
      <p class="v2-muted"><?php echo Helpers::e((string) ($curso['nome'] ?? '')); ?></p>
    </div>
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

  <?php if ($statusFluxo === 'matriculado'): ?>
    <div class="v2-block v2-callout v2-callout-success" role="status">
      <i class="ti ti-circle-check"></i>
      <span><strong>Você já está matriculado neste curso.</strong> Acompanhe o conteúdo na sua área do aluno.</span>
    </div>
    <?php
    $acessarCursoUrl = !empty($situacao['inscricao_id']) && !empty($situacao['curso_id'])
        ? '/v2/aula/?inscricao_id=' . (int) $situacao['inscricao_id'] . '&curso_id=' . (int) $situacao['curso_id'] . '&turma_id=' . (int) ($situacao['turma_id'] ?? 0)
        : '/v2/aluno/';
    ?>
    <div class="v2-quiz-actions"><a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e($acessarCursoUrl); ?>">Acessar Curso</a></div>
  <?php elseif ($statusFluxo === 'pendente_pagamento'): ?>
    <div class="v2-block v2-callout v2-callout-warning" role="status">
      <i class="ti ti-clock-hour-4"></i>
      <span><strong>Você possui uma inscrição pendente para este curso.</strong> Continue o pagamento para concluir sua matrícula.</span>
    </div>
    <?php if ($continuarPagamentoUrl !== ''): ?>
      <div class="v2-quiz-actions"><a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e($continuarPagamentoUrl); ?>">Continuar pagamento</a></div>
    <?php endif; ?>
  <?php elseif (in_array($statusFluxo, array('cancelado', 'expirado', 'falhou', 'reprovado'), true)): ?>
    <div class="v2-block v2-callout v2-callout-info" role="status">
      <i class="ti ti-info-circle"></i>
      <span>Você pode se inscrever novamente. O pedido anterior não está ativo e não bloqueia uma nova inscrição.</span>
    </div>
  <?php endif; ?>

  <?php if (!$loggedIn): ?>
    <section class="v2-block">
      <h2 class="v2-h3" style="margin:0 0 6px;">Entre para continuar</h2>
      <p class="v2-muted">Você pode revisar o curso, mas precisa entrar na conta para iniciar a inscrição.</p>
      <div class="v2-quiz-actions">
        <a class="v2-btn v2-btn-primary" href="/v2/login?origem=v2_aluno">Entrar</a>
        <a class="v2-btn v2-btn-ghost" href="/v2/cadastro">Criar conta</a>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($mostrarForm): ?>
    <section class="v2-block">
      <h2 class="v2-h3" style="margin:0 0 8px;">Resumo da inscrição</h2>
      <dl class="v2-sumlist">
        <div class="v2-sumrow"><dt>Curso</dt><dd><?php echo Helpers::e((string) ($curso['nome'] ?? '')); ?></dd></div>
        <?php if (!empty($turmaSel['nome'])): ?>
          <div class="v2-sumrow"><dt>Turma</dt><dd><?php echo Helpers::e((string) $turmaSel['nome']); ?></dd></div>
          <div class="v2-sumrow"><dt>Status da turma</dt><dd><?php echo Helpers::e((string) ($turmaSel['status'] ?? '')); ?></dd></div>
        <?php endif; ?>
        <div class="v2-sumrow"><dt>Valor</dt><dd>
          <?php if (!empty($curso['desconto_promocional'])): ?>
            <span class="v2-muted" style="text-decoration:line-through;">R$ <?php echo number_format((float) ($curso['valor'] ?? 0), 2, ',', '.'); ?></span>
            <strong>R$ <?php echo number_format((float) ($curso['valor_efetivo'] ?? 0), 2, ',', '.'); ?></strong>
          <?php else: ?>
            R$ <?php echo number_format((float) ($curso['valor_efetivo'] ?? ($curso['valor'] ?? 0)), 2, ',', '.'); ?>
          <?php endif; ?>
        </dd></div>
      </dl>
    </section>

    <form class="v2-checkout-form" method="post" action="/v2/checkout/inscricao" data-native-submit id="v2-checkout-inscricao-form">
      <input type="hidden" name="curso_evento_id" value="<?php echo (int) ($curso['id'] ?? 0); ?>">
      <input type="hidden" name="turma_id" value="<?php echo !empty($turmaSel['id']) ? (int) $turmaSel['id'] : ''; ?>">

      <section class="v2-block">
        <h2 class="v2-h3" style="margin:0 0 10px;">Dados do pagador</h2>

        <div class="v2-field">
          <label for="ci-nome">Nome do pagador</label>
          <input class="v2-input" type="text" id="ci-nome" name="pagador_nome" value="<?php echo Helpers::e($pf('nome', $usuarioNome)); ?>" autocomplete="name">
        </div>
        <div class="v2-field">
          <label for="ci-cpf">CPF do pagador <span class="v2-muted v2-sm">(obrigatório)</span></label>
          <input class="v2-input" type="text" id="ci-cpf" name="pagador_cpf" value="<?php echo Helpers::e($pf('cpf')); ?>" placeholder="000.000.000-00" inputmode="numeric" data-mask-cpf autocomplete="off" required>
        </div>
        <div class="v2-field">
          <label for="ci-email">E-mail do pagador</label>
          <input class="v2-input" type="email" id="ci-email" name="pagador_email" value="<?php echo Helpers::e($pf('email', $usuarioEmail)); ?>" autocomplete="email">
        </div>
        <div class="v2-field">
          <label for="ci-tel">WhatsApp <span class="v2-muted v2-sm">(opcional)</span></label>
          <input class="v2-input" type="text" id="ci-tel" name="pagador_telefone" value="<?php echo Helpers::e($pf('telefone')); ?>" inputmode="tel" autocomplete="tel">
        </div>
        <div class="v2-field">
          <label for="ci-estado">Estado</label>
          <select class="v2-input" id="ci-estado" name="pagador_estado">
            <option value="">Selecione o estado</option>
            <?php foreach (array('AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO') as $uf): ?>
              <option value="<?php echo Helpers::e($uf); ?>"<?php echo $estadoAtual === $uf ? ' selected' : ''; ?>><?php echo Helpers::e($uf); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="v2-field">
          <label for="ci-cidade">Cidade</label>
          <input class="v2-input" type="text" id="ci-cidade" name="pagador_cidade" value="<?php echo Helpers::e($pf('cidade')); ?>" autocomplete="address-level2">
        </div>
        <div class="v2-field">
          <label for="ci-tipo">Tipo do pedido</label>
          <select class="v2-input" id="ci-tipo" name="tipo_pedido">
            <option value="propria"<?php echo $tipoPedidoAtual === 'propria' ? ' selected' : ''; ?>>Compra própria</option>
            <option value="terceiros"<?php echo $tipoPedidoAtual === 'terceiros' ? ' selected' : ''; ?>>Compra para terceiros</option>
            <option value="lote"<?php echo $tipoPedidoAtual === 'lote' ? ' selected' : ''; ?>>Compra em lote</option>
          </select>
        </div>
        <div class="v2-field">
          <label for="ci-qtd">Quantidade de vagas <span class="v2-muted v2-sm">(apenas para terceiros/lote)</span></label>
          <input class="v2-input" type="number" id="ci-qtd" name="quantidade" min="1" value="<?php echo (int) max(1, $quantidadeAtual); ?>">
        </div>
        <div class="v2-field">
          <label for="ci-obs">Comentários sobre a inscrição <span class="v2-muted v2-sm">(opcional)</span></label>
          <textarea class="v2-textarea" id="ci-obs" name="observacoes_publicas" rows="3"><?php echo Helpers::e($observacoesAtual); ?></textarea>
        </div>
      </section>

      <div class="v2-quiz-actions">
        <button type="submit" class="v2-btn v2-btn-primary" data-checkout-btn data-loading-label="Avançando…"><i class="ti ti-arrow-right"></i> Avançar</button>
        <a class="v2-btn v2-btn-ghost" href="/v2/curso/?curso_id=<?php echo (int) ($curso['id'] ?? 0); ?>">Voltar ao curso</a>
      </div>
      <p class="v2-muted v2-sm" style="margin-top:8px;">O pagamento ainda não acontece aqui: você revisa os dados e o resumo antes de seguir para o pagamento no fluxo oficial.</p>
    </form>
  <?php endif; ?>
</section>
