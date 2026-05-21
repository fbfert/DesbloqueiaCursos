<?php use App\Core\Helpers; ?>

<?php
$relatorios = isset($relatorios) && is_array($relatorios) ? $relatorios : array('ok' => false, 'message' => 'Relatório indisponível.');
$relatoriosFiltros = isset($relatorios_filtros) && is_array($relatorios_filtros) ? $relatorios_filtros : array();
$areaCursoBaseUrl = isset($areaCursoBaseUrl) && $areaCursoBaseUrl !== '' ? $areaCursoBaseUrl : '/admin/area-curso';
$relatoriosOk = !empty($relatorios['ok']);

$queryBase = array(
    'curso_id' => !empty($curso['id']) ? (int) $curso['id'] : 0,
    'turma_id' => !empty($turma['id']) ? (int) $turma['id'] : '',
    'aba' => 'relatorios',
    'busca' => $relatoriosFiltros['busca'] ?? '',
    'status_inscricao' => $relatoriosFiltros['status_inscricao'] ?? '',
    'faixa_progresso' => $relatoriosFiltros['faixa_progresso'] ?? '',
    'atividade_id' => $relatoriosFiltros['atividade_id'] ?? 0,
    'status_correcao' => $relatoriosFiltros['status_correcao'] ?? '',
    'situacao_elegibilidade' => $relatoriosFiltros['situacao_elegibilidade'] ?? '',
    'filtro_certificado' => $relatoriosFiltros['filtro_certificado'] ?? '',
    'tipo_pendencia' => $relatoriosFiltros['tipo_pendencia'] ?? '',
);

$statusInscricaoOptions = array(
    '' => 'Todas',
    'ativa' => 'Ativa',
    'em_andamento' => 'Em andamento',
    'concluida' => 'Concluída',
    'concluida_sem_certificado' => 'Concluída sem certificado',
    'certificado_emitido' => 'Certificado emitido',
);

$faixaProgressoOptions = array(
    '' => 'Todas',
    '0' => '0%',
    '1-49' => '1% a 49%',
    '50-99' => '50% a 99%',
    '100' => '100%',
);

$statusCorrecaoOptions = array(
    '' => 'Todas',
    'pendente' => 'Pendências de correção',
    'corrigida' => 'Corrigidas',
);

$situacaoElegibilidadeOptions = array(
    '' => 'Todas',
    'apto' => 'Apto',
    'pendente' => 'Pendente',
    'nao_apto' => 'Não apto',
    'em_andamento' => 'Em andamento',
    'certificado_emitido' => 'Certificado emitido',
);

$filtroCertificadoOptions = array(
    '' => 'Todos',
    'com_certificado' => 'Com certificado',
    'sem_certificado' => 'Sem certificado',
);

$tipoPendenciaOptions = array(
    '' => 'Todas',
    'progresso' => 'Progresso',
    'atividades' => 'Atividades',
    'presenca' => 'Presença',
    'avaliacao' => 'Avaliação',
    'aguardando_correcao' => 'Aguardando correção',
);

$alunos = isset($relatorios['alunos']) && is_array($relatorios['alunos']) ? $relatorios['alunos'] : array();
$atividades = isset($relatorios['atividades']) && is_array($relatorios['atividades']) ? $relatorios['atividades'] : array();
$pendencias = isset($relatorios['pendencias']) && is_array($relatorios['pendencias']) ? $relatorios['pendencias'] : array();
$resumo = isset($relatorios['resumo']) && is_array($relatorios['resumo']) ? $relatorios['resumo'] : array();
$painelAptos = isset($relatorios['painel_aptos']) && is_array($relatorios['painel_aptos']) ? $relatorios['painel_aptos'] : array();
$painelAptosResumo = isset($painelAptos['resumo']) && is_array($painelAptos['resumo']) ? $painelAptos['resumo'] : array();
$painelAptosAlunos = isset($painelAptos['alunos']) && is_array($painelAptos['alunos']) ? $painelAptos['alunos'] : array();
$painelAptosCriterios = isset($painelAptos['criterios']) && is_array($painelAptos['criterios']) ? $painelAptos['criterios'] : array();
$painelAptosAviso = isset($painelAptos['aviso']) ? (string) $painelAptos['aviso'] : 'Este painel apenas informa elegibilidade. A emissão de certificado continua pelo fluxo atual.';

