<div class="admin-page admin-pedido-show">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Pedido #<?php echo (int) $pedido['id']; ?></h1>
        <p class="admin-page__subtitle"><?php echo htmlspecialchars($pedido['codigo'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($pedido['status'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="admin-pedido-show__top">
    <article class="status-card admin-pedido-comprovante-card">
        <strong>Resumo do pedido</strong>
        <p>
            <strong>Pagador:</strong> <?php echo htmlspecialchars((string) $pedido['pagador_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
            <strong>E-mail:</strong> <?php echo htmlspecialchars((string) $pedido['pagador_email'], ENT_QUOTES, 'UTF-8'); ?><br>
            <strong>CPF:</strong> <?php echo htmlspecialchars((string) $pedido['pagador_cpf'], ENT_QUOTES, 'UTF-8'); ?><br>
            <strong>Total:</strong> R$ <?php echo number_format((float) $pedido['total'], 2, ',', '.'); ?>
        </p>
        <?php if (!empty($can_see_pix) && !empty($pedido['comprovante_atual'])): ?>
            <p>
                <strong>Comprovante atual:</strong>
                v<?php echo (int) $pedido['comprovante_atual']['versao']; ?> -
                <?php echo htmlspecialchars((string) $pedido['comprovante_atual']['status'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>
    </article>

    <article class="status-card admin-pedido-comprovante-card">
        <strong>Conferência do comprovante</strong>
        <?php if (!empty($can_see_pix) && !empty($pedido['comprovante_atual'])): ?>
            <?php
            $comprovanteAtual = $pedido['comprovante_atual'];
            $comprovanteUrl = '/admin/pedidos/comprovante?pedido_id=' . (int) $pedido['id'] . '&comprovante_id=' . (int) $comprovanteAtual['id'];
            $mimeType = isset($comprovanteAtual['arquivo_mime_type']) ? (string) $comprovanteAtual['arquivo_mime_type'] : '';
            $isVisualizavel = strpos($mimeType, 'image/') === 0 || $mimeType === 'application/pdf';
            ?>
            <div class="admin-pedido-comprovante-meta">
                <span><strong>Arquivo:</strong> <?php echo htmlspecialchars((string) $comprovanteAtual['arquivo_nome_original'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span><strong>Enviado em:</strong> <?php echo htmlspecialchars((string) $comprovanteAtual['enviado_em'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span><strong>Status:</strong> <?php echo htmlspecialchars((string) $comprovanteAtual['status'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="admin-pedido-comprovante-zoom">
                <button type="button" class="button-link button-link--ghost js-zoom-out">−</button>
                <button type="button" class="button-link button-link--ghost js-zoom-reset">100%</button>
                <button type="button" class="button-link button-link--ghost js-zoom-in">+</button>
            </div>
            <p class="admin-pedido-comprovante-actions">
                <a class="card-link" href="<?php echo htmlspecialchars($comprovanteUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Abrir comprovante em nova aba</a>
            </p>
            <?php if ($isVisualizavel): ?>
                <div class="admin-pedido-comprovante-viewer">
                    <iframe class="admin-pedido-comprovante-frame js-comprovante-frame" src="<?php echo htmlspecialchars($comprovanteUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Comprovante PIX"></iframe>
                </div>
            <?php else: ?>
                <p>Este tipo de arquivo não possui pré-visualização inline.</p>
            <?php endif; ?>
        <?php else: ?>
            <p>Nenhum comprovante enviado para este pedido.</p>
        <?php endif; ?>
    </article>
</section>

<section class="status-card">
    <strong>Ações do pedido</strong>
    <div class="grid-forms">
        <form method="post" action="/admin/pedidos/aprovar" class="admin-form">
            <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
            <label>Observação</label>
            <textarea name="observacao" rows="3"></textarea>
            <button type="submit" class="button-link button-link--primary">Aprovar pedido</button>
        </form>

        <form method="post" action="/admin/pedidos/marcar-pendencia" class="admin-form">
            <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
            <label>Observação</label>
            <textarea name="observacao" rows="3"></textarea>
            <button type="submit" class="button-link button-link--primary">Marcar pendência</button>
        </form>

        <form method="post" action="/admin/pedidos/solicitar-reenvio" class="admin-form">
            <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
            <label>Observação</label>
            <textarea name="observacao" rows="3"></textarea>
            <button type="submit" class="button-link button-link--primary">Solicitar reenvio PIX</button>
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
                        <td><?php echo htmlspecialchars((string) $item['curso_nome'], ENT_QUOTES, 'UTF-8'); ?><br><small>#<?php echo (int) $item['curso_evento_id']; ?></small></td>
                        <td><?php echo htmlspecialchars((string) $item['turma_nome'], ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo !empty($item['turma_id']) ? '#' . (int) $item['turma_id'] : '-'; ?></small></td>
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
                <tr><th>Nome</th><th>CPF</th><th>E-mail</th><th>Ordem</th></tr>
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
                <tr><th>ID</th><th>Participante</th><th>Curso</th><th>Turma</th><th>Status</th><th>Progresso</th></tr>
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
    <strong>Comprovantes PIX (histórico)</strong>
    <?php if (!empty($can_see_pix)): ?>
        <div class="table-wrap">
            <table class="admin-table admin-table--pedido-comprovantes">
                <thead>
                    <tr>
                        <th>Versão</th>
                        <th>Status</th>
                        <th>Enviado em</th>
                        <th>Arquivo</th>
                        <th>Motivo do reenvio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedido['comprovantes'] as $comprovante): ?>
                        <tr>
                            <td><?php echo (int) $comprovante['versao']; ?></td>
                            <td><?php echo htmlspecialchars($comprovante['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $comprovante['enviado_em'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a href="/admin/pedidos/comprovante?pedido_id=<?php echo (int) $pedido['id']; ?>&comprovante_id=<?php echo (int) $comprovante['id']; ?>" target="_blank" rel="noopener">
                                    <?php echo htmlspecialchars((string) $comprovante['arquivo_nome_original'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
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
                <tr><th>Status anterior</th><th>Status novo</th><th>Observação</th><th>Data</th></tr>
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

<script>
(function () {
    var frame = document.querySelector('.js-comprovante-frame');
    if (!frame) {
        return;
    }

    var zoom = 1;
    var minZoom = 0.5;
    var maxZoom = 3;
    var step = 0.1;
    var btnIn = document.querySelector('.js-zoom-in');
    var btnOut = document.querySelector('.js-zoom-out');
    var btnReset = document.querySelector('.js-zoom-reset');

    function applyZoom() {
        frame.style.transform = 'scale(' + zoom + ')';
        frame.style.transformOrigin = 'top left';
        if (btnReset) {
            btnReset.textContent = Math.round(zoom * 100) + '%';
        }
    }

    if (btnIn) {
        btnIn.addEventListener('click', function () {
            zoom = Math.min(maxZoom, zoom + step);
            applyZoom();
        });
    }

    if (btnOut) {
        btnOut.addEventListener('click', function () {
            zoom = Math.max(minZoom, zoom - step);
            applyZoom();
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', function () {
            zoom = 1;
            applyZoom();
        });
    }
})();
</script>
