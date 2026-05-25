<?php
$pedidosExcluidos = isset($pedidos_excluidos) && is_array($pedidos_excluidos) ? $pedidos_excluidos : array();
$filters = isset($filters) && is_array($filters) ? $filters : array();
$totalPedidosExcluidos = count($pedidosExcluidos);

if (!function_exists('pedidosFormatarTelefone')) {
    function pedidosFormatarTelefone($telefone)
    {
        $telefone = preg_replace('/\D+/', '', (string) $telefone);

        if ($telefone === '') {
            return '';
        }

        if (strlen($telefone) === 11) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
        }

        if (strlen($telefone) === 10) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
        }

        return $telefone;
    }
}
?>

<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Pedidos excluídos</h1>
            <p class="admin-page__subtitle">Consulta da lixeira de pedidos com filtros de busca.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/pedidos">Voltar para pedidos</a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <strong>Filtros de busca</strong>
        <form method="get" action="/admin/pedidos/excluidos" class="admin-filters">
            <div class="admin-filters__row">
                <label>Busca
                    <input type="text" name="q" value="<?php echo htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Código, pagador, e-mail, CPF ou curso">
                </label>
                <label>Status
                    <select name="status">
                        <?php $statusAtual = (string) ($filters['status'] ?? ''); ?>
                        <option value="">Todos</option>
                        <option value="rascunho" <?php echo $statusAtual === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                        <option value="aguardando_pagamento" <?php echo $statusAtual === 'aguardando_pagamento' ? 'selected' : ''; ?>>Aguardando pagamento</option>
                        <option value="comprovante_enviado" <?php echo $statusAtual === 'comprovante_enviado' ? 'selected' : ''; ?>>Comprovante enviado</option>
                        <option value="em_analise" <?php echo $statusAtual === 'em_analise' ? 'selected' : ''; ?>>Em análise</option>
                        <option value="pendencia" <?php echo $statusAtual === 'pendencia' ? 'selected' : ''; ?>>Pendência</option>
                        <option value="aguardando_reenvio" <?php echo $statusAtual === 'aguardando_reenvio' ? 'selected' : ''; ?>>Aguardando reenvio</option>
                        <option value="cancelado" <?php echo $statusAtual === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                        <option value="expirado" <?php echo $statusAtual === 'expirado' ? 'selected' : ''; ?>>Expirado</option>
                    </select>
                </label>
                <label>Curso
                    <input type="text" name="curso" value="<?php echo htmlspecialchars((string) ($filters['curso'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nome do curso ou evento">
                </label>
                <label>De
                    <input type="date" name="de" value="<?php echo htmlspecialchars((string) ($filters['de'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <label>Até
                    <input type="date" name="ate" value="<?php echo htmlspecialchars((string) ($filters['ate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </label>
            </div>
            <div class="cta-group">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/pedidos/excluidos">Limpar filtros</a>
            </div>
        </form>
    </section>

    <section class="status-card">
        <strong>Pedidos excluídos</strong>
        <p><?php echo (int) $totalPedidosExcluidos; ?> pedido(s) encontrado(s).</p>
        <div class="table-wrap">
            <table class="admin-table admin-table--pedidos-excluidos">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Pagador</th>
                        <th>Curso</th>
                        <th>Status</th>
                        <th>Excluído em</th>
                        <th>Excluído por</th>
                        <th>Justificativa</th>
                        <th>Detalhe técnico</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidosExcluidos)): ?>
                        <tr>
                            <td colspan="8">Nenhum pedido excluído encontrado.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($pedidosExcluidos as $pedido): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $pedido['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo htmlspecialchars((string) $pedido['pagador_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                                <small><?php echo htmlspecialchars((string) $pedido['pagador_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php if (!empty($pedido['pagador_telefone'])): ?>
                                    <br><small><?php echo htmlspecialchars(pedidosFormatarTelefone((string) $pedido['pagador_telefone']), ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($pedido['cursos_nome'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $pedido['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($pedido['excluido_em'] ?? $pedido['deleted_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($pedido['excluido_por_nome'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($pedido['justificativa_exclusao'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <details class="admin-pedidos__actions-details">
                                    <summary>Ver detalhes</summary>
                                    <div class="admin-pedidos__actions-body">
                                        <div class="admin-pedidos__bulk-warning">
                                            <p><strong>ID da lixeira:</strong> <?php echo htmlspecialchars((string) ($pedido['lixeira_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p><strong>Itens:</strong> <?php echo (int) ($pedido['total_itens'] ?? 0); ?></p>
                                            <p><strong>Snapshot:</strong></p>
                                            <pre style="white-space:pre-wrap;word-break:break-word;margin:0;padding:12px;border:1px solid var(--admin-border);border-radius:8px;background:#f8faff;"><?php echo htmlspecialchars((string) ($pedido['snapshot_dados'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></pre>
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
