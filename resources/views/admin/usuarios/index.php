<?php use App\Core\Helpers; ?>
<?php
$filters = isset($filters) && is_array($filters) ? $filters : array();
$currentSortBy = isset($filters['sort_by']) ? (string) $filters['sort_by'] : 'id';
$currentSortDir = isset($filters['sort_dir']) ? strtolower((string) $filters['sort_dir']) : 'desc';
$queryBase = array(
    'q' => isset($filters['q']) ? $filters['q'] : '',
    'status' => isset($filters['status']) ? $filters['status'] : '',
    'perfil' => isset($filters['perfil']) ? $filters['perfil'] : '',
);
function sortUrl($field, $currentSortBy, $currentSortDir, $queryBase) {
    $nextDir = ($currentSortBy === $field && $currentSortDir === 'asc') ? 'desc' : 'asc';
    $params = array_merge($queryBase, array('sort_by' => $field, 'sort_dir' => $nextDir));
    return '/admin/usuarios?' . http_build_query($params);
}
?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Usuários</h1>
            <p class="admin-page__subtitle">Gestão de usuários com papéis de acesso.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link" href="/admin/usuarios/criar">Novo usuário</a>
        </div>
    </header>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card usuarios-filtros-card" style="margin-bottom:12px;">
        <form method="get" action="/admin/usuarios" class="admin-filters">
            <div class="admin-filters__row usuarios-filtros-row">
            <label>Busca
                <input type="text" name="q" value="<?php echo Helpers::e((string) ($filters['q'] ?? '')); ?>" placeholder="Nome, e-mail ou CPF">
            </label>
            <label>Status
                <select name="status">
                    <?php $statusAtual = (string) ($filters['status'] ?? ''); ?>
                    <option value="">Todos</option>
                    <option value="ativo" <?php echo $statusAtual === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="inativo" <?php echo $statusAtual === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                    <option value="bloqueado" <?php echo $statusAtual === 'bloqueado' ? 'selected' : ''; ?>>Bloqueado</option>
                </select>
            </label>
            <label>Perfil
                <select name="perfil">
                    <?php $perfilAtual = (string) ($filters['perfil'] ?? ''); ?>
                    <option value="">Todos</option>
                    <?php foreach ($perfis as $perfil): ?>
                        <option value="<?php echo Helpers::e($perfil['slug']); ?>" <?php echo $perfilAtual === $perfil['slug'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($perfil['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            </div>
            <input type="hidden" name="sort_by" value="<?php echo Helpers::e($currentSortBy); ?>">
            <input type="hidden" name="sort_dir" value="<?php echo Helpers::e($currentSortDir); ?>">
            <div class="cta-group usuarios-filtros-acoes">
                <button type="submit">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/usuarios">Limpar filtros</a>
            </div>
        </form>
    </section>

    <section class="status-card usuarios-lista-card">
        <div class="table-wrap">
            <table class="admin-table admin-table--usuarios-lista">
                <thead>
                    <tr>
                        <th><a href="<?php echo Helpers::e(sortUrl('id', $currentSortBy, $currentSortDir, $queryBase)); ?>">ID</a></th>
                        <th><a href="<?php echo Helpers::e(sortUrl('nome', $currentSortBy, $currentSortDir, $queryBase)); ?>">Nome</a></th>
                        <th><a href="<?php echo Helpers::e(sortUrl('email', $currentSortBy, $currentSortDir, $queryBase)); ?>">E-mail</a></th>
                        <th><a href="<?php echo Helpers::e(sortUrl('cpf', $currentSortBy, $currentSortDir, $queryBase)); ?>">CPF</a></th>
                        <th>Perfil</th>
                        <th><a href="<?php echo Helpers::e(sortUrl('status', $currentSortBy, $currentSortDir, $queryBase)); ?>">Status</a></th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr><td colspan="7">Nenhum usuário encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo (int) $usuario['id']; ?></td>
                                <td><a href="/admin/usuarios/editar?usuario_id=<?php echo (int) $usuario['id']; ?>"><?php echo Helpers::e($usuario['nome']); ?></a></td>
                                <td><?php echo Helpers::e($usuario['email']); ?></td>
                                <td><?php echo Helpers::e($usuario['cpf']); ?></td>
                                <td><?php echo Helpers::e((string) $usuario['perfis']); ?></td>
                                <td><?php echo Helpers::e($usuario['status']); ?></td>
                                <td class="usuarios-acoes-col">
                                    <a href="#" onclick="return excluirUsuario(<?php echo (int) $usuario['id']; ?>);">Inativar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <details>
            <summary><strong>Usuários inativos</strong></summary>
            <div class="table-wrap" style="margin-top:12px;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID lixeira</th>
                            <th>Usuário</th>
                            <th>Justificativa</th>
                            <th>Excluído por</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lixeira_usuarios)): ?>
                            <tr><td colspan="5">Nenhum item na lixeira de usuários.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lixeira_usuarios as $item): ?>
                                <tr>
                                    <td><?php echo (int) $item['id']; ?></td>
                                    <td><?php echo Helpers::e($item['snapshot_nome'] !== '' ? $item['snapshot_nome'] : ('ID ' . (int) $item['entidade_id'])); ?></td>
                                    <td><?php echo Helpers::e($item['justificativa']); ?></td>
                                    <td><?php echo Helpers::e((string) $item['excluido_por_nome']); ?></td>
                                    <td><?php echo Helpers::e((string) $item['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </details>
    </section>
</section>

<form method="post" action="/admin/usuarios/excluir" id="form-excluir-usuario" style="display:none;">
    <input type="hidden" name="id" id="excluir-usuario-id" value="">
    <input type="hidden" name="justificativa" id="excluir-usuario-justificativa" value="">
</form>

<script>
function excluirUsuario(usuarioId) {
    var justificativa = window.prompt('Informe a justificativa para inativar o usuário e enviar para a lixeira:');
    if (justificativa === null) return false;
    justificativa = justificativa.trim();
    if (!justificativa) {
        window.alert('A justificativa é obrigatória.');
        return false;
    }
    document.getElementById('excluir-usuario-id').value = String(usuarioId);
    document.getElementById('excluir-usuario-justificativa').value = justificativa;
    document.getElementById('form-excluir-usuario').submit();
    return false;
}
</script>
