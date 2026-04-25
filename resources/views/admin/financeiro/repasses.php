<section class="hero">
    <h1>Repasses</h1>
    <p>Fechamento, documentos e pagamentos vinculados as apuracoes.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Filtrar apuracao</strong>
    <form method="get" action="/admin/financeiro/repasses" class="form-grid">
        <label>
            Apuracao
            <select name="apuracao_id">
                <option value="0">Todas</option>
                <?php foreach ($apuracoes as $apuracao): ?>
                    <option value="<?php echo (int) $apuracao['id']; ?>" <?php echo (int) $apuracao_id === (int) $apuracao['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($apuracao['competencia'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div>
            <button type="submit">Filtrar</button>
        </div>
    </form>
</section>

<section class="status-card">
    <strong>Gerar repasses</strong>
    <form method="post" action="/admin/financeiro/repasses/gerar" class="form-grid">
        <label>
            Apuracao
            <select name="apuracao_id" required>
                <option value="">Selecione</option>
                <?php foreach ($apuracoes as $apuracao): ?>
                    <option value="<?php echo (int) $apuracao['id']; ?>">
                        <?php echo htmlspecialchars($apuracao['competencia'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div>
            <button type="submit">Gerar repasses</button>
        </div>
    </form>
</section>

<section class="status-card">
    <strong>Repasses</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Competência</th>
                    <th>Professor</th>
                    <th>Tipo</th>
                    <th>Base liquida</th>
                    <th>Bruto</th>
                    <th>Retido</th>
                    <th>Líquido</th>
                    <th>Status</th>
                    <th>Documento</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($repasses)): ?>
                    <tr>
                        <td colspan="9">Nenhum repasse encontrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($repasses as $repasse): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($repasse['competencia'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($repasse['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($repasse['tipo_fiscal'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['base_liquida'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_bruto'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_retenido'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_liquido'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($repasse['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if (!empty($repasse['documento_obrigatorio'])): ?>
                                Obrigatorio
                            <?php else: ?>
                                Não obrigatorio
                            <?php endif; ?>
                            <br>
                            <small><?php echo htmlspecialchars((string) $repasse['documento_validado_em'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="9">
                            <div class="grid-2">
                                <form method="post" action="/admin/financeiro/documento" enctype="multipart/form-data" class="form-grid">
                                    <input type="hidden" name="repasse_id" value="<?php echo (int) $repasse['id']; ?>">
                                    <strong>Documento</strong>
                                    <label>
                                        Tipo
                                        <select name="tipo_documento">
                                            <option value="nota_fiscal">Nota fiscal</option>
                                            <option value="rpa">RPA</option>
                                            <option value="outro">Outro</option>
                                        </select>
                                    </label>
                                    <label>
                                        Numero
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
                                    <div class="full">
                                        <button type="submit">Registrar documento</button>
                                    </div>
                                </form>

                                <form method="post" action="/admin/financeiro/pagamento" enctype="multipart/form-data" class="form-grid">
                                    <input type="hidden" name="repasse_id" value="<?php echo (int) $repasse['id']; ?>">
                                    <strong>Pagamento</strong>
                                    <label>
                                        Data
                                        <input type="date" name="data_pagamento" value="<?php echo date('Y-m-d'); ?>">
                                    </label>
                                    <label>
                                        Valor
                                        <input type="number" step="0.01" min="0" name="valor" value="<?php echo number_format((float) $repasse['valor_liquido'], 2, '.', ''); ?>">
                                    </label>
                                    <label>
                                        Metodo
                                        <select name="metodo">
                                            <option value="pix">PIX</option>
                                            <option value="ted">TED</option>
                                            <option value="transferencia">Transferencia</option>
                                            <option value="boleto">Boleto</option>
                                            <option value="outro">Outro</option>
                                        </select>
                                    </label>
                                    <label>
                                        Referencia
                                        <input type="text" name="referencia_bancaria" maxlength="120">
                                    </label>
                                    <label>
                                        Comprovante
                                        <input type="file" name="comprovante">
                                    </label>
                                    <div class="full">
                                        <button type="submit">Registrar pagamento</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

