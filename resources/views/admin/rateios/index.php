<?php use App\Core\Helpers; ?>

<section class="hero">
    <h1>Rateios</h1>
    <p>Visao consolidada dos rateios por curso e turma.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="quick-actions">
    <a class="card-link" href="/admin/rateios/criar<?php echo !empty($apuracao_id) ? '?apuracao_id=' . (int) $apuracao_id : ''; ?>">Novo rateio</a>
    <a class="card-link" href="/admin/financeiro">Financeiro</a>
    <a class="card-link" href="/admin/financeiro/repasses">Repasses</a>
</section>

<section class="status-card">
    <strong>Selecionar apuracao</strong>
    <form method="get" action="/admin/rateios" class="form-grid">
        <label>
            Apuracao
            <select name="apuracao_id">
                <option value="0">Todas</option>
                <?php foreach ($apuracoes as $apuracao): ?>
                    <option value="<?php echo (int) $apuracao['id']; ?>" <?php echo (int) ($apuracao_id ?? 0) === (int) $apuracao['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($apuracao['competencia']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Filtrar</button>
    </form>
</section>

<section class="status-card">
    <strong>Rateios</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Competência</th>
                    <th>Curso</th>
                    <th>Turma</th>
                    <th>Base liquida</th>
                    <th>Percentual</th>
                    <th>Restante empresa</th>
                    <th>Participantes</th>
                    <th>Valor rateado</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rateios)): ?>
                    <tr><td colspan="10">Nenhum rateio encontrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($rateios as $rateio): ?>
                    <tr>
                        <td><?php echo Helpers::e($rateio['competencia'] ?? ''); ?></td>
                        <td><?php echo Helpers::e($rateio['curso_nome'] ?? ''); ?></td>
                        <td><?php echo Helpers::e($rateio['turma_nome'] ?? ''); ?></td>
                        <td>R$ <?php echo number_format((float) $rateio['base_liquida'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format((float) $rateio['percentual_total'], 2, ',', '.'); ?>%</td>
                        <td><?php echo number_format((float) ($rateio['percentual_restante_empresa'] ?? 0), 2, ',', '.'); ?>%</td>
                        <td><?php echo (int) ($rateio['participantes_count'] ?? 0); ?></td>
                        <td>R$ <?php echo number_format((float) $rateio['valor_rateio_total'], 2, ',', '.'); ?></td>
                        <td><?php echo Helpers::e($rateio['status']); ?></td>
                        <td>
                            <a href="/admin/rateios/show?rateio_id=<?php echo (int) $rateio['id']; ?>">Detalhar</a>
                            <a href="/admin/rateios/editar?rateio_id=<?php echo (int) $rateio['id']; ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

