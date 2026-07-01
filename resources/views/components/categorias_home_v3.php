<?php

use App\Core\Helpers;

$categoriasDestaque = isset($categoriasDestaque) && is_array($categoriasDestaque) ? $categoriasDestaque : array();
?>
<?php if (!empty($categoriasDestaque)): ?>
<section class="dbc-section dbc-categorias">
    <div class="dbc-section__header">
        <h2 class="dbc-section__title">Categorias</h2>
    </div>
    <div class="dbc-categorias__grid">
        <?php foreach ($categoriasDestaque as $categoria): ?>
            <?php
                $categoriaNome = isset($categoria['nome']) ? (string) $categoria['nome'] : '';
                $categoriaSlug = isset($categoria['slug']) ? (string) $categoria['slug'] : '';
                $totalCursos = isset($categoria['total_cursos']) ? (int) $categoria['total_cursos'] : 0;
            ?>
            <a class="dbc-category-card" href="/categorias/<?php echo Helpers::e($categoriaSlug); ?>/cursos" aria-label="Ver cursos da categoria <?php echo Helpers::e($categoriaNome); ?>">
                <?php if (!empty($categoria['thumbnail'])): ?>
                    <img src="<?php echo Helpers::e($categoria['thumbnail']); ?>" alt="<?php echo Helpers::e($categoriaNome); ?>" loading="lazy">
                <?php else: ?>
                    <span class="dbc-category-card__placeholder" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="dbc-category-card__overlay">
                    <span class="dbc-category-card__title"><?php echo Helpers::e($categoriaNome); ?></span>
                    <span class="dbc-category-card__count"><?php echo $totalCursos; ?> <?php echo $totalCursos === 1 ? 'curso' : 'cursos'; ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
