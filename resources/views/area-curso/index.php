<?php use App\Core\Helpers; ?>
<?php // Cache-bust para sincronizaÃ§Ã£o de deploy. ?>

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
    'video_externo' => 'VÃ­deo externo',
    'embed_controlado' => 'Embed controlado',
);
$tipoEntregaLabels = array(
    'texto' => 'Texto',
    'arquivo' => 'Arquivo',
    'texto_ou_arquivo' => 'Texto ou arquivo',
);
$conteudoNovo = isset($conteudo_novo) && is_array($conteudo_novo) ? $conteudo_novo : array('ok' => false, 'modulos' => array());
$temConteudoNovo = !empty($conteudoNovo['ok']) && !empty($conteudoNovo['modulos']);
$resumoConteudoNovo = !empty($conteudoNovo['resumo']) && is_array($conteudoNovo['resumo']) ? $conteudoNovo['resumo'] : array();
$selectedAtividade = !empty($selected_atividade) ? $selected_atividade : null;
$elegibilidade = isset($elegibilidade) && is_array($elegibilidade) ? $elegibilidade : array();
$elegibilidadeMotivos = !empty($elegibilidade['motivos']) && is_array($elegibilidade['motivos']) ? $elegibilidade['motivos'] : array();
$situacaoConclusao = isset($elegibilidade['situacao']) ? (string) $elegibilidade['situacao'] : 'pendente';
$mapaSituacaoConclusao = array(
    'em_andamento' => 'Em andamento',
    'pendente' => 'Pendente',
    'apto' => 'Apto',
    'nao_apto' => 'NÃ£o apto',
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
            return 'VÃ­deo externo';
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
    <p><?php echo Helpers::e($cursoNome); ?> Â· <?php echo Helpers::e($turmaNome); ?></p>
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

<?php if (!$temConteudoNovo): ?>
<section class="sala-virtual-layout">
    <aside class="status-card sala-virtual-summary">
        <strong>Progresso geral</strong>
        <span><?php echo Helpers::e(number_format($percentualProgresso, 2, ',', '.')); ?>%</span>
        <div class="sala-progresso">
            <div class="sala-progresso__meta">
                <span><?php echo (int) $aulasConcluidas; ?> de <?php echo (int) $totalAulasPublicadas; ?> aulas concluÃ­das</span>
                <span><?php echo (int) $modulosConcluidos; ?> de <?php echo (int) $totalModulosPublicados; ?> mÃ³dulos concluÃ­dos</span>
            </div>
            <div class="sala-progresso__bar">
                <span style="width: <?php echo Helpers::e(number_format(max(0, min(100, $percentualProgresso)), 2, '.', '')); ?>%;"></span>
            </div>
        </div>
        <div class="pill-row">
            <span class="pill"><?php echo (int) $totalModulosPublicados; ?> mÃ³dulos publicados</span>
            <span class="pill"><?php echo (int) $totalAulasPublicadas; ?> aulas publicadas</span>
            <span class="pill<?php echo $inscricaoPodeAcessar ? ' pill--success' : ''; ?>"><?php echo Helpers::e($inscricaoStatus !== '' ? $inscricaoStatus : 'ativa'); ?></span>
        </div>
        <p class="muted-row">Acompanhe seu progresso mÃ­nimo e marque as aulas concluÃ­das conforme avanÃ§a no curso.</p>
        <div class="sala-bloco-extra">
            <strong>Minha conclusÃ£o</strong>
            <p class="muted-row">SituaÃ§Ã£o atual: <?php echo Helpers::e($situacaoConclusaoLabel); ?>.</p>
            <p class="muted-row">
                Progresso: <?php echo Helpers::e(number_format((float) ($elegibilidade['percentual_progresso'] ?? $percentualProgresso), 2, ',', '.')); ?>%
                Â· Atividades corrigidas: <?php echo (int) ($elegibilidade['atividades_corrigidas'] ?? 0); ?>
                Â· PendÃªncias: <?php echo (int) ($elegibilidade['atividades_pendentes'] ?? 0); ?>
            </p>
            <?php if (isset($elegibilidade['media_atividades']) && $elegibilidade['media_atividades'] !== null): ?>
                <p class="muted-row">MÃ©dia das atividades: <?php echo Helpers::e(number_format((float) $elegibilidade['media_atividades'], 2, ',', '.')); ?></p>
            <?php endif; ?>
            <?php if (isset($elegibilidade['presenca_percentual']) && $elegibilidade['presenca_percentual'] !== null): ?>
                <p class="muted-row">PresenÃ§a: <?php echo Helpers::e(number_format((float) $elegibilidade['presenca_percentual'], 2, ',', '.')); ?>%</p>
            <?php endif; ?>
            <?php if (isset($elegibilidade['avaliacao_nota']) && $elegibilidade['avaliacao_nota'] !== null): ?>
                <p class="muted-row">AvaliaÃ§Ã£o: <?php echo Helpers::e(number_format((float) $elegibilidade['avaliacao_nota'], 2, ',', '.')); ?></p>
            <?php endif; ?>
            <?php if (!empty($elegibilidade['certificado_emitido'])): ?>
                <p class="muted-row">Seu certificado jÃ¡ foi emitido.</p>
            <?php endif; ?>
            <?php if (!empty($elegibilidadeMotivos)): ?>
                <?php foreach ($elegibilidadeMotivos as $motivo): ?>
                    <p class="muted-row"><?php echo Helpers::e($motivo); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
            <p class="muted-row">A conclusÃ£o exibida aqui nÃ£o emite certificado automaticamente.</p>
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
                    <p>Selecione a inscriÃ§Ã£o para continuar a navegaÃ§Ã£o.</p>
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
                            <?php if (!empty($inscricaoItem['turma_nome'])): ?> Â· <?php echo Helpers::e($inscricaoItem['turma_nome']); ?><?php endif; ?>
                            <?php if ($inscricaoItemStatus !== ''): ?> Â· <?php echo Helpers::e($inscricaoItemStatus); ?><?php endif; ?>
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
                            MÃ³dulo em destaque
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
                                        <strong>HistÃ³rico da entrega</strong>
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
                                                    <span><?php echo !empty($item['created_at']) ? Helpers::e(date('d/m/Y H:i', strtotime($item['created_at']))) : 'â€”'; ?></span>
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
                                <p class="muted-row">Esta entrega jÃ¡ foi corrigida.</p>
                            <?php else: ?>
                                <?php if ($prazoEncerradoSemEntrega): ?>
                                    <p class="muted-row">Prazo encerrado. A nova entrega serÃ¡ sinalizada como atrasada.</p>
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
<?php endif; ?>

<?php if ($temConteudoNovo): ?>
        <section class="panel sala-virtual-panel sala-curso-conteudo">
            <div class="panel-header sala-curso-conteudo__header">
                <div>
                    <h2>Conteúdo do curso</h2>
                    <p>Avance pelos módulos abaixo. Os itens obrigatórios precisam ser concluídos para a emissão do certificado, quando o curso possuir certificação.</p>
                </div>
                <a class="button-link button-link--ghost" href="/aluno/meus-cursos">Voltar para Meus cursos</a>
            </div>

            <?php $resumoNovo = $resumoConteudoNovo; ?>
            <article class="status-card sala-curso-conteudo__resumo">
                <strong>Progresso do conteúdo unificado</strong>
                <span class="sala-curso-conteudo__resumo-percentual"><?php echo Helpers::e(number_format((float) ($resumoNovo['percentual'] ?? 0), 2, ',', '.')); ?>%</span>
                <div class="pill-row" style="margin-top:12px;">
                    <span class="pill"><?php echo (int) ($resumoNovo['concluidos_obrigatorios'] ?? 0); ?> de <?php echo (int) ($resumoNovo['total_obrigatorios'] ?? 0); ?> obrigatórios concluídos</span>
                    <span class="pill"><?php echo (int) ($resumoNovo['avaliacoes_pendentes'] ?? 0); ?> avaliações textuais pendentes</span>
                    <span class="pill"><?php echo (int) ($resumoNovo['itens_pendentes'] ?? 0); ?> itens pendentes</span>
                    <span class="pill pill--<?php echo Helpers::e($resumoNovo['status_class'] ?? 'neutral'); ?>"><?php echo Helpers::e($resumoNovo['status_label'] ?? 'Pendente'); ?></span>
                </div>
                <p class="muted-row">O certificado considera os itens obrigatórios do curso, avaliações textuais, presença e demais critérios definidos.</p>
                <?php if (!empty($resumoNovo['avaliacoes_pendentes'])): ?>
                    <p class="muted-row">As avaliações textuais serão corrigidas pelo professor. Após a correção, sua nota e feedback aparecerão nesta página.</p>
                <?php endif; ?>
            </article>

            <?php foreach ($conteudoNovo['modulos'] as $moduloConteudo): ?>
                <?php
                $itensConteudo = !empty($moduloConteudo['itens']) ? $moduloConteudo['itens'] : array();
                $moduloPercentual = isset($moduloConteudo['percentual_conclusao']) ? (float) $moduloConteudo['percentual_conclusao'] : 0;
                ?>
                <article class="status-card sala-curso-conteudo__modulo">
                    <div class="sala-curso-conteudo__modulo-header">
                        <div>
                            <strong><?php echo Helpers::textoLms($moduloConteudo['titulo'] ?? 'Módulo'); ?></strong>
                            <?php if (!empty($moduloConteudo['descricao'])): ?>
                                <span><?php echo nl2br(Helpers::textoLmsMultilinha($moduloConteudo['descricao'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="pill-row">
                            <span class="pill"><?php echo (int) ($moduloConteudo['concluidos_obrigatorios'] ?? 0); ?> de <?php echo (int) ($moduloConteudo['total_obrigatorios'] ?? 0); ?> obrigatórios concluídos</span>
                            <span class="pill"><?php echo (int) ($moduloConteudo['total_itens'] ?? 0); ?> itens</span>
                            <span class="pill"><?php echo Helpers::e(number_format(max(0, min(100, $moduloPercentual)), 2, ',', '.')); ?>%</span>
                            <span class="pill pill--<?php echo Helpers::e($moduloConteudo['status_class'] ?? 'neutral'); ?>"><?php echo Helpers::e($moduloConteudo['status_label'] ?? 'Pendente'); ?></span>
                        </div>
                    </div>

                    <?php if (empty($itensConteudo)): ?>
                        <p class="muted-row">Sem itens publicados neste módulo.</p>
                    <?php else: ?>
                        <div class="sala-curso-conteudo__itens">
                            <?php foreach ($itensConteudo as $itemConteudo): ?>
                                <?php
                                $tipo = (string) ($itemConteudo['tipo'] ?? 'texto');
                                $tipoLabel = !empty($itemConteudo['tipo_label']) ? (string) $itemConteudo['tipo_label'] : Helpers::tipoConteudoLms($tipo);
                                $statusClass = !empty($itemConteudo['status_class']) ? (string) $itemConteudo['status_class'] : 'neutral';
                                $statusLabel = !empty($itemConteudo['status_label']) ? (string) $itemConteudo['status_label'] : Helpers::statusLms((string) ($itemConteudo['progresso_aluno']['status'] ?? 'nao_iniciado'));
                                $detalheItem = !empty($itemConteudo['detalhe']) && is_array($itemConteudo['detalhe']) ? $itemConteudo['detalhe'] : array();
                                $acaoUrl = !empty($itemConteudo['acao_url']) ? (string) $itemConteudo['acao_url'] : '';
                                $detalhesUrl = !empty($itemConteudo['detalhes_url']) ? (string) $itemConteudo['detalhes_url'] : '';
                                $obrigatorio = !empty($itemConteudo['obrigatorio']);
                                ?>
                                <article class="sala-curso-conteudo__item sala-curso-conteudo__item--<?php echo Helpers::e($statusClass); ?>">
                                    <div class="sala-curso-conteudo__item-body">
                                        <div class="pill-row">
                                            <span class="pill"><?php echo Helpers::e($tipoLabel); ?></span>
                                            <span class="pill<?php echo $obrigatorio ? ' pill--alert' : ''; ?>"><?php echo $obrigatorio ? 'Obrigatório' : 'Opcional'; ?></span>
                                            <span class="pill pill--<?php echo Helpers::e($statusClass); ?>"><?php echo Helpers::e($statusLabel); ?></span>
                                        </div>
                                        <strong><?php echo Helpers::textoLms($itemConteudo['titulo'] ?? ''); ?></strong>
                                        <?php if (!empty($itemConteudo['descricao_curta'])): ?>
                                            <p class="muted-row"><?php echo nl2br(Helpers::textoLmsMultilinha($itemConteudo['descricao_curta'])); ?></p>
                                        <?php endif; ?>

                                        <?php if ($tipo === 'arquivo'): ?>
                                            <p class="muted-row">
                                                <?php if (!empty($detalheItem['nome_original'])): ?>Arquivo: <?php echo Helpers::textoLms($detalheItem['nome_original']); ?><?php endif; ?>
                                                <?php if (!empty($detalheItem['extensao'])): ?> · Extensão: <?php echo Helpers::textoLms($detalheItem['extensao']); ?><?php endif; ?>
                                                <?php if (!empty($detalheItem['tamanho_bytes'])): ?> · Tamanho: <?php echo Helpers::e(number_format(((int) $detalheItem['tamanho_bytes']) / 1024, 2, ',', '.')); ?> KB<?php endif; ?>
                                            </p>
                                        <?php elseif ($tipo === 'link'): ?>
                                            <p class="muted-row">O link externo será aberto em nova aba.</p>
                                        <?php elseif ($tipo === 'video'): ?>
                                            <p class="muted-row">Assista ao vídeo dentro da área do item para registrar o acesso.</p>
                                        <?php elseif ($tipo === 'avaliacao_textual'): ?>
                                            <p class="muted-row">
                                                <?php if (!empty($detalheItem['prazo'])): ?>Prazo: <?php echo Helpers::e(date('d/m/Y H:i', strtotime($detalheItem['prazo']))); ?> · <?php endif; ?>
                                                <?php if (!empty($itemConteudo['avaliacao_entrega']['nota']) && $itemConteudo['avaliacao_entrega']['nota'] !== ''): ?>Nota: <?php echo Helpers::e(number_format((float) $itemConteudo['avaliacao_entrega']['nota'], 2, ',', '.')); ?> · <?php endif; ?>
                                                Status da resposta: <?php echo Helpers::e(Helpers::statusLms((string) ($itemConteudo['avaliacao_entrega']['status'] ?? 'aguardando_envio'))); ?>
                                            </p>
                                            <?php if (!empty($itemConteudo['avaliacao_entrega']['feedback'])): ?>
                                                <p class="muted-row">Feedback: <?php echo nl2br(Helpers::textoLmsMultilinha($itemConteudo['avaliacao_entrega']['feedback'])); ?></p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="sala-curso-conteudo__item-actions">
                                        <?php if ($tipo === 'etiqueta'): ?>
                                            <?php if (!empty($detalhesUrl)): ?><a class="button-link button-link--ghost" href="<?php echo Helpers::e($detalhesUrl); ?>">Ler orientação</a><?php endif; ?>
                                        <?php elseif ($tipo === 'arquivo'): ?>
                                            <?php if (!empty($acaoUrl)): ?><a class="button-link" href="<?php echo Helpers::e($acaoUrl); ?>">Baixar arquivo</a><?php endif; ?>
                                            <?php if (!empty($detalhesUrl)): ?><a class="button-link button-link--ghost" href="<?php echo Helpers::e($detalhesUrl); ?>">Abrir detalhes</a><?php endif; ?>
                                        <?php elseif ($tipo === 'link'): ?>
                                            <?php if (!empty($acaoUrl)): ?><a class="button-link" href="<?php echo Helpers::e($acaoUrl); ?>">Acessar link</a><?php endif; ?>
                                        <?php elseif ($tipo === 'video'): ?>
                                            <?php if (!empty($acaoUrl)): ?><a class="button-link" href="<?php echo Helpers::e($acaoUrl); ?>">Assistir vídeo</a><?php endif; ?>
                                        <?php elseif ($tipo === 'avaliacao_textual'): ?>
                                            <?php if (!empty($detalhesUrl)): ?><a class="button-link" href="<?php echo Helpers::e($detalhesUrl); ?>"><?php echo !empty($itemConteudo['avaliacao_entrega']) ? 'Ver avaliação' : 'Responder avaliação'; ?></a><?php endif; ?>
                                        <?php else: ?>
                                            <?php if (!empty($detalhesUrl)): ?><a class="button-link button-link--ghost" href="<?php echo Helpers::e($detalhesUrl); ?>">Abrir conteúdo</a><?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
<?php endif; ?>
