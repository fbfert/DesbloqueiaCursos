<?php use App\Core\Helpers; ?>
<?php
// Catálogo (template v4-claude). A navbar/bottom nav/footer vêm do layout.php.
$cursos               = isset($cursos) && is_array($cursos) ? $cursos : array();
$categoriasFiltro     = isset($categoriasFiltro) && is_array($categoriasFiltro) ? $categoriasFiltro : array();
$categoriaSelecionada = isset($categoriaSelecionada) && is_array($categoriaSelecionada) ? $categoriaSelecionada : null;
$categoriaSlugAtual   = isset($categoriaSlugAtual) ? (string) $categoriaSlugAtual : '';
$buscaAtual           = isset($buscaAtual) ? trim((string) $buscaAtual) : '';
$pageTitle            = isset($page_title) && trim((string) $page_title) !== '' ? (string) $page_title : 'Cursos e eventos';
$loggedIn             = !empty($loggedIn);

$dcCores = array('#fff4ec', '#f0e8ff', '#d1faf5', '#eaf3de', '#faeeda', '#ffe4ea');
$dcCor = fn(int $i): string => $dcCores[$i % count($dcCores)];
$dcIconCores = array('#FF6A00', '#4B008E', '#007a6a', '#3B6D11', '#854F0B', '#c00030');
$dcIconCor = fn(int $i): string => $dcIconCores[$i % count($dcIconCores)];
$dcIcones = array('ti-chart-bar', 'ti-scale', 'ti-coin', 'ti-bulb', 'ti-speakerphone', 'ti-code');
$dcIcone = fn(int $i): string => $dcIcones[$i % count($dcIcones)];
$dcPreco = function (array $curso): string {
    $v = isset($curso['valor_efetivo']) ? (float) $curso['valor_efetivo'] : (float) ($curso['valor'] ?? 0);
    return $v <= 0 ? 'Grátis' : 'R$ ' . number_format($v, 2, ',', '.');
};
$dcModalidade = fn ($valor): string => Helpers::modalidadeCurso($valor);
$paginacao = isset($paginacao) && is_array($paginacao) ? $paginacao : array('total' => count($cursos), 'pagina' => 1, 'total_paginas' => 1);
$totalCursos = (int) $paginacao['total'];
$dcPaginaAtual = (int) $paginacao['pagina'];
$dcTotalPaginas = (int) $paginacao['total_paginas'];
?>

<?php if (!empty($success)): ?>
<div class="dc-container">
  <div class="dc-callout dc-callout-success" style="margin-top:16px;">
    <i class="ti ti-circle-check"></i><span><?php echo Helpers::e($success); ?></span>
  </div>
</div>
<?php endif; ?>

<!-- Busca -->
<div class="dc-search-bar">
  <div class="dc-container">
    <form method="GET" action="/cursos" class="dc-search-form" role="search">
      <div class="dc-search-wrap">
        <i class="ti ti-search"></i>
        <input type="search" name="busca" class="dc-input dc-search-input"
               placeholder="Buscar cursos e eventos…" autocomplete="off"
               value="<?php echo Helpers::e($buscaAtual); ?>" aria-label="Buscar cursos">
      </div>
      <button type="submit" class="dc-btn dc-btn-primary">Buscar</button>
    </form>
  </div>
</div>

<!-- Chips de categoria -->
<?php if (!empty($categoriasFiltro)): ?>
<div class="dc-chips-row" role="list" aria-label="Filtrar por categoria">
  <a href="/cursos" class="dc-chip<?php echo ($categoriaSlugAtual === '' && $buscaAtual === '') ? ' on' : ''; ?>" role="listitem">Todos</a>
  <?php foreach ($categoriasFiltro as $cat): ?>
    <?php $catSlug = (string) ($cat['slug'] ?? ''); ?>
    <a href="/cursos?categoria=<?php echo urlencode($catSlug); ?>"
       class="dc-chip<?php echo ($categoriaSlugAtual !== '' && $categoriaSlugAtual === $catSlug) ? ' on' : ''; ?>" role="listitem">
      <?php echo Helpers::e((string) ($cat['nome'] ?? '')); ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Resultados -->
