<?php use App\Core\Helpers; ?>
<?php
// Área do aluno (template v4-claude). Navbar/bottom nav/footer vêm do layout.php.
$usuarioNome = isset($usuarioNome) ? trim((string) $usuarioNome) : '';
if ($usuarioNome === '') { $usuarioNome = 'aluno'; }
$inscricoes = isset($inscricoes) && is_array($inscricoes) ? $inscricoes : array();
$pedidosPendentes = isset($pedidosPendentes) && is_array($pedidosPendentes) ? $pedidosPendentes : array();
$inicial = mb_strtoupper(mb_substr($usuarioNome, 0, 1));

$abaAtiva = isset($_GET['aba']) ? (string) $_GET['aba'] : 'cursos';
if (!in_array($abaAtiva, array('cursos', 'pedidos'), true)) { $abaAtiva = 'cursos'; }

// Feedback de sucesso (string ou array)
$feedbackTexto = '';
if (isset($pedidoCanceladoFeedback) && is_array($pedidoCanceladoFeedback) && !empty($pedidoCanceladoFeedback['title'])) {
    $feedbackTexto = (string) $pedidoCanceladoFeedback['title'];
} elseif (isset($success) && is_array($success) && !empty($success['message'])) {
    $feedbackTexto = (string) $success['message'];
} elseif (!empty($success) && is_string($success)) {
    $feedbackTexto = (string) $success;
}

$statusComAcesso = array('ativa', 'em_andamento', 'concluida', 'concluida_sem_certificado', 'certificado_emitido');
?>

<!-- Saudação + stats -->
<div class="dc-aluno-header">
  <div class="dc-container">
    <div class="dc-aluno-greet-row">
      <div>
        <p class="dc-text-muted dc-text-sm">Olá, bem-vindo de volta 👋</p>
        <h1 class="dc-aluno-nome"><?php echo Helpers::e($usuarioNome); ?></h1>
      </div>
      <div class="dc-aluno-avatar"><?php echo Helpers::e($inicial); ?></div>
    </div>
    <div class="dc-aluno-stats">
      <div class="dc-aluno-stat">
        <div class="dc-stat-n"><?php echo count($inscricoes); ?></div>
        <div class="dc-stat-l">Cursos ativos</div>
      </div>
      <div class="dc-aluno-stat">
        <div class="dc-stat-n"><?php echo count($pedidosPendentes); ?></div>
        <div class="dc-stat-l"><?php echo count($pedidosPendentes) === 1 ? 'Pedido pendente' : 'Pedidos pendentes'; ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Tabs (links reais, sem JS) -->
<div class="dc-aluno-tabs">
  <a href="/meus-cursos?aba=cursos" class="dc-aluno-tab<?php echo $abaAtiva === 'cursos' ? ' on' : ''; ?>">Meus cursos</a>
  <a href="/meus-cursos?aba=pedidos" class="dc-aluno-tab<?php echo $abaAtiva === 'pedidos' ? ' on' : ''; ?>">Pedidos<?php echo count($pedidosPendentes) > 0 ? ' (' . count($pedidosPendentes) . ')' : ''; ?></a>
</div>

