<?php
$totalPerfis = is_array($profiles) ? count($profiles) : 0;
$totalPermissoes = 0;
$totalModulos = is_array($permissions) ? count($permissions) : 0;

foreach ((array) $permissions as $grupoPermissoes) {
    if (is_array($grupoPermissoes)) {
        $totalPermissoes += count($grupoPermissoes);
    }
}
?>

<div class="admin-page">
    <section class="hero admin-dashboard-hero">
        <div class="hero__content">
            <h1>RBAC</h1>
            <p>Perfis, permissões e controle de acesso.</p>
        </div>
        <div class="hero__panel admin-dashboard-hero__panel">
            <strong>Resumo rápido</strong>
            <div class="admin-dashboard-highlight">
                <span>Perfis ativos</span>
                <strong><?php echo (int) $totalPerfis; ?></strong>
                <small>grupos de acesso cadastrados</small>
            </div>
            <div class="admin-dashboard-highlight">
                <span>Permissões</span>
                <strong><?php echo (int) $totalPermissoes; ?></strong>
                <small>regras disponíveis</small>
            </div>
            <div class="admin-dashboard-highlight">
                <span>Módulos</span>
                <strong><?php echo (int) $totalModulos; ?></strong>
                <small>agrupamentos de permissão</small>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Atalhos de acesso</h2>
        </div>
        <div class="quick-actions quick-actions--dashboard">
            <a class="card-link admin-shortcut" href="/admin/configuracoes-globais/seguranca"><span>Configurações de segurança</span><small>Políticas gerais de acesso</small></a>
            <a class="card-link admin-shortcut" href="/admin/dashboard"><span>Dashboard</span><small>Voltar ao painel executivo</small></a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Perfis</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Slug</th>
                    <th>Nome</th>
                    <th>Permissões</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($profiles as $profile): ?>
                    <tr>
                        <td><?php echo (int) $profile['id']; ?></td>
                        <td><?php echo htmlspecialchars($profile['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($profile['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $profile['total_permissoes']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Permissões</h2>
        </div>
        <?php foreach ($permissions as $module => $items): ?>
            <h3><?php echo htmlspecialchars($module, ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="table-wrap">
                <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Slug</th>
                        <th>Ação</th>
                        <th>Nome</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $permission): ?>
                        <tr>
                            <td><?php echo (int) $permission['id']; ?></td>
                            <td><?php echo htmlspecialchars($permission['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($permission['acao'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($permission['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Atribuir permissões a perfil</h2>
        </div>
        <form method="post" action="/admin/rbac/perfis/permissoes" class="admin-form">
        <label>
            Perfil
            <select name="perfil_id" required>
                <option value="">Selecione</option>
                <?php foreach ($profiles as $profile): ?>
                    <option value="<?php echo (int) $profile['id']; ?>"><?php echo htmlspecialchars($profile['nome'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="permission-grid">
            <?php foreach ($permissions as $module => $items): ?>
                <div class="permission-group">
                    <h3><?php echo htmlspecialchars($module, ENT_QUOTES, 'UTF-8'); ?></h3>
                    <?php foreach ($items as $permission): ?>
                        <label class="auth-check">
                            <input type="checkbox" name="permissao_ids[]" value="<?php echo (int) $permission['id']; ?>">
                            <?php echo htmlspecialchars($permission['nome'], ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
            <button type="submit">Salvar permissões</button>
        </form>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Atribuir perfis a usuário</h2>
        </div>
        <form method="post" action="/admin/rbac/usuarios/perfis" class="admin-form">
        <label>
            ID do usuário
            <input type="number" name="usuario_id" min="1" required>
        </label>
        <div class="permission-grid">
            <?php foreach ($profiles as $profile): ?>
                <label class="auth-check">
                    <input type="checkbox" name="perfil_ids[]" value="<?php echo (int) $profile['id']; ?>">
                    <?php echo htmlspecialchars($profile['nome'], ENT_QUOTES, 'UTF-8'); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="submit">Salvar perfis</button>
        </form>
    </section>
</div>

