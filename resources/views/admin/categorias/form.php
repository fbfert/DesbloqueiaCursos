<?php use App\Core\Helpers; ?>

<?php $categoria = isset($form_data['categoria']) ? $form_data['categoria'] : null; ?>
<?php $categorias = isset($form_data['categorias']) ? $form_data['categorias'] : array(); ?>

<section class="hero">
    <h1><?php echo Helpers::e($title); ?></h1>
    <p>Cadastro e manutencao de categorias.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
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
        <button type="submit"><?php echo Helpers::e($submit_label); ?></button>
    </form>
</section>

