<?php

use App\Core\Helpers;

$cursoAtual = isset($curso) && is_array($curso) ? $curso : array();
$turmaAtual = isset($turma) && is_array($turma) ? $turma : array();
$certificadosContexto = isset($certificados) && is_array($certificados) ? $certificados : array();
$certificadosLista = isset($certificadosContexto['certificados']) && is_array($certificadosContexto['certificados']) ? $certificadosContexto['certificados'] : array();
$aptosParaEmissao = isset($certificadosContexto['aptos_para_emissao']) && is_array($certificadosContexto['aptos_para_emissao']) ? $certificadosContexto['aptos_para_emissao'] : array();
$pendentesCertificados = isset($certificadosContexto['pendentes']) && is_array($certificadosContexto['pendentes']) ? $certificadosContexto['pendentes'] : array();
$resumoCertificados = isset($certificadosContexto['resumo']) && is_array($certificadosContexto['resumo']) ? $certificadosContexto['resumo'] : array();
$filtrosCertificados = isset($certificados_filtros) && is_array($certificados_filtros) ? $certificados_filtros : array('busca' => '', 'status' => '');
$certificadoSelecionado = isset($certificado_selecionado) && is_array($certificado_selecionado) && isset($certificado_selecionado['certificado']) && is_array($certificado_selecionado['certificado']) ? $certificado_selecionado['certificado'] : null;
$csrfFieldAtual = isset($csrfField) ? (string) $csrfField : '';
$cursoIdAtual = !empty($cursoAtual['id']) ? (int) $cursoAtual['id'] : 0;
$turmaIdAtual = !empty($turmaAtual['id']) ? (int) $turmaAtual['id'] : 0;

$buildAreaCursoUrl = function (array $params = array()) use ($cursoIdAtual, $turmaIdAtual, $filtrosCertificados) {
    $query = array(
        'curso_id' => $cursoIdAtual,
        'aba' => 'certificados',
    );

    if ($turmaIdAtual > 0) {
        $query['turma_id'] = $turmaIdAtual;
    }

    foreach ($filtrosCertificados as $chave => $valor) {
        if ($valor === null || $valor === '') {
            continue;
        }
        $query['certificados_' . $chave] = $valor;
    }

    foreach ($params as $chave => $valor) {
        if ($valor === null || $valor === '') {
            continue;
        }
        $query[$chave] = $valor;
    }

    return '/admin/area-curso?' . http_build_query($query);
};

$statusLabel = function ($status) {
    $status = (string) $status;
    switch ($status) {
        case 'emitido':
            return 'Emitido';
        case 'cancelado':
            return 'Cancelado';
        case 'revogado':
            return 'Revogado';
        case 'substituido':
            return 'Substituído';
        default:
            return $status !== '' ? $status : '—';
    }
};

$badgeClass = function ($status) {
    $status = (string) $status;
    if ($status === 'emitido') {
        return 'badge badge--success';
    }
    if ($status === 'cancelado' || $status === 'revogado') {
        return 'badge badge--danger';
    }
    if ($status === 'substituido') {
        return 'badge badge--warning';
    }
    return 'badge badge--soft';
};

$formatPercent = function ($value) {
    return $value !== null && $value !== '' ? number_format((float) $value, 2, ',', '.') . '%' : '—';
};

$formatDate = function ($value) {
    return $value !== null && $value !== '' ? Helpers::e((string) $value) : '—';
};

$temFiltrosAtivos = !empty($filtrosCertificados['busca']) || !empty($filtrosCertificados['status']);
$certificadoDetalhe = $certificadoSelecionado;
?>

