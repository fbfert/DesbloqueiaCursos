<?php
use App\Core\Helpers;

$categorias = isset($categorias) && is_array($categorias) ? $categorias : array();
$coursePalette = isset($coursePalette) && is_array($coursePalette) ? $coursePalette : array();
?>
<div class="v2-container" style="padding-top:16px;">
  <nav class="v2-breadcrumb" aria-label="Caminho">
    <a href="/v2/">Início</a>
    <i class="ti ti-chevron-right" aria-hidden="true"></i>
    <span aria-current="page">Categorias</span>
  </nav>
  <header class="v2-catalog-header">
    <h1 class="v2-h1">Categorias</h1>
    <p class="v2-muted">Explore os cursos por área de interesse.</p>
  </header>

  <?php if (!empty($categorias)): ?>
    <div class="v2-cats v2-cats-pagina" style="padding:6px 0 40px;">
      <?php foreach ($categorias as $index => $categoria): ?>
        <?php $tema = $coursePalette[$index % count($coursePalette)]; ?>
        <a href="<?php echo Helpers::e((string) $categoria['url']); ?>" class="v2-cat">
          <span class="v2-cat-ic" style="background:linear-gradient(135deg,<?php echo Helpers::e($tema['g1']); ?>,<?php echo Helpers::e($tema['g2']); ?>);">
            <?php if ($categoria['thumbnail'] !== ''): ?>
              <img src="<?php echo Helpers::e((string) $categoria['thumbnail']); ?>" alt="<?php echo Helpers::e((string) $categoria['nome']); ?>" style="width:100%;height:auto;display:block;">
            <?php else: ?>
              <i class="ti <?php echo Helpers::e($tema['icon']); ?>" style="color:<?php echo Helpers::e($tema['cor']); ?>;"></i>
            <?php endif; ?>
          </span>
          <span class="v2-cat-nome"><?php echo Helpers::e((string) $categoria['nome']); ?></span>
          <span class="v2-cat-q"><?php echo number_format((int) $categoria['total_cursos'], 0, ',', '.'); ?> <?php echo (int) $categoria['total_cursos'] === 1 ? 'curso' : 'cursos'; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="v2-empty">
      <i class="ti ti-category"></i>
      <p>Nenhuma categoria disponível no momento.</p>
    </div>
  <?php endif; ?>
</div>
