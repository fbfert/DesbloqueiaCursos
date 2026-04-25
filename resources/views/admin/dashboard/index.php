<?php use App\Core\Helpers; ?>
<?php
$cardsList = isset($cards) && is_array($cards) ? $cards : array();
$cardsByLabel = array();
foreach ($cardsList as $card) {
    if (!empty($card['label'])) {
        $cardsByLabel[$card['label']] = $card;
    }
}

$findCardByLabels = function (array $labels) use ($cardsByLabel) {
    foreach ($labels as $label) {
        if (isset($cardsByLabel[$label])) {
            return $cardsByLabel[$label];
        }
    }

    return null;
};

$highlightGroups = array(
    array('Vendas do mês', 'Vendas do mes'),
    array('Pedidos pendentes'),
    array('Comprovantes em análise', 'Comprovantes em analise'),
    array('Total a pagar a professores'),
);

$highlights = array();
foreach ($highlightGroups as $groupLabels) {
    $card = $findCardByLabels($groupLabels);
    if ($card !== null) {
        $highlights[] = $card;
    }
}

$pendingOrdersCard = $findCardByLabels(array('Pedidos pendentes'));
$pixInAnalysisCard = $findCardByLabels(array('Comprovantes em análise', 'Comprovantes em analise'));
$pendingRepassesCard = $findCardByLabels(array('Total a pagar a professores'));

$pendingOrdersValue = isset($pendingOrdersCard['value']) ? $pendingOrdersCard['value'] : '0';
$pixInAnalysisValue = isset($pixInAnalysisCard['value']) ? $pixInAnalysisCard['value'] : '0';
$pendingRepassesValue = isset($pendingRepassesCard['value']) ? $pendingRepassesCard['value'] : 'R$ 0,00';
?>

<div class="admin-page">
    <section class="hero admin-dashboard-hero">
        <div class="hero__content">
            <span class="eyebrow">Painel administrativo</span>
            <h1>Dashboard executivo</h1>
            <p>Consolidado comercial, acadêmico, operacional e financeiro do portal.</p>
        </div>
        <div class="hero__panel admin-dashboard-hero__panel">
            <strong>Resumo rápido</strong>
            <?php if (empty($highlights)): ?>
                <p class="muted">Sem indicadores para exibir no momento.</p>
            <?php endif; ?>
            <?php foreach ($highlights as $item): ?>
                <div class="admin-dashboard-highlight">
                    <span><?php echo Helpers::e($item['label']); ?></span>
                    <strong><?php echo Helpers::e($item['value']); ?></strong>
                    <small><?php echo Helpers::e($item['subvalue']); ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Atalhos administrativos</h2>
        </div>
        <div class="quick-actions quick-actions--dashboard">
            <a class="card-link admin-shortcut" href="/admin/pedidos"><span>Pedidos</span><small>Análise, aprovação e pendências</small></a>
            <a class="card-link admin-shortcut" href="/admin/comprovantes-pix"><span>Comprovantes PIX</span><small>Validação manual de pagamentos</small></a>
            <a class="card-link admin-shortcut" href="/admin/inscricoes"><span>Inscrições</span><small>Status e acompanhamento</small></a>
            <a class="card-link admin-shortcut" href="/admin/cursos"><span>Cursos</span><small>Catálogo e publicação</small></a>
            <a class="card-link admin-shortcut" href="/admin/turmas"><span>Turmas</span><small>Edições e vagas</small></a>
            <a class="card-link admin-shortcut" href="/admin/cupons"><span>Cupons</span><small>Campanhas e descontos</small></a>
            <a class="card-link admin-shortcut" href="/admin/financeiro"><span>Financeiro</span><small>Apurações e repasses</small></a>
            <a class="card-link admin-shortcut" href="/admin/configuracoes-globais"><span>Configurações</span><small>Parâmetros globais do portal</small></a>
            <a class="card-link admin-shortcut" href="/admin/rbac"><span>Acessos (RBAC)</span><small>Perfis e permissões</small></a>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Ações prioritárias</h2>
        </div>
        <div class="quick-actions quick-actions--dashboard">
            <a class="card-link admin-shortcut admin-shortcut--alert" href="/admin/pedidos">
                <span>Pedidos pendentes</span>
                <small><?php echo Helpers::e((string) $pendingOrdersValue); ?> aguardando ação</small>
            </a>
            <a class="card-link admin-shortcut admin-shortcut--alert" href="/admin/comprovantes-pix">
                <span>Comprovantes em análise</span>
                <small><?php echo Helpers::e((string) $pixInAnalysisValue); ?> itens para revisar</small>
            </a>
            <a class="card-link admin-shortcut" href="/admin/financeiro/repasses">
                <span>Repasses pendentes</span>
                <small><?php echo Helpers::e((string) $pendingRepassesValue); ?> em aberto</small>
            </a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Filtros</h2>
        </div>
        <form method="get" action="/admin/dashboard" class="admin-filters">
            <div class="admin-filters__row">
                <label>
                    Período
                    <select name="periodo">
                        <option value="hoje" <?php echo ($filters['periodo'] ?? '') === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                        <option value="semana" <?php echo ($filters['periodo'] ?? '') === 'semana' ? 'selected' : ''; ?>>Semana</option>
                        <option value="mes" <?php echo ($filters['periodo'] ?? '') === 'mes' ? 'selected' : ''; ?>>Mês</option>
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
            </div>
            <div class="admin-actions">
                <button type="submit">Aplicar filtros</button>
                <a href="/admin/dashboard" class="button-link button-link--ghost">Limpar filtros</a>
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

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Top cursos e eventos</h2>
        </div>
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

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Receita por período</h2>
        </div>
        <div class="dashboard-chart">
            <?php if (empty($revenue_by_period)): ?>
                <p class="muted">Nenhum movimento encontrado no período selecionado.</p>
            <?php endif; ?>
            <?php foreach ($revenue_by_period as $row): ?>
                <?php
                $periodo = isset($row['periodo']) ? (string) $row['periodo'] : '';
                $periodoFormatado = $periodo;
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodo)) {
                    $periodoFormatado = date('d/m/Y', strtotime($periodo));
                }
                ?>
                <div class="dashboard-chart__row">
                    <div class="dashboard-chart__meta">
                        <span><?php echo Helpers::e($periodoFormatado); ?></span>
                        <span>R$ <?php echo number_format((float) $row['receita'], 2, ',', '.'); ?> | <?php echo (int) $row['total_pedidos']; ?> pedidos</span>
                    </div>
                    <div class="dashboard-chart__track">
                        <span class="dashboard-chart__fill" style="width: <?php echo number_format((float) $row['barra_percentual'], 2, '.', ''); ?>%;"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Repasses por competência</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table">
            <thead>
                <tr>
                    <th>Competência</th>
                    <th>Bruto</th>
                    <th>Retido</th>
                    <th>Líquido</th>
                    <th>Pago</th>
                    <th>Quantidade</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($repasses_by_competencia)): ?>
                    <tr><td colspan="6">Nenhuma apuração encontrada.</td></tr>
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
</div>