<section class="dc-section">
  <div class="dc-container">
    <div class="dc-section-header">
      <h1 class="dc-section-title"><?php echo Helpers::e($pageTitle); ?></h1>
      <span class="dc-text-sm dc-text-muted"><?php echo $totalCursos . ' ' . ($totalCursos === 1 ? 'curso' : 'cursos'); ?></span>
    </div>

    <?php if ($buscaAtual !== ''): ?>
      <p class="dc-text-sm dc-text-muted" style="margin-bottom:12px;">
        Resultados para “<strong><?php echo Helpers::e($buscaAtual); ?></strong>”.
      </p>
    <?php endif; ?>

    <?php if (empty($cursos)): ?>
      <div class="dc-empty">
        <i class="ti ti-search-off"></i>
        <p>Nenhum curso encontrado<?php echo $buscaAtual !== '' ? ' para “' . Helpers::e($buscaAtual) . '”' : ''; ?>.</p>
        <a href="/cursos" class="dc-btn dc-btn-ghost">Ver todos os cursos</a>
      </div>
    <?php else: ?>
      <div class="dc-course-list">
        <?php foreach ($cursos as $i => $curso): ?>
        <a href="/cursos/detalhe?curso_id=<?php echo (int) ($curso['id'] ?? 0); ?>" class="dc-card" aria-label="<?php echo Helpers::e((string) ($curso['nome'] ?? '')); ?>">
          <div class="dc-thumb" style="background:<?php echo $dcCor((int) $i); ?>;">
            <?php if (!empty($curso['thumbnail'])): ?>
              <img src="<?php echo Helpers::e((string) $curso['thumbnail']); ?>" alt="" class="dc-thumb-img">
            <?php else: ?>
              <i class="ti <?php echo $dcIcone((int) $i); ?>" style="color:<?php echo $dcIconCor((int) $i); ?>;"></i>
            <?php endif; ?>
          </div>
          <div class="dc-card-body">
            <?php if (!empty($curso['destaque'])): ?>
              <span class="dc-badge dc-badge-destaque"><i class="ti ti-flame"></i> Destaque</span>
            <?php endif; ?>
            <?php if (!empty($curso['categoria_nome'])): ?>
              <div class="dc-card-cat"><?php echo Helpers::e((string) $curso['categoria_nome']); ?></div>
            <?php endif; ?>
            <div class="dc-card-title"><?php echo Helpers::e((string) ($curso['nome'] ?? '')); ?></div>
            <div class="dc-card-foot">
              <div class="dc-card-meta">
                <?php if (!empty($curso['carga_horaria'])): ?>
                  <span><i class="ti ti-clock"></i><?php echo (int) $curso['carga_horaria']; ?>h</span>
                <?php endif; ?>
                <?php if (!empty($curso['modalidade'])): ?>
                  <span><i class="ti ti-device-desktop"></i><?php echo Helpers::e($dcModalidade($curso['modalidade'])); ?></span>
                <?php endif; ?>
              </div>
              <?php $preco = $dcPreco($curso); ?>
              <?php if ($preco === 'Grátis'): ?>
                <span class="dc-price-free">Gratuito</span>
              <?php else: ?>
                <span class="dc-price-val"><?php echo Helpers::e($preco); ?></span>
              <?php endif; ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <?php if ($dcTotalPaginas > 1): ?>
      <nav class="dc-pagination" aria-label="Navegação entre páginas de cursos">
        <?php if ($dcPaginaAtual > 1): ?>
          <a class="dc-btn dc-btn-ghost dc-btn-sm" href="<?php echo Helpers::e(catalogoPaginaUrl($dcPaginaAtual - 1, $catalogoCategoriaSlug, $catalogoBuscaAtual)); ?>">‹ Anterior</a>
        <?php endif; ?>
        <?php
          $dcPagIni = max(1, $dcPaginaAtual - 2);
          $dcPagFim = min($dcTotalPaginas, $dcPaginaAtual + 2);
        ?>
        <?php if ($dcPagIni > 1): ?>
          <a class="dc-btn dc-btn-ghost dc-btn-sm" href="<?php echo Helpers::e(catalogoPaginaUrl(1, $catalogoCategoriaSlug, $catalogoBuscaAtual)); ?>">1</a>
          <?php if ($dcPagIni > 2): ?><span class="dc-text-sm dc-text-muted">…</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($dcPagLoop = $dcPagIni; $dcPagLoop <= $dcPagFim; $dcPagLoop++): ?>
          <a class="dc-btn dc-btn-sm <?php echo $dcPagLoop === $dcPaginaAtual ? 'dc-btn-primary' : 'dc-btn-ghost'; ?>" href="<?php echo Helpers::e(catalogoPaginaUrl($dcPagLoop, $catalogoCategoriaSlug, $catalogoBuscaAtual)); ?>"><?php echo $dcPagLoop; ?></a>
        <?php endfor; ?>
        <?php if ($dcPagFim < $dcTotalPaginas): ?>
          <?php if ($dcPagFim < $dcTotalPaginas - 1): ?><span class="dc-text-sm dc-text-muted">…</span><?php endif; ?>
          <a class="dc-btn dc-btn-ghost dc-btn-sm" href="<?php echo Helpers::e(catalogoPaginaUrl($dcTotalPaginas, $catalogoCategoriaSlug, $catalogoBuscaAtual)); ?>"><?php echo $dcTotalPaginas; ?></a>
        <?php endif; ?>
        <?php if ($dcPaginaAtual < $dcTotalPaginas): ?>
          <a class="dc-btn dc-btn-ghost dc-btn-sm" href="<?php echo Helpers::e(catalogoPaginaUrl($dcPaginaAtual + 1, $catalogoCategoriaSlug, $catalogoBuscaAtual)); ?>">Próxima ›</a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
