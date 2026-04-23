<?php use App\Core\Helpers; ?>

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
        <dt>Valor</dt><dd>R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></dd>
        <dt>Status</dt><dd><?php echo Helpers::e($curso['status']); ?></dd>
        <dt>Turmas</dt><dd><?php echo (int) $curso['total_turmas']; ?></dd>
        <dt>Pessoas vinculadas</dt><dd><?php echo (int) $curso['total_pessoas_vinculadas']; ?></dd>
    </dl>
</section>

<section class="status-card">
    <div class="split-actions">
        <a href="/admin/cursos/editar?curso_id=<?php echo (int) $curso['id']; ?>">Editar</a>
        <a href="/admin/cursos">Voltar</a>
    </div>
    <form method="post" action="/admin/cursos/excluir" class="admin-form" style="margin-top: 16px;">
        <input type="hidden" name="id" value="<?php echo (int) $curso['id']; ?>">
        <label>
            Justificativa para lixeira
            <input type="text" name="justificativa" required>
        </label>
        <button type="submit">Excluir curso/evento</button>
    </form>
</section>
