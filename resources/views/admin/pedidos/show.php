<div class="admin-page admin-pedido-show">
<?php
$nomesCursosPedido = array();
if (!empty($pedido['itens']) && is_array($pedido['itens'])) {
    foreach ($pedido['itens'] as $item) {
        if (!empty($item['curso_nome'])) {
            $nomesCursosPedido[] = (string) $item['curso_nome'];
        }
    }
}
$nomesCursosPedido = array_values(array_unique($nomesCursosPedido));
$nomeCursoPedido = !empty($nomesCursosPedido) ? implode(', ', $nomesCursosPedido) : '';
$formatarTelefone = function ($telefone) {
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
};
$telefonePagador = '';
if (!empty($pedido['usuario_pagador']) && is_array($pedido['usuario_pagador']) && !empty($pedido['usuario_pagador']['telefone'])) {
    $telefonePagador = (string) $pedido['usuario_pagador']['telefone'];
} elseif (!empty($pedido['pagador_telefone'])) {
    $telefonePagador = (string) $pedido['pagador_telefone'];
}
$telefonePagador = $formatarTelefone($telefonePagador);
?>
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Pedido #<?php echo (int) $pedido['id']; ?><?php if ($nomeCursoPedido !== ''): ?> - <?php echo htmlspecialchars($nomeCursoPedido, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></h1>
        <p class="admin-page__subtitle"><?php echo htmlspecialchars($pedido['codigo'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($pedido['status'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>
<?php
$cupomAtual = !empty($pedido['cupom']) && is_array($pedido['cupom']) ? $pedido['cupom'] : null;
$cupomManual = !empty($pedido['cupom_manual']) && is_array($pedido['cupom_manual']) ? $pedido['cupom_manual'] : array('ok' => false, 'motivos_texto' => 'Não é possível aplicar cupom neste pedido.');
$podeReceberComprovante = in_array((string) $pedido['status'], array('aguardando_pagamento', 'comprovante_enviado', 'pendencia', 'aguardando_reenvio'), true);
$exigeMotivoReenvioComprovante = !empty($pedido['comprovantes']);
?>

<section class="admin-pedido-show__top">
    <article class="status-card admin-pedido-comprovante-card">
        <strong>Resumo do pedido</strong>
        <p>
            <?php if (!empty($pedido['is_presente'])): ?>
                <span class="badge badge--status badge--status-pendente" style="margin-bottom: 8px;">Presente</span><br>
                <strong>Campanha:</strong>
                <?php if (!empty($pedido['presente_campanha_id'])): ?>
                    <a href="/admin/promocionais/presentes/show?campanha_id=<?php echo (int) $pedido['presente_campanha_id']; ?>">
                        <?php echo htmlspecialchars((string) ($pedido['presente_campanha_titulo'] ?? $pedido['presente_titulo'] ?? 'Presente promocional'), ENT_QUOTES, 'UTF-8'); ?>
                    </a><br>
                <?php else: ?>
                    <?php echo htmlspecialchars((string) ($pedido['presente_campanha_titulo'] ?? $pedido['presente_titulo'] ?? 'Presente promocional'), ENT_QUOTES, 'UTF-8'); ?><br>
                <?php endif; ?>
                <strong>Justificativa:</strong> <?php echo htmlspecialchars((string) ($pedido['presente_justificativa'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?><br>
            <?php endif; ?>
            <strong>Pagador:</strong> <?php echo htmlspecialchars((string) $pedido['pagador_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
            <strong>E-mail:</strong> <?php echo htmlspecialchars((string) $pedido['pagador_email'], ENT_QUOTES, 'UTF-8'); ?><br>
            <strong>Telefone/WhatsApp:</strong> <?php echo htmlspecialchars($telefonePagador !== '' ? $telefonePagador : '-', ENT_QUOTES, 'UTF-8'); ?><br>
            <strong>CPF:</strong> <?php echo htmlspecialchars((string) $pedido['pagador_cpf'], ENT_QUOTES, 'UTF-8'); ?><br>
            <strong>Total:</strong> R$ <?php echo number_format((float) $pedido['total'], 2, ',', '.'); ?><br>
            <strong>Status do pagamento:</strong> <?php echo htmlspecialchars((string) $pedido['status'], ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <?php if (!empty($pedido['is_presente'])): ?>
            <p class="alert-info">Este pedido foi concedido como presente e não gera financeiro, rateio ou comissão.</p>
        <?php endif; ?>
        <?php if ($cupomAtual): ?>
            <p>
                <strong>Cupom atual:</strong> <?php echo htmlspecialchars((string) $cupomAtual['cupom_codigo'], ENT_QUOTES, 'UTF-8'); ?><br>
                <strong>Desconto:</strong> R$ <?php echo number_format((float) $pedido['desconto_total'], 2, ',', '.'); ?><br>
                <strong>Total ajustado:</strong> R$ <?php echo number_format((float) $pedido['total'], 2, ',', '.'); ?>
            </p>
            <?php else: ?>
                <p><strong>Cupom atual:</strong> Nenhum cupom aplicado.</p>
            <?php endif; ?>
            <?php if ((string) $pedido['status'] === 'cancelado'): ?>
                <p>
                    <strong>Cancelamento:</strong> Este pedido foi cancelado pelo aluno.
                    A reversão administrativa exige justificativa e restaura o status anterior do pedido.
                </p>
            <?php endif; ?>
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

    <article class="status-card admin-pedido-comprovante-card" id="comprovante-manual">
        <strong>Enviar comprovante de pagamento</strong>
        <?php if (!empty($can_see_pix)): ?>
            <?php if ($podeReceberComprovante): ?>
                <p>Use este formulário para anexar manualmente um comprovante de pagamento ao pedido.</p>

                <form method="post" action="/admin/pedidos/comprovante" enctype="multipart/form-data" class="admin-form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">

                    <label for="comprovante_manual_arquivo">Arquivo do comprovante</label>
                    <input id="comprovante_manual_arquivo" type="file" name="comprovante" accept="image/*,application/pdf" required>

                    <label for="comprovante_manual_valor">Valor informado</label>
                    <input id="comprovante_manual_valor" type="text" name="valor_informado" value="<?php echo htmlspecialchars((string) $pedido['total'], ENT_QUOTES, 'UTF-8'); ?>">

                    <?php if ($exigeMotivoReenvioComprovante): ?>
                        <label for="comprovante_manual_motivo">Motivo do reenvio</label>
                        <textarea id="comprovante_manual_motivo" name="motivo_reenvio" rows="4" required placeholder="Explique por que este comprovante está sendo reenviado."></textarea>
                    <?php endif; ?>

                    <button type="submit" class="button-link button-link--primary">Enviar comprovante</button>
                </form>
            <?php else: ?>
                <p>Este pedido não aceita comprovante neste status. O envio manual fica disponível quando o pedido está em aguardando pagamento, pendência, aguardando reenvio ou comprovante enviado.</p>
            <?php endif; ?>
        <?php else: ?>
            <p>Você não tem permissão para enviar comprovantes neste pedido.</p>
        <?php endif; ?>
    </article>
</section>

<section class="status-card admin-pedido-cupom-card" id="cupom-manual">
    <strong>Cupom do pedido</strong>
    <?php if (!empty($can_manage_pedidos)): ?>
        <?php if (!empty($pedido['cupom_codigo'])): ?>
            <div class="alert-info admin-pedido-cupom-card__current">
                Cupom aplicado no pedido: <?php echo htmlspecialchars((string) $pedido['cupom_codigo'], ENT_QUOTES, 'UTF-8'); ?>.
                Desconto atual: R$ <?php echo number_format((float) $pedido['desconto_total'], 2, ',', '.'); ?>.
                Total atual: R$ <?php echo number_format((float) $pedido['total'], 2, ',', '.'); ?>.
            </div>
        <?php endif; ?>
        <?php if (!empty($cupomManual['ok'])): ?>
            <?php if (!empty($cupomManual['pedido_confirmado'])): ?>
                <div class="alert-warning admin-pedido-cupom-card__warning">
                    <?php echo htmlspecialchars((string) ($cupomManual['alerta_texto'] ?? 'Este pedido já está aprovado/pago. A aplicação do cupom será registrada como ajuste financeiro pós-aprovação, alterará o total do pedido e não mudará o status.'), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($pedido['comprovante_atual']) && !in_array((string) $pedido['comprovante_atual']['status'], array('aprovado', 'reprovado'), true)): ?>
                <div class="alert-warning admin-pedido-cupom-card__warning">
                    Este pedido já possui comprovante PIX. Confira se o valor pago corresponde ao novo total.
                </div>
            <?php endif; ?>

            <details class="admin-pedidos__actions-details admin-pedido-cupom-card__details">
                <summary><?php echo !empty($cupomManual['pedido_confirmado']) ? 'Aplicar cupom pós-aprovação' : 'Aplicar cupom manualmente'; ?></summary>
                <div class="admin-pedidos__actions-body">
                    <?php if ($cupomAtual): ?>
                        <div class="alert-info admin-pedido-cupom-card__current">
                            <strong>Cupom aplicado atualmente:</strong> <?php echo htmlspecialchars((string) $cupomAtual['cupom_codigo'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <strong>Valor original:</strong> R$ <?php echo number_format((float) $pedido['subtotal'], 2, ',', '.'); ?><br>
                            <strong>Desconto atual:</strong> R$ <?php echo number_format((float) $pedido['desconto_total'], 2, ',', '.'); ?><br>
                            <strong>Total atual:</strong> R$ <?php echo number_format((float) $pedido['total'], 2, ',', '.'); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="/admin/pedidos/cupom-manual" class="admin-form admin-pedido-cupom-card__form">
                        <?php echo $csrfField; ?>
                        <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                        <input type="hidden" name="acao" value="aplicar">

                        <label for="cupom_codigo_manual">Código do cupom</label>
                        <input id="cupom_codigo_manual" type="text" name="cupom_codigo" placeholder="Informe o código do cupom">

                        <label for="cupom_justificativa_manual">Justificativa</label>
                        <textarea id="cupom_justificativa_manual" name="justificativa" rows="3" required placeholder="Cliente não conseguiu aplicar o cupom no checkout e enviou comprovante com valor descontado."></textarea>

                        <div class="admin-pedido-cupom-card__help">
                            <?php if (!empty($cupomManual['pedido_confirmado'])): ?>
                                A aplicação será registrada como ajuste financeiro pós-aprovação sem alteração do status do pedido.
                            <?php else: ?>
                                Se já existir um cupom ativo, ele será substituído pelo código informado após a validação.
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="button-link button-link--primary" onclick="return confirmarAcaoCritica({ palavra: 'APLICAR', pergunta: 'Você conferiu a aplicação manual deste cupom?' });"><?php echo !empty($cupomManual['pedido_confirmado']) ? ($cupomAtual ? 'Substituir cupom pós-aprovação' : 'Aplicar cupom pós-aprovação') : 'Aplicar cupom manualmente'; ?></button>
                    </form>

                    <?php if ($cupomAtual && empty($cupomManual['pedido_confirmado'])): ?>
                        <form method="post" action="/admin/pedidos/cupom-manual" class="admin-form admin-pedido-cupom-card__form admin-pedido-cupom-card__remove-form">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                            <input type="hidden" name="acao" value="remover">

                            <label for="cupom_justificativa_remover">Justificativa para remoção</label>
                            <textarea id="cupom_justificativa_remover" name="justificativa" rows="3" required placeholder="Informe a justificativa para remover o cupom aplicado."></textarea>

                            <button type="submit" class="button-link button-link--danger" onclick="return confirmarAcaoCritica({ palavra: 'EXCLUIR', pergunta: 'Você conferiu a remoção do cupom aplicado deste pedido?' });">Remover cupom aplicado</button>
                        </form>
                    <?php elseif ($cupomAtual): ?>
                        <p class="muted">A remoção simples de cupom em pedido confirmado está bloqueada para evitar aumento retroativo do valor. Para trocar o cupom, informe o novo código acima.</p>
                    <?php endif; ?>
                </div>
            </details>
        <?php else: ?>
            <div class="alert-danger admin-pedido-cupom-card__blocked">
                <?php echo htmlspecialchars((string) $cupomManual['motivos_texto'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p>Você não tem permissão para alterar cupom neste pedido.</p>
    <?php endif; ?>
</section>

<section class="status-card" id="acoes-pedido">
    <strong>Ações do pedido</strong>
    <div class="grid-forms">
        <?php if (!empty($can_manage_pedidos) && (string) $pedido['status'] === 'rascunho'): ?>
            <form method="post" action="/admin/pedidos/rascunho-aguardando-pagamento" class="admin-form">
                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                <label for="rascunho_aguardando_pagamento_justificativa">Justificativa da alteração</label>
                <textarea id="rascunho_aguardando_pagamento_justificativa" name="justificativa" rows="3" required placeholder="Informe a justificativa para mover este pedido de rascunho para aguardando pagamento."></textarea>
                <button type="submit" class="button-link button-link--primary" onclick="return confirmarAcaoCritica({ palavra: 'ALTERAR', pergunta: 'Você conferiu a mudança deste pedido para aguardando pagamento?' });">Mover para aguardando pagamento</button>
            </form>
        <?php endif; ?>

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

        <?php if (!empty($can_manage_pedidos) && (string) $pedido['status'] === 'cancelado'): ?>
            <form method="post" action="/admin/pedidos/reverter-cancelamento" class="admin-form">
                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                <label for="reverter_cancelamento_justificativa">Justificativa da reversão</label>
                <textarea id="reverter_cancelamento_justificativa" name="justificativa" rows="3" required placeholder="Informe a justificativa para restaurar este pedido ao status anterior."></textarea>
                <button type="submit" class="button-link button-link--primary" onclick="return confirmarAcaoCritica({ palavra: 'REVERTER', pergunta: 'Você conferiu a reversão deste cancelamento?' });">Reverter cancelamento</button>
            </form>

            <form method="post" action="/admin/pedidos/reabrir-aguardando-pagamento" class="admin-form">
                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                <label for="reabrir_justificativa">Justificativa da reabertura</label>
                <textarea id="reabrir_justificativa" name="justificativa" rows="3" required placeholder="Informe a justificativa para reabrir este pedido como aguardando pagamento."></textarea>
                <button type="submit" class="button-link button-link--primary" onclick="return confirmarAcaoCritica({ palavra: 'REABRIR', pergunta: 'Você conferiu a reabertura deste pedido como aguardando pagamento?' });">Reabrir como aguardando pagamento</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($can_manage_pedidos)): ?>
            <form method="post" action="/admin/pedidos/excluir" class="admin-form admin-pedidos__delete-form">
                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                <label>Justificativa da lixeira</label>
                <textarea name="justificativa" rows="3" required placeholder="Informe a justificativa da exclusão."></textarea>
                <?php if (!empty($pedido['exclusao']) && empty($pedido['exclusao']['ok'])): ?>
                    <div class="alert-danger">
                        <?php echo htmlspecialchars($pedido['exclusao']['motivos_texto'] ?? 'Este pedido não pode ser excluído.', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <button type="submit" class="button-link button-link--danger" <?php echo (!empty($pedido['exclusao']) && empty($pedido['exclusao']['ok'])) ? 'disabled' : ''; ?> onclick="return confirmarAcaoCritica({ palavra: 'EXCLUIR', pergunta: 'Você conferiu a exclusão deste pedido?' });">🗑 Excluir pedido</button>
            </form>
        <?php endif; ?>
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