<section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'certificados' ? ' is-active' : ''; ?>" data-area-curso-tab="certificados" id="area-curso-certificados">
    <div class="panel-header">
        <div>
            <?php echo areaCursoHeadingWithTooltip('Certificados', 'Acompanhe a emissão, a validação pública e o histórico dos certificados deste curso.'); ?>
            <p class="muted">Gestão contextual dos certificados emitidos para o curso e a turma selecionados.</p>
        </div>
        <div class="area-curso-actions">
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('export' => 'csv'))); ?>">Exportar CSV</a>
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'aptos-certificado'))); ?>">Ver aptos para certificado</a>
        </div>
    </div>

    <div class="area-curso-summary-grid admin-mt-12 area-curso-certificados__summary">
        <div class="area-curso-summary-card">
            <small>Emitidos</small>
            <strong><?php echo (int) ($resumoCertificados['emitidos'] ?? 0); ?></strong>
            <span>certificados ativos no contexto</span>
        </div>
        <div class="area-curso-summary-card">
            <small>Aptos para emissão</small>
            <strong><?php echo (int) ($resumoCertificados['aptos'] ?? 0); ?></strong>
            <span>participantes prontos para gerar certificado</span>
        </div>
        <div class="area-curso-summary-card">
            <small>Pendentes</small>
            <strong><?php echo (int) ($resumoCertificados['pendentes'] ?? 0); ?></strong>
            <span>participantes ainda sem aptidão</span>
        </div>
        <div class="area-curso-summary-card">
            <small>Cancelados</small>
            <strong><?php echo (int) ($resumoCertificados['cancelados'] ?? 0); ?></strong>
            <span>certificados anulados administrativamente</span>
        </div>
        <div class="area-curso-summary-card">
            <small>Revogados</small>
            <strong><?php echo (int) ($resumoCertificados['revogados'] ?? 0); ?></strong>
            <span>certificados revogados</span>
        </div>
        <div class="area-curso-summary-card">
            <small>Substituídos</small>
            <strong><?php echo (int) ($resumoCertificados['substituidos'] ?? 0); ?></strong>
            <span>reemissões que substituíram versões anteriores</span>
        </div>
    </div>

    <section class="status-card area-curso-table-card admin-mt-16 area-curso-certificados__filters">
        <div class="panel-header">
            <div>
                <h2>Filtros</h2>
                <p>Filtre por participante, código do certificado e status atual.</p>
            </div>
            <?php if ($temFiltrosAtivos): ?>
                <span class="badge badge--warning">Filtros ativos</span>
            <?php endif; ?>
        </div>

        <form method="get" action="/admin/area-curso" class="form-grid admin-area-curso__form area-curso-certificados__filter-form">
            <input type="hidden" name="curso_id" value="<?php echo (int) $cursoIdAtual; ?>">
            <input type="hidden" name="aba" value="certificados">
            <?php if ($turmaIdAtual > 0): ?>
                <input type="hidden" name="turma_id" value="<?php echo (int) $turmaIdAtual; ?>">
            <?php endif; ?>
            <label>
                Busca
                <input type="text" name="certificados_busca" value="<?php echo Helpers::e((string) ($filtrosCertificados['busca'] ?? '')); ?>" placeholder="Nome, CPF, código ou turma">
            </label>
            <label>
                Status
                <select name="certificados_status">
                    <option value="">Todos</option>
                    <option value="emitido" <?php echo (($filtrosCertificados['status'] ?? '') === 'emitido') ? 'selected' : ''; ?>>Emitido</option>
                    <option value="cancelado" <?php echo (($filtrosCertificados['status'] ?? '') === 'cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                    <option value="revogado" <?php echo (($filtrosCertificados['status'] ?? '') === 'revogado') ? 'selected' : ''; ?>>Revogado</option>
                    <option value="substituido" <?php echo (($filtrosCertificados['status'] ?? '') === 'substituido') ? 'selected' : ''; ?>>Substituído</option>
                </select>
            </label>
            <div class="cta-group full area-curso-certificados__filter-actions">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('certificados_busca' => '', 'certificados_status' => ''))); ?>">Limpar filtros</a>
            </div>
        </form>
    </section>

    <section class="status-card area-curso-table-card admin-mt-16 area-curso-certificados__table">
        <div class="panel-header">
            <div>
                <h2>Certificados emitidos</h2>
            </div>
            <span class="badge badge--soft"><?php echo (int) count($certificadosLista); ?> registros</span>
        </div>

        <?php if (empty($certificadosLista)): ?>
            <p class="muted admin-mt-12">Nenhum certificado encontrado neste contexto.</p>
        <?php else: ?>
            <div class="table-wrap admin-mt-12">
                <table class="admin-table admin-table--area-certificados">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Participante</th>
                            <th>Turma</th>
                            <th>Status</th>
                            <th>Emitido em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($certificadosLista as $certificado): ?>
                            <tr>
                                <td>
                                    <strong><?php echo Helpers::e((string) ($certificado['codigo'] ?? '')); ?></strong><br>
                                    <small class="muted"><?php echo Helpers::e((string) ($certificado['pedido_codigo'] ?? '')); ?></small>
                                </td>
                                <td>
                                    <?php echo Helpers::e((string) ($certificado['participante_nome'] ?? '')); ?><br>
                                    <small class="muted"><?php echo Helpers::e((string) ($certificado['cpf_participante'] ?? '')); ?></small>
                                </td>
                                <td><?php echo Helpers::e((string) ($certificado['turma_nome'] ?? '—')); ?></td>
                                <td><span class="<?php echo Helpers::e($badgeClass(isset($certificado['status']) ? $certificado['status'] : '')); ?>"><?php echo Helpers::e($statusLabel(isset($certificado['status']) ? $certificado['status'] : '')); ?></span></td>
                                <td><?php echo $formatDate(isset($certificado['emitido_em']) ? $certificado['emitido_em'] : null); ?></td>
                                <td>
                                    <div class="split-actions area-curso-actions area-curso-certificados__row-actions">
                                        <a class="button-link button-link--ghost" href="/admin/certificados/pdf?codigo=<?php echo urlencode((string) ($certificado['codigo'] ?? '')); ?>">PDF</a>
                                        <a class="button-link button-link--ghost" href="/certificados/validar?codigo=<?php echo urlencode((string) ($certificado['codigo'] ?? '')); ?>">Validação pública</a>
                                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('certificado_id' => (int) ($certificado['id'] ?? 0)))); ?>">Detalhes</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="status-card area-curso-table-card admin-mt-16 area-curso-certificados__table">
        <div class="panel-header">
            <div>
                <h2>Aptos para emissão</h2>
                <p>Inscrições elegíveis que ainda não possuem certificado emitido.</p>
            </div>
            <span class="badge badge--soft"><?php echo (int) count($aptosParaEmissao); ?> registros</span>
        </div>

        <?php if (empty($aptosParaEmissao)): ?>
            <p class="muted admin-mt-12">Nenhum aluno apto para emissão neste contexto.</p>
        <?php else: ?>
            <div class="table-wrap admin-mt-12">
                <table class="admin-table admin-table--area-certificados">
                    <thead>
                        <tr>
                            <th>Participante</th>
                            <th>Turma</th>
                            <th>Progresso</th>
                            <th>Presença</th>
                            <th>Nota final</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aptosParaEmissao as $inscricao): ?>
                            <tr>
                                <td><?php echo Helpers::e((string) ($inscricao['participante_nome'] ?? '')); ?></td>
                                <td><?php echo Helpers::e((string) ($inscricao['turma_nome'] ?? '—')); ?></td>
                                <td><?php echo $formatPercent(isset($inscricao['percentual_progresso']) ? $inscricao['percentual_progresso'] : null); ?></td>
                                <td><?php echo $formatPercent(isset($inscricao['presenca_percentual']) ? $inscricao['presenca_percentual'] : null); ?></td>
                                <td><?php echo isset($inscricao['nota_final']) && $inscricao['nota_final'] !== null ? number_format((float) $inscricao['nota_final'], 2, ',', '.') : '—'; ?></td>
                                <td>
                                    <form method="post" action="/admin/area-curso/certificados/emitir" class="area-curso-certificados__inline-form">
                                        <?php echo $csrfFieldAtual; ?>
                                        <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                        <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                        <input type="hidden" name="inscricao_id" value="<?php echo (int) ($inscricao['id'] ?? 0); ?>">
                                        <input type="hidden" name="aba" value="certificados">
                                        <input type="hidden" name="certificados_busca" value="<?php echo Helpers::e((string) ($filtrosCertificados['busca'] ?? '')); ?>">
                                        <input type="hidden" name="certificados_status" value="<?php echo Helpers::e((string) ($filtrosCertificados['status'] ?? '')); ?>">
                                        <button type="submit" class="button-link button-link--primary">Emitir certificado</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="status-card area-curso-table-card admin-mt-16 area-curso-certificados__table">
        <div class="panel-header">
            <div>
                <h2>Pendentes / não aptos</h2>
                <p>Inscrições sem certificado que ainda não atingiram os critérios de emissão.</p>
            </div>
            <span class="badge badge--soft"><?php echo (int) count($pendentesCertificados); ?> registros</span>
        </div>

        <?php if (empty($pendentesCertificados)): ?>
            <p class="muted admin-mt-12">Nenhuma pendência encontrada neste contexto.</p>
        <?php else: ?>
            <div class="table-wrap admin-mt-12">
                <table class="admin-table admin-table--area-certificados">
                    <thead>
                        <tr>
                            <th>Participante</th>
                            <th>Turma</th>
                            <th>Progresso</th>
                            <th>Presença</th>
                            <th>Nota final</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendentesCertificados as $inscricao): ?>
                            <tr>
                                <td><?php echo Helpers::e((string) ($inscricao['participante_nome'] ?? '')); ?></td>
                                <td><?php echo Helpers::e((string) ($inscricao['turma_nome'] ?? '—')); ?></td>
                                <td><?php echo $formatPercent(isset($inscricao['percentual_progresso']) ? $inscricao['percentual_progresso'] : null); ?></td>
                                <td><?php echo $formatPercent(isset($inscricao['presenca_percentual']) ? $inscricao['presenca_percentual'] : null); ?></td>
                                <td><?php echo isset($inscricao['nota_final']) && $inscricao['nota_final'] !== null ? number_format((float) $inscricao['nota_final'], 2, ',', '.') : '—'; ?></td>
                                <td><span class="badge badge--soft">Aguardando conclusão</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="status-card area-curso-table-card admin-mt-16 area-curso-certificados__detail">
        <div class="panel-header">
            <div>
                <h2>Detalhes administrativos</h2>
                <p>Selecione um certificado na tabela para ver PDF, validações e histórico.</p>
            </div>
        </div>

        <?php if (empty($certificadoDetalhe)): ?>
            <p class="muted">Nenhum certificado selecionado.</p>
        <?php else: ?>
            <div class="area-curso-certificados__detail-grid">
                <div class="area-curso-certificados__detail-main">
                    <dl class="detail-list">
                        <div><dt>Código</dt><dd><?php echo Helpers::e((string) ($certificadoDetalhe['codigo'] ?? '')); ?></dd></div>
                        <div><dt>Status</dt><dd><?php echo Helpers::e($statusLabel(isset($certificadoDetalhe['status']) ? $certificadoDetalhe['status'] : '')); ?></dd></div>
                        <div><dt>Participante</dt><dd><?php echo Helpers::e((string) ($certificadoDetalhe['participante_nome'] ?? '')); ?></dd></div>
                        <div><dt>CPF</dt><dd><?php echo Helpers::e((string) ($certificadoDetalhe['cpf_mascarado'] ?? $certificadoDetalhe['cpf_participante'] ?? '')); ?></dd></div>
                        <div><dt>Curso</dt><dd><?php echo Helpers::e((string) ($certificadoDetalhe['curso_nome'] ?? '')); ?></dd></div>
                        <div><dt>Turma</dt><dd><?php echo Helpers::e((string) ($certificadoDetalhe['turma_nome'] ?? '—')); ?></dd></div>
                        <div><dt>Emitido em</dt><dd><?php echo Helpers::e((string) ($certificadoDetalhe['emitido_em'] ?? '—')); ?></dd></div>
                        <div><dt>Pedido</dt><dd><?php echo Helpers::e((string) ($certificadoDetalhe['pedido_codigo'] ?? '—')); ?></dd></div>
                    </dl>

                    <div class="pill-row admin-mt-16">
                        <a class="pill" href="<?php echo Helpers::e((string) ($certificadoDetalhe['pdf_url'] ?? '/admin/certificados/pdf?codigo=' . urlencode((string) ($certificadoDetalhe['codigo'] ?? '')))); ?>">Abrir PDF</a>
                        <a class="pill" href="<?php echo Helpers::e((string) ($certificadoDetalhe['validacao_url'] ?? '/certificados/validar?codigo=' . urlencode((string) ($certificadoDetalhe['codigo'] ?? '')))); ?>">Validação pública</a>
                    </div>
                </div>

                <div class="area-curso-certificados__detail-side">
                    <details class="area-curso-collapsible-form" open>
                        <summary>Reemissão</summary>
                        <div class="area-curso-collapsible-form__body">
                            <form method="post" action="/admin/area-curso/certificados/reemitir" class="form-grid admin-area-curso__form">
                                <?php echo $csrfFieldAtual; ?>
                                <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                <input type="hidden" name="certificado_id" value="<?php echo (int) ($certificadoDetalhe['id'] ?? 0); ?>">
                                <input type="hidden" name="aba" value="certificados">
                                <input type="hidden" name="certificados_busca" value="<?php echo Helpers::e((string) ($filtrosCertificados['busca'] ?? '')); ?>">
                                <input type="hidden" name="certificados_status" value="<?php echo Helpers::e((string) ($filtrosCertificados['status'] ?? '')); ?>">
                                <label class="checkbox full">
                                    <input type="checkbox" name="manter_codigo" value="1" checked>
                                    Manter o código atual
                                </label>
                                <button type="submit" class="button-link button-link--primary">Reemitir certificado</button>
                            </form>
                        </div>
                    </details>

                    <details class="area-curso-collapsible-form admin-mt-12">
                        <summary>Cancelar certificado</summary>
                        <div class="area-curso-collapsible-form__body">
                            <form method="post" action="/admin/area-curso/certificados/cancelar" class="form-grid admin-area-curso__delete-form">
                                <?php echo $csrfFieldAtual; ?>
                                <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                <input type="hidden" name="certificado_id" value="<?php echo (int) ($certificadoDetalhe['id'] ?? 0); ?>">
                                <input type="hidden" name="aba" value="certificados">
                                <input type="hidden" name="certificados_busca" value="<?php echo Helpers::e((string) ($filtrosCertificados['busca'] ?? '')); ?>">
                                <input type="hidden" name="certificados_status" value="<?php echo Helpers::e((string) ($filtrosCertificados['status'] ?? '')); ?>">
                                <label class="full">
                                    Observação obrigatória
                                    <input type="text" name="observacao" required placeholder="Motivo do cancelamento">
                                </label>
                                <button type="submit" class="button-link button-link--danger">Cancelar</button>
                            </form>
                        </div>
                    </details>

                    <details class="area-curso-collapsible-form admin-mt-12">
                        <summary>Revogar certificado</summary>
                        <div class="area-curso-collapsible-form__body">
                            <form method="post" action="/admin/area-curso/certificados/revogar" class="form-grid admin-area-curso__delete-form">
                                <?php echo $csrfFieldAtual; ?>
                                <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                <input type="hidden" name="certificado_id" value="<?php echo (int) ($certificadoDetalhe['id'] ?? 0); ?>">
                                <input type="hidden" name="aba" value="certificados">
                                <input type="hidden" name="certificados_busca" value="<?php echo Helpers::e((string) ($filtrosCertificados['busca'] ?? '')); ?>">
                                <input type="hidden" name="certificados_status" value="<?php echo Helpers::e((string) ($filtrosCertificados['status'] ?? '')); ?>">
                                <label class="full">
                                    Observação obrigatória
                                    <input type="text" name="observacao" required placeholder="Motivo da revogação">
                                </label>
                                <button type="submit" class="button-link button-link--danger">Revogar</button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>

            <section class="admin-mt-16">
                <div class="panel-header">
                    <div>
                        <h2>Histórico</h2>
                        <p>Movimentações do certificado selecionado.</p>
                    </div>
                </div>

                <?php if (empty($certificadoDetalhe['historico'])): ?>
                    <p class="muted">Sem histórico registrado.</p>
                <?php else: ?>
                    <div class="table-wrap admin-mt-12">
                        <table class="admin-table admin-table--area-certificados">
                            <thead>
                                <tr>
                                    <th>Status anterior</th>
                                    <th>Status novo</th>
                                    <th>Observação</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($certificadoDetalhe['historico'] as $item): ?>
                                    <tr>
                                        <td><?php echo Helpers::e((string) ($item['status_anterior'] ?? '—')); ?></td>
                                        <td><?php echo Helpers::e((string) ($item['status_novo'] ?? '—')); ?></td>
                                        <td><?php echo Helpers::e((string) ($item['observacao'] ?? '')); ?></td>
                                        <td><?php echo Helpers::e((string) ($item['created_at'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-mt-16">
                <div class="panel-header">
                    <div>
                        <h2>Validações públicas</h2>
                        <p>Consultas registradas na validação pública do certificado.</p>
                    </div>
                </div>

                <?php if (empty($certificadoDetalhe['validacoes'])): ?>
                    <p class="muted">Nenhuma validação registrada.</p>
                <?php else: ?>
                    <div class="table-wrap admin-mt-12">
                        <table class="admin-table admin-table--area-certificados">
                            <thead>
                                <tr>
                                    <th>Resultado</th>
                                    <th>CPF informado</th>
                                    <th>IP</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($certificadoDetalhe['validacoes'] as $validacao): ?>
                                    <tr>
                                        <td><?php echo Helpers::e((string) ($validacao['resultado'] ?? '')); ?></td>
                                        <td><?php echo Helpers::e((string) ($validacao['cpf_informado'] ?? '—')); ?></td>
                                        <td><?php echo Helpers::e((string) ($validacao['ip_address'] ?? '—')); ?></td>
                                        <td><?php echo Helpers::e((string) ($validacao['created_at'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </section>
</section>
