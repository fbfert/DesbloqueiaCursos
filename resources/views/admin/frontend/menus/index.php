<?php use App\Core\Helpers; ?>
<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Menus do frontend</h1>
            <p class="admin-page__subtitle">Gerencie menus exibidos no site público.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link" href="/admin/frontend/menus/criar">Novo menu</a>
        </div>
    </header>
    <?php if (!empty($success)): ?><section class="auth-message auth-message-success"><p><?php echo Helpers::e($success); ?></p></section><?php endif; ?>
    <?php if (!empty($errors)): ?><section class="auth-message auth-message-error"><?php foreach ($errors as $error): ?><p><?php echo Helpers::e($error); ?></p><?php endforeach; ?></section><?php endif; ?>

    <section class="status-card">
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Código</th><th>Nome</th><th>Posição</th><th>Status</th><th>Ordem</th><th>Ações</th></tr></thead>
                <tbody>
                <?php if (empty($menus)): ?>
                    <tr><td colspan="6">Nenhum menu cadastrado.</td></tr>
                <?php else: foreach ($menus as $menu): ?>
                    <tr>
                        <td><?php echo Helpers::e($menu['codigo']); ?></td>
                        <td><?php echo Helpers::e($menu['nome_admin']); ?></td>
                        <td><?php echo Helpers::e($menu['posicao']); ?></td>
                        <td><?php echo (int) $menu['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?></td>
                        <td><?php echo (int) $menu['ordem']; ?></td>
                        <td><a href="/admin/frontend/menus/editar?menu_id=<?php echo (int) $menu['id']; ?>">Editar</a> | <a href="/admin/frontend/menus/itens?menu_id=<?php echo (int) $menu['id']; ?>">Itens</a> | <a href="#" onclick="return excluirMenu(<?php echo (int) $menu['id']; ?>);">Excluir</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
<form method="post" action="/admin/frontend/menus/excluir" id="form-excluir-menu" style="display:none;">
    <input type="hidden" name="id" id="excluir-menu-id" value="">
    <input type="hidden" name="justificativa" id="excluir-menu-justificativa" value="">
</form>
<script>
function excluirMenu(id) {
    var justificativa = window.prompt('Informe a justificativa para enviar o menu à lixeira:');
    if (justificativa === null) return false;
    justificativa = justificativa.trim();
    if (justificativa === '') { window.alert('A justificativa é obrigatória.'); return false; }
    document.getElementById('excluir-menu-id').value = String(id);
    document.getElementById('excluir-menu-justificativa').value = justificativa;
    document.getElementById('form-excluir-menu').submit();
    return false;
}
</script>
