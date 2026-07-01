<?php use App\Core\Helpers; ?>

<?php
$cursoAtual = isset($curso) && is_array($curso) ? $curso : array();
$turmaAtual = isset($turma) && is_array($turma) ? $turma : array();
$participantesAtual = isset($participantes) && is_array($participantes) ? $participantes : array();
$presencasAtual = isset($presencas) && is_array($presencas) ? $presencas : array();
$presencasFiltrosAtual = isset($presencas_filtros) && is_array($presencas_filtros) ? $presencas_filtros : array();
$modulos = isset($modulos) && is_array($modulos) ? $modulos : array();
$csrfFieldAtual = isset($csrfField) ? (string) $csrfField : '';
$cursoIdAtual = !empty($cursoAtual['id']) ? (int) $cursoAtual['id'] : 0;
$turmaIdAtual = !empty($turmaAtual['id']) ? (int) $turmaAtual['id'] : 0;

$buildAreaCursoUrl = function (array $params = array()) use ($cursoIdAtual, $turmaIdAtual) {
    $query = array('curso_id' => $cursoIdAtual);
    if ($turmaIdAtual > 0) {
        $query['turma_id'] = $turmaIdAtual;
    }

    foreach ($params as $chave => $valor) {
        if ($valor === null || $valor === '') {
            continue;
        }
        $query[$chave] = $valor;
    }

    return '/admin/area-curso?' . http_build_query($query);
};

$buildPresencaUrl = function (array $params = array()) use ($cursoIdAtual, $turmaIdAtual) {
    $query = array('curso_id' => $cursoIdAtual, 'aba' => 'presenca');
    if ($turmaIdAtual > 0) {
        $query['turma_id'] = $turmaIdAtual;
    }

    foreach ($params as $chave => $valor) {
        if ($valor === null || $valor === '') {
            continue;
        }
        $query[$chave] = $valor;
    }

    return '/admin/area-curso?' . http_build_query($query);
};

$aulasPresenca = array();
foreach ($modulos as $modulo) {
    if (empty($modulo['aulas']) || !is_array($modulo['aulas'])) {
        continue;
    }
    foreach ($modulo['aulas'] as $aula) {
        $aulasPresenca[] = $aula;
    }
}

$statusOptions = array(
    '' => 'Todos',
    'presente' => 'Presente',
    'ausente' => 'Ausente',
    'justificada' => 'Justificada',
);

$presencaBadgeClass = function ($status) {
    switch ((string) $status) {
        case 'presente':
            return 'badge badge--success';
        case 'ausente':
            return 'badge badge--danger';
        case 'justificada':
            return 'badge badge--warn';
        default:
            return 'badge';
    }
};

$presencaLabel = function ($status) {
    $mapa = array(
        'presente' => 'Presente',
        'ausente' => 'Ausente',
        'justificada' => 'Justificada',
    );

    $status = (string) $status;
    return isset($mapa[$status]) ? $mapa[$status] : ucfirst(str_replace('_', ' ', $status));
};

$totalPresencas = count($presencasAtual);
$totalPresentes = 0;
$totalAusentes = 0;
$totalJustificadas = 0;
$totalAlunos = 0;
$alunosMap = array();
foreach ($participantesAtual as $participante) {
    if (!empty($participante['inscricao_id'])) {
        $alunosMap[(int) $participante['inscricao_id']] = true;
    }
}
foreach ($presencasAtual as $presenca) {
    $status = (string) ($presenca['status'] ?? '');
    if ($status === 'presente') {
        $totalPresentes++;
    } elseif ($status === 'ausente') {
        $totalAusentes++;
    } elseif ($status === 'justificada') {
        $totalJustificadas++;
    }
}
$totalAlunos = count($alunosMap);
?>

