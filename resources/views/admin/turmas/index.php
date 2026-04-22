<section class="hero">
    <h1>Turmas</h1>
    <p>Instancias vinculadas aos cursos e eventos do portal.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Turmas cadastradas</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Codigo</th>
                    <th>Curso</th>
                    <th>Categoria</th>
                    <th>Modalidade</th>
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
                        <td><?php echo htmlspecialchars((string) $turma['categoria_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($turma['curso_modalidade'], ENT_QUOTES, 'UTF-8'); ?></td>
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
