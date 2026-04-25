<?php use App\Core\Helpers; ?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Categorias</h1>
        <p class="admin-page__subtitle">Organização administrativa da árvore de conteúdo do portal.</p>
    </div>
    <div class="admin-page__actions">
        <a class="button-link" href="/admin/categorias/criar">Nova categoria</a>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Lista de categorias</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Slug</th>
                    <th>Ordem</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categorias)): ?>
                    <tr><td colspan="6">Nenhuma categoria cadastrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($categorias as $categoria): ?>
                    <tr>
                        <td><?php echo Helpers::e($categoria['nome']); ?></td>
                        <td><?php echo Helpers::e($categoria['slug']); ?></td>
                        <td><?php echo (int) $categoria['ordem']; ?></td>
                        <td><?php echo Helpers::e($categoria['status']); ?></td>
                        <td><?php echo (int) $categoria['total_cursos']; ?></td>
                        <td>
                            <div class="split-actions">
                                <a href="/admin/categorias/show?categoria_id=<?php echo (int) $categoria['id']; ?>">Ver</a>
                                <a href="/admin/categorias/editar?categoria_id=<?php echo (int) $categoria['id']; ?>">Editar</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

