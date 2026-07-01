<section class="hero">
    <h1>Meu financeiro</h1>
    <p>Visualizacao restrita aos cursos e turmas atribuídos ao professor.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Perfil fiscal</strong>
    <?php if (!empty($fiscal)): ?>
        <p>Tipo: <?php echo htmlspecialchars($fiscal['tipo_pessoa'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Documento: <?php echo htmlspecialchars(!empty($fiscal['cpf']) ? $fiscal['cpf'] : $fiscal['cnpj'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Alíquota: <?php echo number_format((float) $fiscal['aliquota_retencao'], 2, ',', '.'); ?>%</p>
        <p>Status: <?php echo htmlspecialchars($fiscal['status'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p>E-mail financeiro: <?php echo htmlspecialchars((string) $fiscal['email_financeiro'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php else: ?>
        <p>Perfil fiscal ainda não cadastrado.</p>
    <?php endif; ?>
</section>

<section class="status-card">
    <strong>Repasses</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Competência</th>
                    <th>Base liquida</th>
                    <th>Bruto</th>
                    <th>Retido</th>
                    <th>Líquido</th>
                    <th>Status</th>
                    <th>Apuracao</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($repasses)): ?>
                    <tr>
                        <td colspan="7">Nenhum repasse encontrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($repasses as $repasse): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($repasse['competencia'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['base_liquida'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_bruto'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_retenido'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_liquido'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($repasse['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($repasse['apuracao_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="status-card">
    <strong>Espelhos de RPA</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Competência</th>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Bruto</th>
                    <th>Retido</th>
                    <th>Líquido</th>
                    <th>Arquivo</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($espelhos)): ?>
                    <tr>
                        <td colspan="8">Nenhum espelho encontrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($espelhos as $espelho): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($espelho['competencia'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($espelho['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $espelho['cpf'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>R$ <?php echo number_format((float) $espelho['valor_bruto'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $espelho['valor_retenido'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $espelho['valor_liquido'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($espelho['arquivo_nome_original'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($espelho['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