<section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'presenca' ? ' is-active' : ''; ?>" data-area-curso-tab="presenca" id="area-curso-presenca">
    <div class="area-curso-presenca">
        <div class="area-curso-section-heading">
            <div>
                <?php echo areaCursoHeadingWithTooltip('Presença', 'Registre presença, ausência e justificativas por data e aula para o curso ou turma selecionados.'); ?>
                <p class="muted">Registre presença, ausência e justificativas por data e aula para o curso ou turma selecionados.</p>
            </div>
            <div class="area-curso-actions">
                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'participantes'))); ?>">Ver participantes</a>
                <a class="button-link" href="<?php echo Helpers::e($buildPresencaUrl(array('export' => 'csv'))); ?>">Exportar CSV</a>
            </div>
        </div>

        <div class="area-curso-summary-grid admin-mt-12">
            <div class="area-curso-summary-card">
                <small>Total de registros</small>
                <strong><?php echo (int) $totalPresencas; ?></strong>
                <span>lançamentos de presença</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Presentes</small>
                <strong><?php echo (int) $totalPresentes; ?></strong>
                <span>marcações confirmadas</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Ausentes</small>
                <strong><?php echo (int) $totalAusentes; ?></strong>
                <span>faltas registradas</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Justificadas</small>
                <strong><?php echo (int) $totalJustificadas; ?></strong>
                <span>ocorrências justificadas</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Alunos na lista</small>
                <strong><?php echo (int) $totalAlunos; ?></strong>
                <span>participantes para lançamento</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Aulas disponíveis</small>
                <strong><?php echo (int) count($aulasPresenca); ?></strong>
                <span>para vincular ao registro</span>
            </div>
        </div>

        <div class="area-curso-presenca__filters admin-mt-16">
            <form method="get" action="/admin/area-curso" class="form-grid admin-area-curso__form area-curso-presenca__filter-form">
                <input type="hidden" name="curso_id" value="<?php echo (int) $cursoIdAtual; ?>">
                <?php if ($turmaIdAtual > 0): ?>
                    <input type="hidden" name="turma_id" value="<?php echo (int) $turmaIdAtual; ?>">
                <?php endif; ?>
                <input type="hidden" name="aba" value="presenca">
                <label class="full">Buscar registro
                    <input type="text" name="presenca_busca" value="<?php echo Helpers::e($presencasFiltrosAtual['busca'] ?? ''); ?>" placeholder="Nome, CPF, e-mail ou aula">
                </label>
                <label>Data
                    <input type="date" name="presenca_data" value="<?php echo Helpers::e($presencasFiltrosAtual['data_presenca'] ?? ''); ?>">
                </label>
                <label>Aula
                    <select name="presenca_aula_id">
                        <option value="">Todas</option>
                        <?php foreach ($aulasPresenca as $aula): ?>
                            <option value="<?php echo (int) $aula['id']; ?>" <?php echo !empty($presencasFiltrosAtual['aula_id']) && (int) $presencasFiltrosAtual['aula_id'] === (int) $aula['id'] ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($aula['titulo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="presenca_status">
                        <?php foreach ($statusOptions as $valor => $rotulo): ?>
                            <option value="<?php echo Helpers::e($valor); ?>" <?php echo (($presencasFiltrosAtual['status'] ?? '') === $valor) ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="cta-group full area-curso-presenca__filter-actions">
                    <button type="submit" class="button-link button-link--primary">Filtrar</button>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildPresencaUrl()); ?>">Limpar filtros</a>
                </div>
            </form>
        </div>

        <div class="area-curso-presenca__batch admin-mt-16">
            <details class="area-curso-collapsible-form" open>
                <summary>Registrar presença em lote</summary>
                <div class="area-curso-collapsible-form__body">
                    <form method="post" action="/admin/area-curso/presenca" class="form-grid admin-area-curso__form area-curso-presenca__batch-form">
                        <?php echo $csrfFieldAtual; ?>
                        <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                        <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                        <input type="hidden" name="aba" value="presenca">
                        <label>Data
                            <input type="date" name="data_presenca" value="<?php echo date('Y-m-d'); ?>" required>
                        </label>
                        <label>Aula
                            <select name="aula_id">
                                <option value="">Sem aula</option>
                                <?php foreach ($aulasPresenca as $aula): ?>
                                    <option value="<?php echo (int) $aula['id']; ?>"><?php echo Helpers::e($aula['titulo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Status padrão
                            <select name="status_padrao">
                                <option value="presente">Presente</option>
                                <option value="ausente">Ausente</option>
                                <option value="justificada">Justificada</option>
                            </select>
                        </label>
                        <label class="full">Observação
                            <textarea name="observacao" rows="2"></textarea>
                        </label>
                        <div class="area-curso-presenca__participants-table table-wrap full">
                            <table class="admin-table admin-table--area-presenca">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="area-curso-presenca-selecionar-todos"></th>
                                        <th>Participante</th>
                                        <th>Inscrição</th>
                                        <th>Status</th>
                                        <th>Presença</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($participantesAtual)): ?>
                                        <tr>
                                            <td colspan="5">
                                                <div class="area-curso-empty-state">
                                                    <p class="muted">Nenhum participante encontrado para lançamento de presença.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($participantesAtual as $participante): ?>
                                        <?php $inscricaoId = (int) ($participante['inscricao_id'] ?? 0); ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="inscricao_ids[]" value="<?php echo $inscricaoId; ?>" class="area-curso-presenca-checkbox">
                                            </td>
                                            <td>
                                                <strong><?php echo Helpers::e($participante['nome'] ?? $participante['usuario_nome'] ?? 'Participante'); ?></strong><br>
                                                <small><?php echo Helpers::e($participante['cpf'] ?? $participante['usuario_cpf'] ?? '-'); ?></small>
                                            </td>
                                            <td><?php echo Helpers::e($participante['inscricao_status'] ?? '-'); ?></td>
                                            <td>
                                                <?php $statusLinha = !empty($participante['inscricao_status']) ? $participante['inscricao_status'] : ''; ?>
                                                <span class="<?php echo Helpers::e($presencaBadgeClass($statusLinha)); ?>"><?php echo Helpers::e($presencaLabel($statusLinha)); ?></span>
                                            </td>
                                            <td>
                                                <select name="status_por_inscricao[<?php echo $inscricaoId; ?>]">
                                                    <option value="presente">Presente</option>
                                                    <option value="ausente">Ausente</option>
                                                    <option value="justificada">Justificada</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php
                        $cancel_url = $buildPresencaUrl();
                        $show_save_as_copy = false;
                        require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                        ?>
                    </form>
                </div>
            </details>
        </div>

        <div class="area-curso-presenca__history admin-mt-16">
            <div class="panel-header">
                <div>
                    <h2>Histórico de presença</h2>
                </div>
                <details class="area-curso-collapsible-form area-curso-collapsible-form--inline">
                    <summary>Excluir em lote</summary>
                    <div class="area-curso-collapsible-form__body">
                        <form method="post" action="/admin/area-curso/presenca/excluir" class="form-grid admin-area-curso__delete-form" id="area-curso-presenca-excluir-lote">
                            <?php echo $csrfFieldAtual; ?>
                            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                            <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                            <input type="hidden" name="aba" value="presenca">
                            <label class="full">Justificativa
                                <input type="text" name="justificativa" required>
                            </label>
                            <button type="submit" class="button-link button-link--danger">Enviar selecionadas para a lixeira</button>
                        </form>
                    </div>
                </details>
            </div>
            <div class="table-wrap">
                <table class="admin-table admin-table--area-presenca-historico">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="area-curso-presenca-historico-selecionar-todos"></th>
                            <th>Data</th>
                            <th>Participante</th>
                            <th>Curso</th>
                            <th>Turma</th>
                            <th>Aula</th>
                            <th>Status</th>
                            <th>Observação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($presencasAtual)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="area-curso-empty-state">
                                        <p class="muted">Nenhum registro de presença encontrado.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($presencasAtual as $presenca): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="ids[]" value="<?php echo (int) ($presenca['id'] ?? 0); ?>" form="area-curso-presenca-excluir-lote" class="area-curso-presenca-historico-checkbox">
                                </td>
                                <td><?php echo Helpers::e($presenca['data_presenca'] ?? '-'); ?></td>
                                <td>
                                    <strong><?php echo Helpers::e($presenca['participante_nome'] ?? '-'); ?></strong><br>
                                    <small><?php echo Helpers::e($presenca['participante_cpf'] ?? '-'); ?></small><br>
                                    <small><?php echo Helpers::e($presenca['participante_email'] ?? '-'); ?></small>
                                </td>
                                <td><?php echo Helpers::e($presenca['curso_nome'] ?? '-'); ?></td>
                                <td><?php echo Helpers::e($presenca['turma_nome'] ?? '-'); ?></td>
                                <td><?php echo Helpers::e($presenca['aula_titulo'] ?? '-'); ?></td>
                                <td><span class="<?php echo Helpers::e($presencaBadgeClass($presenca['status'] ?? '')); ?>"><?php echo Helpers::e($presencaLabel($presenca['status'] ?? '')); ?></span></td>
                                <td><?php echo Helpers::e($presenca['observacao'] ?? '-'); ?></td>
                                <td>
                                    <details class="area-curso-collapsible-form area-curso-collapsible-form--inline">
                                        <summary>Excluir</summary>
                                        <div class="area-curso-collapsible-form__body">
                                            <form method="post" action="/admin/area-curso/presenca/excluir" class="form-grid admin-area-curso__delete-form">
                                                <?php echo $csrfFieldAtual; ?>
                                                <input type="hidden" name="id" value="<?php echo (int) ($presenca['id'] ?? 0); ?>">
                                                <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                                <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                                <input type="hidden" name="aba" value="presenca">
                                                <input type="text" name="justificativa" placeholder="Justificativa" required>
                                                <button type="submit">Enviar para a lixeira</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var selecionarTodos = document.getElementById('area-curso-presenca-selecionar-todos');
    if (!selecionarTodos) {
        return;
    }

    selecionarTodos.addEventListener('change', function () {
        document.querySelectorAll('.area-curso-presenca-checkbox').forEach(function (checkbox) {
            checkbox.checked = selecionarTodos.checked;
        });
    });
})();
</script>

<script>
(function () {
    var selecionarTodosHistorico = document.getElementById('area-curso-presenca-historico-selecionar-todos');
    if (!selecionarTodosHistorico) {
        return;
    }

    selecionarTodosHistorico.addEventListener('change', function () {
        document.querySelectorAll('.area-curso-presenca-historico-checkbox').forEach(function (checkbox) {
            checkbox.checked = selecionarTodosHistorico.checked;
        });
    });
})();
</script>
