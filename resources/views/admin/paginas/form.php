<?php
use App\Core\Helpers;

$pagina = isset($form_data['pagina']) ? $form_data['pagina'] : null;
$old = isset($old) && is_array($old) ? $old : array();

$value = function ($field, $default = '') use ($old, $pagina) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }

    if ($pagina && array_key_exists($field, $pagina)) {
        return $pagina[$field];
    }

    return $default;
};
?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
            <p class="admin-page__subtitle">Defina título, slug, rota e conteúdo HTML da página.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/paginas">Voltar</a>
        </div>
    </header>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="status-card">
        <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
            <input type="hidden" name="id" value="<?php echo (int) $value('id', 0); ?>">

            <label>
                Título
                <input type="text" name="titulo" value="<?php echo Helpers::e($value('titulo')); ?>" required>
            </label>

            <label>
                Slug
                <input type="text" name="slug" value="<?php echo Helpers::e($value('slug')); ?>" placeholder="termos-de-uso">
            </label>

            <label>
                Rota
                <input type="text" name="rota" value="<?php echo Helpers::e($value('rota')); ?>" placeholder="/termos-de-uso" required>
            </label>

            <label>
                Resumo
                <textarea name="resumo" rows="3"><?php echo Helpers::e($value('resumo')); ?></textarea>
            </label>

            <label>
                Status
                <?php $status = (string) $value('status', 'rascunho'); ?>
                <select name="status">
                    <option value="rascunho" <?php echo $status === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                    <option value="publicada" <?php echo $status === 'publicada' ? 'selected' : ''; ?>>Publicada</option>
                    <option value="inativa" <?php echo $status === 'inativa' ? 'selected' : ''; ?>>Inativa</option>
                </select>
            </label>

            <label>
                Ordem
                <input type="number" name="ordem" value="<?php echo Helpers::e((string) $value('ordem', 0)); ?>">
            </label>

            <label>
                Conteúdo HTML
                <textarea name="conteudo_html" rows="14" style="font-family:Consolas, monospace;"><?php echo Helpers::e($value('conteudo_html')); ?></textarea>
            </label>

            <?php $cancelUrl = '/admin/paginas'; ?>
            <?php $showSaveAsCopy = !empty($pagina); ?>
            <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
        </form>
    </section>
</section>
