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
$cursoNome = Helpers::normalizarTextoLms(isset($curso['nome']) ? $curso['nome'] : '');
$turmaNome = Helpers::normalizarTextoLms(!empty($turma['nome']) ? $turma['nome'] : '');
$cursoTurmaTitulo = $cursoNome !== '' ? $cursoNome : 'Curso';
if ($turmaNome !== '') {
    $cursoTurmaTitulo .= ' - ' . $turmaNome;
}
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
    'nao_apto' => 'Não apto',
    'certificado_emitido' => 'Certificado emitido',
);
$situacaoConclusaoLabel = isset($mapaSituacaoConclusao[$situacaoConclusao]) ? $mapaSituacaoConclusao[$situacaoConclusao] : 'Pendente';
$mapaStatusIconesCompactos = array(
    'success' => 'check-circle',
    'warning' => 'alert-circle',
    'danger' => 'x-circle',
    'info' => 'clock-3',
    'neutral' => 'clock-3',
);

if (!function_exists('aluno_meta_icone_svg')) {
    function aluno_meta_icone_svg($icone, $titulo, $classeExtra = '')
    {
        $titulo = Helpers::e((string) $titulo);
        $classeExtra = trim((string) $classeExtra);

        $svg = '';
        if ($icone === 'check-circle') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/><circle cx="12" cy="12" r="9"/></svg>';
        } elseif ($icone === 'file-text') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M8 13h8"/><path d="M8 17h6"/></svg>';
        } elseif ($icone === 'alert-circle') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 8v5"/><path d="M12 16h.01"/><circle cx="12" cy="12" r="9"/></svg>';
        } elseif ($icone === 'clock-3') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/><path d="M12 12l-2-2"/><path d="M12 3v2"/><path d="M21 12h-2"/><path d="M5 12H3"/></svg>';
        } elseif ($icone === 'list') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 6h12"/><path d="M8 12h12"/><path d="M8 18h12"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg>';
        } elseif ($icone === 'chart-pie') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 9 9h-9z"/><path d="M12 3v9h9"/></svg>';
        } elseif ($icone === 'play-circle') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M10 8l7 4-7 4z"/></svg>';
        } elseif ($icone === 'download') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3v10"/><path d="M8 10l4 4 4-4"/><path d="M5 19h14"/></svg>';
        } elseif ($icone === 'external-link') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 5h5v5"/><path d="M10 14 19 5"/><path d="M19 13v6H5V5h6"/></svg>';
        } elseif ($icone === 'square-pen') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 4h16v16H4z"/><path d="M9 15l6-6 2 2-6 6-3 1z"/></svg>';
        } elseif ($icone === 'info') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 10v6"/><path d="M12 7h.01"/></svg>';
        } elseif ($icone === 'lock') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';
        } elseif ($icone === 'circle') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="8"/></svg>';
        } elseif ($icone === 'hourglass') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 3h12"/><path d="M6 21h12"/><path d="M8 3c0 4 4 5 4 9s-4 5-4 9"/><path d="M16 3c0 4-4 5-4 9s4 5 4 9"/></svg>';
        } elseif ($icone === 'x-circle') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6"/><path d="M15 9l-6 6"/></svg>';
        } elseif ($icone === 'replay') {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 7v5h5"/><path d="M20 17a9 9 0 1 1-2.2-9.1L16 10"/></svg>';
        } else {
            $svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/></svg>';
        }

        return '<span class="aluno-card-meta-icon ' . Helpers::e($classeExtra) . '" title="' . $titulo . '" aria-hidden="true">' . $svg . '</span>';
    }
}

if (!function_exists('aluno_meta_inline_item')) {
    function aluno_meta_inline_item($icone, $valor, $titulo, $ariaLabel, $classe = '', $mostrarValor = true)
    {
        $classe = trim('aluno-meta-inline-item ' . $classe);
        $iconeHtml = aluno_meta_icone_svg($icone, $titulo);
        $valorHtml = $mostrarValor ? '<span>' . Helpers::e((string) $valor) . '</span>' : '<span class="sr-only">' . Helpers::e((string) $valor) . '</span>';
        return '<span class="' . Helpers::e($classe) . '" title="' . Helpers::e((string) $titulo) . '" aria-label="' . Helpers::e((string) $ariaLabel) . '">' . $iconeHtml . $valorHtml . '</span>';
    }
}

