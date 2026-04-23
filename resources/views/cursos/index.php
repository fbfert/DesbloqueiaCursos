<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Cursos e eventos</h1>
    <p>Confira a oferta ativa do portal e abra o fluxo de inscrição pelo curso ou pela turma.</p>
</section>

<section class="card-grid">
    <?php if (empty($cursos)): ?>
        <article class="status-card">
            <strong>Nenhum curso ativo</strong>
            <span>O catalogo ainda nao possui itens publicos disponiveis.</span>
        </article>
    <?php else: ?>
        <?php foreach ($cursos as $curso): ?>
            <article class="course-card">
                <div class="course-card__media">
                    <strong><?php echo Helpers::e($curso['nome']); ?></strong>
                    <span><?php echo Helpers::e($curso['categoria_nome']); ?></span>
                </div>
                <div class="course-card__body">
                    <div class="pill-row">
                        <span class="pill"><?php echo Helpers::e($curso['tipo']); ?></span>
                        <span class="pill"><?php echo Helpers::e($curso['modalidade']); ?></span>
                        <?php if (!empty($curso['em_promocao'])): ?>
                            <span class="pill pill--alert">Promocao</span>
                        <?php endif; ?>
                    </div>
                    <p><?php echo Helpers::e($curso['descricao_curta']); ?></p>
                    <div class="course-card__meta">
                        <span><?php echo (int) $curso['total_turmas']; ?> turma(s)</span>
                        <strong><?php echo Helpers::e($curso['valor']); ?></strong>
                    </div>
                    <div class="cta-group">
                        <a class="button-link" href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>">Ver detalhe</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
