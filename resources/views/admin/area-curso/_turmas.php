<?php use App\Core\Helpers; ?>

<?php
$turmas = isset($turmas) && is_array($turmas) ? $turmas : array();
$cursoAtual = isset($curso) && is_array($curso) ? $curso : array();
$turmasInscritosMap = isset($turmas_inscritos) && is_array($turmas_inscritos) ? $turmas_inscritos : array();
$csrfTurmaField = isset($csrfField) ? (string) $csrfField : '';
$canManageTurmas = !empty($can_manage_turmas);
$returnToTurmas = '/admin/area-curso?curso_id=' . (int) ($cursoAtual['id'] ?? 0) . '&aba=turmas';
$returnToTurmasEncoded = urlencode($returnToTurmas);

$formatDate = function ($value) {
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d/m/Y', $timestamp);
};

$statusBadgeClass = function ($status) {
    $status = (string) $status;
    if ($status === 'aberta') {
        return 'badge badge--success';
    }
    if ($status === 'planejada') {
        return 'badge badge--warn';
    }

    return 'badge';
};

$statusLabel = function ($status) {
    $mapa = array(
        'planejada' => 'Planejada',
        'aberta' => 'Aberta',
        'encerrada' => 'Encerrada',
        'excluida' => 'Excluída',
    );

    $status = (string) $status;

    return isset($mapa[$status]) ? $mapa[$status] : ucfirst(str_replace('_', ' ', $status));
};

$resumoTurmas = array(
    'total' => count($turmas),
    'abertas' => 0,
    'planejadas' => 0,
    'encerradas' => 0,
    'vagas' => 0,
    'inscritos' => 0,
);

foreach ($turmas as $turmaResumo) {
    $status = isset($turmaResumo['status']) ? (string) $turmaResumo['status'] : '';
    if ($status === 'aberta') {
        $resumoTurmas['abertas']++;
    } elseif ($status === 'planejada') {
        $resumoTurmas['planejadas']++;
    } elseif ($status === 'encerrada') {
        $resumoTurmas['encerradas']++;
    }

    if (!empty($turmaResumo['vagas'])) {
        $resumoTurmas['vagas'] += (int) $turmaResumo['vagas'];
    }

    $turmaResumoId = !empty($turmaResumo['id']) ? (int) $turmaResumo['id'] : 0;
    if ($turmaResumoId > 0 && isset($turmasInscritosMap[$turmaResumoId])) {
        $resumoTurmas['inscritos'] += (int) $turmasInscritosMap[$turmaResumoId];
    }
}

$renderAcoes = function (array $turma) use ($canManageTurmas, $returnToTurmasEncoded, $cursoAtual) {
    $turmaId = (int) $turma['id'];
    $cursoId = !empty($cursoAtual['id']) ? (int) $cursoAtual['id'] : 0;

    $html = '<div class="split-actions area-curso-actions">';

    if ($canManageTurmas) {
        $html .= '<a href="/admin/turmas/editar?turma_id=' . $turmaId . '&curso_id=' . $cursoId . '&return_to=' . $returnToTurmasEncoded . '">Editar</a>';
    } else {
        $html .= '<span class="muted">Sem ações disponíveis</span>';
    }

    $html .= '</div>';

    return $html;
};
?>

<section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'turmas' ? ' is-active' : ''; ?>" data-area-curso-tab="turmas" id="area-curso-turmas">
    <div class="area-curso-section-heading">
        <div>
            <?php echo areaCursoHeadingWithTooltip('Turmas', 'Gerencie as turmas vinculadas a este curso, seus períodos, vagas, modalidade e situação.'); ?>
        </div>
        <?php if ($canManageTurmas): ?>
            <div class="area-curso-actions">
                <a class="button-link button-link--primary" href="/admin/turmas/criar?curso_id=<?php echo !empty($cursoAtual['id']) ? (int) $cursoAtual['id'] : 0; ?>&return_to=<?php echo $returnToTurmasEncoded; ?>">Nova turma</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="area-curso-summary-grid admin-mt-12">
        <div class="area-curso-summary-card">
            <small>Total de turmas</small>
            <strong><?php echo (int) $resumoTurmas['total']; ?></strong>
            <span>cadastradas para este curso</span>
        </div>
        <div class="area-curso-summary-card">
            <small>Turmas abertas</small>
            <strong><?php echo (int) $resumoTurmas['abertas']; ?></strong>
            <span>disponíveis para operação</span>
        </div>
        <div class="area-curso-summary-card">
            <small>Turmas planejadas</small>
            <strong><?php echo (int) $resumoTurmas['planejadas']; ?></strong>
            <span>ainda em preparação</span>
        </div>
        <div class="area-curso-summary-card">
            <small>Turmas encerradas</small>
            <strong><?php echo (int) $resumoTurmas['encerradas']; ?></strong>
            <span>finalizadas no cadastro</span>
        </div>
        <div class="area-curso-summary-card">
            <small>Vagas totais</small>
            <strong><?php echo (int) $resumoTurmas['vagas']; ?></strong>
            <span>soma das turmas cadastradas</span>
        </div>
        <div class="area-curso-summary-card">
            <small>Inscritos</small>
            <strong><?php echo (int) $resumoTurmas['inscritos']; ?></strong>
            <span>participantes ativos nas turmas</span>
        </div>
    </div>

    <?php if (empty($turmas)): ?>
        <div class="area-curso-empty-state admin-mt-16">
            <p class="muted">Nenhuma turma cadastrada para este curso.</p>
            <?php if ($canManageTurmas): ?>
                <a class="button-link button-link--primary" href="/admin/turmas/criar?curso_id=<?php echo !empty($cursoAtual['id']) ? (int) $cursoAtual['id'] : 0; ?>&return_to=<?php echo $returnToTurmasEncoded; ?>">Criar primeira turma</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="area-curso-table-card admin-mt-16">
            <div class="table-wrap">
                <table class="admin-table admin-table--area-cursos">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Código</th>
                            <th>Status</th>
                            <th>Início</th>
                            <th>Fim</th>
                            <th>Vagas</th>
                            <th>Inscritos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($turmas as $turma): ?>
                            <?php
                            $turmaId = (int) $turma['id'];
                            $turmaInscritos = isset($turmasInscritosMap[$turmaId]) ? (int) $turmasInscritosMap[$turmaId] : 0;
                            ?>
                            <tr>
                                <td><span class="muted">#<?php echo $turmaId; ?></span></td>
                                <td><?php echo Helpers::e($turma['nome']); ?></td>
                                <td><?php echo Helpers::e($turma['codigo']); ?></td>
                                <td><span class="<?php echo Helpers::e($statusBadgeClass($turma['status'])); ?>"><?php echo Helpers::e($statusLabel($turma['status'])); ?></span></td>
                                <td><?php echo Helpers::e($formatDate($turma['data_inicio'] ?? '')); ?></td>
                                <td><?php echo Helpers::e($formatDate($turma['data_fim'] ?? '')); ?></td>
                                <td><?php echo isset($turma['vagas']) && $turma['vagas'] !== null ? (int) $turma['vagas'] : '-'; ?></td>
                                <td><?php echo (int) $turmaInscritos; ?></td>
                                <td><?php echo $renderAcoes($turma); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>
