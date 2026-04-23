<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1><?php echo Helpers::e($curso['nome']); ?></h1>
    <p><?php echo Helpers::e($curso['descricao_curta']); ?></p>
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
        <dl class="summary-list">
            <dt>Categoria</dt>
            <dd><?php echo Helpers::e($curso['categoria_nome']); ?></dd>
            <dt>Tipo</dt>
            <dd><?php echo Helpers::e($curso['tipo']); ?></dd>
            <dt>Modalidade</dt>
            <dd><?php echo Helpers::e($curso['modalidade']); ?></dd>
            <dt>Valor</dt>
            <dd><?php echo Helpers::e($curso['valor']); ?></dd>
        </dl>
        <p><?php echo Helpers::e($curso['descricao_completa']); ?></p>
    </article>

    <article class="checkout-panel">
        <h2>Turmas</h2>
        <?php if (empty($curso['turmas'])): ?>
            <p class="muted">Nenhuma turma cadastrada para este item.</p>
        <?php else: ?>
            <div class="stack">
                <?php foreach ($curso['turmas'] as $turma): ?>
                    <div class="status-card">
                        <strong><?php echo Helpers::e($turma['nome']); ?></strong>
                        <span><?php echo Helpers::e($turma['codigo']); ?></span>
                        <span><?php echo Helpers::e($turma['status']); ?></span>
                        <?php if ($turma['status'] !== 'cancelada'): ?>
                            <div class="cta-group">
                                <a class="button-link" href="/checkout/inscricao?curso_id=<?php echo (int) $curso['id']; ?>&turma_id=<?php echo (int) $turma['id']; ?>">Inscrever nesta turma</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="checkout-panel">
    <h2>Pessoas vinculadas</h2>
    <?php if (empty($curso['pessoas_vinculadas'])): ?>
        <p class="muted">Sem vinculos cadastrados.</p>
    <?php else: ?>
        <div class="pill-row">
            <?php foreach ($curso['pessoas_vinculadas'] as $pessoa): ?>
                <span class="pill"><?php echo Helpers::e($pessoa['tipo_pessoa']); ?> - <?php echo Helpers::e($pessoa['nome']); ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

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
