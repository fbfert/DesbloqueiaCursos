<?php use App\Core\Helpers; ?>

<section class="hero">
    <h1>Dashboard executivo</h1>
    <p>Consolidado comercial, academico, operacional e financeiro do portal.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Filtros</strong>
    <form method="get" action="/admin/dashboard" class="form-grid">
        <label>
            Periodo
            <select name="periodo">
                <option value="hoje" <?php echo ($filters['periodo'] ?? '') === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                <option value="semana" <?php echo ($filters['periodo'] ?? '') === 'semana' ? 'selected' : ''; ?>>Semana</option>
                <option value="mes" <?php echo ($filters['periodo'] ?? '') === 'mes' ? 'selected' : ''; ?>>Mes</option>
                <option value="custom" <?php echo ($filters['periodo'] ?? '') === 'custom' ? 'selected' : ''; ?>>Personalizado</option>
            </select>
        </label>
        <label>
            Data inicial
            <input type="date" name="data_inicio" value="<?php echo Helpers::e($filters['data_inicio'] ?? ''); ?>">
        </label>
        <label>
            Data final
            <input type="date" name="data_fim" value="<?php echo Helpers::e($filters['data_fim'] ?? ''); ?>">
        </label>
        <label>
            Curso/evento
            <select name="curso_evento_id">
                <option value="0">Todos</option>
                <?php foreach (($options['cursos'] ?? array()) as $curso): ?>
                    <option value="<?php echo (int) $curso['id']; ?>" <?php echo (int) ($filters['curso_evento_id'] ?? 0) === (int) $curso['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($curso['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Turma
            <select name="turma_id">
                <option value="0">Todas</option>
                <?php foreach (($options['turmas'] ?? array()) as $turma): ?>
                    <option value="<?php echo (int) $turma['id']; ?>" <?php echo (int) ($filters['turma_id'] ?? 0) === (int) $turma['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($turma['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Categoria
            <select name="categoria_id">
                <option value="0">Todas</option>
                <?php foreach (($options['categorias'] ?? array()) as $categoria): ?>
                    <option value="<?php echo (int) $categoria['id']; ?>" <?php echo (int) ($filters['categoria_id'] ?? 0) === (int) $categoria['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($categoria['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Cidade
            <input type="text" name="cidade" value="<?php echo Helpers::e($filters['cidade'] ?? ''); ?>">
        </label>
        <label>
            UF
            <input type="text" name="estado" maxlength="2" value="<?php echo Helpers::e($filters['estado'] ?? ''); ?>">
        </label>
        <div class="full">
            <button type="submit">Aplicar filtros</button>
        </div>
    </form>
</section>

<section class="status-grid">
    <?php foreach (($cards ?? array()) as $card): ?>
        <article class="status-card">
            <strong><?php echo Helpers::e($card['label']); ?></strong>
            <p class="dashboard-kpi__value"><?php echo Helpers::e($card['value']); ?></p>
            <p class="dashboard-kpi__sub"><?php echo Helpers::e($card['subvalue']); ?></p>
        </article>
    <?php endforeach; ?>
</section>

<section class="status-card">
    <strong>Top cursos e eventos</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>Categoria</th>
                    <th>Vendidos</th>
                    <th>Pedidos</th>
                    <th>Receita</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($top_courses)): ?>
                    <tr><td colspan="5">Nenhum dado encontrado para os filtros selecionados.</td></tr>
                <?php endif; ?>
                <?php foreach ($top_courses as $curso): ?>
                    <tr>
                        <td><?php echo Helpers::e($curso['nome']); ?></td>
                        <td><?php echo Helpers::e($curso['categoria_nome']); ?></td>
                        <td><?php echo (int) $curso['total_vendido']; ?></td>
                        <td><?php echo (int) $curso['total_pedidos']; ?></td>
                        <td>R$ <?php echo number_format((float) $curso['receita'], 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="status-card">
    <strong>Receita por periodo</strong>
    <div class="dashboard-chart">
        <?php if (empty($revenue_by_period)): ?>
            <p class="muted">Nenhum movimento encontrado no periodo selecionado.</p>
        <?php endif; ?>
        <?php foreach ($revenue_by_period as $row): ?>
            <div class="dashboard-chart__row">
                <div class="dashboard-chart__meta">
                    <span><?php echo Helpers::e($row['periodo']); ?></span>
                    <span>R$ <?php echo number_format((float) $row['receita'], 2, ',', '.'); ?> | <?php echo (int) $row['total_pedidos']; ?> pedidos</span>
                </div>
                <div class="dashboard-chart__track">
                    <span class="dashboard-chart__fill" style="width: <?php echo number_format((float) $row['barra_percentual'], 2, '.', ''); ?>%;"></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="status-card">
    <strong>Repasses por competencia</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Competencia</th>
                    <th>Bruto</th>
                    <th>Retido</th>
                    <th>Liquido</th>
                    <th>Pago</th>
                    <th>Quantidade</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($repasses_by_competencia)): ?>
                    <tr><td colspan="6">Nenhuma apuracao encontrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($repasses_by_competencia as $row): ?>
                    <tr>
                        <td><?php echo Helpers::e($row['competencia']); ?></td>
                        <td>R$ <?php echo number_format((float) $row['valor_bruto'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $row['valor_retenido'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $row['valor_liquido'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $row['valor_pago'], 2, ',', '.'); ?></td>
                        <td><?php echo (int) $row['total_repasses']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
