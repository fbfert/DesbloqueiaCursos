<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Pedido #<?php echo (int) $pedido['id']; ?></h1>
        <p class="admin-page__subtitle"><?php echo htmlspecialchars($pedido['codigo'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($pedido['status'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Resumo</strong>
    <p>
        <strong>Pagador:</strong> <?php echo htmlspecialchars((string) $pedido['pagador_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
        <strong>Email:</strong> <?php echo htmlspecialchars((string) $pedido['pagador_email'], ENT_QUOTES, 'UTF-8'); ?><br>
        <strong>CPF:</strong> <?php echo htmlspecialchars((string) $pedido['pagador_cpf'], ENT_QUOTES, 'UTF-8'); ?><br>
        <strong>Total:</strong> R$ <?php echo number_format((float) $pedido['total'], 2, ',', '.'); ?>
    </p>
    <?php if (!empty($can_see_pix) && !empty($pedido['comprovante_atual'])): ?>
        <p>
            <strong>Comprovante atual:</strong>
            v<?php echo (int) $pedido['comprovante_atual']['versao']; ?>
            - <?php echo htmlspecialchars((string) $pedido['comprovante_atual']['status'], ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>
</section>

<section class="status-card">
    <strong>Ações do pedido</strong>
    <div class="grid-forms">
        <form method="post" action="/admin/pedidos/aprovar" class="admin-form">
            <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
            <label>Observação</label>
            <textarea name="observacao" rows="3"></textarea>
            <button type="submit">Aprovar pedido</button>
        </form>

        <form method="post" action="/admin/pedidos/marcar-pendencia" class="admin-form">
            <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
            <label>Observação</label>
            <textarea name="observacao" rows="3"></textarea>
            <button type="submit">Marcar pendência</button>
        </form>

        <form method="post" action="/admin/pedidos/solicitar-reenvio" class="admin-form">
            <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
            <label>Observação</label>
            <textarea name="observacao" rows="3"></textarea>
            <button type="submit">Solicitar reenvio PIX</button>
        </form>
    </div>
</section>

<section class="status-card">
    <strong>Itens</strong>
    <div class="table-wrap">
        <table class="admin-table admin-table--pedido-itens">
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>Turma</th>
                    <th>Quantidade</th>
                    <th>Valor unitário</th>
                    <th>Valor total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedido['itens'] as $item): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars((string) $item['curso_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <small>#<?php echo (int) $item['curso_evento_id']; ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars((string) $item['turma_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <small><?php echo !empty($item['turma_id']) ? '#' . (int) $item['turma_id'] : '-'; ?></small>
                        </td>
                        <td><?php echo (int) $item['quantidade']; ?></td>
                        <td>R$ <?php echo number_format((float) $item['valor_unitario'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $item['valor_total'], 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="status-card">
    <strong>Participantes</strong>
    <div class="table-wrap">
        <table class="admin-table admin-table--pedido-participantes">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Email</th>
                    <th>Ordem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedido['participantes'] as $participante): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $participante['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $participante['cpf'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $participante['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $participante['ordem']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="status-card">
    <strong>Inscrições vinculadas</strong>
    <div class="table-wrap">
        <table class="admin-table admin-table--pedido-inscricoes">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Participante</th>
                    <th>Curso</th>
                    <th>Turma</th>
                    <th>Status</th>
                    <th>Progresso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedido['inscricoes'] as $inscricao): ?>
                    <tr>
                        <td><?php echo (int) $inscricao['id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $inscricao['participante_pedido_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $inscricao['curso_evento_id']; ?></td>
                        <td><?php echo !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : '-'; ?></td>
                        <td><?php echo htmlspecialchars($inscricao['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format((float) $inscricao['percentual_progresso'], 2, ',', '.'); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="status-card">
    <strong>Comprovantes PIX</strong>
    <?php if (!empty($can_see_pix)): ?>
        <div class="table-wrap">
            <table class="admin-table admin-table--pedido-comprovantes">
                <thead>
                    <tr>
                    <th>Versão</th>
                        <th>Status</th>
                        <th>Enviado em</th>
                    <th>Motivo do reenvio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedido['comprovantes'] as $comprovante): ?>
                        <tr>
                            <td><?php echo (int) $comprovante['versao']; ?></td>
                            <td><?php echo htmlspecialchars($comprovante['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $comprovante['enviado_em'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $comprovante['motivo_reenvio'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>Sem acesso aos comprovantes PIX.</p>
    <?php endif; ?>
</section>

<section class="status-card">
    <strong>Histórico do pedido</strong>
    <div class="table-wrap">
        <table class="admin-table admin-table--pedido-historico">
            <thead>
                <tr>
                    <th>Status anterior</th>
                    <th>Status novo</th>
                    <th>Observação</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedido['historico'] as $historico): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $historico['status_anterior'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $historico['status_novo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $historico['observacao'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $historico['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

