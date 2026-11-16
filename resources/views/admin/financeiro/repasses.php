<?php
$rotulosStatusRepasse = array(
    'pendente' => 'Pendente',
    'aguardando_documento' => 'Aguardando documento',
    'documento_recebido' => 'Documento recebido',
    'aprovado' => 'Aprovado para pagamento',
    'pago' => 'Pago',
    'cancelado' => 'Cancelado',
);
$rotulosStatusDocumento = array(
    'pendente' => 'Pendente',
    'recebido' => 'Recebido',
    'validado' => 'Validado',
    'rejeitado' => 'Rejeitado',
);
$rotulosTipoDocumento = array(
    'nota_fiscal' => 'Nota fiscal',
    'rpa' => 'RPA',
    'outro' => 'Outro',
);
$rotulosMetodo = array(
    'pix' => 'PIX',
    'ted' => 'TED',
    'transferencia' => 'Transferência',
    'boleto' => 'Boleto',
    'outro' => 'Outro',
);
$h = function ($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
$dataHora = function ($valor) {
    return $valor ? date('d/m/Y H:i', strtotime($valor)) : '';
};
?>
<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Repasses</h1>
        <p class="admin-page__subtitle">Fechamento, documentos e pagamentos vinculados às apurações.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Filtrar apuração</strong>
    <form method="get" action="/admin/financeiro/repasses" class="form-grid">
        <label>
            Apuração
            <select name="apuracao_id">
                <option value="0">Todas</option>
                <?php foreach ($apuracoes as $apuracao): ?>
                    <option value="<?php echo (int) $apuracao['id']; ?>" <?php echo (int) $apuracao_id === (int) $apuracao['id'] ? 'selected' : ''; ?>>
                        <?php echo $h($apuracao['competencia']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="cta-group full">
            <button type="submit" class="button-link button-link--primary">Filtrar</button>
            <a class="button-link button-link--ghost" href="/admin/financeiro/repasses">Limpar filtros</a>
        </div>
    </form>
</section>

<section class="status-card">
    <strong>Gerar repasses</strong>
    <p class="muted">Cada apuração gera os repasses uma única vez.</p>
    <form method="post" action="/admin/financeiro/repasses/gerar" class="form-grid">
        <label>
            Apuração
            <select name="apuracao_id" required>
                <option value="">Selecione</option>
                <?php foreach ($apuracoes as $apuracao): ?>
                    <option value="<?php echo (int) $apuracao['id']; ?>">
                        <?php echo $h($apuracao['competencia']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="cta-group full">
            <button type="submit" class="button-link button-link--primary">Gerar repasses</button>
            <a class="button-link button-link--ghost" href="/admin/financeiro/repasses">Cancelar</a>
        </div>
    </form>
</section>

<section class="status-card">
    <strong>Repasses</strong>
    <div class="table-wrap">
            <table class="admin-table admin-table--financeiro-repasses">
            <thead>
                <tr>
                    <th>Competência</th>
                    <th>Professor</th>
                    <th>Tipo</th>
                    <th>Base líquida</th>
                    <th>Bruto</th>
                    <th>Retido</th>
                    <th>Líquido</th>
                    <th>Status</th>
                    <th>Documento</th>
                    <th>Pago em</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($repasses)): ?>
                    <tr>
                        <td colspan="10">Nenhum repasse encontrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($repasses as $repasse): ?>
                    <?php
                    $statusRepasse = (string) $repasse['status'];
                    $encerrado = in_array($statusRepasse, array('pago', 'cancelado'), true);
                    $documentos = isset($repasse['documentos']) ? $repasse['documentos'] : array();
                    $pagamentos = isset($repasse['pagamentos']) ? $repasse['pagamentos'] : array();
                    ?>
                    <tr>
                        <td><?php echo $h($repasse['competencia']); ?></td>
                        <td><?php echo $h($repasse['usuario_nome']); ?></td>
                        <td><?php echo $h(strtoupper((string) $repasse['tipo_fiscal'])); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['base_liquida'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_bruto'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_retenido'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_liquido'], 2, ',', '.'); ?></td>
                        <td><?php echo $h(isset($rotulosStatusRepasse[$statusRepasse]) ? $rotulosStatusRepasse[$statusRepasse] : $statusRepasse); ?></td>
                        <td>
                            <?php echo !empty($repasse['documento_obrigatorio']) ? 'Obrigatório' : 'Não obrigatório'; ?>
                            <?php if (!empty($repasse['documento_validado_em'])): ?>
                                <br><small>Validado em <?php echo $h($dataHora($repasse['documento_validado_em'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $h($dataHora(isset($repasse['pago_em']) ? $repasse['pago_em'] : null)); ?></td>
                    </tr>
                    <tr>
                        <td colspan="10">
                            <?php if (!empty($documentos)): ?>
                                <strong>Documentos enviados</strong>
                                <ul class="admin-list">
                                    <?php foreach ($documentos as $documento): ?>
                                        <?php $statusDocumento = (string) $documento['status']; ?>
                                        <li>
                                            <a href="/admin/financeiro/documento/arquivo?id=<?php echo (int) $documento['id']; ?>" target="_blank" rel="noopener">Ver documento</a>
                                            — <?php echo $h(isset($rotulosTipoDocumento[$documento['tipo_documento']]) ? $rotulosTipoDocumento[$documento['tipo_documento']] : $documento['tipo_documento']); ?>
                                            <?php if (!empty($documento['numero_documento'])): ?>nº <?php echo $h($documento['numero_documento']); ?><?php endif; ?>
                                            — <?php echo $h(isset($rotulosStatusDocumento[$statusDocumento]) ? $rotulosStatusDocumento[$statusDocumento] : $statusDocumento); ?>
                                            <?php if ($statusDocumento === 'rejeitado' && !empty($documento['motivo_rejeicao'])): ?>
                                                <br><small>Motivo: <?php echo $h($documento['motivo_rejeicao']); ?></small>
                                            <?php endif; ?>
                                            <?php if (!$encerrado && $statusDocumento === 'recebido'): ?>
                                                <div class="cta-group">
                                                    <form method="post" action="/admin/financeiro/documento/validar">
                                                        <input type="hidden" name="documento_id" value="<?php echo (int) $documento['id']; ?>">
                                                        <input type="hidden" name="apuracao_id" value="<?php echo (int) $apuracao_id; ?>">
                                                        <button type="submit" class="button-link button-link--primary">Validar</button>
                                                    </form>
                                                    <form method="post" action="/admin/financeiro/documento/rejeitar" class="form-grid">
                                                        <input type="hidden" name="documento_id" value="<?php echo (int) $documento['id']; ?>">
                                                        <input type="hidden" name="apuracao_id" value="<?php echo (int) $apuracao_id; ?>">
                                                        <label>
                                                            Motivo da rejeição
                                                            <input type="text" name="motivo" maxlength="500" required>
                                                        </label>
                                                        <button type="submit" class="button-link button-link--ghost">Rejeitar</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($pagamentos)): ?>
                                <strong>Pagamentos</strong>
                                <ul class="admin-list">
                                    <?php foreach ($pagamentos as $pagamento): ?>
                                        <li>
                                            <?php echo $h($pagamento['data_pagamento'] ? date('d/m/Y', strtotime($pagamento['data_pagamento'])) : ''); ?>
                                            — R$ <?php echo number_format((float) $pagamento['valor'], 2, ',', '.'); ?>
                                            — <?php echo $h(isset($rotulosMetodo[$pagamento['metodo']]) ? $rotulosMetodo[$pagamento['metodo']] : $pagamento['metodo']); ?>
                                            <?php if (!empty($pagamento['referencia_bancaria'])): ?>— <?php echo $h($pagamento['referencia_bancaria']); ?><?php endif; ?>
                                            <?php if (!empty($pagamento['comprovante_caminho'])): ?>
                                                — <a href="/admin/financeiro/pagamento/comprovante?id=<?php echo (int) $pagamento['id']; ?>" target="_blank" rel="noopener">Ver comprovante</a>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!$encerrado): ?>
                            <div class="grid-2">
                                <form method="post" action="/admin/financeiro/documento" enctype="multipart/form-data" class="form-grid">
                                    <input type="hidden" name="repasse_id" value="<?php echo (int) $repasse['id']; ?>">
                                    <strong>Enviar documento</strong>
                                    <label>
                                        Tipo
                                        <select name="tipo_documento">
                                            <option value="nota_fiscal">Nota fiscal</option>
                                            <option value="rpa">RPA</option>
                                            <option value="outro">Outro</option>
                                        </select>
                                    </label>
                                    <label>
                                        Número
                                        <input type="text" name="numero_documento" maxlength="80">
                                    </label>
                                    <label>
                                        Arquivo
                                        <input type="file" name="arquivo" required>
                                    </label>
                                    <label class="full">
                                        Observação
                                        <textarea name="observacao" rows="2"></textarea>
                                    </label>
                                        <div class="full cta-group">
                                            <button type="submit" class="button-link button-link--primary">Registrar documento</button>
                                        </div>
                                </form>

                                <?php if (empty($repasse['bloqueio_pagamento'])): ?>
                                <form method="post" action="/admin/financeiro/pagamento" enctype="multipart/form-data" class="form-grid">
                                    <input type="hidden" name="repasse_id" value="<?php echo (int) $repasse['id']; ?>">
                                    <strong>Registrar pagamento</strong>
                                    <label>
                                        Data
                                        <input type="date" name="data_pagamento" value="<?php echo date('Y-m-d'); ?>">
                                    </label>
                                    <label>
                                        Valor (igual ao líquido)
                                        <input type="text" name="valor" value="<?php echo number_format((float) $repasse['valor_liquido'], 2, ',', '.'); ?>" readonly>
                                    </label>
                                    <label>
                                        Método
                                        <select name="metodo">
                                            <option value="pix">PIX</option>
                                            <option value="ted">TED</option>
                                            <option value="transferencia">Transferência</option>
                                            <option value="boleto">Boleto</option>
                                            <option value="outro">Outro</option>
                                        </select>
                                    </label>
                                    <label>
                                        Referência
                                        <input type="text" name="referencia_bancaria" maxlength="120">
                                    </label>
                                    <label>
                                        Comprovante
                                        <input type="file" name="comprovante">
                                    </label>
                                    <div class="full cta-group">
                                        <button type="submit" class="button-link button-link--primary">Registrar pagamento</button>
                                    </div>
                                </form>
                                <?php else: ?>
                                <div class="form-grid">
                                    <strong>Pagamento</strong>
                                    <p class="muted full"><?php echo $h($repasse['bloqueio_pagamento']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
