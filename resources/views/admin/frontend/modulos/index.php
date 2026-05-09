<?php use App\Core\Helpers; ?>
<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Módulos do frontend</h1>
            <p class="admin-page__subtitle">Gerencie os blocos dinâmicos do site público.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link" href="/admin/frontend/modulos/criar">Novo módulo</a>
        </div>
    </header>

    <?php if (!empty($success)): ?><section class="auth-message auth-message-success"><p><?php echo Helpers::e($success); ?></p></section><?php endif; ?>
    <?php if (!empty($errors)): ?><section class="auth-message auth-message-error"><?php foreach ($errors as $error): ?><p><?php echo Helpers::e($error); ?></p><?php endforeach; ?></section><?php endif; ?>

    <section class="status-card">
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Código</th><th>Nome</th><th>Posição</th><th>Tipo</th><th>Status</th><th>Ordem</th><th>Ações</th></tr></thead>
                <tbody>
                <?php if (empty($modulos)): ?>
                    <tr><td colspan="7">Nenhum módulo cadastrado.</td></tr>
                <?php else: foreach ($modulos as $modulo): ?>
                    <tr>
                        <td><?php echo Helpers::e($modulo['codigo']); ?></td>
                        <td><?php echo Helpers::e($modulo['nome_admin']); ?></td>
                        <td><?php echo Helpers::e($modulo['posicao']); ?></td>
                        <td><?php echo Helpers::e($modulo['tipo']); ?></td>
                        <td><?php echo (int) $modulo['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?></td>
                        <td><?php echo (int) $modulo['ordem']; ?></td>
                        <td><a href="/admin/frontend/modulos/editar?modulo_id=<?php echo (int) $modulo['id']; ?>">Editar</a> | <a href="#" onclick="return excluirModulo(<?php echo (int) $modulo['id']; ?>);">Lixeira</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="status-card">
        <details>
            <summary><strong>Lixeira de módulos</strong></summary>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>ID lixeira</th><th>ID módulo</th><th>Nome</th><th>Código</th><th>Justificativa</th><th>Excluído por</th><th>Data</th></tr></thead>
                    <tbody>
                    <?php if (empty($lixeira_modulos)): ?>
                        <tr><td colspan="7">Nenhum módulo na lixeira.</td></tr>
                    <?php else: foreach ($lixeira_modulos as $registro): $snapshot = json_decode((string) $registro['snapshot_dados'], true); ?>
                        <tr>
                            <td><?php echo (int) $registro['id']; ?></td>
                            <td><?php echo (int) $registro['entidade_id']; ?></td>
                            <td><?php echo Helpers::e(is_array($snapshot) && isset($snapshot['nome_admin']) ? $snapshot['nome_admin'] : '-'); ?></td>
                            <td><?php echo Helpers::e(is_array($snapshot) && isset($snapshot['codigo']) ? $snapshot['codigo'] : '-'); ?></td>
                            <td><?php echo Helpers::e($registro['justificativa']); ?></td>
                            <td><?php echo Helpers::e($registro['excluido_por_nome'] ?: '-'); ?></td>
                            <td><?php echo Helpers::e($registro['created_at']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </details>
    </section>
</section>

<form method="post" action="/admin/frontend/modulos/excluir" id="form-excluir-modulo" style="display:none;">
    <input type="hidden" name="id" id="excluir-modulo-id" value="">
    <input type="hidden" name="justificativa" id="excluir-modulo-justificativa" value="">
</form>
<script>
function excluirModulo(id) {
    var justificativa = window.prompt('Informe a justificativa para enviar o módulo à lixeira:');
    if (justificativa === null) return false;
    justificativa = justificativa.trim();
    if (justificativa === '') { window.alert('A justificativa é obrigatória.'); return false; }
    document.getElementById('excluir-modulo-id').value = String(id);
    document.getElementById('excluir-modulo-justificativa').value = justificativa;
    document.getElementById('form-excluir-modulo').submit();
    return false;
}
</script>
