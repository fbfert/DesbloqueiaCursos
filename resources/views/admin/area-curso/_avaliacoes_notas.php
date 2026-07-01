<?php use App\Core\Helpers; ?>

<?php
$cursoAtual = isset($curso) && is_array($curso) ? $curso : array();
$turmaAtual = isset($turma) && is_array($turma) ? $turma : array();
$avaliacoesNotas = isset($avaliacoes_notas) && is_array($avaliacoes_notas) ? $avaliacoes_notas : array();
$avaliacoesNotasFiltrosAtual = isset($avaliacoes_notas_filtros) && is_array($avaliacoes_notas_filtros) ? $avaliacoes_notas_filtros : array();
$avaliacoes = isset($avaliacoesNotas['avaliacoes']) && is_array($avaliacoesNotas['avaliacoes']) ? $avaliacoesNotas['avaliacoes'] : array();
$inscricoes = isset($avaliacoesNotas['inscricoes']) && is_array($avaliacoesNotas['inscricoes']) ? $avaliacoesNotas['inscricoes'] : array();
$atividades = isset($avaliacoesNotas['atividades']) && is_array($avaliacoesNotas['atividades']) ? $avaliacoesNotas['atividades'] : array();
$resumo = isset($avaliacoesNotas['resumo']) && is_array($avaliacoesNotas['resumo']) ? $avaliacoesNotas['resumo'] : array();
$formState = isset($avaliacoes_notas_form) && is_array($avaliacoes_notas_form) ? $avaliacoes_notas_form : array();
$csrfFieldAtual = isset($csrfField) ? (string) $csrfField : '';
$cursoIdAtual = !empty($cursoAtual['id']) ? (int) $cursoAtual['id'] : 0;
$turmaIdAtual = !empty($turmaAtual['id']) ? (int) $turmaAtual['id'] : 0;

