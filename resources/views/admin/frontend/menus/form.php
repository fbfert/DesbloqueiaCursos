<?php
use App\Core\Helpers;
$menu = isset($form_data['menu']) ? $form_data['menu'] : null;
$old = isset($old) && is_array($old) ? $old : array();
$value = function ($field, $default = '') use ($old, $menu) {
    if (array_key_exists($field, $old)) { return $old[$field]; }
    if ($menu && array_key_exists($field, $menu)) { return $menu[$field]; }
    return $default;
};
?>
<section class="admin-page">
    <header class="admin-page__header">
        <div><h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1></div>
        <div class="admin-page__actions"><a class="button-link button-link--ghost" href="/admin/frontend/menus">Voltar</a></div>
    </header>
    <?php if (!empty($errors)): ?><section class="auth-message auth-message-error"><?php foreach ($errors as $error): ?><p><?php echo Helpers::e($error); ?></p><?php endforeach; ?></section><?php endif; ?>
    <section class="status-card">
        <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
            <input type="hidden" name="id" value="<?php echo (int) $value('id', 0); ?>">
            <label>Nome administrativo<input type="text" name="nome_admin" value="<?php echo Helpers::e($value('nome_admin')); ?>" required></label>
            <label>Código<input type="text" name="codigo" value="<?php echo Helpers::e($value('codigo')); ?>" required></label>
            <label>Posição<input type="text" name="posicao" value="<?php echo Helpers::e($value('posicao')); ?>" required></label>
            <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) $value('ordem', 0)); ?>"></label>
            <label>Observações administrativas<textarea name="observacoes_admin" rows="4"><?php echo Helpers::e($value('observacoes_admin')); ?></textarea></label>
            <label><input type="checkbox" name="ativo" value="1" <?php echo (int) $value('ativo', 1) === 1 ? 'checked' : ''; ?>> Ativo</label>
            <?php $cancelUrl = '/admin/frontend/menus'; ?>
            <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
        </form>
    </section>
</section>
