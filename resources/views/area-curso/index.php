<?php use App\Core\Helpers; ?>
<?php // Cache-bust para sincronização de deploy. ?>

<?php
$progressoDados = isset($progresso) && is_array($progresso) ? $progresso : array();
$percentualProgresso = isset($progressoDados['percentual']) ? (float) $progressoDados['percentual'] : (isset($percentual_progresso) ? (float) $percentual_progresso : 0);
$aulasConcluidas = isset($progressoDados['aulas_concluidas']) ? (int) $progressoDados['aulas_concluidas'] : 0;
$totalAulasPublicadas = isset($progressoDados['total_aulas_publicadas']) ? (int) $progressoDados['total_aulas_publicadas'] : 0;
$totalModulosPublicados = isset($progressoDados['total_modulos_publicados']) ? (int) $progressoDados['total_modulos_publicados'] : 0;
$modulosConcluidos = isset($progressoDados['modulos_concluidos']) ? (int) $progressoDados['modulos_concluidos'] : 0;
$aulasConcluidasIds = isset($progressoDados['aulas_concluidas_ids']) && is_array($progressoDados['aulas_concluidas_ids']) ? $progressoDados['aulas_concluidas_ids'] : array();
$modulosConcluidosIds = isset($progressoDados['modulos_concluidos_ids']) && is_array($progressoDados['modulos_concluidos_ids']) ? $progressoDados['modulos_concluidos_ids'] : array();
$inscricaoAtualId = isset($inscricao['id']) ? (int) $inscricao['id'] : 0;
$cursoId = isset($inscricao['curso_evento_id']) ? (int) $inscricao['curso_evento_id'] : 0;
$turmaId = isset($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
$cursoNome = isset($curso['nome']) ? $curso['nome'] : 'Sala virtual';
$turmaNome = !empty($turma['nome']) ? $turma['nome'] : 'Sem turma definida';
$inscricaoStatus = isset($inscricao['status']) ? (string) $inscricao['status'] : '';
$pedidoStatus = isset($inscricao['pedido_status']) ? (string) $inscricao['pedido_status'] : '';
$inscricaoPodeAcessar = in_array($inscricaoStatus, array('ativa', 'em_andamento', 'concluida', 'concluida_sem_certificado', 'certificado_emitido'), true);
$temModulos = !empty($modulos);
$tipoMaterialLabels = array(
    'arquivo_protegido' => 'Arquivo protegido',
    'link_externo' => 'Link externo',
    'video_externo' => 'Vídeo externo',
    'embed_controlado' => 'Embed controlado',
);
$tipoEntregaLabels = array(
    'texto' => 'Texto',
    'arquivo' => 'Arquivo',
    'texto_ou_arquivo' => 'Texto ou arquivo',
);
$selectedAtividade = !empty($selected_atividade) ? $selected_atividade : null;
$elegibilidade = isset($elegibilidade) && is_array($elegibilidade) ? $elegibilidade : array();
$elegibilidadeMotivos = !empty($elegibilidade['motivos']) && is_array($elegibilidade['motivos']) ? $elegibilidade['motivos'] : array();
$situacaoConclusao = isset($elegibilidade['situacao']) ? (string) $elegibilidade['situacao'] : 'pendente';
$mapaSituacaoConclusao = array(
    'em_andamento' => 'Em andamento',
    'pendente' => 'Pendente',
    'apto' => 'Apto',
    'nao_apto' => 'Não apto',
    'certificado_emitido' => 'Certificado emitido',
);
$situacaoConclusaoLabel = isset($mapaSituacaoConclusao[$situacaoConclusao]) ? $mapaSituacaoConclusao[$situacaoConclusao] : 'Pendente';

if (!function_exists('sala_material_label')) {
    function sala_material_label(array $material, array $labels)
    {
        $tipo = !empty($material['tipo_material']) ? (string) $material['tipo_material'] : '';
        if ($tipo !== '' && isset($labels[$tipo])) {
            return $labels[$tipo];
        }

        $tipoArquivo = !empty($material['tipo_arquivo']) ? (string) $material['tipo_arquivo'] : '';
        if ($tipoArquivo === 'link') {
            return 'Link externo';
        }
        if ($tipoArquivo === 'video') {
            return 'Vídeo externo';
        }
        if ($tipoArquivo === 'embed') {
            return 'Embed controlado';
        }

        return 'Arquivo protegido';
    }
}
?>

<section class="page-header">
    <h1>Sala virtual</h1>
    <p><?php echo Helpers::e($cursoNome); ?> · <?php echo Helpers::e($turmaNome); ?></p>
</section>

<?php if (!empty($success)): ?>
    <section class="auth-message auth-message-success">
        <p><?php echo Helpers::e($success); ?></p>
    </section>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <section class="auth-message auth-message-error">
        <?php foreach ($errors as $error): ?>
            <p><?php echo Helpers::e($error); ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="sala-virtual-layout">
    <aside class="status-card sala-virtual-summary">
        <strong>Progresso geral</strong>
        <span><?php echo Helpers::e(number_format($percentualProgresso, 2, ',', '.')); ?>%</span>
        <div class="sala-progresso">
            <div class="sala-progresso__meta">
                <span><?php echo (int) $aulasConcluidas; ?> de <?php echo (int) $totalAulasPublicadas; ?> aulas concluídas</span>
                <span><?php echo (int) $modulosConcluidos; ?> de <?php echo (int) $totalModulosPublicados; ?> módulos concluídos</span>
            </div>
            <div class="sala-progresso__bar">
                <span style="width: <?php echo Helpers::e(number_format(max(0, min(100, $percentualProgresso)), 2, '.', '')); ?>%;"></span>
            </div>
        </div>
        <div class="pill-row">
            <span class="pill"><?php echo (int) $totalModulosPublicados; ?> módulos publicados</span>
            <span class="pill"><?php echo (int) $totalAulasPublicadas; ?> aulas publicadas</span>
            <span class="pill<?php echo $inscricaoPodeAcessar ? ' pill--success' : ''; ?>"><?php echo Helpers::e($inscricaoStatus !== '' ? $inscricaoStatus : 'ativa'); ?></span>
        </div>
        <p class="muted-row">Acompanhe seu progresso mínimo e marque as aulas concluídas conforme avança no curso.</p>
        <div class="sala-bloco-extra">
            <strong>Minha conclusão</strong>
            <p class="muted-row">Situação atual: <?php echo Helpers::e($situacaoConclusaoLabel); ?>.</p>
            <p class="muted-row">
                Progresso: <?php echo Helpers::e(number_format((float) ($elegibilidade['percentual_progresso'] ?? $percentualProgresso), 2, ',', '.')); ?>%
                · Atividades corrigidas: <?php echo (int) ($elegibilidade['atividades_corrigidas'] ?? 0); ?>
                · Pendências: <?php echo (int) ($elegibilidade['atividades_pendentes'] ?? 0); ?>
            </p>
            <?php if (isset($elegibilidade['media_atividades']) && $elegibilidade['media_atividades'] !== null): ?>
                <p class="muted-row">Média das atividades: <?php echo Helpers::e(number_format((float) $elegibilidade['media_atividades'], 2, ',', '.')); ?></p>
            <?php endif; ?>
            <?php if (isset($elegibilidade['presenca_percentual']) && $elegibilidade['presenca_percentual'] !== null): ?>
                <p class="muted-row">Presença: <?php echo Helpers::e(number_format((float) $elegibilidade['presenca_percentual'], 2, ',', '.')); ?>%</p>
            <?php endif; ?>
            <?php if (isset($elegibilidade['avaliacao_nota']) && $elegibilidade['avaliacao_nota'] !== null): ?>
                <p class="muted-row">Avaliação: <?php echo Helpers::e(number_format((float) $elegibilidade['avaliacao_nota'], 2, ',', '.')); ?></p>
            <?php endif; ?>
            <?php if (!empty($elegibilidade['certificado_emitido'])): ?>
                <p class="muted-row">Seu certificado já foi emitido.</p>
            <?php endif; ?>
            <?php if (!empty($elegibilidadeMotivos)): ?>
                <?php foreach ($elegibilidadeMotivos as $motivo): ?>
                    <p class="muted-row"><?php echo Helpers::e($motivo); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
            <p class="muted-row">A conclusão exibida aqui não emite certificado automaticamente.</p>
        </div>
        <p style="margin-top: 12px;">
            <a class="button-link" href="/aluno/meus-cursos">Voltar para Meus cursos</a>
        </p>
    </aside>

    <div class="sala-virtual-main">
        <section class="status-card">
            <div class="panel-header">
                <div>
                    <h2>Curso inscrito</h2>
                    <p>Selecione a inscrição para continuar a navegação.</p>
                </div>
            </div>

            <?php if (!empty($inscricoes)): ?>
                <div class="pill-row">
                    <?php foreach ($inscricoes as $inscricaoItem): ?>
                        <?php
                        $inscricaoItemId = (int) $inscricaoItem['id'];
                        $inscricaoItemCursoId = (int) $inscricaoItem['curso_evento_id'];
                        $inscricaoItemTurmaId = !empty($inscricaoItem['turma_id']) ? (int) $inscricaoItem['turma_id'] : 0;
                        $inscricaoItemHref = '/aluno/cursos?inscricao_id=' . $inscricaoItemId . '&curso_id=' . $inscricaoItemCursoId . ($inscricaoItemTurmaId > 0 ? '&turma_id=' . $inscricaoItemTurmaId : '');
                        $inscricaoItemStatus = isset($inscricaoItem['status']) ? (string) $inscricaoItem['status'] : '';
                        ?>
                        <a class="pill<?php echo $inscricaoItemId === $inscricaoAtualId ? ' pill--active' : ''; ?>" href="<?php echo Helpers::e($inscricaoItemHref); ?>">
                            <?php echo Helpers::e($inscricaoItem['curso_nome']); ?>
                            <?php if (!empty($inscricaoItem['turma_nome'])): ?> · <?php echo Helpers::e($inscricaoItem['turma_nome']); ?><?php endif; ?>
                            <?php if ($inscricaoItemStatus !== ''): ?> · <?php echo Helpers::e($inscricaoItemStatus); ?><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($selected_atividade) || !empty($selected_aula) || !empty($selected_modulo)): ?>
                <article class="status-card sala-virtual-focus">
                    <strong>
                        <?php if (!empty($selected_atividade)): ?>
                            Atividade em destaque
                        <?php elseif (!empty($selected_aula)): ?>
                            Aula em destaque
                        <?php else: ?>
                            Módulo em destaque
                        <?php endif; ?>
                    </strong>
                    <span>
                        <?php
                        if (!empty($selected_atividade)) {
                            echo Helpers::e($selected_atividade['titulo']);
                        } elseif (!empty($selected_aula)) {
                            echo Helpers::e($selected_aula['titulo']);
                        } else {
                            echo Helpers::e($selected_modulo['titulo']);
                        }
                        ?>
                    </span>
                    <?php if (!empty($selected_atividade)): ?>
                        <?php if (!empty($selected_atividade['descricao'])): ?>
                            <p class="muted-row"><?php echo nl2br(Helpers::e($selected_atividade['descricao'])); ?></p>
                        <?php endif; ?>
                        <?php
                        $prazoAtividadeSelecionada = !empty($selected_atividade['prazo']) ? strtotime($selected_atividade['prazo']) : false;
                        $entregaSelecionadaStatus = !empty($selected_atividade['entrega_usuario_status']) ? (string) $selected_atividade['entrega_usuario_status'] : '';
                        $entregaSelecionadaEm = !empty($selected_atividade['entrega_usuario_entregue_em']) ? strtotime($selected_atividade['entrega_usuario_entregue_em']) : false;
                        $entregaDetalhada = isset($selected_atividade_entrega) && is_array($selected_atividade_entrega) ? $selected_atividade_entrega : null;
                        $entregaHistorico = !empty($entregaDetalhada['historico']) && is_array($entregaDetalhada['historico']) ? $entregaDetalhada['historico'] : array();
                        $entregaSelecionadaAtrasada = $prazoAtividadeSelecionada !== false && $entregaSelecionadaEm !== false && $entregaSelecionadaEm > $prazoAtividadeSelecionada;
                        $prazoEncerradoSemEntrega = $prazoAtividadeSelecionada !== false && $entregaSelecionadaEm === false && time() > $prazoAtividadeSelecionada;
                        $podeEnviarAtividade = $entregaSelecionadaStatus !== 'corrigida';
                        $textoBotaoAtividade = $entregaSelecionadaStatus === 'devolvida' ? 'Reenviar atividade' : 'Enviar resposta';
                        ?>
                        <div class="pill-row" style="margin-top: 10px;">
                            <span class="pill"><?php echo Helpers::e(!empty($tipoEntregaLabels[$selected_atividade['tipo_entrega']]) ? $tipoEntregaLabels[$selected_atividade['tipo_entrega']] : 'Texto'); ?></span>
                            <span class="pill"><?php echo !empty($selected_atividade['prazo']) ? Helpers::e(date('d/m/Y H:i', strtotime($selected_atividade['prazo']))) : 'Sem prazo'; ?></span>
                            <span class="pill"><?php echo Helpers::e(ucfirst((string) $selected_atividade['status'])); ?></span>
                            <?php if ($entregaSelecionadaAtrasada): ?>
                                <span class="pill pill--warning">Entrega atrasada</span>
                            <?php elseif ($prazoEncerradoSemEntrega): ?>
                                <span class="pill pill--warning">Prazo encerrado</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($selected_atividade['entrega_usuario_id'])): ?>
                            <div class="sala-bloco-extra">
                                <strong>Sua entrega</strong>
                                <p class="muted-row">Status: <?php echo Helpers::e(ucfirst((string) $selected_atividade['entrega_usuario_status'])); ?></p>
                                <?php if ($entregaSelecionadaStatus === 'devolvida'): ?>
                                    <p class="muted-row">Atividade devolvida para ajuste. Revise a resposta e reenvie quando estiver pronto.</p>
                                <?php elseif ($entregaSelecionadaStatus === 'corrigida'): ?>
                                    <p class="muted-row">Entrega corrigida.</p>
                                <?php elseif ($entregaSelecionadaAtrasada): ?>
                                    <p class="muted-row">Entrega enviada fora do prazo.</p>
                                <?php endif; ?>
                                <?php if ($selected_atividade['entrega_usuario_nota'] !== null): ?>
                                    <p class="muted-row">Nota: <?php echo Helpers::e(number_format((float) $selected_atividade['entrega_usuario_nota'], 2, ',', '.')); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($selected_atividade['entrega_usuario_feedback'])): ?>
                                    <p class="muted-row">Feedback: <?php echo nl2br(Helpers::e($selected_atividade['entrega_usuario_feedback'])); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($selected_atividade['entrega_usuario_entregue_em'])): ?>
                                    <p class="muted-row">Enviada em: <?php echo Helpers::e(date('d/m/Y H:i', strtotime($selected_atividade['entrega_usuario_entregue_em']))); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($selected_atividade['entrega_usuario_arquivo_caminho'])): ?>
                                    <p><a class="button-link button-link--ghost" href="/aluno/cursos/atividade/arquivo?entrega_id=<?php echo (int) $selected_atividade['entrega_usuario_id']; ?>">Baixar seu arquivo</a></p>
                                <?php endif; ?>
                                <?php if (!empty($entregaHistorico)): ?>
                                    <div class="sala-bloco-extra" style="margin-top: 16px;">
                                        <strong>Histórico da entrega</strong>
                                        <ul class="history-list">
                                            <?php foreach ($entregaHistorico as $item): ?>
                                                <li>
                                                    <?php
                                                    $acao = isset($item['acao']) ? (string) $item['acao'] : 'evento';
                                                    $historicoLabels = array(
                                                        'area_curso.atividade.entrega_criada' => 'Entrega criada',
                                                        'area_curso.atividade.entrega_reenviada' => 'Entrega reenviada',
                                                        'area_curso.atividade.entrega_corrigida' => 'Entrega corrigida',
                                                        'area_curso.atividade.entrega_devolvida' => 'Entrega devolvida',
                                                        'area_curso.atividade.entrega_bloqueada' => 'Entrega bloqueada',
                                                    );
                                                    ?>
                                                    <strong><?php echo Helpers::e($historicoLabels[$acao] ?? $acao); ?></strong>
                                                    <span><?php echo !empty($item['created_at']) ? Helpers::e(date('d/m/Y H:i', strtotime($item['created_at']))) : '—'; ?></span>
                                                    <?php if (!empty($item['usuario_nome'])): ?>
                                                        <small><?php echo Helpers::e($item['usuario_nome']); ?></small>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="sala-bloco-extra">
                            <strong><?php echo $entregaSelecionadaStatus === 'devolvida' ? 'Reenviar atividade' : 'Responder atividade'; ?></strong>
                            <?php if (!empty($selected_atividade['entrega_usuario_id']) && (string) $selected_atividade['entrega_usuario_status'] === 'corrigida'): ?>
                                <p class="muted-row">Esta entrega já foi corrigida.</p>
                            <?php else: ?>
                                <?php if ($prazoEncerradoSemEntrega): ?>
                                    <p class="muted-row">Prazo encerrado. A nova entrega será sinalizada como atrasada.</p>
                                <?php endif; ?>
                                <?php if (!empty($selected_atividade['entrega_usuario_id']) && (string) $selected_atividade['entrega_usuario_status'] === 'devolvida'): ?>
                                    <p class="muted-row">O professor devolveu a atividade para ajuste.</p>
                                <?php endif; ?>
                                <form method="post" action="/aluno/cursos/atividade/enviar" class="form-grid" enctype="multipart/form-data">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoAtualId; ?>">
                                    <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo (int) $turmaId; ?>">
                                    <input type="hidden" name="modulo_id" value="<?php echo !empty($selected_atividade['modulo_id']) ? (int) $selected_atividade['modulo_id'] : 0; ?>">
                                    <input type="hidden" name="aula_id" value="<?php echo !empty($selected_atividade['aula_id']) ? (int) $selected_atividade['aula_id'] : 0; ?>">
                                    <input type="hidden" name="atividade_id" value="<?php echo (int) $selected_atividade['id']; ?>">
                                    <?php if ($selected_atividade['tipo_entrega'] !== 'arquivo'): ?>
                                        <label class="full">Resposta<textarea name="resposta_texto" rows="5"><?php echo Helpers::e($selected_atividade['entrega_usuario_resposta_texto'] ?? ''); ?></textarea></label>
                                    <?php endif; ?>
                                    <?php if ($selected_atividade['tipo_entrega'] !== 'texto'): ?>
                                        <label class="full">Arquivo<input type="file" name="arquivo"></label>
                                    <?php endif; ?>
                                    <button type="submit" class="full"><?php echo Helpers::e($textoBotaoAtividade); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!empty($selected_aula) && !empty($selected_aula['conteudo'])): ?>
                        <p class="muted-row"><?php echo nl2br(Helpers::e($selected_aula['conteudo'])); ?></p>
                    <?php elseif (!empty($selected_modulo['descricao'])): ?>
                        <p class="muted-row"><?php echo nl2br(Helpers::e($selected_modulo['descricao'])); ?></p>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
        </section>

        <section class="panel sala-virtual-panel">
            <div class="panel-header">
                <div>
                    <h2>Módulos</h2>
                    <p>Conteúdo publicado da sua inscrição.</p>
                </div>
            </div>

            <?php if (!$temModulos): ?>
                <article class="status-card">
                    <strong>Nenhum módulo publicado ainda.</strong>
                    <span>Assim que o conteúdo for liberado, ele aparecerá aqui.</span>
                </article>
            <?php else: ?>
                <?php foreach ($modulos as $modulo): ?>
                    <?php
                    $moduloIdAtual = (int) $modulo['id'];
                    $moduloAberto = !empty($selected_modulo) && (int) $selected_modulo['id'] === $moduloIdAtual;
                    $moduloConcluido = in_array($moduloIdAtual, $modulosConcluidosIds, true);
                    $moduloStatus = isset($modulo['status']) ? (string) $modulo['status'] : 'publicado';
                    $aulas = !empty($modulo['aulas']) ? $modulo['aulas'] : array();
                    $materiaisModulo = !empty($modulo['materiais']) ? $modulo['materiais'] : array();
                    $linksModulo = !empty($modulo['links']) ? $modulo['links'] : array();
                    ?>
                    <details class="sala-modulo<?php echo $moduloAberto ? ' sala-modulo--aberto' : ''; ?>" <?php echo $moduloAberto ? 'open' : ''; ?>>
                        <summary>
                            <div class="sala-modulo__summary">
                                <strong><?php echo Helpers::e($modulo['titulo']); ?></strong>
                                <?php if (!empty($modulo['descricao'])): ?>
                                    <span><?php echo Helpers::e($modulo['descricao']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="pill-row">
                                <span class="pill"><?php echo isset($modulo['total_aulas']) ? (int) $modulo['total_aulas'] : count($aulas); ?> aulas</span>
                                <span class="pill"><?php echo isset($modulo['total_materiais']) ? (int) $modulo['total_materiais'] : count($materiaisModulo); ?> materiais</span>
                                <span class="pill"><?php echo isset($modulo['total_atividades']) ? (int) $modulo['total_atividades'] : 0; ?> atividades</span>
                                <span class="pill"><?php echo Helpers::e($moduloStatus); ?></span>
                                <?php if ($moduloConcluido): ?>
                                    <span class="pill pill--success">Concluído</span>
                                <?php endif; ?>
                            </div>
                        </summary>
                        <div class="sala-modulo__body">
                            <?php if (!empty($modulo['descricao'])): ?>
                                <p class="muted-row"><?php echo nl2br(Helpers::e($modulo['descricao'])); ?></p>
                            <?php endif; ?>

                            <?php if (empty($aulas)): ?>
                                <article class="status-card">
                                    <strong>Nenhuma aula publicada neste módulo.</strong>
                                    <span>Quando as aulas forem liberadas, elas aparecerão aqui.</span>
                                </article>
                            <?php else: ?>
                                <div class="sala-aulas-lista">
                                    <?php foreach ($aulas as $aula): ?>
                                        <?php
                                        $aulaIdAtual = (int) $aula['id'];
                                        $aulaConcluida = in_array($aulaIdAtual, $aulasConcluidasIds, true);
                                        $aulaTipo = !empty($aula['tipo']) ? (string) $aula['tipo'] : 'texto';
                                        $aulaStatus = isset($aula['status']) ? (string) $aula['status'] : 'publicado';
                                        $aulaUrl = '/aluno/cursos/material?material_id=' . $aulaIdAtual;
                                        $aulaMateriais = !empty($aula['materiais']) ? $aula['materiais'] : array();
                                        ?>
                                        <article class="sala-aula<?php echo $aulaConcluida ? ' sala-aula--concluida' : ''; ?><?php echo !empty($selected_aula) && (int) $selected_aula['id'] === $aulaIdAtual ? ' sala-aula--selecionada' : ''; ?>">
                                            <div class="sala-aula__header">
                                                <div>
                                                    <strong><?php echo Helpers::e($aula['titulo']); ?></strong>
                                                    <p class="muted-row"><?php echo !empty($aula['conteudo']) ? nl2br(Helpers::e($aula['conteudo'])) : 'Aula publicada para acompanhamento do conteúdo.'; ?></p>
                                                </div>
                                                <div class="pill-row">
                                                    <span class="pill"><?php echo Helpers::e($aulaTipo); ?></span>
                                                    <span class="pill"><?php echo Helpers::e($aulaStatus); ?></span>
                                                    <span class="pill"><?php echo isset($aula['total_atividades']) ? (int) $aula['total_atividades'] : 0; ?> atividades</span>
                                                    <?php if (!empty($aula['duracao_minutos'])): ?>
                                                        <span class="pill"><?php echo (int) $aula['duracao_minutos']; ?> min</span>
                                                    <?php endif; ?>
                                                    <?php if ($aulaConcluida): ?>
                                                        <span class="pill pill--success">Aula concluída</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if (!empty($aula['url_video'])): ?>
                                                <p style="margin-top: 12px;">
                                                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e($aula['url_video']); ?>" target="_blank" rel="noopener">Abrir conteúdo da aula</a>
                                                </p>
                                            <?php endif; ?>

                                            <div class="sala-aula__acoes">
                                                <?php if (!$aulaConcluida): ?>
                                                    <form method="post" action="/aluno/cursos/modulo/concluir-aula" class="form-grid">
                                                        <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoAtualId; ?>">
                                                        <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
                                                        <input type="hidden" name="turma_id" value="<?php echo (int) $turmaId; ?>">
                                                        <input type="hidden" name="modulo_id" value="<?php echo (int) $moduloIdAtual; ?>">
                                                        <input type="hidden" name="aula_id" value="<?php echo (int) $aulaIdAtual; ?>">
                                                        <button type="submit">Marcar como concluída</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="pill pill--success">Continue para a próxima aula</span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="sala-aula__materiais">
                                                <strong>Materiais da aula</strong>
                                                <?php if (empty($aulaMateriais)): ?>
                                                    <p class="muted-row">Nenhum material disponível nesta aula.</p>
                                                <?php else: ?>
                                                    <div class="sala-materiais-lista">
                                                        <?php foreach ($aulaMateriais as $material): ?>
                                                            <article class="sala-material">
                                                                <div>
                                                                    <strong><?php echo Helpers::e($material['titulo']); ?></strong>
                                                                    <p class="muted-row">
                                                                        <?php echo Helpers::e(sala_material_label($material, $tipoMaterialLabels)); ?>
                                                                        <?php if (!empty($material['descricao'])): ?> · <?php echo Helpers::e($material['descricao']); ?><?php endif; ?>
                                                                    </p>
                                                                </div>
                                                                <a class="button-link button-link--ghost" href="/aluno/cursos/material?material_id=<?php echo (int) $material['id']; ?>">Abrir</a>
                                                            </article>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="sala-aula__atividades">
                                                <strong>Atividades</strong>
                                                <?php if (empty($aula['atividades'])): ?>
                                                    <p class="muted-row">Nenhuma atividade disponível nesta aula.</p>
                                                <?php else: ?>
                                                    <div class="sala-materiais-lista">
                                                        <?php foreach ($aula['atividades'] as $atividade): ?>
                                                            <?php
                                                            $atividadeEntregaStatus = !empty($atividade['entrega_usuario_status']) ? (string) $atividade['entrega_usuario_status'] : '';
                                                            ?>
                                                            <article class="sala-material">
                                                                <div>
                                                                    <strong><?php echo Helpers::e($atividade['titulo']); ?></strong>
                                                                    <p class="muted-row">
                                                                        <?php echo Helpers::e(!empty($tipoEntregaLabels[$atividade['tipo_entrega']]) ? $tipoEntregaLabels[$atividade['tipo_entrega']] : 'Texto'); ?>
                                                                        <?php if (!empty($atividade['prazo'])): ?> · Prazo: <?php echo Helpers::e(date('d/m/Y H:i', strtotime($atividade['prazo']))); ?><?php endif; ?>
                                                                        <?php if ($atividadeEntregaStatus !== ''): ?> · Sua entrega: <?php echo Helpers::e(ucfirst($atividadeEntregaStatus)); ?><?php endif; ?>
                                                                    </p>
                                                                    <?php if (!empty($atividade['entrega_usuario_nota'])): ?>
                                                                        <p class="muted-row">Nota: <?php echo Helpers::e(number_format((float) $atividade['entrega_usuario_nota'], 2, ',', '.')); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <a class="button-link button-link--ghost" href="/aluno/cursos?inscricao_id=<?php echo (int) $inscricaoAtualId; ?>&curso_id=<?php echo (int) $cursoId; ?>&turma_id=<?php echo (int) $turmaId; ?>&modulo_id=<?php echo (int) $moduloIdAtual; ?>&aula_id=<?php echo (int) $aulaIdAtual; ?>&atividade_id=<?php echo (int) $atividade['id']; ?>">Responder atividade</a>
                                                            </article>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($materiaisModulo)): ?>
                                <div class="sala-bloco-extra">
                                    <strong>Materiais do módulo</strong>
                                    <div class="sala-materiais-lista">
                                        <?php foreach ($materiaisModulo as $material): ?>
                                            <article class="sala-material">
                                                <div>
                                                    <strong><?php echo Helpers::e($material['titulo']); ?></strong>
                                                    <p class="muted-row"><?php echo Helpers::e(sala_material_label($material, $tipoMaterialLabels)); ?></p>
                                                </div>
                                                <a class="button-link button-link--ghost" href="/aluno/cursos/material?material_id=<?php echo (int) $material['id']; ?>">Abrir</a>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($linksModulo)): ?>
                                <div class="sala-bloco-extra">
                                    <strong>Links do módulo</strong>
                                    <div class="sala-materiais-lista">
                                        <?php foreach ($linksModulo as $link): ?>
                                            <article class="sala-material">
                                                <div>
                                                    <strong><?php echo Helpers::e($link['titulo']); ?></strong>
                                                    <p class="muted-row">Link externo</p>
                                                </div>
                                                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($link['url']); ?>" target="_blank" rel="noopener">Abrir</a>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</section>
