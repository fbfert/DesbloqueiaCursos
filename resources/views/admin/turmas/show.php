<?php use App\Core\Helpers; ?>
<?php use App\Core\Session; ?>
<?php use App\Services\RbacService; ?>

<?php $canManage = (new RbacService())->userHasPermission(Session::get('usuario_id'), 'conteudo.gerenciar'); ?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($turma['nome']); ?></h1>
        <p class="admin-page__subtitle">Detalhe administrativo da turma.</p>
    </div>
</section>

<section class="status-card">
    <dl class="summary-list">
        <dt>Código</dt><dd><?php echo Helpers::e($turma['codigo']); ?></dd>
        <dt>Curso</dt><dd><?php echo Helpers::e($turma['curso_nome']); ?></dd>
        <dt>Professor responsável</dt><dd><?php echo Helpers::e($turma['professor_responsavel_nome'] ?? '-'); ?></dd>
        <dt>Categoria</dt><dd><?php echo Helpers::e($turma['categoria_nome'] ?? ''); ?></dd>
        <dt>Modalidade</dt><dd><?php echo Helpers::e($turma['curso_modalidade']); ?></dd>
        <dt>Data de início</dt><dd><?php echo Helpers::e((string) $turma['data_inicio']); ?></dd>
        <dt>Data de fim</dt><dd><?php echo Helpers::e((string) $turma['data_fim']); ?></dd>
        <dt>Vagas</dt><dd><?php echo (int) $turma['vagas']; ?></dd>
        <dt>Status</dt><dd><?php echo Helpers::e($turma['status']); ?></dd>
    </dl>
</section>
</div>

<section class="status-card">
    <div class="split-actions">
        <?php if ($canManage): ?>
            <a href="/admin/turmas/editar?turma_id=<?php echo (int) $turma['id']; ?>">Editar</a>
        <?php endif; ?>
        <a href="/admin/turmas">Voltar</a>
        <?php if ($canManage): ?>
            <form method="post" action="/admin/turmas/status" class="admin-form">
                <input type="hidden" name="id" value="<?php echo (int) $turma['id']; ?>">
                <input type="hidden" name="status" value="<?php echo $turma['status'] === 'aberta' ? 'encerrada' : 'aberta'; ?>">
                <button type="submit"><?php echo $turma['status'] === 'aberta' ? 'Encerrar' : 'Abrir'; ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($canManage): ?>
        <form method="post" action="/admin/turmas/excluir" class="admin-form admin-mt-16">
            <input type="hidden" name="id" value="<?php echo (int) $turma['id']; ?>">
            <label>
                Justificativa para lixeira
                <input type="text" name="justificativa" required>
            </label>
            <button type="submit">Excluir turma</button>
        </form>
    <?php endif; ?>
</section>