$exportBase = $queryBase;
$exportBase['export'] = 'csv';
?>

<section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'relatorios' ? ' is-active' : ''; ?>" data-area-curso-tab="relatorios" id="area-curso-relatorios">
    <div class="panel-header">
        <div>
            <?php echo areaCursoHeadingWithTooltip('Relatórios', 'Indicadores pedagógicos e operacionais do curso/turma.'); ?>
        </div>
        <?php if ($relatoriosOk): ?>
            <div class="admin-area-curso__actions">
                <a href="<?php echo Helpers::e($areaCursoBaseUrl . '?' . http_build_query(array_merge($exportBase, array('relatorio' => 'progresso')))); ?>">Exportar progresso CSV</a>
                <a href="<?php echo Helpers::e($areaCursoBaseUrl . '?' . http_build_query(array_merge($exportBase, array('relatorio' => 'atividades')))); ?>">Exportar atividades CSV</a>
                <a href="<?php echo Helpers::e($areaCursoBaseUrl . '?' . http_build_query(array_merge($exportBase, array('relatorio' => 'conteudo_avaliacoes')))); ?>">Exportar conteúdo textual CSV</a>
                <a href="<?php echo Helpers::e($areaCursoBaseUrl . '?' . http_build_query(array_merge($exportBase, array('aba' => 'aptos-certificado', 'relatorio' => 'aptos_certificado')))); ?>">Exportar aptos CSV</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$relatoriosOk): ?>
        <p class="muted"><?php echo Helpers::e($relatorios['message'] ?? 'Relatório indisponível.'); ?></p>
    <?php else: ?>
        <form method="get" action="<?php echo Helpers::e($areaCursoBaseUrl); ?>" class="form-grid admin-area-curso__form">
            <input type="hidden" name="curso_id" value="<?php echo !empty($curso['id']) ? (int) $curso['id'] : 0; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <input type="hidden" name="aba" value="relatorios">
            <label class="full">
                Busca
                <input type="text" name="busca" value="<?php echo Helpers::e($relatoriosFiltros['busca'] ?? ''); ?>" placeholder="Nome, e-mail ou CPF">
            </label>
            <label>
                Status da inscrição
                <select name="status_inscricao">
                    <?php foreach ($statusInscricaoOptions as $valor => $rotulo): ?>
                        <option value="<?php echo Helpers::e($valor); ?>" <?php echo ($relatoriosFiltros['status_inscricao'] ?? '') === $valor ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($rotulo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Faixa de progresso
                <select name="faixa_progresso">
                    <?php foreach ($faixaProgressoOptions as $valor => $rotulo): ?>
                        <option value="<?php echo Helpers::e($valor); ?>" <?php echo ($relatoriosFiltros['faixa_progresso'] ?? '') === $valor ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($rotulo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Atividade
                <select name="atividade_id">
                    <option value="0">Todas</option>
                    <?php foreach ($atividades as $atividade): ?>
                        <option value="<?php echo (int) $atividade['id']; ?>" <?php echo (int) ($relatoriosFiltros['atividade_id'] ?? 0) === (int) $atividade['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($atividade['titulo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Correção
                <select name="status_correcao">
                    <?php foreach ($statusCorrecaoOptions as $valor => $rotulo): ?>
                        <option value="<?php echo Helpers::e($valor); ?>" <?php echo ($relatoriosFiltros['status_correcao'] ?? '') === $valor ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($rotulo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Situação de elegibilidade
                <select name="situacao_elegibilidade">
                    <?php foreach ($situacaoElegibilidadeOptions as $valor => $rotulo): ?>
                        <option value="<?php echo Helpers::e($valor); ?>" <?php echo ($relatoriosFiltros['situacao_elegibilidade'] ?? '') === $valor ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($rotulo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Certificado
                <select name="filtro_certificado">
                    <?php foreach ($filtroCertificadoOptions as $valor => $rotulo): ?>
                        <option value="<?php echo Helpers::e($valor); ?>" <?php echo ($relatoriosFiltros['filtro_certificado'] ?? '') === $valor ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($rotulo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Pendência
                <select name="tipo_pendencia">
                    <?php foreach ($tipoPendenciaOptions as $valor => $rotulo): ?>
                        <option value="<?php echo Helpers::e($valor); ?>" <?php echo ($relatoriosFiltros['tipo_pendencia'] ?? '') === $valor ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($rotulo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Aplicar filtros</button>
        </form>

        <div class="admin-area-curso__stats">
            <div><small>Inscritos válidos</small><strong><?php echo (int) ($resumo['alunos_inscritos'] ?? 0); ?></strong></div>
            <div><small>Acesso liberado</small><strong><?php echo (int) ($resumo['alunos_acesso_liberado'] ?? 0); ?></strong></div>
            <div><small>Alunos com progresso registrado</small><strong><?php echo (int) ($resumo['alunos_com_progresso_registrado'] ?? $resumo['alunos_que_acessaram_sala'] ?? 0); ?></strong></div>
            <div><small>Módulos publicados</small><strong><?php echo (int) ($resumo['modulos_publicados'] ?? 0); ?></strong></div>
            <div><small>Aulas publicadas</small><strong><?php echo (int) ($resumo['aulas_publicadas'] ?? 0); ?></strong></div>
            <div><small>Materiais publicados</small><strong><?php echo (int) ($resumo['materiais_publicados'] ?? 0); ?></strong></div>
            <div><small>Atividades publicadas</small><strong><?php echo (int) ($resumo['atividades_publicadas'] ?? 0); ?></strong></div>
            <div><small>Entregas enviadas</small><strong><?php echo (int) ($resumo['entregas_enviadas'] ?? 0); ?></strong></div>
            <div><small>Entregas corrigidas</small><strong><?php echo (int) ($resumo['entregas_corrigidas'] ?? 0); ?></strong></div>
            <div><small>Pendências de correção</small><strong><?php echo (int) ($resumo['entregas_pendentes_correcao'] ?? 0); ?></strong></div>
            <div><small>Progresso médio</small><strong><?php echo number_format((float) ($resumo['progresso_medio'] ?? 0), 2, ',', '.'); ?>%</strong></div>
            <div><small>Certificados emitidos</small><strong><?php echo (int) ($resumo['certificados_emitidos'] ?? 0); ?></strong></div>
            <div><small>Aptos</small><strong><?php echo (int) ($resumo['elegibilidade_apto'] ?? 0); ?></strong></div>
            <div><small>Pendentes</small><strong><?php echo (int) ($resumo['elegibilidade_pendente'] ?? 0); ?></strong></div>
            <div><small>Em andamento</small><strong><?php echo (int) ($resumo['elegibilidade_em_andamento'] ?? 0); ?></strong></div>
            <div><small>Não aptos</small><strong><?php echo (int) ($resumo['elegibilidade_nao_apto'] ?? 0); ?></strong></div>
            <div><small>Com certificado</small><strong><?php echo (int) ($resumo['elegibilidade_certificado_emitido'] ?? 0); ?></strong></div>
        </div>

        <section class="status-card admin-area-curso__section">
            <div class="panel-header">
                <div>
                    <?php echo areaCursoHeadingWithTooltip('Progresso por aluno', 'Resumo individual por inscrição válida do contexto selecionado.'); ?>
                </div>
                <span class="badge badge--soft"><?php echo count($alunos); ?> registros</span>
            </div>

            <?php if (empty($alunos)): ?>
                <p class="muted">Nenhum aluno encontrado para os filtros aplicados.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>E-mail</th>
                                <th>Turma</th>
                                <th>Progresso</th>
                                <th>Aulas</th>
                                <th>Atividades</th>
                                <th>Nota média</th>
                                <th>Último acesso</th>
                                <th>Status</th>
                                <th>Certificado</th>
                                <th>Elegibilidade</th>
                                <th>Motivos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunos as $aluno): ?>
                                <tr>
                                    <td><?php echo Helpers::e($aluno['aluno_nome']); ?></td>
                                    <td><?php echo Helpers::e($aluno['aluno_email']); ?></td>
                                    <td><?php echo Helpers::e($aluno['turma_nome']); ?></td>
                                    <td><?php echo number_format((float) $aluno['progresso_percentual'], 2, ',', '.'); ?>%</td>
                                    <td><?php echo (int) $aluno['aulas_concluidas']; ?> / <?php echo (int) $aluno['total_aulas_publicadas']; ?></td>
                                    <td><?php echo (int) $aluno['atividades_entregues']; ?> / <?php echo (int) $aluno['atividades_pendentes']; ?></td>
                                    <td><?php echo $aluno['nota_media'] !== null ? number_format((float) $aluno['nota_media'], 2, ',', '.') : '—'; ?></td>
                                    <td><?php echo !empty($aluno['ultimo_acesso']) ? Helpers::e(date('d/m/Y H:i', strtotime($aluno['ultimo_acesso']))) : '—'; ?></td>
                                    <td><span class="badge badge--soft"><?php echo Helpers::e(ucfirst((string) $aluno['status_inscricao'])); ?></span></td>
                                    <td><?php echo !empty($aluno['certificado_emitido']) ? 'Sim' : 'Não'; ?></td>
                                    <td><span class="badge badge--soft"><?php echo Helpers::e(str_replace('_', ' ', (string) ($aluno['elegibilidade_situacao'] ?? 'pendente'))); ?></span></td>
                                    <td><?php echo Helpers::e((string) ($aluno['elegibilidade_motivos_texto'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div class="admin-area-curso__report-lists">
            <?php $conteudoAvaliacoes = isset($relatorios['conteudo_avaliacoes']) && is_array($relatorios['conteudo_avaliacoes']) ? $relatorios['conteudo_avaliacoes'] : array(); ?>
            <?php $conteudoResumo = isset($conteudoAvaliacoes['resumo']) && is_array($conteudoAvaliacoes['resumo']) ? $conteudoAvaliacoes['resumo'] : array(); ?>
            <?php $conteudoRegistros = isset($conteudoAvaliacoes['registros']) && is_array($conteudoAvaliacoes['registros']) ? $conteudoAvaliacoes['registros'] : array(); ?>
            <section class="status-card admin-area-curso__section">
                <div class="panel-header"><div><?php echo areaCursoHeadingWithTooltip('Conteúdo Unificado — Avaliações Textuais', 'Resumo consolidado das avaliações textuais do conteúdo.'); ?></div></div>
                <div class="admin-area-curso__stats">
                    <div><small>Avaliações textuais publicadas</small><strong><?php echo (int) ($conteudoResumo['total_avaliacoes_textuais'] ?? 0); ?></strong></div>
                    <div><small>Entregas recebidas</small><strong><?php echo (int) ($conteudoResumo['entregas_enviadas'] ?? 0); ?></strong></div>
                    <div><small>Pendentes de correção</small><strong><?php echo (int) ($conteudoResumo['pendentes_correcao'] ?? 0); ?></strong></div>
                    <div><small>Aprovadas</small><strong><?php echo (int) ($conteudoResumo['aprovadas'] ?? 0); ?></strong></div>
                    <div><small>Reprovadas</small><strong><?php echo (int) ($conteudoResumo['reprovadas'] ?? 0); ?></strong></div>
                    <div><small>Média geral</small><strong><?php echo isset($conteudoResumo['media_geral']) && $conteudoResumo['media_geral'] !== null ? Helpers::e(number_format((float) $conteudoResumo['media_geral'], 2, ',', '.')) : '—'; ?></strong></div>
                    <div><small>Alunos com pendências</small><strong><?php echo (int) ($conteudoResumo['alunos_com_pendencia'] ?? 0); ?></strong></div>
                </div>
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Aluno</th><th>Avaliação</th><th>Status</th><th>Nota</th><th>Prazo</th><th>Enviado em</th><th>Corrigido em</th></tr></thead>
                        <tbody>
                        <?php if (empty($conteudoRegistros)): ?>
                            <tr><td colspan="7" class="muted">Nenhum registro para o contexto atual.</td></tr>
                        <?php else: foreach ($conteudoRegistros as $registro): ?>
                            <tr>
                                <td><?php echo Helpers::e((string) ($registro['aluno_nome'] ?? '')); ?></td>
                                <td><?php echo Helpers::e((string) ($registro['avaliacao_titulo'] ?? '')); ?></td>
                                <td><?php echo Helpers::e((string) ($registro['status'] ?? '')); ?></td>
                                <td><?php echo $registro['nota'] !== null ? Helpers::e(number_format((float) $registro['nota'], 2, ',', '.')) : '—'; ?></td>
                                <td><?php echo !empty($registro['prazo']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $registro['prazo']))) : '—'; ?></td>
                                <td><?php echo !empty($registro['enviado_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $registro['enviado_em']))) : '—'; ?></td>
                                <td><?php echo !empty($registro['corrigido_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $registro['corrigido_em']))) : '—'; ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="status-card admin-area-curso__section" id="area-curso-relatorios-aptos-certificado">
                <div class="panel-header">
                    <div>
                        <?php echo areaCursoHeadingWithTooltip('Aptos para certificado', $painelAptosAviso); ?>
                    </div>
                    <span class="badge badge--soft"><?php echo count($painelAptosAlunos); ?> alunos</span>
                </div>

                <div class="admin-area-curso__stats">
                    <div><small>Acesso liberado</small><strong><?php echo (int) ($painelAptosResumo['total_alunos'] ?? 0); ?></strong></div>
                    <div><small>Aptos</small><strong><?php echo (int) ($painelAptosResumo['aptos'] ?? 0); ?></strong></div>
                    <div><small>Pendentes</small><strong><?php echo (int) ($painelAptosResumo['pendentes'] ?? 0); ?></strong></div>
                    <div><small>Não aptos</small><strong><?php echo (int) ($painelAptosResumo['nao_aptos'] ?? 0); ?></strong></div>
                    <div><small>Em andamento</small><strong><?php echo (int) ($painelAptosResumo['em_andamento'] ?? 0); ?></strong></div>
                    <div><small>Com certificado</small><strong><?php echo (int) ($painelAptosResumo['certificado_emitido'] ?? 0); ?></strong></div>
                    <div><small>Sem certificado</small><strong><?php echo (int) ($painelAptosResumo['sem_certificado'] ?? 0); ?></strong></div>
                    <div><small>Aguardando correção</small><strong><?php echo (int) ($painelAptosResumo['aguardando_correcao'] ?? 0); ?></strong></div>
                    <div><small>Pendência de progresso</small><strong><?php echo (int) ($painelAptosResumo['pendencia_progresso'] ?? 0); ?></strong></div>
                    <div><small>Pendência de atividade</small><strong><?php echo (int) ($painelAptosResumo['pendencia_atividade'] ?? 0); ?></strong></div>
                </div>

                <?php if (!empty($painelAptosCriterios)): ?>
                    <p class="muted">
                        Critérios ativos:
                        progresso mínimo <?php echo Helpers::e(number_format((float) ($painelAptosCriterios['progresso_minimo'] ?? 0), 2, ',', '.')); ?>%;
                        atividades <?php echo !empty($painelAptosCriterios['exigir_atividades']) ? 'exigidas' : 'não exigidas'; ?>;
                        presença <?php echo !empty($painelAptosCriterios['exigir_presenca']) ? 'exigida' : 'não exigida'; ?>;
                        avaliação <?php echo !empty($painelAptosCriterios['exigir_avaliacao']) ? 'exigida' : 'não exigida'; ?>.
                    </p>
                <?php endif; ?>

                <?php if (empty($painelAptosAlunos)): ?>
                    <p class="muted">Nenhum aluno encontrado para os filtros aplicados.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Aluno</th>
                                    <th>E-mail</th>
                                    <th>Turma</th>
                                    <th>Status</th>
                                    <th>Progresso</th>
                                    <th>Atividades</th>
                                    <th>Média</th>
                                    <th>Conteúdo obrigatório</th>
                                    <th>Pendências conteúdo</th>
                                    <th>Textuais pendentes/reprovadas</th>
                                    <th>Situação</th>
                                    <th>Certificado</th>
                                    <th>Motivos/Pendências</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($painelAptosAlunos as $aluno): ?>
                                    <tr>
                                        <td><?php echo Helpers::e((string) ($aluno['aluno_nome'] ?? '')); ?></td>
                                        <td><?php echo Helpers::e((string) ($aluno['aluno_email'] ?? '')); ?></td>
                                        <td><?php echo Helpers::e((string) ($aluno['turma_nome'] ?? '')); ?></td>
                                        <td><span class="badge badge--soft"><?php echo Helpers::e((string) ($aluno['status_inscricao'] ?? '')); ?></span></td>
                                        <td><?php echo Helpers::e(number_format((float) ($aluno['progresso_percentual'] ?? 0), 2, ',', '.')); ?>%</td>
                                        <td><?php echo (int) ($aluno['atividades_entregues'] ?? 0); ?> / <?php echo (int) ($aluno['entregas_corrigidas'] ?? 0); ?></td>
                                        <td><?php echo isset($aluno['nota_media']) && $aluno['nota_media'] !== null ? Helpers::e(number_format((float) $aluno['nota_media'], 2, ',', '.')) : '—'; ?></td>
                                        <td>
                                            <?php echo (int) ($aluno['elegibilidade_conteudo_obrigatorios_concluidos'] ?? 0); ?>
                                            /
                                            <?php echo (int) ($aluno['elegibilidade_conteudo_obrigatorios_total'] ?? 0); ?>
                                            (<?php echo Helpers::e(number_format((float) ($aluno['elegibilidade_conteudo_percentual'] ?? 0), 2, ',', '.')); ?>%)
                                        </td>
                                        <td><?php echo (int) ($aluno['elegibilidade_conteudo_obrigatorios_pendentes'] ?? 0); ?></td>
                                        <td>
                                            <?php echo (int) ($aluno['elegibilidade_conteudo_avaliacoes_pendentes'] ?? 0); ?>
                                            /
                                            <?php echo (int) ($aluno['elegibilidade_conteudo_avaliacoes_reprovadas'] ?? 0); ?>
                                        </td>
                                        <td><span class="badge badge--soft"><?php echo Helpers::e(str_replace('_', ' ', (string) ($aluno['elegibilidade_situacao'] ?? 'pendente'))); ?></span></td>
                                        <td><?php echo !empty($aluno['certificado_emitido']) ? 'Sim' : 'Não'; ?></td>
                                        <td>
                                            <details>
                                                <summary>Ver detalhe</summary>
                                                <p class="muted">Inscrição #<?php echo (int) ($aluno['inscricao_id'] ?? 0); ?> · Aulas <?php echo (int) ($aluno['aulas_concluidas'] ?? 0); ?>/<?php echo (int) ($aluno['total_aulas_publicadas'] ?? 0); ?></p>
                                                <p class="muted">Atividades enviadas: <?php echo (int) ($aluno['atividades_entregues'] ?? 0); ?> · Corrigidas: <?php echo (int) ($aluno['entregas_corrigidas'] ?? 0); ?> · Pendentes: <?php echo (int) ($aluno['atividades_pendentes'] ?? 0); ?> · Devolvidas/aguardando: <?php echo (int) ($aluno['entregas_pendentes_correcao'] ?? 0); ?></p>
                                                <p class="muted">Conteúdo obrigatório: <?php echo (int) ($aluno['elegibilidade_conteudo_obrigatorios_concluidos'] ?? 0); ?>/<?php echo (int) ($aluno['elegibilidade_conteudo_obrigatorios_total'] ?? 0); ?> (<?php echo Helpers::e(number_format((float) ($aluno['elegibilidade_conteudo_percentual'] ?? 0), 2, ',', '.')); ?>%) · Pendentes: <?php echo (int) ($aluno['elegibilidade_conteudo_obrigatorios_pendentes'] ?? 0); ?></p>
                                                <p class="muted">Avaliações textuais obrigatórias pendentes: <?php echo (int) ($aluno['elegibilidade_conteudo_avaliacoes_pendentes'] ?? 0); ?> · Reprovadas: <?php echo (int) ($aluno['elegibilidade_conteudo_avaliacoes_reprovadas'] ?? 0); ?></p>
                                                <p class="muted"><?php echo Helpers::e((string) ($aluno['elegibilidade_motivos_texto'] ?? '')); ?></p>
                                                <p class="muted">Este painel apenas informa elegibilidade. A emissão de certificado continua pelo fluxo atual.</p>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="status-card admin-area-curso__section">
                <div class="panel-header">
                    <div>
                        <?php echo areaCursoHeadingWithTooltip('Situações de atenção', 'Itens operacionais que merecem acompanhamento, sem confundir certificado com pendência pedagógica.'); ?>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Lista</th>
                                <th>Quantidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Alunos sem acesso à sala</td><td><?php echo count($pendencias['sem_acesso'] ?? array()); ?></td></tr>
                            <tr><td>Alunos com 0% de progresso</td><td><?php echo count($pendencias['zero_progresso'] ?? array()); ?></td></tr>
                            <tr><td>Alunos com atividades pendentes</td><td><?php echo count($pendencias['atividades_pendentes'] ?? array()); ?></td></tr>
                            <tr><td>Alunos com entregas aguardando correção</td><td><?php echo count($pendencias['aguardando_correcao'] ?? array()); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="status-card admin-area-curso__section">
                <div class="panel-header">
                    <div>
                        <?php echo areaCursoHeadingWithTooltip('Atividades', 'Resumo por atividade publicada do contexto atual.'); ?>
                    </div>
                    <span class="badge badge--soft"><?php echo count($atividades); ?> atividades</span>
                </div>

                <?php if (empty($atividades)): ?>
                    <p class="muted">Nenhuma atividade publicada encontrada.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Aula</th>
                                    <th>Módulo</th>
                                    <th>Prazo</th>
                                    <th>Esperado</th>
                                    <th>Enviadas</th>
                                    <th>Corrigidas</th>
                                    <th>Pendentes</th>
                                    <th>Média</th>
                                    <th>Menor</th>
                                    <th>Maior</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($atividades as $atividade): ?>
                                    <tr>
                                        <td><?php echo Helpers::e($atividade['titulo']); ?></td>
                                        <td><?php echo Helpers::e($atividade['aula_titulo']); ?></td>
                                        <td><?php echo Helpers::e($atividade['modulo_titulo']); ?></td>
                                        <td><?php echo !empty($atividade['prazo']) ? Helpers::e(date('d/m/Y H:i', strtotime($atividade['prazo']))) : 'Sem prazo'; ?></td>
                                        <td><?php echo (int) $atividade['total_esperado']; ?></td>
                                        <td><?php echo (int) $atividade['entregas_enviadas']; ?></td>
                                        <td><?php echo (int) $atividade['entregas_corrigidas']; ?></td>
                                        <td><?php echo (int) $atividade['entregas_pendentes_correcao']; ?></td>
                                        <td><?php echo $atividade['media_nota'] !== null ? number_format((float) $atividade['media_nota'], 2, ',', '.') : '—'; ?></td>
                                        <td><?php echo $atividade['menor_nota'] !== null ? number_format((float) $atividade['menor_nota'], 2, ',', '.') : '—'; ?></td>
                                        <td><?php echo $atividade['maior_nota'] !== null ? number_format((float) $atividade['maior_nota'], 2, ',', '.') : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</section>