if (!function_exists('aluno_meta_icone_svg_bruto')) {
    function aluno_meta_icone_svg_bruto($icone)
    {
        if ($icone === 'check-circle') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/><circle cx="12" cy="12" r="9"/></svg>';
        }
        if ($icone === 'file-text') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M8 13h8"/><path d="M8 17h6"/></svg>';
        }
        if ($icone === 'alert-circle') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 8v5"/><path d="M12 16h.01"/><circle cx="12" cy="12" r="9"/></svg>';
        }
        if ($icone === 'clock-3') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/><path d="M12 12l-2-2"/><path d="M12 3v2"/><path d="M21 12h-2"/><path d="M5 12H3"/></svg>';
        }
        if ($icone === 'list') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 6h12"/><path d="M8 12h12"/><path d="M8 18h12"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg>';
        }
        if ($icone === 'chart-pie') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 9 9h-9z"/><path d="M12 3v9h9"/></svg>';
        }
        if ($icone === 'play-circle') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M10 8l7 4-7 4z"/></svg>';
        }
        if ($icone === 'download') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3v10"/><path d="M8 10l4 4 4-4"/><path d="M5 19h14"/></svg>';
        }
        if ($icone === 'external-link') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 5h5v5"/><path d="M10 14 19 5"/><path d="M19 13v6H5V5h6"/></svg>';
        }
        if ($icone === 'square-pen') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 4h16v16H4z"/><path d="M9 15l6-6 2 2-6 6-3 1z"/></svg>';
        }
        if ($icone === 'info') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 10v6"/><path d="M12 7h.01"/></svg>';
        }
        if ($icone === 'lock') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';
        }
        if ($icone === 'circle') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="8"/></svg>';
        }
        if ($icone === 'hourglass') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 3h12"/><path d="M6 21h12"/><path d="M8 3c0 4 4 5 4 9s-4 5-4 9"/><path d="M16 3c0 4-4 5-4 9s4 5 4 9"/></svg>';
        }
        if ($icone === 'x-circle') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6"/><path d="M15 9l-6 6"/></svg>';
        }
        if ($icone === 'replay') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 7v5h5"/><path d="M20 17a9 9 0 1 1-2.2-9.1L16 10"/></svg>';
        }
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/></svg>';
    }
}

if (!function_exists('aluno_meta_pill_v2')) {
    function aluno_meta_pill_v2($icone, $valor, $titulo, $ariaLabel, $classe = '', $mostrarValor = true)
    {
        $classe = trim('aluno-meta-pill-v2 ' . $classe);
        $iconeSvg = aluno_meta_icone_svg_bruto($icone);
        $valorHtml = $mostrarValor ? '<span class="aluno-meta-value-v2">' . Helpers::e((string) $valor) . '</span>' : '<span class="sr-only">' . Helpers::e((string) $valor) . '</span>';
        return '<span class="' . Helpers::e($classe) . '" title="' . Helpers::e((string) $titulo) . '" aria-label="' . Helpers::e((string) $ariaLabel) . '"><span class="aluno-meta-icon-v2" aria-hidden="true">' . $iconeSvg . '</span>' . $valorHtml . '</span>';
    }
}

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
    <h1><?php echo Helpers::e($cursoTurmaTitulo); ?></h1>
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
                - Atividades corrigidas: <?php echo (int) ($elegibilidade['atividades_corrigidas'] ?? 0); ?>
                - Pendências: <?php echo (int) ($elegibilidade['atividades_pendentes'] ?? 0); ?>
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
                            <?php if (!empty($inscricaoItem['turma_nome'])): ?> - <?php echo Helpers::e($inscricaoItem['turma_nome']); ?><?php endif; ?>
                            <?php if ($inscricaoItemStatus !== ''): ?> - <?php echo Helpers::e($inscricaoItemStatus); ?><?php endif; ?>
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
                        <div class="muted-row"><?php echo Helpers::renderSafeHtml($selected_atividade['descricao'], 'full'); ?></div>
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
                                                    <span><?php echo !empty($item['created_at']) ? Helpers::e(date('d/m/Y H:i', strtotime($item['created_at']))) : '-'; ?></span>
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
                        <div class="muted-row js-conteudo-texto-audio" data-audio-texto="1"><?php echo Helpers::renderSafeHtml($selected_aula['conteudo'], 'full'); ?></div>
                    <?php elseif (!empty($selected_modulo['descricao'])): ?>
                        <div class="muted-row"><?php echo Helpers::renderSafeHtml($selected_modulo['descricao'], 'basic'); ?></div>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
        </section>
