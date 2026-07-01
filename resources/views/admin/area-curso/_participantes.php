<?php use App\Core\Helpers; ?>

<?php
$cursoAtual = isset($curso) && is_array($curso) ? $curso : array();
$turmaAtual = isset($turma) && is_array($turma) ? $turma : array();
$participantesAtual = isset($participantes) && is_array($participantes) ? $participantes : array();
$participantesFiltrosAtual = isset($participantes_filtros) && is_array($participantes_filtros) ? $participantes_filtros : array();
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

$buildPedidoUrl = function ($pedidoId) {
    return '/admin/pedidos/show?pedido_id=' . (int) $pedidoId;
};

$statusBadgeClass = function ($status) {
    $status = (string) $status;
    if ($status === 'ativa' || $status === 'em_andamento') {
        return 'badge badge--success';
    }
    if ($status === 'concluida' || $status === 'certificado_emitido') {
        return 'badge badge--soft';
    }
    if ($status === 'concluida_sem_certificado') {
        return 'badge badge--warn';
    }

    return 'badge';
};

$statusLabel = function ($status) {
    $mapa = array(
        'ativa' => 'Ativa',
        'em_andamento' => 'Em andamento',
        'concluida' => 'Concluída',
        'concluida_sem_certificado' => 'Concluída sem certificado',
        'certificado_emitido' => 'Certificado emitido',
        'pendente' => 'Pendente',
        'cancelada' => 'Cancelada',
        'reprovada' => 'Reprovada',
        'com_pendencia' => 'Com pendência',
    );

    $status = (string) $status;
    return isset($mapa[$status]) ? $mapa[$status] : ucfirst(str_replace('_', ' ', $status));
};

$participantesFiltrados = $participantesAtual;
$totalParticipantes = count($participantesFiltrados);
$totalAtivos = 0;
$totalEmAndamento = 0;
$totalConcluidos = 0;
$totalCertificados = 0;
$totalPendentes = 0;

foreach ($participantesFiltrados as $participante) {
    $statusInscricao = !empty($participante['inscricao_status']) ? (string) $participante['inscricao_status'] : '';

    if ($statusInscricao === 'ativa') {
        $totalAtivos++;
    }
    if ($statusInscricao === 'em_andamento') {
        $totalEmAndamento++;
    }
    if ($statusInscricao === 'concluida' || $statusInscricao === 'concluida_sem_certificado') {
        $totalConcluidos++;
    }
    if ($statusInscricao === 'certificado_emitido') {
        $totalCertificados++;
    }
    if ($statusInscricao === 'pendente' || $statusInscricao === 'com_pendencia') {
        $totalPendentes++;
    }
}
?>

