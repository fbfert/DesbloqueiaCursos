<?php

use App\Core\Helpers;

$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
?>
<?php if (!empty($cursos)): ?>
<section class="dbc-section dbc-destaques">
    <div class="dbc-section__header">
        <h2 class="dbc-section__title">Cursos em destaque</h2>
    </div>
    <div class="dbc-destaques__track">
        <?php foreach ($cursos as $curso): ?>
            <?php
                $cursoNome = isset($curso['nome']) ? (string) $curso['nome'] : '';
                $categoriaNome = !empty($curso['categoria_nome']) ? (string) $curso['categoria_nome'] : 'Sem categoria';
                $modalidade = isset($curso['modalidade']) ? (string) $curso['modalidade'] : '';
                $modalidadeLabel = $modalidade !== '' ? Helpers::modalidadeCurso($modalidade) : '';
                $cargaHoraria = isset($curso['carga_horaria']) ? (int) $curso['carga_horaria'] : 0;
                $valorEfetivo = isset($curso['valor_efetivo']) ? (float) $curso['valor_efetivo'] : (float) (isset($curso['valor']) ? $curso['valor'] : 0);
                $emPromocao = !empty($curso['desconto_promocional']);
            ?>
            <article class="dbc-course-card">
                <div class="dbc-course-card__media<?php echo empty($curso['thumbnail']) ? ' dbc-course-card__media--placeholder' : ''; ?>">
                    <?php if (!empty($curso['thumbnail'])): ?>
                        <img src="<?php echo Helpers::e($curso['thumbnail']); ?>" alt="<?php echo Helpers::e($cursoNome); ?>" loading="lazy">
                    <?php endif; ?>
                    <?php if ($modalidadeLabel !== ''): ?>
                        <span class="dbc-course-card__badge"><?php echo Helpers::e($modalidadeLabel); ?></span>
                    <?php endif; ?>
                </div>
                <div class="dbc-course-card__body">
                    <span class="dbc-course-card__category"><?php echo Helpers::e($categoriaNome); ?></span>
                    <h3 class="dbc-course-card__title"><?php echo Helpers::e($cursoNome); ?></h3>
                    <?php if ($cargaHoraria > 0): ?>
                        <div class="dbc-course-card__meta">
                            <svg class="dbc-course-card__meta-icon" viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
                                <path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                            </svg>
                            <span><?php echo $cargaHoraria; ?>h</span>
                        </div>
                    <?php endif; ?>
                    <div class="dbc-course-card__footer">
                        <div class="dbc-course-card__price-group">
                            <?php if ($emPromocao): ?>
                                <span class="dbc-course-card__price--old">R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></span>
                            <?php endif; ?>
                            <span class="dbc-course-card__price"><?php echo $valorEfetivo <= 0 ? 'Gratuito' : 'R$ ' . number_format($valorEfetivo, 2, ',', '.'); ?></span>
                        </div>
                        <a class="dbc-course-card__cta" href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>">Ver curso</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
