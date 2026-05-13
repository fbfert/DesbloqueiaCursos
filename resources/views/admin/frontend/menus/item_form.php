<?php
use App\Core\Helpers;
$item = isset($item) ? $item : null;
$old = isset($old) && is_array($old) ? $old : array();
$value = function ($field, $default = '') use ($old, $item) {
    if (array_key_exists($field, $old)) { return $old[$field]; }
    if ($item && array_key_exists($field, $item)) { return $item[$field]; }
    return $default;
};
?>
<section class="admin-page">
    <header class="admin-page__header">
        <div><h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1></div>
        <div class="admin-page__actions"><a class="button-link button-link--ghost" href="/admin/frontend/menus/itens?menu_id=<?php echo (int) ($menu ? $menu['id'] : 0); ?>">Voltar</a></div>
    </header>
    <?php if (!empty($errors)): ?><section class="auth-message auth-message-error"><?php foreach ($errors as $error): ?><p><?php echo Helpers::e($error); ?></p><?php endforeach; ?></section><?php endif; ?>
    <?php if (!$menu): ?>
        <section class="auth-message auth-message-error"><p>Menu não encontrado.</p></section>
    <?php else: ?>
    <section class="status-card">
        <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
            <input type="hidden" name="menu_id" value="<?php echo (int) $menu['id']; ?>">
            <input type="hidden" name="id" value="<?php echo (int) $value('id', 0); ?>">
            <label>Rótulo<input type="text" name="rotulo" value="<?php echo Helpers::e($value('rotulo')); ?>" required></label>
            <label>URL<input type="text" name="url" value="<?php echo Helpers::e($value('url')); ?>" required></label>
            <?php $target = (string) $value('target', '_self'); ?>
            <label>Target
                <select name="target">
                    <option value="_self" <?php echo $target === '_self' ? 'selected' : ''; ?>>_self</option>
                    <option value="_blank" <?php echo $target === '_blank' ? 'selected' : ''; ?>>_blank</option>
                </select>
            </label>
            <label>Rel<input type="text" name="rel" value="<?php echo Helpers::e($value('rel')); ?>" placeholder="noopener noreferrer"></label>
            <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) $value('ordem', 0)); ?>"></label>
            <label><input type="checkbox" name="ativo" value="1" <?php echo (int) $value('ativo', 1) === 1 ? 'checked' : ''; ?>> Ativo</label>
            <?php $cancelUrl = '/admin/frontend/menus/itens?menu_id=' . (int) $menu['id']; ?>
            <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
        </form>
    </section>
    <?php endif; ?>
</section>
