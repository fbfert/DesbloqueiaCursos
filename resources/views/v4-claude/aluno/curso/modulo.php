<?php use App\Core\Helpers; ?>
<?php
// Itens de um módulo (template v4-claude).
$inscricaoId = isset($inscricao['id']) ? (int) $inscricao['id'] : 0;
$cursoId = isset($inscricao['curso_evento_id']) ? (int) $inscricao['curso_evento_id'] : 0;
$turmaId = isset($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
$cursoNome = Helpers::normalizarTextoLms(isset($curso['nome']) ? $curso['nome'] : '');
$modulo = isset($conteudo_modulo) && is_array($conteudo_modulo) ? $conteudo_modulo : array();
$moduloId = (int) ($modulo['id'] ?? 0);
$moduloTitulo = Helpers::normalizarTextoLms((string) ($modulo['titulo'] ?? 'Módulo'));
$moduloUrl = '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId . '/modulo/' . $moduloId;
$cursoUrl = isset($conteudo_geral_url) && $conteudo_geral_url !== '' ? (string) $conteudo_geral_url : '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId;
$itens = !empty($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array();
$totalItens = (int) ($modulo['total_itens'] ?? count($itens));
$concluidosItens = (int) ($modulo['concluidos_itens'] ?? 0);
$percentual = max(0, min(100, (float) ($modulo['percentual_conclusao'] ?? 0)));

// Ícone/cor por tipo de item
$tipoIcone = array(
    'texto' => array('ti-file-text', '#f0e8ff', '#4B008E'),
    'video' => array('ti-player-play', '#fff4ec', '#FF6A00'),
    'arquivo' => array('ti-file', '#d1faf5', '#007a6a'),
    'link' => array('ti-external-link', '#d1faf5', '#007a6a'),
    'etiqueta' => array('ti-tag', '#faeeda', '#854F0B'),
    'avaliacao_textual' => array('ti-pencil', '#ffe4ea', '#c00030'),
    'quiz' => array('ti-help-circle', '#faeeda', '#854F0B'),
    'html' => array('ti-code', '#e0f2fe', '#075985'),
    'video_incorporado' => array('ti-player-play', '#fff4ec', '#FF6A00'),
);
?>

<div class="dc-container">

  <?php if (!empty($success)): ?>
    <div class="dc-callout dc-callout-success"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e(is_string($success) ? $success : ''); ?></span></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="dc-callout dc-callout-danger"><i class="ti ti-alert-triangle"></i><span><?php foreach ((array) $errors as $err): ?><?php echo Helpers::e((string) $err); ?><br><?php endforeach; ?></span></div>
  <?php endif; ?>

  <div class="dc-lms-header">
    <div>
      <a href="<?php echo Helpers::e($cursoUrl); ?>" class="dc-lms-back-link"><i class="ti ti-arrow-left"></i> Voltar aos módulos</a>
      <p class="dc-text-muted dc-text-sm">Conteúdos do módulo</p>
      <h1 class="dc-h2"><?php echo Helpers::e($moduloTitulo); ?></h1>
      <p class="dc-text-sm dc-text-muted"><?php echo Helpers::e($cursoNome); ?></p>
    </div>
    <div class="dc-lms-prog">
      <div class="dc-progress-track"><div class="dc-progress-fill" style="width:<?php echo $percentual; ?>%"></div></div>
      <div class="dc-progress-row">
        <span class="dc-progress-pct"><?php echo number_format($percentual, 0); ?>%</span>
        <span class="dc-progress-lbl"><?php echo $totalItens > 0 ? $concluidosItens . '/' . $totalItens . ' concluídos' : 'sem conteúdos'; ?></span>
      </div>
    </div>
  </div>

  <?php if (empty($itens)): ?>
    <div class="dc-empty">
      <i class="ti ti-file-off"></i>
      <p>Este módulo ainda não possui conteúdos publicados.</p>
      <a href="<?php echo Helpers::e($cursoUrl); ?>" class="dc-btn dc-btn-ghost">Voltar aos módulos</a>
    </div>
  <?php else: ?>
    <?php foreach ($itens as $item): ?>
      <?php
      $itemId = (int) ($item['id'] ?? 0);
      $itemUrl = !empty($item['detalhes_url']) ? (string) $item['detalhes_url'] : $moduloUrl . '/conteudo/' . $itemId;
      $itemTipo = isset($item['tipo']) ? (string) $item['tipo'] : '';
      $itemTipoLabel = isset($item['tipo_label']) ? (string) $item['tipo_label'] : 'Conteúdo';
      $itemStatus = isset($item['status_label']) ? (string) $item['status_label'] : '';
      $itemConcluido = !empty($item['concluido_aluno']);
      $ic = $tipoIcone[$itemTipo] ?? array('ti-file-text', '#f5f0ff', '#6b7280');
      ?>
      <a href="<?php echo Helpers::e($itemUrl); ?>" class="dc-card" style="margin-bottom:8px;">
        <div class="dc-thumb" style="background:<?php echo $ic[1]; ?>;width:46px;height:46px;flex-shrink:0;">
          <i class="ti <?php echo $ic[0]; ?>" style="color:<?php echo $ic[2]; ?>;"></i>
        </div>
        <div class="dc-card-body">
          <div class="dc-card-title"><?php echo Helpers::e(Helpers::normalizarTextoLms((string) ($item['titulo'] ?? 'Conteúdo'))); ?></div>
          <div class="dc-card-foot">
            <div class="dc-card-meta">
              <span><?php echo Helpers::e($itemTipoLabel); ?></span>
            </div>
            <?php if ($itemConcluido): ?>
              <span class="dc-badge dc-badge-gratis"><i class="ti ti-circle-check"></i> Concluído</span>
            <?php elseif ($itemStatus !== ''): ?>
              <span class="dc-badge dc-badge-novo"><?php echo Helpers::e($itemStatus); ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