<?php endif; ?>

<?php if ($temConteudoNovo): ?>
        <section class="panel sala-virtual-panel sala-curso-conteudo">
            <?php $resumoNovo = $resumoConteudoNovo; ?>
            <article class="status-card sala-curso-conteudo__resumo">
                <div class="conteudo-item-progress__header">
                    <div class="conteudo-item-progress__headline">
                        <div class="conteudo-item-progress__title">
                            <strong>Progresso</strong>
                            <span class="conteudo-item-progress__percentual"><?php echo Helpers::e(number_format((float) ($resumoNovo['percentual'] ?? 0), 2, ',', '.')); ?>%</span>
                        </div>
                        <details class="conteudo-item-progress__info">
                            <summary aria-label="Informações sobre o certificado" title="Informações sobre o certificado">
                                <span aria-hidden="true">i</span>
                            </summary>
                            <div class="conteudo-item-progress__info-panel" role="note">
                                O certificado considera os itens obrigatórios do curso, avaliações textuais, presença e demais critérios definidos.
                            </div>
                        </details>
                    </div>
                </div>
                <div class="aluno-meta-row-v2 aluno-progress-meta-v2" aria-label="Resumo do progresso">
                    <?php echo aluno_meta_pill_v2('check-circle', (int) ($resumoNovo['concluidos_obrigatorios'] ?? 0) . '/' . (int) ($resumoNovo['total_obrigatorios'] ?? 0), (int) ($resumoNovo['concluidos_obrigatorios'] ?? 0) . ' de ' . (int) ($resumoNovo['total_obrigatorios'] ?? 0) . ' obrigatórios concluídos', (int) ($resumoNovo['concluidos_obrigatorios'] ?? 0) . ' de ' . (int) ($resumoNovo['total_obrigatorios'] ?? 0) . ' obrigatórios concluídos', 'aluno-meta-ok'); ?>
                    <?php echo aluno_meta_pill_v2('file-text', (int) ($resumoNovo['avaliacoes_pendentes'] ?? 0), (int) ($resumoNovo['avaliacoes_pendentes'] ?? 0) . ' avaliações textuais pendentes', (int) ($resumoNovo['avaliacoes_pendentes'] ?? 0) . ' avaliações textuais pendentes', 'aluno-meta-text'); ?>
                    <?php echo aluno_meta_pill_v2('alert-circle', (int) ($resumoNovo['itens_pendentes'] ?? 0), (int) ($resumoNovo['itens_pendentes'] ?? 0) . ' itens pendentes', (int) ($resumoNovo['itens_pendentes'] ?? 0) . ' itens pendentes', 'aluno-meta-alert'); ?>
                    <?php echo aluno_meta_pill_v2(isset($mapaStatusIconesCompactos[$resumoNovo['status_class'] ?? 'neutral']) ? $mapaStatusIconesCompactos[$resumoNovo['status_class'] ?? 'neutral'] : 'clock-3', '', 'Status: ' . ($resumoNovo['status_label'] ?? 'Pendente'), 'Status: ' . ($resumoNovo['status_label'] ?? 'Pendente'), 'aluno-meta-status', false); ?>
                </div>
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
                                <div><?php echo Helpers::renderSafeHtml($moduloConteudo['descricao'], 'basic'); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="aluno-meta-row-v2 aluno-modulo-meta-v2" aria-label="Resumo do módulo">
                            <?php echo aluno_meta_pill_v2('check-circle', (int) ($moduloConteudo['concluidos_obrigatorios'] ?? 0) . '/' . (int) ($moduloConteudo['total_obrigatorios'] ?? 0), (int) ($moduloConteudo['concluidos_obrigatorios'] ?? 0) . ' de ' . (int) ($moduloConteudo['total_obrigatorios'] ?? 0) . ' obrigatórios concluídos neste módulo', (int) ($moduloConteudo['concluidos_obrigatorios'] ?? 0) . ' de ' . (int) ($moduloConteudo['total_obrigatorios'] ?? 0) . ' obrigatórios concluídos neste módulo', 'aluno-meta-ok'); ?>
                            <?php echo aluno_meta_pill_v2('list', (int) ($moduloConteudo['total_itens'] ?? 0), (int) ($moduloConteudo['total_itens'] ?? 0) . ' itens neste módulo', (int) ($moduloConteudo['total_itens'] ?? 0) . ' itens neste módulo', 'aluno-meta-list'); ?>
                            <?php echo aluno_meta_pill_v2('chart-pie', number_format(max(0, min(100, $moduloPercentual)), 2, ',', '.') . '%', number_format(max(0, min(100, $moduloPercentual)), 2, ',', '.') . ' por cento concluído neste módulo', number_format(max(0, min(100, $moduloPercentual)), 2, ',', '.') . ' por cento concluído neste módulo', 'aluno-meta-progress'); ?>
                            <?php echo aluno_meta_pill_v2(isset($mapaStatusIconesCompactos[$moduloConteudo['status_class'] ?? 'neutral']) ? $mapaStatusIconesCompactos[$moduloConteudo['status_class'] ?? 'neutral'] : 'clock-3', '', 'Status: ' . ($moduloConteudo['status_label'] ?? 'Pendente'), 'Status: ' . ($moduloConteudo['status_label'] ?? 'Pendente'), 'aluno-meta-status', false); ?>
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
                                $tipoIcone = 'info';
                                if ($tipo === 'video') {
                                    $tipoIcone = 'play-circle';
                                } elseif ($tipo === 'texto') {
                                    $tipoIcone = 'file-text';
                                } elseif ($tipo === 'arquivo') {
                                    $tipoIcone = 'download';
                                } elseif ($tipo === 'link') {
                                    $tipoIcone = 'external-link';
                                } elseif ($tipo === 'avaliacao_textual') {
                                    $tipoIcone = 'square-pen';
                                } elseif ($tipo === 'etiqueta') {
                                    $tipoIcone = 'info';
                                }
                                $statusIcone = 'clock-3';
                                $statusTextoMinusculo = Helpers::normalizarTextoLms(mb_strtolower($statusLabel, 'UTF-8'));
                                if (strpos($statusTextoMinusculo, 'devolv') !== false) {
                                    $statusIcone = 'replay';
                                } elseif (strpos($statusTextoMinusculo, 'corre') !== false || strpos($statusTextoMinusculo, 'aguard') !== false) {
                                    $statusIcone = 'hourglass';
                                } elseif (strpos($statusTextoMinusculo, 'aprov') !== false || strpos($statusTextoMinusculo, 'conclu') !== false) {
                                    $statusIcone = 'check-circle';
                                } elseif (strpos($statusTextoMinusculo, 'reprov') !== false) {
                                    $statusIcone = 'x-circle';
                                } elseif (strpos($statusTextoMinusculo, 'pend') !== false) {
                                    $statusIcone = 'alert-circle';
                                } elseif (strpos($statusTextoMinusculo, 'andamento') !== false || strpos($statusTextoMinusculo, 'acess') !== false) {
                                    $statusIcone = 'clock-3';
                                }
                                ?>
                                <article class="sala-curso-conteudo__item sala-curso-conteudo__item--<?php echo Helpers::e($statusClass); ?>">
                                    <div class="sala-curso-conteudo__item-body">
                                        <div class="aluno-meta-row-v2 aluno-item-meta-v2" aria-label="Resumo do item">
                                            <?php echo aluno_meta_pill_v2($tipoIcone, '', 'Tipo: ' . $tipoLabel, 'Tipo: ' . $tipoLabel, 'aluno-meta-' . $tipo, false); ?>
                                            <?php echo aluno_meta_pill_v2($obrigatorio ? 'lock' : 'circle', '', $obrigatorio ? 'Item obrigatório' : 'Item opcional', $obrigatorio ? 'Item obrigatório' : 'Item opcional', $obrigatorio ? 'aluno-meta-required' : 'aluno-meta-optional', false); ?>
                                            <?php echo aluno_meta_pill_v2($statusIcone, '', 'Status: ' . $statusLabel, 'Status: ' . $statusLabel, 'aluno-meta-status', false); ?>
                                        </div>
                                        <strong><?php echo Helpers::textoLms($itemConteudo['titulo'] ?? ''); ?></strong>
                                        <?php if (!empty($itemConteudo['descricao_curta'])): ?>
                                            <div class="muted-row"><?php echo Helpers::renderSafeHtml($itemConteudo['descricao_curta'], 'basic'); ?></div>
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








