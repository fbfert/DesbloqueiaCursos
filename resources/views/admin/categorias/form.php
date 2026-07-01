<?php use App\Core\Helpers; ?>

<?php $categoria = isset($form_data['categoria']) ? $form_data['categoria'] : null; ?>
<?php $categorias = isset($form_data['categorias']) ? $form_data['categorias'] : array(); ?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
        <p class="admin-page__subtitle">Cadastro e manutenção de categorias.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo !empty($categoria['id']) ? (int) $categoria['id'] : 0; ?>">
        <label>
            Nome
            <input type="text" name="nome" value="<?php echo Helpers::e($categoria['nome'] ?? ''); ?>" required>
        </label>
        <label>
            Slug
            <input type="text" name="slug" value="<?php echo Helpers::e($categoria['slug'] ?? ''); ?>">
        </label>
        <label>
            Categoria pai
            <select name="parent_id">
                <option value="">Sem categoria pai</option>
                <?php foreach ($categorias as $item): ?>
                    <?php if (!empty($categoria['id']) && (int) $categoria['id'] === (int) $item['id']) continue; ?>
                    <option value="<?php echo (int) $item['id']; ?>" <?php echo !empty($categoria['parent_id']) && (int) $categoria['parent_id'] === (int) $item['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($item['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Ordem
            <input type="number" name="ordem" min="0" value="<?php echo Helpers::e((string) ($categoria['ordem'] ?? 0)); ?>">
        </label>
        <label>
            Status
            <select name="status">
                <option value="ativo" <?php echo (($categoria['status'] ?? '') === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                <option value="inativo" <?php echo (($categoria['status'] ?? '') === 'inativo') ? 'selected' : ''; ?>>Inativo</option>
            </select>
        </label>
        <label class="full">
            Descrição
            <textarea name="descricao" rows="4"><?php echo Helpers::e($categoria['descricao'] ?? ''); ?></textarea>
        </label>
        <label class="full">
            Thumbnail da categoria
            <input type="text" name="thumbnail" value="<?php echo Helpers::e($categoria['thumbnail'] ?? ''); ?>" placeholder="/assets/uploads/categorias/exemplo.jpg ou https://...">
            <small>Informe uma URL pública ou o caminho do arquivo. O upload abaixo substitui este valor quando enviado.</small>
        </label>
        <label class="full">
            Enviar nova thumbnail
            <input type="file" name="thumbnail_upload" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
            <small>Formatos aceitos: JPG, JPEG, PNG, WEBP e GIF. Tamanho máximo: 5 MB.</small>
        </label>
        <?php if (!empty($categoria['thumbnail'])): ?>
            <div class="full" style="display:flex;flex-direction:column;gap:8px;">
                <strong>Prévia atual</strong>
                <img src="<?php echo Helpers::e($categoria['thumbnail']); ?>" alt="<?php echo Helpers::e($categoria['nome'] ?? 'Categoria'); ?>" style="max-width:160px;border-radius:12px;display:block;">
            </div>
        <?php endif; ?>
        <?php $cancel_url = '/admin/categorias'; ?>
        <?php $showSaveAsCopy = !empty($categoria['id']); ?>
        <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
    </form>
</section>
</div>

