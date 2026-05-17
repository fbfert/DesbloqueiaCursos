<?php
$totalApuracoes = is_array($apuracoes) ? count($apuracoes) : 0;
$totalPerfisFiscais = is_array($professores_fiscal) ? count($professores_fiscal) : 0;
$apuracoesAbertas = 0;

foreach ((array) $apuracoes as $apuracaoResumo) {
    if (isset($apuracaoResumo['status']) && (string) $apuracaoResumo['status'] !== 'fechada') {
        $apuracoesAbertas++;
    }
}
?>

<div class="admin-page">
    <section class="admin-page__header admin-page__header--with-metrics">
        <div class="admin-page__header-content">
            <h1 class="admin-page__title">Financeiro</h1>
            <p class="admin-page__subtitle">Apurações mensais, repasses, perfil fiscal dos professores e parâmetros do rateio.</p>
        </div>
        <div class="admin-page__metrics">
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Apurações registradas</span>
                <strong class="admin-page__metric-value"><?php echo (int) $totalApuracoes; ?></strong>
                <small class="admin-page__metric-help">histórico consolidado</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Apurações em aberto</span>
                <strong class="admin-page__metric-value"><?php echo (int) $apuracoesAbertas; ?></strong>
                <small class="admin-page__metric-help">pendentes de fechamento</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Perfis fiscais</span>
                <strong class="admin-page__metric-value"><?php echo (int) $totalPerfisFiscais; ?></strong>
                <small class="admin-page__metric-help">professores cadastrados</small>
            </article>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Atalhos financeiros</h2>
        </div>
        <div class="quick-actions quick-actions--dashboard">
            <a class="card-link admin-shortcut" href="/admin/financeiro/repasses"><span>Repasses</span><small>Geração e pagamento por competência</small></a>
            <a class="card-link admin-shortcut" href="/admin/professores-fiscais"><span>Professores fiscais</span><small>Gestão detalhada de perfis</small></a>
            <a class="card-link admin-shortcut" href="/admin/rateios"><span>Rateios</span><small>Acompanhamento por curso/turma</small></a>
            <a class="card-link admin-shortcut" href="/admin/dashboard"><span>Dashboard</span><small>Voltar ao painel executivo</small></a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="grid-2">
        <article class="status-card">
            <strong>Parâmetro atual</strong>
            <p>Rateio máximo: <?php echo number_format((float) $configuracao_financeira['percentual_rateio_maximo'], 2, ',', '.'); ?>%</p>
            <p>Fechamento por competência: <?php echo htmlspecialchars((string) $configuracao_financeira['data_corte_financeiro'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><?php echo htmlspecialchars((string) $configuracao_financeira['observacao_repasse'], ENT_QUOTES, 'UTF-8'); ?></p>
        </article>

        <article class="status-card">
            <strong>Nova apuração</strong>
            <form method="post" action="/admin/financeiro/apurar" class="form-grid">
                <label>
                    Competência
                    <input type="month" name="competencia" required>
                </label>
                <div class="full cta-group">
                    <button type="submit" class="button-link button-link--primary">Apurar</button>
                    <a class="button-link button-link--ghost" href="/admin/financeiro">Cancelar</a>
                </div>
            </form>
        </article>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Apurações</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table admin-table--financeiro-apuracoes">
            <thead>
                <tr>
                    <th>Competência</th>
                    <th>Base bruta</th>
                    <th>Líquida</th>
                    <th>Rateio</th>
                    <th>Retido</th>
                    <th>Status</th>
                    <th>Fechada em</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($apuracoes)): ?>
                    <tr>
                        <td colspan="7">Nenhuma apuração encontrada.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($apuracoes as $apuracao): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($apuracao['competencia'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>R$ <?php echo number_format((float) $apuracao['base_bruta'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $apuracao['base_liquida'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $apuracao['valor_rateio_total'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $apuracao['valor_retenido_total'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($apuracao['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $apuracao['fechada_em'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Perfil fiscal dos professores</h2>
        </div>
        <form method="post" action="/admin/financeiro/professor-fiscal" class="form-grid">
        <label>
            Professor
            <select name="usuario_id" required>
                <option value="">Selecione</option>
                <?php foreach ($professores as $professor): ?>
                    <option value="<?php echo (int) $professor['id']; ?>">
                        <?php echo htmlspecialchars($professor['nome'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($professor['email'], ENT_QUOTES, 'UTF-8'); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Tipo fiscal
            <select name="tipo_pessoa">
                <option value="pf">PF</option>
                <option value="pj">PJ</option>
            </select>
        </label>

        <label>
            CPF
            <input type="text" name="cpf" maxlength="14">
        </label>

        <label>
            CNPJ
            <input type="text" name="cnpj" maxlength="20">
        </label>

        <label>
            Razão social
            <input type="text" name="razao_social" maxlength="191">
        </label>

        <label>
            Nome fantasia
            <input type="text" name="nome_fantasia" maxlength="191">
        </label>

        <label>
            Inscrição municipal
            <input type="text" name="inscricao_municipal" maxlength="100">
        </label>

        <label>
            Alíquota de retenção (%)
            <input type="number" step="0.01" min="0" max="100" name="aliquota_retencao" value="0.00">
        </label>

        <label>
            E-mail financeiro
            <input type="email" name="email_financeiro" maxlength="191">
        </label>

        <label>
            Status
            <select name="status">
                <option value="ativo">Ativo</option>
                <option value="inativo">Inativo</option>
            </select>
        </label>

        <label class="full">
            Observação
            <textarea name="observacao" rows="3"></textarea>
        </label>

        <label class="full">
            <input type="checkbox" name="exige_nota_fiscal" value="1">
            Exigir nota fiscal
        </label>

        <div class="full">
            <?php
            $cancel_url = '/admin/financeiro';
            $show_save_as_copy = false;
            $save_label = 'Salvar perfil fiscal';
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </div>
        </form>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Perfis cadastrados</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table admin-table--financeiro-perfis">
            <thead>
                <tr>
                    <th>Professor</th>
                    <th>Tipo</th>
                    <th>Documento</th>
                    <th>Alíquota</th>
                    <th>Exige NF</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($professores_fiscal)): ?>
                    <tr>
                        <td colspan="6">Nenhum perfil fiscal cadastrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($professores_fiscal as $perfil): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($perfil['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($perfil['tipo_pessoa'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(!empty($perfil['cpf']) ? $perfil['cpf'] : $perfil['cnpj'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format((float) $perfil['aliquota_retencao'], 2, ',', '.'); ?>%</td>
                        <td><?php echo !empty($perfil['exige_nota_fiscal']) ? 'Sim' : 'Não'; ?></td>
                        <td><?php echo htmlspecialchars($perfil['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </section>
</div>

