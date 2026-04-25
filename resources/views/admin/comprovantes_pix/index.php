<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Comprovantes PIX</h1>
        <p class="admin-page__subtitle">Análise administrativa dos comprovantes versionados.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Comprovantes</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Pagador</th>
                    <th>Versão</th>
                    <th>Status</th>
                    <th>Enviado em</th>
                    <th>Pedido</th>
                    <th>Motivo do reenvio</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($comprovantes_pix)): ?>
                    <tr>
                        <td colspan="8">Nenhum comprovante encontrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($comprovantes_pix as $comprovante): ?>
                    <tr>
                        <td>
                            <a href="/admin/pedidos/show?pedido_id=<?php echo (int) $comprovante['pedido_id']; ?>">
                                <?php echo htmlspecialchars((string) $comprovante['pedido_codigo'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </td>
                        <td>
                            <?php echo htmlspecialchars((string) $comprovante['pagador_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <small><?php echo htmlspecialchars((string) $comprovante['pagador_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td><?php echo (int) $comprovante['versao']; ?></td>
                        <td><?php echo htmlspecialchars((string) $comprovante['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $comprovante['enviado_em'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $comprovante['pedido_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $comprovante['motivo_reenvio'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <form method="post" action="/admin/comprovantes-pix/aprovar">
                                <input type="hidden" name="comprovante_id" value="<?php echo (int) $comprovante['id']; ?>">
                                <input type="text" name="observacao" placeholder="Observação">
                                <button type="submit">Aprovar</button>
                            </form>
                            <form method="post" action="/admin/comprovantes-pix/reprovar">
                                <input type="hidden" name="comprovante_id" value="<?php echo (int) $comprovante['id']; ?>">
                                <input type="text" name="observacao" placeholder="Motivo">
                                <button type="submit">Reprovar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

