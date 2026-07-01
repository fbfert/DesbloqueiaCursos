<?php use App\Core\Helpers; ?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($categoria['nome']); ?></h1>
        <p class="admin-page__subtitle">Detalhes da categoria e ações administrativas.</p>
    </div>
</section>

<section class="status-card">
    <dl class="summary-list">
        <dt>ID</dt><dd><?php echo (int) $categoria['id']; ?></dd>
        <dt>Slug</dt><dd><?php echo Helpers::e($categoria['slug']); ?></dd>
        <dt>Status</dt><dd><?php echo Helpers::e($categoria['status']); ?></dd>
        <dt>Ordem</dt><dd><?php echo (int) $categoria['ordem']; ?></dd>
        <dt>Imagem</dt>
        <dd>
            <?php if (!empty($categoria['thumbnail'])): ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <img src="<?php echo Helpers::e($categoria['thumbnail']); ?>" alt="<?php echo Helpers::e($categoria['nome']); ?>" style="max-width:180px;border-radius:12px;display:block;">
                    <span><?php echo Helpers::e($categoria['thumbnail']); ?></span>
                </div>
            <?php else: ?>
                <span class="muted">Sem imagem</span>
            <?php endif; ?>
        </dd>
        <dt>Descrição</dt><dd><?php echo Helpers::e($categoria['descricao'] ?? ''); ?></dd>
    </dl>
</section>
</div>

<section class="status-card">
    <div class="split-actions">
        <a href="/admin/categorias/editar?categoria_id=<?php echo (int) $categoria['id']; ?>">Editar</a>
        <a href="/admin/categorias">Voltar</a>
    </div>
    <form method="post" action="/admin/categorias/excluir" class="admin-form admin-mt-16">
        <input type="hidden" name="id" value="<?php echo (int) $categoria['id']; ?>">
        <label>
            Justificativa para lixeira
            <input type="text" name="justificativa" required>
        </label>
        <button type="submit" onclick="return confirmarAcaoCritica({ palavra: 'EXCLUIR', pergunta: 'Você conferiu a exclusão desta categoria?' });">Excluir categoria</button>
    </form>
</section>

