<?php use App\Core\Helpers; ?>

<section class="hero">
    <h1><?php echo Helpers::e($categoria['nome']); ?></h1>
    <p>Detalhes da categoria e acoes administrativas.</p>
</section>

<section class="status-card">
    <dl class="summary-list">
        <dt>ID</dt><dd><?php echo (int) $categoria['id']; ?></dd>
        <dt>Slug</dt><dd><?php echo Helpers::e($categoria['slug']); ?></dd>
        <dt>Status</dt><dd><?php echo Helpers::e($categoria['status']); ?></dd>
        <dt>Ordem</dt><dd><?php echo (int) $categoria['ordem']; ?></dd>
        <dt>Descrição</dt><dd><?php echo Helpers::e($categoria['descricao'] ?? ''); ?></dd>
    </dl>
</section>

<section class="status-card">
    <div class="split-actions">
        <a href="/admin/categorias/editar?categoria_id=<?php echo (int) $categoria['id']; ?>">Editar</a>
        <a href="/admin/categorias">Voltar</a>
    </div>
    <form method="post" action="/admin/categorias/excluir" class="admin-form" style="margin-top: 16px;">
        <input type="hidden" name="id" value="<?php echo (int) $categoria['id']; ?>">
        <label>
            Justificativa para lixeira
            <input type="text" name="justificativa" required>
        </label>
        <button type="submit">Excluir categoria</button>
    </form>
</section>

