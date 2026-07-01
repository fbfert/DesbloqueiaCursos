<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Configurações financeiras</h1>
        <p class="admin-page__subtitle">Corte financeiro e teto de rateio.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="/admin/configuracoes-globais/financeiro" class="form-grid">
        <label>
            Data de corte financeira
            <input type="date" name="data_corte_financeiro" value="<?php echo htmlspecialchars((string) (isset($configuracao['data_corte_financeiro']) ? $configuracao['data_corte_financeiro'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Percentual máximo de rateio
            <input type="number" step="0.01" min="0" max="75" name="percentual_rateio_maximo" value="<?php echo htmlspecialchars(number_format((float) (isset($configuracao['percentual_rateio_maximo']) ? $configuracao['percentual_rateio_maximo'] : 75), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Observação de repasse
            <textarea name="observacao_repasse" rows="5"><?php echo htmlspecialchars((string) (isset($configuracao['observacao_repasse']) ? $configuracao['observacao_repasse'] : ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>
        <?php $cancel_url = '/admin/configuracoes-globais/financeiro'; ?>
        <?php $showSaveAndNew = false; ?>
        <?php $showSaveAndExit = false; ?>
        <?php $showSaveAsCopy = false; ?>
        <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
    </form>
</section>
</div>

