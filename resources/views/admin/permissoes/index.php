<?php use App\Core\Helpers; ?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Permissões e papéis</h1>
            <p class="admin-page__subtitle">CRUD de papéis e permissões de acesso.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link" href="/admin/permissoes/perfil/criar">Novo papel</a>
            <a class="button-link button-link--ghost" href="/admin/permissoes/item/criar">Nova permissão</a>
        </div>
    </header>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <h2>Papéis</h2>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Nome</th><th>Slug</th><th>Status</th><th>Permissões</th><th>Ações</th></tr></thead>
                <tbody>
                <?php if (empty($perfis)): ?>
                    <tr><td colspan="6">Nenhum papel encontrado.</td></tr>
                <?php else: foreach ($perfis as $perfil): ?>
                    <tr>
                        <td><?php echo (int) $perfil['id']; ?></td>
                        <td><?php echo Helpers::e($perfil['nome']); ?></td>
                        <td><?php echo Helpers::e($perfil['slug']); ?></td>
                        <td><?php echo Helpers::e($perfil['status']); ?></td>
                        <td><?php echo (int) $perfil['total_permissoes']; ?></td>
                        <td>
                            <a href="/admin/permissoes/perfil/editar?perfil_id=<?php echo (int) $perfil['id']; ?>">Editar</a> |
                            <a href="#" onclick="return excluirPerfil(<?php echo (int) $perfil['id']; ?>);">Excluir</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <h2>Permissões</h2>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Módulo</th><th>Ação</th><th>Slug</th><th>Nome</th><th>Ações</th></tr></thead>
                <tbody>
                <?php if (empty($permissoes)): ?>
                    <tr><td colspan="6">Nenhuma permissão encontrada.</td></tr>
                <?php else: foreach ($permissoes as $permissao): ?>
                    <tr>
                        <td><?php echo (int) $permissao['id']; ?></td>
                        <td><?php echo Helpers::e($permissao['modulo']); ?></td>
                        <td><?php echo Helpers::e($permissao['acao']); ?></td>
                        <td><?php echo Helpers::e($permissao['slug']); ?></td>
                        <td><?php echo Helpers::e($permissao['nome']); ?></td>
                        <td>
                            <a href="/admin/permissoes/item/editar?permissao_id=<?php echo (int) $permissao['id']; ?>">Editar</a> |
                            <a href="#" onclick="return excluirPermissao(<?php echo (int) $permissao['id']; ?>);">Excluir</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

<form method="post" action="/admin/permissoes/perfil/excluir" id="form-excluir-perfil" style="display:none;">
    <input type="hidden" name="id" id="excluir-perfil-id"><input type="hidden" name="justificativa" id="excluir-perfil-justificativa">
</form>
<form method="post" action="/admin/permissoes/item/excluir" id="form-excluir-permissao" style="display:none;">
    <input type="hidden" name="id" id="excluir-permissao-id"><input type="hidden" name="justificativa" id="excluir-permissao-justificativa">
</form>
<script>
function excluirPerfil(id){var j=prompt('Justificativa para lixeira:');if(j===null)return false;j=j.trim();if(!j){alert('A justificativa é obrigatória.');return false;}document.getElementById('excluir-perfil-id').value=id;document.getElementById('excluir-perfil-justificativa').value=j;document.getElementById('form-excluir-perfil').submit();return false;}
function excluirPermissao(id){var j=prompt('Justificativa para lixeira:');if(j===null)return false;j=j.trim();if(!j){alert('A justificativa é obrigatória.');return false;}document.getElementById('excluir-permissao-id').value=id;document.getElementById('excluir-permissao-justificativa').value=j;document.getElementById('form-excluir-permissao').submit();return false;}
</script>