<div class="dc-container" style="padding-top:16px;">

  <?php if ($feedbackTexto !== ''): ?>
    <div class="dc-callout dc-callout-success"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e($feedbackTexto); ?></span></div>
  <?php endif; ?>

  <?php if ($abaAtiva === 'cursos'): ?>

    <?php if (empty($inscricoes)): ?>
      <div class="dc-empty">
        <i class="ti ti-book-off"></i>
        <p>Você ainda não possui cursos disponíveis. Quando sua inscrição for aprovada, o acesso à sala aparecerá aqui.</p>
        <a href="/cursos" class="dc-btn dc-btn-primary">Explorar cursos</a>
      </div>
    <?php else: ?>
      <?php foreach ($inscricoes as $inscricao): ?>
        <?php
        $statusInscricao = isset($inscricao['status']) ? (string) $inscricao['status'] : '';
        $progresso = isset($inscricao['percentual_progresso']) && $inscricao['percentual_progresso'] !== null
            ? (int) round((float) $inscricao['percentual_progresso'])
            : 0;
        $progresso = min(100, max(0, $progresso));
        $cor = $progresso >= 100 ? 'var(--dc-success)' : 'var(--dc-laranja)';
        $statusLabel = $progresso >= 100 ? 'Concluído' : ($progresso > 0 ? 'Em andamento' : 'Não iniciado');
        $statusCls = $progresso >= 100 ? 'dc-badge-gratis' : ($progresso > 0 ? 'dc-badge-destaque' : 'dc-badge-novo');
        $podeAcessar = in_array($statusInscricao, $statusComAcesso, true);
        $salaHref = '/aluno/cursos?inscricao_id=' . (int) ($inscricao['id'] ?? 0)
            . '&curso_id=' . (int) ($inscricao['curso_evento_id'] ?? 0)
            . '&turma_id=' . (int) ($inscricao['turma_id'] ?? 0);
        $tag = $podeAcessar ? 'a' : 'div';
        ?>
        <<?php echo $tag; ?> <?php echo $podeAcessar ? 'href="' . Helpers::e($salaHref) . '"' : ''; ?> class="dc-card" style="margin-bottom:10px;<?php echo $podeAcessar ? '' : 'cursor:default;'; ?>">
          <div class="dc-thumb" style="background:#fff4ec;width:54px;height:54px;flex-shrink:0;">
            <i class="ti ti-certificate" style="color:#FF6A00;"></i>
          </div>
          <div class="dc-card-body">
            <div class="dc-card-title"><?php echo Helpers::e((string) ($inscricao['curso_nome'] ?? '')); ?></div>
            <?php if (!empty($inscricao['turma_nome'])): ?>
              <div class="dc-card-meta" style="margin-bottom:6px;"><span><i class="ti ti-users"></i><?php echo Helpers::e((string) $inscricao['turma_nome']); ?></span></div>
            <?php endif; ?>
            <div class="dc-progress">
              <div class="dc-progress-track">
                <div class="dc-progress-fill" style="width:<?php echo $progresso; ?>%;background:<?php echo $cor; ?>;"></div>
              </div>
              <div class="dc-progress-row">
                <span class="dc-progress-pct" style="color:<?php echo $cor; ?>;"><?php echo $progresso; ?>%</span>
                <span class="dc-badge <?php echo $statusCls; ?>" style="font-size:.65rem;"><?php echo $statusLabel; ?></span>
              </div>
            </div>
            <?php if (!$podeAcessar): ?>
              <p class="dc-text-sm dc-text-muted" style="margin-top:6px;">Acesso liberado após aprovação da inscrição.</p>
            <?php endif; ?>
          </div>
        </<?php echo $tag; ?>>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php elseif ($abaAtiva === 'pedidos'): ?>

    <?php if (empty($pedidosPendentes)): ?>
      <div class="dc-empty">
        <i class="ti ti-receipt-off"></i>
        <p>Nenhum pedido pendente.</p>
        <a href="/cursos" class="dc-btn dc-btn-ghost">Ver cursos</a>
      </div>
    <?php else: ?>
      <?php foreach ($pedidosPendentes as $pedido): ?>
        <?php
        $pedidoId = (int) ($pedido['id'] ?? 0);
        $cursoNome = !empty($pedido['curso_nome']) ? (string) $pedido['curso_nome'] : 'Curso não identificado';
        ?>
        <div class="dc-pedido-card">
          <div class="dc-pedido-top">
            <span class="dc-pedido-titulo"><?php echo Helpers::e($cursoNome); ?></span>
            <span class="dc-badge dc-badge-destaque">Pendente</span>
          </div>
          <div class="dc-pedido-meta">
            <?php if (!empty($pedido['codigo'])): ?>
              <span><i class="ti ti-receipt"></i><?php echo Helpers::e((string) $pedido['codigo']); ?></span>
            <?php endif; ?>
            <span><i class="ti ti-qrcode"></i>PIX</span>
          </div>
          <a href="/checkout/resumo?pedido_id=<?php echo $pedidoId; ?>" class="dc-btn dc-btn-outline dc-btn-block" style="margin-top:10px;">
            <i class="ti ti-arrow-right"></i> Continuar pedido
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php endif; ?>

</div>
