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
    <?php
    function statusComprovanteMeta($status)
    {
        $valor = strtolower(trim((string) $status));

        if ($valor === 'aprovado') {
            return array('icone' => '✔', 'classe' => 'badge badge--status badge--status-aprovado', 'texto' => 'Aprovado');
        }

        if ($valor === 'reprovado') {
            return array('icone' => '✖', 'classe' => 'badge badge--status badge--status-reprovado', 'texto' => 'Reprovado');
        }

        return array('icone' => '◷', 'classe' => 'badge badge--status badge--status-pendente', 'texto' => 'Pendente');
    }
    ?>
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
                    <th>Abrir</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($comprovantes_pix)): ?>
                    <tr>
                        <td colspan="8">Nenhum comprovante encontrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($comprovantes_pix as $comprovante): ?>
                    <?php $statusMeta = statusComprovanteMeta(isset($comprovante['status']) ? $comprovante['status'] : ''); ?>
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
                        <td>
                            <span class="<?php echo htmlspecialchars($statusMeta['classe'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($statusMeta['icone'], ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($statusMeta['texto'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars((string) $comprovante['enviado_em'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $comprovante['pedido_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $comprovante['motivo_reenvio'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <a class="button-link button-link--ghost" href="/admin/pedidos/show?pedido_id=<?php echo (int) $comprovante['pedido_id']; ?>">Abrir pedido</a><br>
                            <a class="button-link button-link--ghost" href="/admin/pedidos/comprovante?pedido_id=<?php echo (int) $comprovante['pedido_id']; ?>&comprovante_id=<?php echo (int) $comprovante['id']; ?>" target="_blank" rel="noopener">Ver comprovante</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

