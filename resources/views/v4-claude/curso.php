<?php use App\Core\Helpers; ?>
<?php
// Ficha do curso (template v4-claude). Navbar/bottom nav/footer vêm do layout.php.
$curso    = isset($curso) && is_array($curso) ? $curso : array();
$turmas   = isset($curso['turmas']) && is_array($curso['turmas'])
    ? $curso['turmas']
    : (isset($curso['turmas_abertas']) && is_array($curso['turmas_abertas']) ? $curso['turmas_abertas'] : array());
$loggedIn = !empty($loggedIn);
$errors   = isset($errors) && is_array($errors) ? $errors : array();
$success  = isset($success) ? (string) $success : '';

$cursoId      = (int) ($curso['id'] ?? 0);
$valorEfetivo = isset($curso['valor_efetivo']) ? (float) $curso['valor_efetivo'] : (float) ($curso['valor'] ?? 0);
$precoCurso   = $valorEfetivo <= 0 ? 'Grátis' : 'R$ ' . number_format($valorEfetivo, 2, ',', '.');
$catNome      = (string) ($curso['categoria_nome'] ?? '');

$precoDe = function ($valor) {
    $v = (float) $valor;
    return $v <= 0 ? 'Grátis' : 'R$ ' . number_format($v, 2, ',', '.');
};
$dcModalidade = function ($valor): string {
    $mapa = array('presencial' => 'Presencial', 'online' => 'Online', 'ead' => 'EAD', 'hibrido' => 'Híbrido', 'híbrido' => 'Híbrido', 'ao_vivo' => 'Ao vivo');
    $chave = strtolower(trim((string) $valor));
    return $mapa[$chave] ?? ucfirst($chave);
};

// Professores responsáveis (nome)
$professores = isset($curso['professores_responsaveis']) && is_array($curso['professores_responsaveis']) ? $curso['professores_responsaveis'] : array();
$profNomes = array_values(array_filter(array_map(function ($p) {
    return is_array($p) && !empty($p['nome']) ? (string) $p['nome'] : '';
}, $professores)));
if (empty($profNomes) && !empty($curso['professor_responsavel']['nome'])) {
    $profNomes = array((string) $curso['professor_responsavel']['nome']);
}
$instrutorNome = $profNomes[0] ?? '';

// Situação da inscrição (quando logado)
$situacao = !empty($curso['turma_selecionada']['situacao_inscricao']) && is_array($curso['turma_selecionada']['situacao_inscricao'])
    ? $curso['turma_selecionada']['situacao_inscricao']
    : null;
$statusFluxo = isset($situacao['status_fluxo']) ? (string) $situacao['status_fluxo'] : 'nao_inscrito';
$jaInscrito = $statusFluxo === 'matriculado';
$aguardando = $statusFluxo === 'pendente_pagamento';
$checkoutUrl = !empty($situacao['checkout_url']) ? (string) $situacao['checkout_url'] : '/checkout/resumo';

// Descrição: HTML do editor sanitizado; fallback para descrição curta
$descricaoHtml = '';
if (!empty($curso['descricao_completa'])) {
    $descricaoHtml = Helpers::renderSafeHtml((string) $curso['descricao_completa']);
} elseif (!empty($curso['descricao_curta'])) {
    $descricaoHtml = '<p>' . Helpers::e((string) $curso['descricao_curta']) . '</p>';
}

$primeiraTurmaId = !empty($turmas[0]['id']) ? (int) $turmas[0]['id'] : 0;
$urlInscricaoCurso = '/inscricao?curso_id=' . $cursoId . ($primeiraTurmaId ? '&turma_id=' . $primeiraTurmaId : '');
?>

<?php if ($success !== ''): ?>
<div class="dc-container">
  <div class="dc-callout dc-callout-success" style="margin-top:16px;"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e($success); ?></span></div>
