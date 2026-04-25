<?php
$totalPedidos = is_array($pedidos) ? count($pedidos) : 0;
$pendentes = 0;
$comPix = 0;

foreach ((array) $pedidos as $pedidoResumo) {
    $status = isset($pedidoResumo['status']) ? (string) $pedidoResumo['status'] : '';
    if (in_array($status, array('aguardando_pagamento', 'comprovante_enviado', 'em_analise', 'pendencia', 'aguardando_reenvio'), true)) {
        $pendentes++;
    }

    if (!empty($pedidoResumo['comprovante_pix'])) {
        $comPix++;
    }
}
?>

<div class="admin-page">
    <section class="hero admin-dashboard-hero">
        <div class="hero__content">
            <h1>Pedidos</h1>
            <p>Listagem administrativa de pedidos, participantes e comprovantes PIX.</p>
        </div>
        <div class="hero__panel admin-dashboard-hero__panel">
            <strong>Resumo rápido</strong>
            <div class="admin-dashboard-highlight">
                <span>Pedidos listados</span>
                <strong><?php echo (int) $totalPedidos; ?></strong>
                <small>na tela atual</small>
            </div>
            <div class="admin-dashboard-highlight">
                <span>Pedidos pendentes</span>
                <strong><?php echo (int) $pendentes; ?></strong>
                <small>com necessidade de ação</small>
            </div>
            <div class="admin-dashboard-highlight">
                <span>Com comprovante PIX</span>
                <strong><?php echo (int) $comPix; ?></strong>
                <small>com arquivo enviado</small>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Atalhos de operação</h2>
        </div>
        <div class="quick-actions quick-actions--dashboard">
            <a class="card-link admin-shortcut" href="/admin/comprovantes-pix"><span>Comprovantes PIX</span><small>Fila de análise e aprovação</small></a>
            <a class="card-link admin-shortcut" href="/admin/inscricoes"><span>Inscrições</span><small>Acompanhar status de alunos</small></a>
            <a class="card-link admin-shortcut" href="/admin/dashboard"><span>Dashboard</span><small>Voltar ao painel executivo</small></a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Pedidos</h2>
        </div>
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
                    <th>Ações</th>
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
</div>

