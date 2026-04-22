<section class="hero">
    <h1>RBAC</h1>
    <p>Perfis, permissoes e controle de acesso.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Perfis</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Slug</th>
                    <th>Nome</th>
                    <th>Permissoes</th>
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

<section class="status-card">
    <strong>Permissoes</strong>
    <?php foreach ($permissions as $module => $items): ?>
        <h2><?php echo htmlspecialchars($module, ENT_QUOTES, 'UTF-8'); ?></h2>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Slug</th>
                        <th>Acao</th>
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

<section class="status-card">
    <strong>Atribuir permissoes a perfil</strong>
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
        <button type="submit">Salvar permissoes</button>
    </form>
</section>

<section class="status-card">
    <strong>Atribuir perfis a usuario</strong>
    <form method="post" action="/admin/rbac/usuarios/perfis" class="admin-form">
        <label>
            ID do usuario
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
