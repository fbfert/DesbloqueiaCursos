<?php use App\Core\Helpers; ?>

<?php $categorias = isset($categorias) && is_array($categorias) ? $categorias : array(); ?>

<div class="front-section-stack categorias-page">
    <section class="page-header front-section">
        <h1>Categorias</h1>
        <p>Escolha uma área de interesse e veja os cursos públicos disponíveis em cada categoria.</p>
    </section>

    <section class="categorias-public-grid front-card-grid front-section">
        <?php if (empty($categorias)): ?>
            <article class="status-card front-card">
                <strong>Nenhuma categoria pública disponível no momento.</strong>
                <span>Quando houver categorias ativas com cursos públicos, elas aparecerão aqui.</span>
            </article>
        <?php else: ?>
            <?php foreach ($categorias as $categoria): ?>
                <?php
                $categoriaNome = isset($categoria['nome']) ? (string) $categoria['nome'] : '';
                $categoriaSlug = isset($categoria['slug']) ? (string) $categoria['slug'] : '';
                $categoriaDescricao = isset($categoria['descricao']) ? trim((string) $categoria['descricao']) : '';
                $totalCursos = isset($categoria['total_cursos']) ? (int) $categoria['total_cursos'] : 0;
                ?>
                <article class="category-card front-card">
                    <?php if (!empty($categoria['thumbnail'])): ?>
                        <div class="category-card__image">
                            <a class="category-card__image-link" href="/categorias/<?php echo Helpers::e($categoriaSlug); ?>/cursos" aria-label="Ver cursos da categoria <?php echo Helpers::e($categoriaNome); ?>">
                                <img src="<?php echo Helpers::e($categoria['thumbnail']); ?>" alt="<?php echo Helpers::e($categoriaNome); ?>" loading="lazy">
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="category-card__image">
                            <a class="category-card__image-link" href="/categorias/<?php echo Helpers::e($categoriaSlug); ?>/cursos" aria-label="Ver cursos da categoria <?php echo Helpers::e($categoriaNome); ?>">
                                <div class="category-card__placeholder">
                                    <span><?php echo Helpers::e($categoriaNome); ?></span>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="category-card__body">
                        <strong><?php echo Helpers::e($categoriaNome); ?></strong>
                        <?php if ($categoriaDescricao !== ''): ?>
                            <p><?php echo Helpers::e($categoriaDescricao); ?></p>
                        <?php endif; ?>
                        <div class="category-card__meta">
                            <span><?php echo $totalCursos; ?> curso(s) disponível(is)</span>
                        </div>
                        <div class="cta-group">
                            <a class="button-link button-link--ghost" href="/categorias/<?php echo Helpers::e($categoriaSlug); ?>/cursos">Ver cursos</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
