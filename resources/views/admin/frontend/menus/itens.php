<?php use App\Core\Helpers; ?>
<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Itens do menu</h1>
            <p class="admin-page__subtitle"><?php echo $menu ? 'Menu: ' . Helpers::e($menu['nome_admin']) : 'Menu não encontrado.'; ?></p>
        </div>
        <div class="admin-page__actions">
            <?php if ($menu): ?><a class="button-link" href="/admin/frontend/menus/itens/criar?menu_id=<?php echo (int) $menu['id']; ?>">Novo item</a><?php endif; ?>
            <a class="button-link button-link--ghost" href="/admin/frontend/menus">Voltar</a>
        </div>
    </header>
    <?php if (!empty($success)): ?><section class="auth-message auth-message-success"><p><?php echo Helpers::e($success); ?></p></section><?php endif; ?>
    <?php if (!empty($errors)): ?><section class="auth-message auth-message-error"><?php foreach ($errors as $error): ?><p><?php echo Helpers::e($error); ?></p><?php endforeach; ?></section><?php endif; ?>

    <?php if ($menu): ?>
    <section class="status-card">
        <form method="post" action="/admin/frontend/menus/itens/reordenar">
            <input type="hidden" name="menu_id" value="<?php echo (int) $menu['id']; ?>">
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Rótulo</th><th>URL</th><th>Target</th><th>Status</th><th>Ordem</th><th>Ações</th></tr></thead>
                    <tbody>
                    <?php if (empty($itens)): ?>
                        <tr><td colspan="6">Nenhum item cadastrado.</td></tr>
                    <?php else: foreach ($itens as $item): ?>
                        <tr>
                            <td><?php echo Helpers::e($item['rotulo']); ?></td>
                            <td><?php echo Helpers::e($item['url']); ?></td>
                            <td><?php echo Helpers::e($item['target']); ?></td>
                            <td><?php echo (int) $item['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?></td>
                            <td><input type="number" name="ordens[<?php echo (int) $item['id']; ?>]" value="<?php echo (int) $item['ordem']; ?>" style="width:90px;"></td>
                            <td><a href="/admin/frontend/menus/itens/editar?menu_id=<?php echo (int) $menu['id']; ?>&item_id=<?php echo (int) $item['id']; ?>">Editar</a> | <a href="#" onclick="return excluirItem(<?php echo (int) $menu['id']; ?>, <?php echo (int) $item['id']; ?>);">Lixeira</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($itens)): ?><div class="cta-group" style="margin-top:12px;"><button type="submit">Salvar ordem</button></div><?php endif; ?>
        </form>
    </section>
    <?php endif; ?>
</section>

<form method="post" action="/admin/frontend/menus/itens/excluir" id="form-excluir-item" style="display:none;">
    <input type="hidden" name="menu_id" id="excluir-item-menu-id" value="">
    <input type="hidden" name="id" id="excluir-item-id" value="">
    <input type="hidden" name="justificativa" id="excluir-item-justificativa" value="">
</form>
<script>
function excluirItem(menuId, itemId) {
    var justificativa = window.prompt('Informe a justificativa para enviar o item à lixeira:');
    if (justificativa === null) return false;
    justificativa = justificativa.trim();
    if (justificativa === '') { window.alert('A justificativa é obrigatória.'); return false; }
    document.getElementById('excluir-item-menu-id').value = String(menuId);
    document.getElementById('excluir-item-id').value = String(itemId);
    document.getElementById('excluir-item-justificativa').value = justificativa;
    document.getElementById('form-excluir-item').submit();
    return false;
}
</script>
