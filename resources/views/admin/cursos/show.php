<?php use App\Core\Helpers; ?>
<?php use App\Core\Session; ?>
<?php use App\Services\RbacService; ?>

<?php $canManage = (new RbacService())->userHasPermission(Session::get('usuario_id'), 'conteudo.gerenciar'); ?>

<section class="hero">
    <h1><?php echo Helpers::e($curso['nome']); ?></h1>
    <p>Detalhe administrativo do curso ou evento.</p>
</section>

<section class="status-card">
    <dl class="summary-list">
        <dt>Slug</dt><dd><?php echo Helpers::e($curso['slug']); ?></dd>
        <dt>Categoria</dt><dd><?php echo Helpers::e($curso['categoria_nome'] ?? ''); ?></dd>
        <dt>Tipo</dt><dd><?php echo Helpers::e($curso['tipo']); ?></dd>
        <dt>Modalidade</dt><dd><?php echo Helpers::e($curso['modalidade']); ?></dd>
        <dt>Professor responsavel</dt><dd><?php echo Helpers::e($professor_responsavel['nome'] ?? '-'); ?></dd>
        <dt>Valor</dt><dd>R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></dd>
        <dt>Status</dt><dd><?php echo Helpers::e($curso['status']); ?></dd>
        <dt>Turmas</dt><dd><?php echo (int) $curso['total_turmas']; ?></dd>
        <dt>Pessoas vinculadas</dt><dd><?php echo (int) $curso['total_pessoas_vinculadas']; ?></dd>
    </dl>
</section>

<section class="status-card">
    <div class="split-actions">
        <?php if ($canManage): ?>
            <a href="/admin/cursos/editar?curso_id=<?php echo (int) $curso['id']; ?>">Editar</a>
        <?php endif; ?>
        <a href="/admin/cursos">Voltar</a>
        <?php if ($canManage): ?>
            <form method="post" action="/admin/cursos/status">
                <input type="hidden" name="id" value="<?php echo (int) $curso['id']; ?>">
                <input type="hidden" name="status" value="<?php echo $curso['status'] === 'ativo' ? 'inativo' : 'ativo'; ?>">
                <button type="submit"><?php echo $curso['status'] === 'ativo' ? 'Inativar' : 'Ativar'; ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($canManage): ?>
        <form method="post" action="/admin/cursos/excluir" class="admin-form" style="margin-top: 16px;">
            <input type="hidden" name="id" value="<?php echo (int) $curso['id']; ?>">
            <label>
                Justificativa para lixeira
                <input type="text" name="justificativa" required>
            </label>
            <button type="submit">Excluir curso/evento</button>
        </form>
    <?php endif; ?>
</section>
