<section class="hero">
    <h1>Catalogo</h1>
    <p>Base de cursos, eventos, turmas, destaques e vinculos.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Categorias</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Slug</th>
                    <th>Ordem</th>
                    <th>Catalogo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $categoria): ?>
                    <tr>
                        <td><?php echo (int) $categoria['id']; ?></td>
                        <td><?php echo htmlspecialchars($categoria['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($categoria['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $categoria['ordem']; ?></td>
                        <td><?php echo (int) $categoria['total_cursos']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="status-card">
    <strong>Cursos e eventos</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Slug</th>
                    <th>Categoria</th>
                    <th>Tipo</th>
                    <th>Modalidade</th>
                    <th>Valor</th>
                    <th>Turmas</th>
                    <th>Pessoas</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cursos as $curso): ?>
                    <tr>
                        <td><?php echo (int) $curso['id']; ?></td>
                        <td><?php echo htmlspecialchars($curso['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($curso['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $curso['categoria_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($curso['tipo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($curso['modalidade'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></td>
                        <td><?php echo (int) $curso['total_turmas']; ?></td>
                        <td><?php echo (int) $curso['total_pessoas_vinculadas']; ?></td>
                        <td><?php echo htmlspecialchars($curso['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="status-card">
    <strong>Turmas</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Codigo</th>
                    <th>Curso</th>
                    <th>Inicio</th>
                    <th>Fim</th>
                    <th>Vagas</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($turmas as $turma): ?>
                    <tr>
                        <td><?php echo (int) $turma['id']; ?></td>
                        <td><?php echo htmlspecialchars($turma['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($turma['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($turma['curso_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $turma['data_inicio'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $turma['data_fim'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo isset($turma['vagas']) ? (int) $turma['vagas'] : 0; ?></td>
                        <td><?php echo htmlspecialchars($turma['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="status-card">
    <strong>Destaques</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Curso</th>
                    <th>Slug</th>
                    <th>Ordem</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($destaques as $destaque): ?>
                    <tr>
                        <td><?php echo (int) $destaque['id']; ?></td>
                        <td><?php echo htmlspecialchars($destaque['curso_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($destaque['curso_slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $destaque['ordem']; ?></td>
                        <td><?php echo htmlspecialchars($destaque['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
