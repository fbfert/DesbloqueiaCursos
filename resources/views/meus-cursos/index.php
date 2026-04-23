<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Meus Cursos</h1>
    <p><?php echo Helpers::e($usuarioNome); ?></p>
</section>

<?php if (!empty($success)): ?>
    <section class="auth-message auth-message-success">
        <p><?php echo Helpers::e($success); ?></p>
    </section>
<?php endif; ?>

<section class="card-grid">
    <?php if (empty($inscricoes)): ?>
        <article class="status-card">
            <strong>Sem inscricoes ainda</strong>
            <span>Quando seus pedidos forem aprovados, os cursos vao aparecer aqui.</span>
        </article>
    <?php else: ?>
        <?php foreach ($inscricoes as $inscricao): ?>
            <article class="course-card">
                <div class="course-card__media">
                    <strong><?php echo Helpers::e($inscricao['curso_nome']); ?></strong>
                    <span><?php echo Helpers::e($inscricao['turma_nome']); ?></span>
                </div>
                <div class="course-card__body">
                    <div class="pill-row">
                        <span class="pill"><?php echo Helpers::e($inscricao['status']); ?></span>
                        <span class="pill"><?php echo Helpers::e($inscricao['pedido_status']); ?></span>
                        <?php if (!empty($inscricao['comprovante_status'])): ?>
                            <span class="pill pill--alert"><?php echo Helpers::e($inscricao['comprovante_status']); ?></span>
                        <?php endif; ?>
                    </div>
                    <p><?php echo Helpers::e($inscricao['participante_nome']); ?> - <?php echo Helpers::e($inscricao['participante_cpf']); ?></p>
                    <div class="course-card__meta">
                        <span>Pedido <?php echo Helpers::e($inscricao['pedido_codigo']); ?></span>
                        <a href="/cursos/detalhe?curso_id=<?php echo (int) $inscricao['curso_evento_id']; ?>">Abrir curso</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