$buildAreaCursoUrl = function (array $params = array()) use ($cursoIdAtual, $turmaIdAtual) {
    $query = array(
        'curso_id' => $cursoIdAtual,
        'aba' => 'avaliacoes-notas',
    );

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

$buildCertificadoUrl = function ($certificadoId) {
    return '/admin/certificados/show?certificado_id=' . (int) $certificadoId;
};

$buildAtividadeUrl = function ($atividadeId) use ($cursoIdAtual, $turmaIdAtual) {
    $query = array(
        'curso_id' => $cursoIdAtual,
        'aba' => 'atividades',
        'atividade_id' => (int) $atividadeId,
    );

    if ($turmaIdAtual > 0) {
        $query['turma_id'] = $turmaIdAtual;
    }

    return '/admin/area-curso?' . http_build_query($query);
};

$notaStatusBadgeClass = function ($status) {
    switch ((string) $status) {
        case 'aprovada':
        case 'certificado_emitido':
            return 'badge badge--success';
        case 'reprovada':
        case 'nao_apto':
            return 'badge badge--danger';
        case 'sem_nota':
        case 'pendente':
            return 'badge badge--warn';
        default:
            return 'badge badge--soft';
    }
};

$notaStatusLabel = function ($status) {
    $mapa = array(
        'aprovada' => 'Aprovada',
        'reprovada' => 'Reprovada',
        'pendente' => 'Pendente',
        'sem_nota' => 'Sem nota',
        'nao_apto' => 'Não apto',
        'certificado_emitido' => 'Certificado emitido',
    );

    $status = (string) $status;
    return isset($mapa[$status]) ? $mapa[$status] : ucfirst(str_replace('_', ' ', $status));
};

$inscricaoOptions = array();
foreach ($inscricoes as $inscricao) {
    $inscricaoOptions[] = array(
        'id' => (int) ($inscricao['id'] ?? 0),
        'label' => trim((string) ($inscricao['participante_nome'] ?? $inscricao['aluno_nome'] ?? 'Participante')),
        'nota_final' => isset($inscricao['nota_final']) && $inscricao['nota_final'] !== null ? (float) $inscricao['nota_final'] : null,
    );
}

$avaliacaoOptions = array();
foreach ($avaliacoes as $avaliacao) {
    $avaliacaoOptions[] = array(
        'id' => (int) ($avaliacao['id'] ?? 0),
        'label' => trim((string) ($avaliacao['titulo'] ?? 'Avaliação')),
    );
}

$inscricaoSelecionadaId = !empty($formState['inscricao_id']) ? (int) $formState['inscricao_id'] : (!empty($inscricaoOptions[0]['id']) ? (int) $inscricaoOptions[0]['id'] : 0);
$avaliacaoSelecionadaId = !empty($formState['avaliacao_id']) ? (int) $formState['avaliacao_id'] : (!empty($avaliacaoOptions[0]['id']) ? (int) $avaliacaoOptions[0]['id'] : 0);
$notaSelecionada = isset($formState['nota']) ? (string) $formState['nota'] : '';
$percentualSelecionado = isset($formState['percentual']) ? (string) $formState['percentual'] : '';
$statusSelecionado = isset($formState['status']) && $formState['status'] !== '' ? (string) $formState['status'] : 'corrigida';
$observacaoSelecionada = isset($formState['observacao']) ? (string) $formState['observacao'] : '';

$avaliacoesCount = count($avaliacoes);
$inscricoesCount = count($inscricoes);
$totalComNota = (int) ($resumo['com_nota'] ?? 0);
$totalSemNota = (int) ($resumo['sem_nota'] ?? 0);
$totalAptos = (int) ($resumo['aptos'] ?? 0);
$totalNaoAptos = (int) ($resumo['nao_aptos'] ?? 0);
$totalCertificados = (int) ($resumo['certificados_emitidos'] ?? 0);
$progressoMedio = isset($resumo['progresso_medio']) ? (float) $resumo['progresso_medio'] : 0;
$presencaMedia = isset($resumo['presenca_media']) ? (float) $resumo['presenca_media'] : 0;
$totalPerguntas = 0;
foreach ($avaliacoes as $avaliacao) {
    $totalPerguntas += !empty($avaliacao['perguntas']) && is_array($avaliacao['perguntas']) ? count($avaliacao['perguntas']) : 0;
}

$notaStatusOptions = array(
    '' => 'Todos',
    'com_nota' => 'Com nota',
    'sem_nota' => 'Sem nota',
    'aprovada' => 'Aprovada',
    'reprovada' => 'Reprovada',
    'pendente' => 'Pendente',
    'nao_apto' => 'Não apto',
);

$certificadoOptions = array(
    '' => 'Todos',
    'com_certificado' => 'Com certificado',
    'sem_certificado' => 'Sem certificado',
);

$statusInscricaoOptions = array(
    '' => 'Todas',
    'ativa' => 'Ativa',
    'em_andamento' => 'Em andamento',
    'concluida' => 'Concluída',
    'concluida_sem_certificado' => 'Concluída sem certificado',
    'certificado_emitido' => 'Certificado emitido',
);

$avaliacoesFiltroQuery = array(
    'avaliacoes_busca' => $avaliacoesNotasFiltrosAtual['busca'] ?? '',
    'avaliacoes_status_inscricao' => $avaliacoesNotasFiltrosAtual['status_inscricao'] ?? '',
    'avaliacoes_nota_status' => $avaliacoesNotasFiltrosAtual['nota_status'] ?? '',
    'avaliacoes_certificado' => $avaliacoesNotasFiltrosAtual['certificado'] ?? '',
    'avaliacoes_avaliacao_id' => $avaliacoesNotasFiltrosAtual['avaliacao_id'] ?? 0,
);
?>

<section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'avaliacoes-notas' ? ' is-active' : ''; ?>" data-area-curso-tab="avaliacoes-notas" id="area-curso-avaliacoes-notas">
    <div class="area-curso-avaliacoes-notas">
        <div class="area-curso-section-heading">
            <div>
                <?php echo areaCursoHeadingWithTooltip('Avaliações e notas', 'Centralize notas finais, progresso, presença, certificados e avaliações do contexto selecionado.'); ?>
            </div>
                <div class="area-curso-actions">
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'participantes'))); ?>">Ver participantes</a>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'presenca'))); ?>">Ver presença</a>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'relatorios'))); ?>">Ver relatórios</a>
                    <a class="button-link" href="<?php echo Helpers::e($buildAreaCursoUrl(array_merge($avaliacoesFiltroQuery, array('export' => 'csv')))); ?>">Exportar CSV</a>
                </div>
            </div>

        <div class="area-curso-summary-grid admin-mt-12">
            <div class="area-curso-summary-card">
                <small>Participantes</small>
                <strong><?php echo (int) $inscricoesCount; ?></strong>
                <span>inscrições válidas no contexto</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Com nota</small>
                <strong><?php echo (int) $totalComNota; ?></strong>
                <span>avaliações já consolidadas</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Sem nota</small>
                <strong><?php echo (int) $totalSemNota; ?></strong>
                <span>participantes aguardando lançamento</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Aptos</small>
                <strong><?php echo (int) $totalAptos; ?></strong>
                <span>aptos para certificado</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Não aptos</small>
                <strong><?php echo (int) $totalNaoAptos; ?></strong>
                <span>pendentes de requisitos</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Certificados emitidos</small>
                <strong><?php echo (int) $totalCertificados; ?></strong>
                <span>já emitidos neste contexto</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Progresso médio</small>
                <strong><?php echo number_format($progressoMedio, 2, ',', '.'); ?>%</strong>
                <span>média das inscrições visíveis</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Presença média</small>
                <strong><?php echo number_format($presencaMedia, 2, ',', '.'); ?>%</strong>
                <span>média registrada no contexto</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Avaliações</small>
                <strong><?php echo (int) $avaliacoesCount; ?></strong>
                <span>itens cadastrados</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Perguntas</small>
                <strong><?php echo (int) $totalPerguntas; ?></strong>
                <span>estrutura avaliativa total</span>
            </div>
        </div>

        <div class="area-curso-avaliacoes-notas__grid admin-mt-16">
            <section class="status-card area-curso-table-card area-curso-avaliacoes-notas__quick-form">
                <div class="panel-header">
                    <div>
                        <h2>Lançar ou atualizar nota</h2>
                    </div>
                </div>

                <?php if (empty($inscricaoOptions) || empty($avaliacaoOptions)): ?>
                    <p class="muted">Cadastre ao menos uma avaliação e uma inscrição para usar o lançamento de notas nesta aba.</p>
                <?php else: ?>
                    <form method="post" action="/admin/area-curso/avaliacoes-notas" class="form-grid admin-area-curso__form area-curso-avaliacoes-notas__form">
                        <?php echo $csrfFieldAtual; ?>
                        <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                        <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                        <input type="hidden" name="aba" value="avaliacoes-notas">
                        <label>Inscrição
                            <select name="inscricao_id" required>
                                <?php foreach ($inscricaoOptions as $opcao): ?>
                                    <option value="<?php echo (int) $opcao['id']; ?>" <?php echo (int) $inscricaoSelecionadaId === (int) $opcao['id'] ? 'selected' : ''; ?>>
                                        <?php echo Helpers::e('#' . $opcao['id'] . ' · ' . $opcao['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Avaliação
                            <select name="avaliacao_id" required>
                                <?php foreach ($avaliacaoOptions as $opcao): ?>
                                    <option value="<?php echo (int) $opcao['id']; ?>" <?php echo (int) $avaliacaoSelecionadaId === (int) $opcao['id'] ? 'selected' : ''; ?>>
                                        <?php echo Helpers::e('#' . $opcao['id'] . ' · ' . $opcao['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Nota
                            <input type="number" step="0.01" name="nota" value="<?php echo Helpers::e($notaSelecionada !== '' ? $notaSelecionada : '0'); ?>" required>
                        </label>
                        <label>Percentual
                            <input type="number" step="0.01" name="percentual" value="<?php echo Helpers::e($percentualSelecionado !== '' ? $percentualSelecionado : '0'); ?>" required>
                        </label>
                        <label>Status
                            <select name="status">
                                <option value="corrigida" <?php echo $statusSelecionado === 'corrigida' ? 'selected' : ''; ?>>Corrigida</option>
                                <option value="aprovada" <?php echo $statusSelecionado === 'aprovada' ? 'selected' : ''; ?>>Aprovada</option>
                                <option value="reprovada" <?php echo $statusSelecionado === 'reprovada' ? 'selected' : ''; ?>>Reprovada</option>
                            </select>
                        </label>
                        <label class="full">Observação
                            <textarea name="observacao" rows="3"><?php echo Helpers::e($observacaoSelecionada); ?></textarea>
                        </label>
                        <p class="muted full">A nota registrada nesta aba atualiza a nota final da inscrição e recalcula a aptidão para certificado.</p>
                        <?php
                        $cancel_url = $buildAreaCursoUrl();
                        $show_save_as_copy = false;
                        require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                        ?>
                    </form>
                <?php endif; ?>
            </section>

            <section class="status-card area-curso-table-card area-curso-avaliacoes-notas__avaliacoes">
                <div class="panel-header">
                    <div>
                        <h2>Avaliações cadastradas</h2>
                    </div>
                    <span class="badge badge--soft"><?php echo (int) $avaliacoesCount; ?> itens</span>
                </div>

                <?php if (empty($avaliacoes)): ?>
                    <p class="muted">Nenhuma avaliação cadastrada para este contexto.</p>
                <?php else: ?>
                    <div class="area-curso-avaliacoes-notas__avaliacoes-grid">
                        <?php foreach ($avaliacoes as $avaliacao): ?>
                            <?php
                            $perguntas = !empty($avaliacao['perguntas']) && is_array($avaliacao['perguntas']) ? count($avaliacao['perguntas']) : 0;
                            $titulo = !empty($avaliacao['titulo']) ? (string) $avaliacao['titulo'] : 'Avaliação';
                            $tipo = !empty($avaliacao['tipo']) ? (string) $avaliacao['tipo'] : 'avaliacao';
                            ?>
                            <article class="area-curso-avaliacoes-notas__avaliacao-card">
                                <div class="area-curso-avaliacoes-notas__avaliacao-head">
                                    <strong><?php echo Helpers::e($titulo); ?></strong>
                                    <span class="<?php echo (int) ($avaliacao['visivel'] ?? 0) ? 'badge badge--success' : 'badge badge--warn'; ?>"><?php echo (int) ($avaliacao['visivel'] ?? 0) ? 'Visível' : 'Oculta'; ?></span>
                                </div>
                                <div class="area-curso-avaliacoes-notas__avaliacao-meta">
                                    <span class="badge"><?php echo Helpers::e(ucfirst(str_replace('_', ' ', $tipo))); ?></span>
                                    <span class="badge"><?php echo (int) $perguntas; ?> perguntas</span>
                                    <span class="badge"><?php echo !empty($avaliacao['obrigatoria']) ? 'Obrigatória' : 'Opcional'; ?></span>
                                </div>
                                <p class="muted">Ordem <?php echo (int) ($avaliacao['ordem'] ?? 0); ?> · mínimo <?php echo Helpers::e(number_format((float) ($avaliacao['percentual_minimo'] ?? 0), 2, ',', '.')); ?>%</p>
                                <?php if (isset($avaliacao['nota_minima']) && $avaliacao['nota_minima'] !== null): ?>
                                    <p class="muted">Nota mínima <?php echo Helpers::e(number_format((float) $avaliacao['nota_minima'], 2, ',', '.')); ?></p>
                                <?php endif; ?>
                                <div class="area-curso-avaliacoes-notas__avaliacao-actions">
                                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('nota_avaliacao_id' => (int) $avaliacao['id']))); ?>">Usar na nota</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <section class="status-card area-curso-table-card admin-mt-16">
            <div class="panel-header">
                <div>
                    <h2>Participantes com situação acadêmica</h2>
                </div>
                <span class="badge badge--soft"><?php echo (int) $inscricoesCount; ?> registros</span>
            </div>

            <div class="area-curso-avaliacoes-notas__filters admin-mt-12">
                <form method="get" action="/admin/area-curso" class="form-grid admin-area-curso__form area-curso-avaliacoes-notas__filter-form">
                    <input type="hidden" name="curso_id" value="<?php echo (int) $cursoIdAtual; ?>">
                    <?php if ($turmaIdAtual > 0): ?>
                        <input type="hidden" name="turma_id" value="<?php echo (int) $turmaIdAtual; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="aba" value="avaliacoes-notas">
                    <label class="full">Buscar participante
                        <input type="text" name="avaliacoes_busca" value="<?php echo Helpers::e($avaliacoesNotasFiltrosAtual['busca'] ?? ''); ?>" placeholder="Nome, CPF, turma ou e-mail">
                    </label>
                    <label>Status da inscrição
                        <select name="avaliacoes_status_inscricao">
                            <?php foreach ($statusInscricaoOptions as $valor => $rotulo): ?>
                                <option value="<?php echo Helpers::e($valor); ?>" <?php echo (($avaliacoesNotasFiltrosAtual['status_inscricao'] ?? '') === $valor) ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Situação da nota
                        <select name="avaliacoes_nota_status">
                            <?php foreach ($notaStatusOptions as $valor => $rotulo): ?>
                                <option value="<?php echo Helpers::e($valor); ?>" <?php echo (($avaliacoesNotasFiltrosAtual['nota_status'] ?? '') === $valor) ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Certificado
                        <select name="avaliacoes_certificado">
                            <?php foreach ($certificadoOptions as $valor => $rotulo): ?>
                                <option value="<?php echo Helpers::e($valor); ?>" <?php echo (($avaliacoesNotasFiltrosAtual['certificado'] ?? '') === $valor) ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Avaliação
                        <select name="avaliacoes_avaliacao_id">
                            <option value="0">Todas</option>
                            <?php foreach ($avaliacaoOptions as $opcao): ?>
                                <option value="<?php echo (int) $opcao['id']; ?>" <?php echo (int) ($avaliacoesNotasFiltrosAtual['avaliacao_id'] ?? 0) === (int) $opcao['id'] ? 'selected' : ''; ?>>
                                    <?php echo Helpers::e($opcao['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="cta-group full area-curso-avaliacoes-notas__filter-actions">
                        <button type="submit" class="button-link button-link--primary">Filtrar</button>
                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl()); ?>">Limpar filtros</a>
                    </div>
                </form>
            </div>

            <?php if (empty($inscricoes)): ?>
                <p class="muted admin-mt-12">Nenhuma inscrição encontrada para este contexto.</p>
            <?php else: ?>
                <div class="table-wrap admin-mt-12 area-curso-avaliacoes-notas__table-wrap">
                    <table class="admin-table admin-table--area-avaliacoes-notas">
                        <thead>
                            <tr>
                                <th>Participante</th>
                                <th>Turma</th>
                                <th>Progresso</th>
                                <th>Presença</th>
                                <th>Nota final</th>
                                <th>Apto</th>
                                <th>Certificado</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inscricoes as $inscricao): ?>
                                <?php
                                $inscricaoId = (int) ($inscricao['id'] ?? 0);
                                $notaFinal = isset($inscricao['nota_final']) && $inscricao['nota_final'] !== null ? (float) $inscricao['nota_final'] : null;
                                $statusInscricao = (string) ($inscricao['status'] ?? $inscricao['inscricao_status'] ?? '');
                                $aptoCertificado = !empty($inscricao['apto_certificado']);
                                $certificadoId = !empty($inscricao['certificado_id']) ? (int) $inscricao['certificado_id'] : 0;
                                $certificadoEmitido = $certificadoId > 0 || (!empty($inscricao['certificado_status']) && (string) $inscricao['certificado_status'] === 'emitido');
                                $statusNotaLinha = 'pendente';
                                if ($notaFinal !== null) {
                                    $statusNotaLinha = $aptoCertificado ? 'aprovada' : 'reprovada';
                                } elseif (in_array($statusInscricao, array('ativa', 'em_andamento'), true)) {
                                    $statusNotaLinha = 'sem_nota';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo Helpers::e((string) ($inscricao['participante_nome'] ?? $inscricao['aluno_nome'] ?? 'Participante')); ?></strong><br>
                                        <small><?php echo Helpers::e((string) ($inscricao['participante_cpf'] ?? $inscricao['aluno_cpf'] ?? '-')); ?></small>
                                    </td>
                                    <td><?php echo Helpers::e((string) ($inscricao['turma_nome'] ?? 'Curso inteiro')); ?></td>
                                    <td><?php echo Helpers::e(number_format((float) ($inscricao['percentual_progresso'] ?? 0), 2, ',', '.')); ?>%</td>
                                    <td><?php echo Helpers::e(number_format((float) ($inscricao['presenca_percentual'] ?? 0), 2, ',', '.')); ?>%</td>
                                    <td><?php echo $notaFinal !== null ? Helpers::e(number_format($notaFinal, 2, ',', '.')) : '—'; ?></td>
                                    <td><span class="<?php echo $aptoCertificado ? 'badge badge--success' : 'badge badge--warn'; ?>"><?php echo $aptoCertificado ? 'Apto' : 'Não apto'; ?></span></td>
                                    <td>
                                        <?php if ($certificadoEmitido): ?>
                                            <span class="badge badge--soft">Emitido</span>
                                            <?php if ($certificadoId > 0): ?>
                                                <br><a href="<?php echo Helpers::e($buildCertificadoUrl($certificadoId)); ?>">Ver certificado</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge">Não emitido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="split-actions area-curso-actions area-curso-avaliacoes-notas__row-actions">
                                            <?php if (!empty($avaliacaoOptions)): ?>
                                                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array_merge($avaliacoesFiltroQuery, array('nota_inscricao_id' => $inscricaoId, 'nota_avaliacao_id' => $avaliacaoSelecionadaId > 0 ? $avaliacaoSelecionadaId : (!empty($avaliacaoOptions[0]['id']) ? (int) $avaliacaoOptions[0]['id'] : 0), 'nota' => $notaFinal !== null ? number_format($notaFinal, 2, '.', '') : '', 'percentual' => '', 'status' => $statusNotaLinha)))); ?>">Lançar nota</a>
                                            <?php endif; ?>
                                            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array_merge($avaliacoesFiltroQuery, array('aba' => 'participantes')))); ?>">Ver participante</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="status-card area-curso-table-card admin-mt-16">
            <div class="panel-header">
                <div>
                    <h2>Atividades avaliativas e entregas</h2>
                </div>
                <span class="badge badge--soft"><?php echo (int) count($atividades); ?> atividades</span>
            </div>

            <?php if (empty($atividades)): ?>
                <p class="muted">Nenhuma atividade publicada encontrada neste contexto.</p>
            <?php else: ?>
                <div class="table-wrap admin-mt-12">
                    <table class="admin-table admin-table--area-avaliacoes-notas">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Módulo</th>
                                <th>Aula</th>
                                <th>Prazo</th>
                                <th>Entregas</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($atividades as $atividade): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo Helpers::e((string) ($atividade['titulo'] ?? 'Atividade')); ?></strong><br>
                                        <small><?php echo Helpers::e((string) ($atividade['status'] ?? '')); ?></small>
                                    </td>
                                    <td><?php echo Helpers::e((string) ($atividade['modulo_titulo'] ?? '-')); ?></td>
                                    <td><?php echo Helpers::e((string) ($atividade['aula_titulo'] ?? '-')); ?></td>
                                    <td><?php echo !empty($atividade['prazo']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $atividade['prazo']))) : 'Sem prazo'; ?></td>
                                    <td><?php echo (int) ($atividade['total_entregas'] ?? 0); ?></td>
                                    <td>
                                        <div class="split-actions area-curso-actions area-curso-avaliacoes-notas__row-actions">
                                            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAtividadeUrl((int) ($atividade['id'] ?? 0))); ?>">Ver entregas</a>
                                            <?php if (!empty($atividade['aula_id'])): ?>
                                                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'atividades', 'atividade_aula_id' => (int) $atividade['aula_id'], 'atividade_id' => (int) $atividade['id']))); ?>">Filtrar aula</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
