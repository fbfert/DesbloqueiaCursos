<section class="hero">
    <h1>Cursos e eventos</h1>
    <p>Catalogo administrativo com escopo para conteudo e professor.</p>
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
                    <th>Total</th>
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
                    <th>Categoria</th>
                    <th>Tipo</th>
                    <th>Modalidade</th>
                    <th>Promo</th>
                    <th>Destaque</th>
                    <th>Ordem</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cursos as $curso): ?>
                    <tr>
                        <td><?php echo (int) $curso['id']; ?></td>
                        <td><?php echo htmlspecialchars($curso['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $curso['categoria_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($curso['tipo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($curso['modalidade'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo !empty($curso['em_promocao']) ? 'Sim' : 'Nao'; ?></td>
                        <td><?php echo !empty($curso['destaque']) ? 'Sim' : 'Nao'; ?></td>
                        <td><?php echo (int) $curso['ordem']; ?></td>
                        <td><?php echo htmlspecialchars($curso['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <td colspan="9" class="muted-row">
                            <strong>Thumbnail:</strong> <?php echo htmlspecialchars((string) $curso['thumbnail'], ENT_QUOTES, 'UTF-8'); ?>
                            <br>
                            <strong>Descricao curta:</strong> <?php echo htmlspecialchars((string) $curso['descricao_curta'], ENT_QUOTES, 'UTF-8'); ?>
                            <br>
                            <strong>Descricao completa:</strong> <?php echo htmlspecialchars(substr((string) $curso['descricao_completa'], 0, 180), ENT_QUOTES, 'UTF-8'); ?>
                            <br>
                            <strong>Carga horaria:</strong> <?php echo htmlspecialchars((string) $curso['carga_horaria'], ENT_QUOTES, 'UTF-8'); ?>
                            <br>
                            <strong>Valor:</strong> R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?>
                            <br>
                            <strong>Pessoas vinculadas:</strong> <?php echo count($curso['pessoas_vinculadas']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
