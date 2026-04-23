<?php use App\Core\Helpers; ?>

<section class="hero">
    <h1>Cursos e eventos</h1>
    <p>Operacao administrativa do catalogo principal.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="quick-actions">
    <a class="card-link" href="/admin/cursos/criar">Novo curso/evento</a>
</section>

<section class="status-card">
    <strong>Cursos e eventos</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Categoria</th>
                    <th>Tipo</th>
                    <th>Modalidade</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cursos)): ?>
                    <tr><td colspan="7">Nenhum curso/evento cadastrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($cursos as $curso): ?>
                    <tr>
                        <td><?php echo Helpers::e($curso['nome']); ?></td>
                        <td><?php echo Helpers::e($curso['categoria_nome'] ?? ''); ?></td>
                        <td><?php echo Helpers::e($curso['tipo']); ?></td>
                        <td><?php echo Helpers::e($curso['modalidade']); ?></td>
                        <td>R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></td>
                        <td><?php echo Helpers::e($curso['status']); ?></td>
                        <td>
                            <div class="split-actions">
                                <a href="/admin/cursos/show?curso_id=<?php echo (int) $curso['id']; ?>">Ver</a>
                                <a href="/admin/cursos/editar?curso_id=<?php echo (int) $curso['id']; ?>">Editar</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