<section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'participantes' ? ' is-active' : ''; ?>" data-area-curso-tab="participantes" id="area-curso-participantes">
    <div class="area-curso-participantes">
        <div class="area-curso-section-heading">
            <div>
                <?php echo areaCursoHeadingWithTooltip('Participantes', 'Acompanhe os alunos vinculados ao curso e à turma selecionados.'); ?>
                <p class="muted">Acompanhe os alunos vinculados ao curso e à turma selecionados.</p>
            </div>
            <div class="area-curso-actions">
                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'turmas'))); ?>">Ver turmas</a>
                <a class="button-link" href="/admin/inscricoes">Abrir inscrições</a>
            </div>
        </div>

        <div class="area-curso-summary-grid admin-mt-12">
            <div class="area-curso-summary-card">
                <small>Total de participantes</small>
                <strong><?php echo (int) $totalParticipantes; ?></strong>
                <span>vinculados ao contexto atual</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Ativos</small>
                <strong><?php echo (int) $totalAtivos; ?></strong>
                <span>com acesso liberado</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Em andamento</small>
                <strong><?php echo (int) $totalEmAndamento; ?></strong>
                <span>seguindo o progresso da turma</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Concluídos</small>
                <strong><?php echo (int) $totalConcluidos; ?></strong>
                <span>finalizaram o conteúdo</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Certificados emitidos</small>
                <strong><?php echo (int) $totalCertificados; ?></strong>
                <span>já tiveram emissão concluída</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Com pendência</small>
                <strong><?php echo (int) $totalPendentes; ?></strong>
                <span>inscrições que exigem atenção</span>
            </div>
        </div>

        <div class="area-curso-participantes__filters admin-mt-16">
            <form method="get" action="/admin/area-curso" class="form-grid admin-area-curso__form area-curso-participantes__filter-form">
                <input type="hidden" name="curso_id" value="<?php echo (int) $cursoIdAtual; ?>">
                <?php if ($turmaIdAtual > 0): ?>
                    <input type="hidden" name="turma_id" value="<?php echo (int) $turmaIdAtual; ?>">
                <?php endif; ?>
                <input type="hidden" name="aba" value="participantes">
                <label class="full">Buscar participante
                    <input type="text" name="participante_busca" value="<?php echo Helpers::e($participantesFiltrosAtual['busca'] ?? ''); ?>" placeholder="Nome, CPF, e-mail ou código do pedido">
                </label>
                <label>Status da inscrição
                    <select name="participante_status">
                        <option value="">Todos</option>
                        <option value="ativa" <?php echo ($participantesFiltrosAtual['status'] ?? '') === 'ativa' ? 'selected' : ''; ?>>Ativa</option>
                        <option value="em_andamento" <?php echo ($participantesFiltrosAtual['status'] ?? '') === 'em_andamento' ? 'selected' : ''; ?>>Em andamento</option>
                        <option value="concluida" <?php echo ($participantesFiltrosAtual['status'] ?? '') === 'concluida' ? 'selected' : ''; ?>>Concluída</option>
                        <option value="concluida_sem_certificado" <?php echo ($participantesFiltrosAtual['status'] ?? '') === 'concluida_sem_certificado' ? 'selected' : ''; ?>>Concluída sem certificado</option>
                        <option value="certificado_emitido" <?php echo ($participantesFiltrosAtual['status'] ?? '') === 'certificado_emitido' ? 'selected' : ''; ?>>Certificado emitido</option>
                    </select>
                </label>
                <div class="cta-group full area-curso-participantes__filter-actions">
                    <button type="submit" class="button-link button-link--primary">Filtrar</button>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'participantes'))); ?>">Limpar filtros</a>
                </div>
            </form>
        </div>

        <div class="table-wrap admin-mt-16 area-curso-table-card">
            <table class="admin-table admin-table--area-participantes">
                <thead>
                    <tr>
                        <th>Participante</th>
                        <th>Curso</th>
                        <th>Turma</th>
                        <th>Inscrição</th>
                        <th>Pedido</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($participantesFiltrados)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="area-curso-empty-state">
                                    <p class="muted">Nenhum participante encontrado para este curso.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($participantesFiltrados as $participante): ?>
                        <?php
                        $inscricaoStatus = !empty($participante['inscricao_status']) ? (string) $participante['inscricao_status'] : '';
                        $pedidoStatus = !empty($participante['pedido_status']) ? (string) $participante['pedido_status'] : '';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo Helpers::e($participante['nome'] ?? $participante['usuario_nome'] ?? 'Participante'); ?></strong><br>
                                <small><?php echo Helpers::e($participante['cpf'] ?? $participante['usuario_cpf'] ?? '-'); ?></small><br>
                                <small><?php echo Helpers::e($participante['email'] ?? $participante['usuario_email'] ?? '-'); ?></small>
                            </td>
                            <td><?php echo Helpers::e($participante['curso_nome'] ?? '-'); ?></td>
                            <td><?php echo Helpers::e($participante['turma_nome'] ?? '-'); ?></td>
                            <td>
                                <span class="<?php echo Helpers::e($statusBadgeClass($inscricaoStatus)); ?>"><?php echo Helpers::e($statusLabel($inscricaoStatus)); ?></span><br>
                                <small><?php echo !empty($participante['inscricao_confirmado_em']) ? Helpers::e($participante['inscricao_confirmado_em']) : 'Sem confirmação registrada'; ?></small>
                            </td>
                            <td>
                                <strong><?php echo Helpers::e($participante['pedido_codigo'] ?? '-'); ?></strong><br>
                                <small><?php echo Helpers::e($pedidoStatus !== '' ? $statusLabel($pedidoStatus) : 'Pedido sem status'); ?></small><br>
                                <small>R$ <?php echo number_format((float) ($participante['pedido_total'] ?? 0), 2, ',', '.'); ?></small>
                            </td>
                            <td>
                                <div class="split-actions area-curso-actions area-curso-participantes__actions">
                                    <?php if (!empty($participante['pedido_id'])): ?>
                                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildPedidoUrl($participante['pedido_id'])); ?>">Abrir pedido</a>
                                    <?php endif; ?>
                                    <a class="button-link button-link--ghost" href="/admin/inscricoes">Ver inscrições</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
