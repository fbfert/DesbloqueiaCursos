<?php use App\Core\Helpers; ?>
<?php
// Sala do aluno — lista de módulos (template v4-claude). Reaproveita as vars do AreaCursoController.
$inscricaoId = isset($inscricao['id']) ? (int) $inscricao['id'] : 0;
$cursoId = isset($inscricao['curso_evento_id']) ? (int) $inscricao['curso_evento_id'] : 0;
$turmaId = isset($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
$cursoNome = Helpers::normalizarTextoLms(isset($curso['nome']) ? $curso['nome'] : '');
$turmaNome = Helpers::normalizarTextoLms(!empty($turma['nome']) ? $turma['nome'] : '');
$cursoTitulo = $cursoNome !== '' ? $cursoNome : 'Curso';
$resumo = isset($conteudo_resumo) && is_array($conteudo_resumo) ? $conteudo_resumo : array();
$modulos = isset($conteudo_modulos) && is_array($conteudo_modulos) ? $conteudo_modulos : array();
$totalConteudos = (int) ($resumo['total_itens'] ?? 0);
$concluidos = (int) ($resumo['concluidos_itens'] ?? 0);
$percentual = max(0, min(100, (float) ($resumo['percentual'] ?? ($resumo['percentual_progresso'] ?? 0))));
?>

<div class="dc-container">

  <div class="dc-lms-header">
    <div>
      <p class="dc-text-muted dc-text-sm">Módulos do curso</p>
      <h1 class="dc-h2"><?php echo Helpers::e($cursoTitulo); ?></h1>
      <?php if ($turmaNome !== ''): ?><p class="dc-text-sm dc-text-muted">Turma: <?php echo Helpers::e($turmaNome); ?></p><?php endif; ?>
    </div>
    <div class="dc-lms-prog">
      <div class="dc-progress-track"><div class="dc-progress-fill" style="width:<?php echo $percentual; ?>%"></div></div>
      <div class="dc-progress-row">
        <span class="dc-progress-pct"><?php echo number_format($percentual, 0); ?>%</span>
        <span class="dc-progress-lbl"><?php echo $totalConteudos > 0 ? $concluidos . '/' . $totalConteudos . ' conteúdos' : 'sem conteúdos'; ?></span>
      </div>
    </div>
  </div>

  <?php if (!empty($success)): ?>
    <div class="dc-callout dc-callout-success"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e(is_string($success) ? $success : ''); ?></span></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="dc-callout dc-callout-danger"><i class="ti ti-alert-triangle"></i><span><?php foreach ((array) $errors as $err): ?><?php echo Helpers::e((string) $err); ?><br><?php endforeach; ?></span></div>
  <?php endif; ?>

  <?php if (empty($modulos)): ?>
    <div class="dc-empty">
      <i class="ti ti-folder-off"></i>
      <p>Nenhum módulo publicado foi encontrado nesta turma.</p>
      <a href="/meus-cursos" class="dc-btn dc-btn-ghost">Voltar aos meus cursos</a>
    </div>
  <?php else: ?>
    <?php foreach ($modulos as $i => $modulo): ?>
      <?php
      $moduloId = (int) ($modulo['id'] ?? 0);
      $moduloUrl = '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId . '/modulo/' . $moduloId;
      $mTotal = (int) ($modulo['total_itens'] ?? 0);
      $mConc = (int) ($modulo['concluidos_itens'] ?? 0);
      $mPct = max(0, min(100, (float) ($modulo['percentual_conclusao'] ?? 0)));
      $mStatus = isset($modulo['status_label']) ? (string) $modulo['status_label'] : '';
      ?>
      <a href="<?php echo Helpers::e($moduloUrl); ?>" class="dc-card" style="margin-bottom:10px;">
        <div class="dc-thumb" style="background:#f0e8ff;width:46px;height:46px;flex-shrink:0;">
          <span style="font-weight:700;color:#4B008E;"><?php echo $i + 1; ?></span>
        </div>
        <div class="dc-card-body">
          <div class="dc-card-title"><?php echo Helpers::e(Helpers::normalizarTextoLms((string) ($modulo['titulo'] ?? 'Módulo'))); ?></div>
          <div class="dc-card-meta" style="margin-bottom:6px;">
            <span><i class="ti ti-list"></i><?php echo $mTotal; ?> conteúdos</span>
            <span><i class="ti ti-circle-check"></i><?php echo $mConc; ?>/<?php echo $mTotal; ?></span>
          </div>
          <div class="dc-progress">
            <div class="dc-progress-track"><div class="dc-progress-fill" style="width:<?php echo $mPct; ?>%"></div></div>
            <div class="dc-progress-row">
              <span class="dc-progress-pct"><?php echo number_format($mPct, 0); ?>%</span>
              <?php if ($mStatus !== ''): ?><span class="dc-progress-lbl"><?php echo Helpers::e($mStatus); ?></span><?php endif; ?>
            </div>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