</div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="dc-container">
  <div class="dc-callout dc-callout-danger" style="margin-top:16px;">
    <i class="ti ti-alert-triangle"></i>
    <span><?php foreach ($errors as $err): ?><?php echo Helpers::e((string) $err); ?><br><?php endforeach; ?></span>
  </div>
</div>
<?php endif; ?>

<div class="dc-curso-layout dc-container">

  <!-- COLUNA PRINCIPAL -->
  <div class="dc-curso-main">

    <!-- Capa -->
    <div class="dc-curso-capa" style="background:#fff4ec;">
      <?php if (!empty($curso['thumbnail'])): ?>
        <img src="<?php echo Helpers::e((string) $curso['thumbnail']); ?>" alt="<?php echo Helpers::e((string) ($curso['nome'] ?? '')); ?>" class="dc-curso-capa-img">
      <?php else: ?>
        <i class="ti ti-certificate" style="font-size:48px;color:#FF6A00;opacity:.5;"></i>
      <?php endif; ?>
    </div>

    <!-- Meta rápida -->
    <div class="dc-curso-meta-bar">
      <?php if ($catNome !== ''): ?>
        <span class="dc-card-cat"><?php echo Helpers::e($catNome); ?></span>
      <?php endif; ?>
      <div class="dc-curso-meta-items">
        <?php if (!empty($curso['carga_horaria'])): ?>
          <span><i class="ti ti-clock"></i> <?php echo (int) $curso['carga_horaria']; ?>h</span>
        <?php endif; ?>
        <?php if (!empty($curso['modalidade'])): ?>
          <span>
            <i class="ti ti-<?php echo strtolower((string) $curso['modalidade']) === 'presencial' ? 'map-pin' : 'device-desktop'; ?>"></i>
            <?php echo Helpers::e($dcModalidade($curso['modalidade'])); ?>
          </span>
        <?php endif; ?>
        <?php if (!empty($curso['certificado_previsto'])): ?>
          <span><i class="ti ti-certificate"></i> Certificado</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Título -->
    <h1 class="dc-curso-titulo dc-h1"><?php echo Helpers::e((string) ($curso['nome'] ?? '')); ?></h1>

    <!-- Descrição (HTML sanitizado) -->
    <?php if ($descricaoHtml !== ''): ?>
      <div class="dc-curso-desc"><?php echo $descricaoHtml; ?></div>
    <?php endif; ?>

    <!-- Turmas -->
    <?php if (!empty($turmas)): ?>
    <div class="dc-turmas">
      <h2 class="dc-h2">Turmas disponíveis</h2>
      <?php foreach ($turmas as $turma): ?>
        <?php
          $vagas = isset($turma['vagas']) && $turma['vagas'] !== null ? (int) $turma['vagas'] : null;
          $esgotada = $vagas !== null && $vagas <= 0;
          $turmaLocal = (string) ($turma['local'] ?? ($turma['local_nome'] ?? ''));
          $turmaValor = isset($turma['valor_override']) && $turma['valor_override'] !== null && (float) $turma['valor_override'] > 0
              ? (float) $turma['valor_override']
              : $valorEfetivo;
        ?>
        <div class="dc-turma-card<?php echo $esgotada ? ' dc-turma-esgotada' : ''; ?>">
          <div class="dc-turma-top">
            <span class="dc-turma-nome"><?php echo Helpers::e((string) ($turma['nome'] ?? 'Turma')); ?></span>
            <?php if ($esgotada): ?>
              <span class="dc-badge dc-badge-esgotado">Esgotado</span>
            <?php elseif ($vagas !== null && $vagas > 0): ?>
              <span class="dc-badge dc-badge-gratis"><?php echo $vagas; ?> <?php echo $vagas === 1 ? 'vaga' : 'vagas'; ?></span>
            <?php endif; ?>
          </div>
          <div class="dc-turma-meta">
            <?php if (!empty($turma['data_inicio'])): ?>
              <span><i class="ti ti-calendar"></i> Início <?php echo Helpers::e(date('d/m/Y', strtotime((string) $turma['data_inicio']))); ?></span>
            <?php endif; ?>
            <?php if ($turmaLocal !== ''): ?>
              <span><i class="ti ti-map-pin"></i> <?php echo Helpers::e($turmaLocal); ?></span>
            <?php endif; ?>
          </div>
          <div class="dc-turma-preco"><?php echo Helpers::e($precoDe($turmaValor)); ?></div>
          <?php if (!$esgotada && !$jaInscrito && !$aguardando): ?>
            <a href="/inscricao?curso_id=<?php echo $cursoId; ?>&turma_id=<?php echo (int) ($turma['id'] ?? 0); ?>" class="dc-btn dc-btn-primary dc-btn-block dc-turma-cta">
              Quero desbloquear <i class="ti ti-arrow-right"></i>
            </a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Instrutor -->
    <?php if (!empty($profNomes)): ?>
    <div class="dc-instrutor">
      <h2 class="dc-h2"><?php echo count($profNomes) === 1 ? 'Instrutor' : 'Instrutores'; ?></h2>
      <div class="dc-instrutor-card">
        <div class="dc-instrutor-avatar"><?php echo Helpers::e(mb_strtoupper(mb_substr($instrutorNome !== '' ? $instrutorNome : 'I', 0, 1))); ?></div>
        <div>
          <div class="dc-instrutor-nome"><?php echo Helpers::e(implode(', ', $profNomes)); ?></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /.dc-curso-main -->

  <!-- SIDEBAR / CTA (desktop = sticky card) -->
  <aside class="dc-curso-aside">
    <div class="dc-curso-cta-card">
      <div class="dc-curso-cta-preco"><?php echo Helpers::e($precoCurso); ?></div>

      <?php if ($jaInscrito): ?>
        <a href="/meus-cursos" class="dc-btn dc-btn-primary dc-btn-block"><i class="ti ti-book"></i> Continuar curso</a>
      <?php elseif ($aguardando): ?>
        <a href="<?php echo Helpers::e($checkoutUrl); ?>" class="dc-btn dc-btn-primary dc-btn-block"><i class="ti ti-clock"></i> Continuar pagamento</a>
      <?php else: ?>
        <a href="<?php echo Helpers::e($urlInscricaoCurso); ?>" class="dc-btn dc-btn-primary dc-btn-block">Quero desbloquear <i class="ti ti-arrow-right"></i></a>
      <?php endif; ?>

      <div class="dc-curso-cta-meta">
        <?php if (!empty($curso['carga_horaria'])): ?>
          <span><i class="ti ti-clock"></i> <?php echo (int) $curso['carga_horaria']; ?>h de carga horária</span>
        <?php endif; ?>
        <?php if (!empty($curso['certificado_previsto'])): ?>
          <span><i class="ti ti-certificate"></i> Certificado incluso</span>
        <?php endif; ?>
        <?php if ($instrutorNome !== ''): ?>
          <span><i class="ti ti-user"></i> <?php echo Helpers::e(implode(', ', $profNomes)); ?></span>
        <?php endif; ?>
      </div>
    </div>
  </aside>

</div>

<!-- CTA FIXO MOBILE -->
<?php if (!$jaInscrito && !$aguardando): ?>
<div class="dc-curso-cta-mobile">
  <div class="dc-curso-cta-mobile-preco"><?php echo Helpers::e($precoCurso); ?></div>
  <a href="<?php echo Helpers::e($urlInscricaoCurso); ?>" class="dc-btn dc-btn-primary">Quero desbloquear <i class="ti ti-arrow-right"></i></a>
</div>
<?php elseif ($aguardando): ?>
<div class="dc-curso-cta-mobile">
  <div class="dc-curso-cta-mobile-preco"><?php echo Helpers::e($precoCurso); ?></div>
  <a href="<?php echo Helpers::e($checkoutUrl); ?>" class="dc-btn dc-btn-primary"><i class="ti ti-clock"></i> Continuar pagamento</a>
</div>
<?php endif; ?>
