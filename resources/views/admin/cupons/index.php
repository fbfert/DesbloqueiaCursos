<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Cupons</h1>
        <p class="admin-page__subtitle">Gestão administrativa de cupons, regras e usos.</p>
    </div>
    <div class="admin-page__actions">
        <a class="button-link" href="/admin/cupons/criar">Novo cupom</a>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Cupons</strong>
    <div class="table-wrap">
        <table class="admin-table admin-table--cupons-lista">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Desconto</th>
                    <th>Status</th>
                    <th>Usos</th>
                    <th>Total descontado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cupons)): ?>
                    <tr>
                        <td colspan="8">Nenhum cupom cadastrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($cupons as $cupom): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cupom['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($cupom['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($cupom['tipo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php echo htmlspecialchars($cupom['desconto_tipo'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php echo ' - ' . number_format((float) $cupom['valor_desconto'], 2, ',', '.'); ?>
                        </td>
                        <td><?php echo htmlspecialchars($cupom['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $cupom['total_usos']; ?></td>
                        <td>R$ <?php echo number_format((float) $cupom['total_descontos'], 2, ',', '.'); ?></td>
                        <td>
                            <a href="/admin/cupons/resumo?cupom_id=<?php echo (int) $cupom['id']; ?>">Resumo</a>
                            |
                            <a href="/admin/cupons/editar?cupom_id=<?php echo (int) $cupom['id']; ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

