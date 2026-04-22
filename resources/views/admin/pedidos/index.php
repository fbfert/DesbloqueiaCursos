<section class="hero">
    <h1>Pedidos</h1>
    <p>Listagem administrativa de pedidos, participantes e comprovantes PIX.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Pedidos</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Pagador</th>
                    <th>Participantes</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Comprovante</th>
                    <th>Data</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pedidos)): ?>
                    <tr>
                        <td colspan="8">Nenhum pedido encontrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($pedidos as $pedido): ?>
                    <?php $comprovante = isset($pedido['comprovante_pix']) ? $pedido['comprovante_pix'] : null; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pedido['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php echo htmlspecialchars((string) $pedido['pagador_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <small><?php echo htmlspecialchars((string) $pedido['pagador_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td><?php echo isset($pedido['quantidade_participantes']) ? (int) $pedido['quantidade_participantes'] : 0; ?></td>
                        <td>R$ <?php echo number_format((float) $pedido['total'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($pedido['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($comprovante): ?>
                                <?php echo htmlspecialchars($comprovante['status'], ENT_QUOTES, 'UTF-8'); ?>
                                <br>
                                <small>v<?php echo (int) $comprovante['versao']; ?></small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) $pedido['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <a href="/admin/pedidos/show?pedido_id=<?php echo (int) $pedido['id']; ?>">Abrir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
