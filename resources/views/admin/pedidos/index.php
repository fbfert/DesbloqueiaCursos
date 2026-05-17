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
    <section class="admin-page__header admin-page__header--with-metrics">
        <div class="admin-page__header-content">
            <h1 class="admin-page__title">Pedidos</h1>
            <p class="admin-page__subtitle">Listagem administrativa de pedidos, participantes e comprovantes PIX.</p>
        </div>
        <div class="admin-page__metrics">
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Pedidos listados</span>
                <strong class="admin-page__metric-value"><?php echo (int) $totalPedidos; ?></strong>
                <small class="admin-page__metric-help">na tela atual</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Pedidos pendentes</span>
                <strong class="admin-page__metric-value"><?php echo (int) $pendentes; ?></strong>
                <small class="admin-page__metric-help">com necessidade de ação</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Com comprovante PIX</span>
                <strong class="admin-page__metric-value"><?php echo (int) $comPix; ?></strong>
                <small class="admin-page__metric-help">com arquivo enviado</small>
            </article>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Atalhos de operação</h2>
        </div>
        <div class="quick-actions quick-actions--dashboard">
            <a class="card-link admin-shortcut" href="/admin/comprovantes-pix"><span>Comprovantes PIX</span><small>Fila de análise e aprovação</small></a>
            <a class="card-link admin-shortcut" href="/admin/inscricoes"><span>Inscrições</span><small>Acompanhar status de alunos</small></a>
            <a class="card-link admin-shortcut" href="/admin/pedidos/excluidos"><span>Pedidos excluídos</span><small>Consultar a lixeira de pedidos</small></a>
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
            <table class="admin-table admin-table--pedidos-lista">
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
                    <?php
                    $comprovante = isset($pedido['comprovante_pix']) ? $pedido['comprovante_pix'] : null;
                    $exclusao = isset($pedido['exclusao']) && is_array($pedido['exclusao']) ? $pedido['exclusao'] : array('ok' => false, 'motivos_texto' => 'Este pedido não pode ser excluído.');
                    ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($pedido['codigo'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if (!empty($pedido['is_presente'])): ?>
                                <br><span class="badge badge--status badge--status-pendente">Presente</span>
                                <?php if (!empty($pedido['presente_campanha_titulo'])): ?>
                                    <br><small><?php echo htmlspecialchars((string) $pedido['presente_campanha_titulo'], ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
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
                            <a class="button-link button-link--ghost" href="/admin/pedidos/show?pedido_id=<?php echo (int) $pedido['id']; ?>">Abrir</a>
                        </td>
                    </tr>
                    <?php if (!empty($can_manage_pedidos)): ?>
                        <tr class="admin-pedidos__actions-row">
                            <td colspan="8">
                                <details class="admin-pedidos__actions-details">
                                    <summary>Mais opções</summary>
                                    <div class="admin-pedidos__actions-body">
                                        <?php if (!empty($pedido['cupom_manual']) && !empty($pedido['cupom_manual']['ok'])): ?>
                                            <a class="button-link button-link--ghost" href="/admin/pedidos/show?pedido_id=<?php echo (int) $pedido['id']; ?>#cupom-manual">Aplicar cupom</a>
                                        <?php endif; ?>
                                        <?php if (!empty($exclusao['ok'])): ?>
                                            <form method="post" action="/admin/pedidos/excluir" class="admin-form admin-pedidos__delete-form">
                                                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                                                <label>Justificativa da lixeira</label>
                                                <textarea name="justificativa" rows="2" required placeholder="Informe a justificativa da exclusão."></textarea>
                                                <button type="submit" class="button-link button-link--danger" onclick="return confirm('Tem certeza de que deseja excluir este pedido?');">🗑 Excluir pedido</button>
                                            </form>
                                        <?php else: ?>
                                            <div class="alert-danger">
                                                <?php echo htmlspecialchars(isset($exclusao['motivos_texto']) ? $exclusao['motivos_texto'] : 'Este pedido não pode ser excluído.', ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </section>

    <?php if (!empty($can_manage_pedidos)): ?>
        <section class="admin-section">
            <details class="admin-pedidos__actions-details">
                <summary>Exclusão em lote</summary>
                <div class="admin-pedidos__actions-body">
                    <div class="admin-pedidos__bulk-warning">
                        <p>Esta ação exclui apenas pedidos com mais de 30 dias sem pagamento confirmado. Pedidos pagos, aprovados, com comprovante aprovado, inscrições ativas ou certificados permanecem protegidos.</p>
                        <form method="post" action="/admin/pedidos/excluir-lote" class="admin-form">
                            <input type="hidden" name="dias" value="30">
                            <label>Justificativa da lixeira</label>
                            <textarea name="justificativa" rows="3" required placeholder="Informe a justificativa para a exclusão em lote."></textarea>
                            <div class="cta-group">
                                <button type="submit" class="button-link button-link--danger" onclick="return confirm('Tem certeza de que deseja excluir apenas pedidos antigos sem pagamento confirmado?');">🗑 Excluir pedidos antigos</button>
                            </div>
                        </form>
                    </div>
                </div>
            </details>
        </section>
    <?php endif; ?>
</div>

