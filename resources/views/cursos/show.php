<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1><?php echo Helpers::e($curso['nome']); ?></h1>
    <p><?php echo Helpers::e($curso['descricao_curta'] ?: 'Curso disponivel para inscricao publica.'); ?></p>
</section>

<?php if (!empty($success)): ?>
    <section class="auth-message auth-message-success">
        <p><?php echo Helpers::e($success); ?></p>
    </section>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <section class="auth-message auth-message-error">
        <?php foreach ($errors as $error): ?>
            <p><?php echo Helpers::e($error); ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if (!empty($loggedIn)): ?>
    <section class="notice notice--success">
        <strong>Conta ativa</strong>
        <span><?php echo Helpers::e($usuarioNome); ?></span>
    </section>
<?php endif; ?>

<section class="checkout-grid">
    <article class="checkout-panel">
        <h2>Detalhes</h2>
        <?php if (!empty($curso['thumbnail'])): ?>
            <div class="course-detail__image">
                <img src="<?php echo Helpers::e($curso['thumbnail']); ?>" alt="<?php echo Helpers::e($curso['nome']); ?>">
            </div>
        <?php endif; ?>
        <dl class="summary-list">
            <dt>Categoria</dt>
            <dd><?php echo Helpers::e($curso['categoria_nome'] ?: 'Sem categoria'); ?></dd>
            <dt>Tipo</dt>
            <dd><?php echo Helpers::e($curso['tipo']); ?></dd>
            <dt>Modalidade</dt>
            <dd><?php echo Helpers::e($curso['modalidade']); ?></dd>
            <dt>Valor</dt>
            <dd>R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></dd>
            <?php if (!empty($curso['professor_responsavel']['nome'])): ?>
                <dt>Professor responsavel</dt>
                <dd><?php echo Helpers::e($curso['professor_responsavel']['nome']); ?></dd>
            <?php endif; ?>
        </dl>
        <p><?php echo Helpers::e($curso['descricao_completa'] ?: $curso['descricao_curta']); ?></p>
    </article>

    <article class="checkout-panel">
        <h2>Turmas abertas</h2>
        <?php if (empty($curso['turmas_abertas'])): ?>
            <p class="muted">Não ha turma aberta no momento para inscricao publica.</p>
        <?php else: ?>
            <div class="stack">
                <?php foreach ($curso['turmas_abertas'] as $turma): ?>
                    <div class="status-card<?php echo !empty($curso['turma_selecionada']) && (int) $curso['turma_selecionada']['id'] === (int) $turma['id'] ? ' status-card--selected' : ''; ?>">
                        <strong><?php echo Helpers::e($turma['nome']); ?></strong>
                        <span>Codigo <?php echo Helpers::e($turma['codigo']); ?></span>
                        <?php if (!empty($turma['data_inicio'])): ?>
                            <span>Inicio <?php echo Helpers::e($turma['data_inicio']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($turma['data_fim'])): ?>
                            <span>Fim <?php echo Helpers::e($turma['data_fim']); ?></span>
                        <?php endif; ?>
                        <span>Status <?php echo Helpers::e($turma['status']); ?></span>
                        <div class="cta-group">
                            <a class="button-link button-link--ghost" href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>&turma_id=<?php echo (int) $turma['id']; ?>">Ver turma</a>
                            <a class="button-link" href="/inscricao?curso_id=<?php echo (int) $curso['id']; ?>&turma_id=<?php echo (int) $turma['id']; ?>">Inscrever nesta turma</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<?php if (!empty($curso['inscricao_disponivel']) && !empty($curso['turma_selecionada'])): ?>
    <section class="notice notice--success">
        <strong>Inscrição disponivel</strong>
        <p>A turma <?php echo Helpers::e($curso['turma_selecionada']['nome']); ?> esta aberta e pode receber inscricoes agora.</p>
        <div class="cta-group">
            <a class="button-link" href="/inscricao?curso_id=<?php echo (int) $curso['id']; ?>&turma_id=<?php echo (int) $curso['turma_selecionada']['id']; ?>">Iniciar inscricao</a>
        </div>
    </section>
<?php else: ?>
    <section class="notice">
        <strong>Sem inscricao aberta</strong>
        <p>Este curso esta publico, mas ainda não possui turma aberta para inscricao.</p>
    </section>
<?php endif; ?>

<?php if (empty($loggedIn)): ?>
    <section class="notice">
        <strong>Para continuar</strong>
        <p>Entre na sua conta ou crie uma nova para seguir com a inscricao.</p>
        <div class="cta-group">
            <a class="button-link" href="/login">Entrar</a>
            <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
        </div>
    </section>
<?php endif; ?>

