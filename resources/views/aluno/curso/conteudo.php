<?php use App\Core\Helpers; use App\Core\Session; use App\Support\HtmlEmbedRenderer; ?>
<?php
if (!isset($frontend_template)) { try { $frontend_template = (new \App\Services\ConfiguracaoGlobalService())->templateVisualPortal(); } catch (\Throwable $e) { $frontend_template = 'v1'; } }
if ((string) $frontend_template === 'v4-claude') { require BASE_PATH . '/resources/views/v4-claude/aluno/curso/conteudo.php'; return; }
if (!function_exists('aluno_conteudo_valor')) {
    function aluno_conteudo_valor(array $array, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array) && $array[$key] !== null && $array[$key] !== '') {
                return $array[$key];
            }
        }

        return $default;
    }
}

$inscricaoId = isset($inscricao['id']) ? (int) $inscricao['id'] : 0;
$cursoId = isset($inscricao['curso_evento_id']) ? (int) $inscricao['curso_evento_id'] : 0;
$turmaId = isset($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
$cursoNome = Helpers::normalizarTextoLms(isset($curso['nome']) ? $curso['nome'] : '');
$turmaNome = Helpers::normalizarTextoLms(!empty($turma['nome']) ? $turma['nome'] : '');
$cursoTitulo = $cursoNome !== '' ? $cursoNome : 'Curso';
$modulo = isset($conteudo_modulo) && is_array($conteudo_modulo) ? $conteudo_modulo : array();
$item = isset($conteudo_item) && is_array($conteudo_item) ? $conteudo_item : array();
$detalhe = isset($conteudo_detalhe) && is_array($conteudo_detalhe) ? $conteudo_detalhe : array();
$resumo = isset($conteudo_resumo) && is_array($conteudo_resumo) ? $conteudo_resumo : array();
$moduloId = (int) ($modulo['id'] ?? 0);
$itemId = (int) ($item['id'] ?? 0);
$itemTitulo = Helpers::normalizarTextoLms((string) ($item['titulo'] ?? 'Conteúdo'));
$moduloTitulo = Helpers::normalizarTextoLms((string) ($modulo['titulo'] ?? 'Módulo'));
$tipoLabel = isset($item['tipo_label']) ? (string) $item['tipo_label'] : 'Conteúdo';
$tipo = isset($item['tipo']) ? (string) $item['tipo'] : '';
$statusTexto = isset($item['status_label']) ? (string) $item['status_label'] : 'Pendente';
$statusClasse = isset($item['status_class']) ? (string) $item['status_class'] : 'pill--neutral';
$statusProgressoItem = isset($conteudo_progresso['status']) ? (string) $conteudo_progresso['status'] : '';
$itemConcluido = !empty($item['concluido_aluno']) || in_array($statusProgressoItem, array('concluido', 'aprovada', 'corrigida'), true);
$statusConclusao = $itemConcluido ? 'Concluído' : 'Não concluído';
$cursoProgressPercent = (float) ($resumo['percentual'] ?? ($resumo['percentual_progresso'] ?? 0));
$cursoProgressText = isset($resumo['concluidos_itens'], $resumo['total_itens']) ? (int) $resumo['concluidos_itens'] . '/' . (int) $resumo['total_itens'] . ' conteúdos concluídos' : 'Progresso indisponível';
$voltarModuloUrl = isset($conteudo_voltar_modulo_url) && $conteudo_voltar_modulo_url !== '' ? (string) $conteudo_voltar_modulo_url : '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId . '/modulo/' . $moduloId;
$anteriorUrl = isset($conteudo_anterior_url) ? (string) $conteudo_anterior_url : null;
$anteriorLabel = isset($conteudo_anterior_label) ? (string) $conteudo_anterior_label : null;
$proximoUrl = isset($conteudo_proximo_url) ? (string) $conteudo_proximo_url : null;
$proximoLabel = isset($conteudo_proximo_label) ? (string) $conteudo_proximo_label : null;
$arquivoUrl = isset($item['acao_url']) ? (string) $item['acao_url'] : '';
$arquivoDisponivel = $arquivoUrl !== '';
$conteudoTexto = aluno_conteudo_valor($detalhe, array('conteudo', 'texto', 'descricao', 'corpo', 'html'), '');
$videoUrl = aluno_conteudo_valor($detalhe, array('url', 'video_url', 'link'), '');
$videoEmbed = aluno_conteudo_valor($detalhe, array('embed_html', 'embed', 'html'), '');
$avaliacoes = isset($conteudo_avaliacao_entregas) && is_array($conteudo_avaliacao_entregas) ? $conteudo_avaliacao_entregas : array();
$ultimaEntrega = !empty($avaliacoes) ? $avaliacoes[0] : null;
$avaliacaoPodeEnviar = !empty($conteudo_avaliacao_pode_enviar);
$descricaoCurta = trim((string) ($item['descricao_curta'] ?? ''));
$detalheProfessor = trim((string) aluno_conteudo_valor($detalhe, array('observacao', 'orientacao', 'descricao', 'conteudo'), ''));
$renderRich = static function ($html, $mode = 'basic') {
    return Helpers::renderSafeHtml((string) $html, $mode);
};

$chipsConteudo = array();
$adicionarChip = static function (&$chips, $label, $classe = 'neutral') {
    $label = trim((string) $label);
    if ($label === '') {
        return;
    }

    if (!function_exists('mb_strtolower')) {
        $key = strtolower($label);
    } else {
        $key = mb_strtolower($label, 'UTF-8');
    }

    foreach ($chips as $chip) {
        if (($chip['key'] ?? '') === $key) {
            return;
        }
    }

    $chips[] = array(
        'key' => $key,
        'label' => $label,
        'class' => $classe,
    );
};

$renderNav = static function ($voltarModuloUrl, $anteriorUrl, $anteriorLabel, $proximoUrl, $proximoLabel) {
    ?>
    <nav class="study-nav-icons" aria-label="Navegação do conteúdo">
        <a
            class="study-nav-icons__btn"
            href="<?php echo Helpers::e($voltarModuloUrl); ?>"
            title="Voltar ao módulo"
            aria-label="Voltar ao módulo"
        >
            <span aria-hidden="true">↑</span>
            <span class="sr-only">Voltar ao módulo</span>
        </a>

        <?php if (!empty($anteriorUrl)): ?>
            <a
                class="study-nav-icons__btn"
                href="<?php echo Helpers::e($anteriorUrl); ?>"
                title="Conteúdo anterior"
                aria-label="Conteúdo anterior"
            >
                <span aria-hidden="true">←</span>
                <span class="sr-only">Conteúdo anterior</span>
            </a>
        <?php else: ?>
            <span
                class="study-nav-icons__btn study-nav-icons__btn--disabled"
                title="Não há conteúdo anterior"
                aria-label="Não há conteúdo anterior"
                aria-disabled="true"
            >
                <span aria-hidden="true">←</span>
                <span class="sr-only">Não há conteúdo anterior</span>
            </span>
        <?php endif; ?>

        <?php if (!empty($proximoUrl)): ?>
            <a
                class="study-nav-icons__btn study-nav-icons__btn--primary"
                href="<?php echo Helpers::e($proximoUrl); ?>"
                title="Próximo conteúdo"
                aria-label="Próximo conteúdo"
            >
                <span aria-hidden="true">→</span>
                <span class="sr-only">Próximo conteúdo</span>
            </a>
        <?php else: ?>
            <span
                class="study-nav-icons__btn study-nav-icons__btn--disabled"
                title="Não há próximo conteúdo"
                aria-label="Não há próximo conteúdo"
                aria-disabled="true"
            >
                <span aria-hidden="true">→</span>
                <span class="sr-only">Não há próximo conteúdo</span>
            </span>
        <?php endif; ?>
    </nav>
    <?php
};
?>

<div class="study-page aluno-study-mode">
    <?php if (!empty($success)): ?>
        <section class="auth-message auth-message-success" aria-live="polite">
            <p><?php echo Helpers::e($success); ?></p>
        </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error" aria-live="polite">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="status-card aluno-study-hero study-shell study-shell--hero">
        <?php $renderNav($voltarModuloUrl, $anteriorUrl, $anteriorLabel, $proximoUrl, $proximoLabel); ?>

        <div class="aluno-study-hero__title">
            <p class="aluno-course-eyebrow">Modo de estudo</p>
            <h1><?php echo Helpers::e($itemTitulo); ?></h1>
            <p class="aluno-study-hero__subtitle"><?php echo Helpers::e($cursoProgressText); ?></p>
        </div>

        <?php if ($descricaoCurta !== ''): ?>
            <p class="aluno-study-hero__description"><?php echo Helpers::e($descricaoCurta); ?></p>
        <?php endif; ?>
    </section>

    <section class="status-card aluno-study-content study-shell study-shell--content">
        <?php if ($tipo === 'texto'): ?>
            <div class="conteudo-item-rich conteudo-item-rich--reading js-conteudo-texto-audio" data-audio-texto="1">
                <?php echo $renderRich($conteudoTexto !== '' ? $conteudoTexto : $detalheProfessor, 'full'); ?>
            </div>
        <?php elseif ($tipo === 'html'): ?>
            <article class="conteudo-item-html">
                <iframe
                    id="conteudo-html-frame-<?php echo $itemId; ?>"
                    class="js-conteudo-html-frame conteudo-item-html__frame"
                    sandbox="allow-scripts allow-popups"
                    title="<?php echo Helpers::e($itemTitulo); ?>"
                    loading="lazy"
                    srcdoc="<?php echo Helpers::e(HtmlEmbedRenderer::wrap($conteudoTexto, 'conteudo-html-frame-' . $itemId)); ?>"
                ></iframe>
            </article>
        <?php elseif ($tipo === 'arquivo'): ?>
            <article class="conteudo-item-file">
                <div class="conteudo-item-file__header">
                    <strong>Arquivo do conteúdo</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </div>
                <?php if ($conteudoTexto !== ''): ?>
                    <div class="conteudo-item-file__description"><?php echo $renderRich($conteudoTexto, 'basic'); ?></div>
                <?php endif; ?>
                <?php if ($arquivoDisponivel): ?>
                    <div class="conteudo-item-actions">
                        <a class="button-link" href="<?php echo Helpers::e($arquivoUrl); ?>">Baixar arquivo</a>
                    </div>
                <?php else: ?>
                    <p class="conteudo-item-note">O arquivo não está disponível no momento.</p>
                <?php endif; ?>
            </article>
        <?php elseif ($tipo === 'link'): ?>
            <article class="conteudo-item-link">
                <div class="conteudo-item-link__meta">
                    <strong>Link de acesso</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </div>
                <?php if ($conteudoTexto !== ''): ?>
                    <div class="conteudo-item-link__description"><?php echo $renderRich($conteudoTexto, 'basic'); ?></div>
                <?php endif; ?>
                <?php if ($arquivoDisponivel): ?>
                    <div class="conteudo-item-actions">
                        <a class="button-link" href="<?php echo Helpers::e($arquivoUrl); ?>">Abrir link</a>
                    </div>
                <?php endif; ?>
            </article>
        <?php elseif ($tipo === 'video'): ?>
            <article class="conteudo-item-video">
                <div class="conteudo-item-video__meta">
                    <strong>Vídeo do conteúdo</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </div>
                <?php if ($videoEmbed !== ''): ?>
                    <div class="conteudo-item-video__embed">
                        <?php echo $renderRich($videoEmbed, 'full'); ?>
                    </div>
                <?php elseif ($videoUrl !== ''): ?>
                    <div class="conteudo-item-actions">
                        <a class="button-link" href="<?php echo Helpers::e($videoUrl); ?>" target="_blank" rel="noopener noreferrer">Assistir vídeo</a>
                    </div>
                <?php else: ?>
                    <p class="conteudo-item-note">Não há vídeo disponível para este conteúdo.</p>
                <?php endif; ?>
                <?php if ($conteudoTexto !== ''): ?>
                    <div class="conteudo-item-note"><?php echo $renderRich($conteudoTexto, 'basic'); ?></div>
                <?php endif; ?>
            </article>
        <?php elseif ($tipo === 'avaliacao_textual'): ?>
            <article class="conteudo-item-eval">
                <header class="conteudo-item-eval__header">
                    <strong>Avaliação textual</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </header>

                <?php if ($conteudoTexto !== ''): ?>
                    <div class="conteudo-item-eval__body"><?php echo $renderRich($conteudoTexto, 'full'); ?></div>
                <?php endif; ?>

                <?php if (!empty($ultimaEntrega)): ?>
                    <section class="conteudo-item-feedback">
                        <div class="conteudo-item-feedback__header">
                            <strong>Última entrega</strong>
                            <span><?php echo Helpers::e(Helpers::statusLms((string) ($ultimaEntrega['status'] ?? ''))); ?></span>
                        </div>
                        <div class="conteudo-item-feedback__body">
                            <?php if (!empty($ultimaEntrega['nota'])): ?>
                                <p>Nota: <?php echo Helpers::e(number_format((float) $ultimaEntrega['nota'], 2, ',', '.')); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($ultimaEntrega['feedback'])): ?>
                                <p><?php echo nl2br(Helpers::e((string) $ultimaEntrega['feedback'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="conteudo-item-actions">
                    <?php if ($avaliacaoPodeEnviar): ?>
                        <form method="post" action="/aluno/cursos/conteudo/avaliacao/enviar" class="conteudo-item-form">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="item_id" value="<?php echo (int) $itemId; ?>">
                            <input type="hidden" name="modulo_id" value="<?php echo (int) $moduloId; ?>">
                            <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoId; ?>">
                            <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
                            <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? (int) $turmaId : ''; ?>">
                            <label class="conteudo-item-form__field">
                                <span>Resposta</span>
                                <textarea name="resposta" rows="8" required placeholder="Digite sua resposta aqui"></textarea>
                            </label>
                            <button type="submit">Enviar resposta</button>
                        </form>
                    <?php else: ?>
                        <p class="conteudo-item-note">O reenvio desta avaliação não está disponível no momento.</p>
                    <?php endif; ?>
                </section>
            </article>
        <?php elseif ($tipo === 'quiz'): ?>
            <?php
            // Carregar dados do quiz para o aluno
            $quizService   = new \App\Services\ConteudoQuizService();
            $quizParaAluno = $quizService->findQuizParaAluno($itemId, (int) Session::get('usuario_id'), $inscricaoId);
            $tentativaIdAtiva = Session::pullFlash('quiz_tentativa_id');
            $tentativaIdResultado = Session::pullFlash('quiz_tentativa_id_resultado');

            // Identificar tentativa em andamento
            $tentativaAtiva = null;
            $resultadoTentativa = null;
            if ($quizParaAluno) {
                $quizId = (int) $quizParaAluno['id'];
                // Pegar tentativa ativa (em_andamento)
                $tentativaModel = new \App\Models\ConteudoQuizTentativa();
                $tentativaAtiva = $tentativaModel->findEmAndamento($quizId, $inscricaoId);
                if ($tentativaIdResultado) {
                    $resultadoTentativa = $quizService->obterTentativaParaAluno((int) $tentativaIdResultado, (int) Session::get('usuario_id'));
                }
            }
            ?>

            <article class="conteudo-item-quiz">
                <header class="conteudo-item-quiz__header">
                    <strong>Quiz</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </header>

                <?php if (!$quizParaAluno): ?>
                    <p class="conteudo-item-note">Este quiz ainda não está disponível.</p>
                <?php elseif ($resultadoTentativa): ?>
                    <?php $tent = $resultadoTentativa['tentativa']; $qDados = $resultadoTentativa['quiz']; ?>
                    <div class="conteudo-quiz-resultado">
                        <?php if (!empty($qDados['exibir_resultado_apos_envio'])): ?>
                            <div class="conteudo-quiz-resultado__summary">
                                <p><strong>Você acertou <?php echo (int) $tent['total_acertos']; ?> de <?php echo (int) $tent['total_perguntas']; ?> questões</strong>
                                   (<?php echo number_format((float) $tent['percentual'], 1); ?>%)</p>
                                <?php if ($tent['aprovado'] !== null): ?>
                                    <?php if (!empty($tent['aprovado'])): ?>
                                        <p class="conteudo-quiz-resultado__aprovado">Aprovado!</p>
                                    <?php else: ?>
                                        <p class="conteudo-quiz-resultado__reprovado">Não atingiu o percentual mínimo.</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($qDados['exibir_gabarito_apos_envio']) || !empty($qDados['exibir_comentarios_apos_envio'])): ?>
                            <?php foreach ($resultadoTentativa['perguntas'] as $idxP => $pergunta): ?>
                                <div class="conteudo-quiz-pergunta-resultado" style="margin:12px 0; padding:10px; border-radius:4px; border:1px solid var(--color-border, #e5e7eb);">
                                    <p style="font-weight:600; margin:0 0 6px;"><?php echo $idxP+1; ?>. <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?></p>
                                    <?php $resposta = $pergunta['resposta'] ?? null; ?>
                                    <?php foreach ($pergunta['alternativas'] as $alt): ?>
                                        <?php
                                        $isRespondida = $resposta && (int) ($resposta['alternativa_id'] ?? 0) === (int) $alt['id'];
                                        $isCorreta    = isset($alt['correta']) && !empty($alt['correta']);
                                        $altClass     = '';
                                        if ($isRespondida && $isCorreta) { $altClass = 'color:green; font-weight:600;'; }
                                        elseif ($isRespondida && !$isCorreta) { $altClass = 'color:red;'; }
                                        elseif ($isCorreta) { $altClass = 'color:green;'; }
                                        ?>
                                        <div style="<?php echo $altClass; ?> display:flex; gap:6px; align-items:center; margin:2px 0;">
                                            <?php if ($isRespondida): ?><span>▶</span><?php endif; ?>
                                            <?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (!empty($qDados['exibir_comentarios_apos_envio']) && !empty($pergunta['explicacao'])): ?>
                                        <p class="muted" style="font-size:0.85em; margin:6px 0 0; font-style:italic;"><?php echo Helpers::e((string) $pergunta['explicacao']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($quizParaAluno['pode_nova_tentativa']): ?>
                            <form method="post" action="/aluno/cursos/quiz/iniciar" style="margin-top:12px;">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                <input type="hidden" name="inscricao_id" value="<?php echo $inscricaoId; ?>">
                                <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                                <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? $turmaId : ''; ?>">
                                <button type="submit" class="button-link">Nova tentativa</button>
                            </form>
                        <?php endif; ?>
                    </div>

                <?php elseif ($tentativaAtiva): ?>
                    <?php
                    // Mostrar o formulário de respostas com as respostas já salvas
                    $respostasModel = new \App\Models\ConteudoQuizResposta();
                    $respostasSalvas = $respostasModel->listForTentativa((int) $tentativaAtiva['id']);
                    $respostasMapa = array();
                    foreach ($respostasSalvas as $r) {
                        $respostasMapa[(int) $r['pergunta_id']] = (int) ($r['alternativa_id'] ?? 0);
                    }
                    $perguntasQuiz = $quizParaAluno['perguntas'];
                    ?>
                    <div class="conteudo-item-quiz__meta">
                        <span class="pill pill--info">Em andamento — tentativa <?php echo (int) ($tentativaAtiva['numero_tentativa'] ?? 1); ?></span>
                        <span class="muted"><?php echo count($perguntasQuiz); ?> pergunta(s)</span>
                        <?php if (!empty($quizParaAluno['tentativas_maximas'])): ?>
                            <span class="muted">Tentativas usadas: <?php echo (int) ($quizParaAluno['tentativas_usadas'] ?? 0); ?> / <?php echo (int) $quizParaAluno['tentativas_maximas']; ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($quizParaAluno['instrucoes'])): ?>
                        <div class="conteudo-item-quiz__instrucoes"><?php echo nl2br(Helpers::e((string) $quizParaAluno['instrucoes'])); ?></div>
                    <?php endif; ?>

                    <form method="post" action="/aluno/cursos/quiz/enviar" id="quiz-form" class="conteudo-item-quiz__form">
                        <?php echo $csrfField; ?>
                        <input type="hidden" name="tentativa_id" value="<?php echo (int) $tentativaAtiva['id']; ?>">
                        <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                        <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                        <input type="hidden" name="inscricao_id" value="<?php echo $inscricaoId; ?>">
                        <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                        <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? $turmaId : ''; ?>">

                        <?php foreach ($perguntasQuiz as $idxP => $pergunta): ?>
                            <?php
                            $pid = (int) $pergunta['id'];
                            $respostaSalva = isset($respostasMapa[$pid]) ? $respostasMapa[$pid] : 0;
                            $alts = $pergunta['alternativas'] ?? array();
                            ?>
                            <div class="conteudo-quiz-pergunta" style="margin:16px 0; padding:12px; border:1px solid var(--color-border, #e5e7eb); border-radius:6px;" data-pergunta-id="<?php echo $pid; ?>">
                                <p style="font-weight:600; margin:0 0 8px;" id="pergunta-<?php echo $pid; ?>">
                                    <?php echo $idxP + 1; ?>. <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?>
                                    <?php if (!empty($pergunta['obrigatoria'])): ?>
                                        <span style="color:red;" title="Obrigatória">*</span>
                                    <?php endif; ?>
                                </p>
                                <div style="display:flex; flex-direction:column; gap:8px;" role="radiogroup" aria-labelledby="pergunta-<?php echo $pid; ?>">
                                    <?php foreach ($alts as $alt): ?>
                                        <label style="display:flex; gap:8px; align-items:flex-start; cursor:pointer;">
                                            <input type="radio"
                                                   name="respostas[<?php echo $pid; ?>]"
                                                   value="<?php echo (int) $alt['id']; ?>"
                                                   <?php echo $respostaSalva === (int) $alt['id'] ? 'checked' : ''; ?>
                                                   style="margin-top:4px;">
                                            <span><?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="conteudo-item-actions" style="display:flex; gap:8px; flex-wrap:wrap; margin-top:16px;">
                            <button type="submit" class="button-link button-link--primary" id="quiz-submit-btn">
                                Enviar respostas
                            </button>
                            <button type="button" class="button-link button-link--ghost" id="quiz-save-btn">
                                Salvar e continuar depois
                            </button>
                        </div>
                        <p class="muted" style="font-size:0.85em; margin-top:8px;">(*) Perguntas obrigatórias</p>
                    </form>

                    <script>
                    (function () {
                        var saveBtn = document.getElementById('quiz-save-btn');
                        var submitBtn = document.getElementById('quiz-submit-btn');
                        var form = document.getElementById('quiz-form');
                        if (!saveBtn || !form) { return; }

                        saveBtn.addEventListener('click', function () {
                            var formData = new FormData(form);
                            var payload = { respostas: {} };
                            var csrfToken = formData.get('_token') || formData.get('csrf_token') || '';
                            formData.forEach(function (v, k) {
                                var m = k.match(/^respostas\[(\d+)\]$/);
                                if (m) { payload.respostas[m[1]] = v; }
                            });

                            saveBtn.disabled = true;
                            saveBtn.textContent = 'Salvando...';

                            fetch('/aluno/cursos/quiz/rascunho', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                                body: JSON.stringify({
                                    tentativa_id: <?php echo (int) $tentativaAtiva['id']; ?>,
                                    item_id: <?php echo $itemId; ?>,
                                    inscricao_id: <?php echo $inscricaoId; ?>,
                                    curso_id: <?php echo $cursoId; ?>,
                                    turma_id: <?php echo $turmaId > 0 ? $turmaId : 0; ?>,
                                    respostas: payload.respostas,
                                    _token: csrfToken
                                })
                            })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                saveBtn.disabled = false;
                                saveBtn.textContent = data.ok ? 'Salvo!' : 'Erro ao salvar';
                                setTimeout(function () { saveBtn.textContent = 'Salvar e continuar depois'; }, 2000);
                            })
                            .catch(function () {
                                saveBtn.disabled = false;
                                saveBtn.textContent = 'Erro ao salvar';
                            });
                        });

                        form.addEventListener('submit', function (event) {
                            var perguntas = form.querySelectorAll('[data-pergunta-id]');
                            var faltando = [];
                            perguntas.forEach(function (el) {
                                var pid = el.getAttribute('data-pergunta-id');
                                var checked = el.querySelector('input[type="radio"]:checked');
                                if (!checked) { faltando.push(pid); }
                            });
                            if (faltando.length > 0) {
                                event.preventDefault();
                                alert('Por favor, responda todas as perguntas antes de enviar.');
                                return;
                            }
                            if (submitBtn) {
                                submitBtn.disabled = true;
                                submitBtn.textContent = 'Enviando...';
                            }
                        });
                    })();
                    </script>

                <?php else: ?>
                    <?php $tentativas = $quizParaAluno['tentativas_usadas'] ?? 0; ?>
                    <div class="conteudo-item-quiz__start">
                        <?php if (!empty($quizParaAluno['instrucoes'])): ?>
                            <div class="conteudo-item-quiz__instrucoes"><?php echo nl2br(Helpers::e((string) $quizParaAluno['instrucoes'])); ?></div>
                        <?php endif; ?>

                        <p><?php echo (int) $quizParaAluno['total_perguntas']; ?> pergunta(s)
                           <?php if ($quizParaAluno['tentativas_maximas'] !== null): ?>
                               | Tentativas: <?php echo $tentativas; ?>/<?php echo (int) $quizParaAluno['tentativas_maximas']; ?>
                           <?php elseif ($tentativas > 0): ?>
                               | <?php echo $tentativas; ?> tentativa(s) realizada(s)
                           <?php endif; ?>
                        </p>

                        <?php if ($quizParaAluno['pode_nova_tentativa']): ?>
                            <form method="post" action="/aluno/cursos/quiz/iniciar" style="margin-top:12px;">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                <input type="hidden" name="inscricao_id" value="<?php echo $inscricaoId; ?>">
                                <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                                <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? $turmaId : ''; ?>">
                                <button type="submit" class="button-link button-link--primary">Iniciar quiz</button>
                            </form>
                        <?php else: ?>
                            <p class="conteudo-item-note">Você atingiu o número máximo de tentativas para este quiz.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>

        <?php else: ?>
            <div class="conteudo-item-note">Este conteúdo ainda não possui uma visualização específica.</div>
        <?php endif; ?>
    </section>

    <section class="study-shell study-shell--actions">
        <?php if ($tipo === 'texto' || $tipo === 'html'): ?>
            <div class="conteudo-conclusao-form conteudo-item-concluir-form">
                <div class="conteudo-conclusao-panel conteudo-conclusao-panel--concluido">
                    <div class="conteudo-conclusao-panel__estado" aria-live="polite">
                        <span class="conteudo-conclusao-icone" aria-hidden="true">✓</span>
                        <strong>Concluído</strong>
                    </div>
                    <p class="conteudo-item-note" style="margin:0;">Este conteúdo foi concluído automaticamente ao ser aberto.</p>
                </div>
            </div>
        <?php elseif ($tipo !== 'avaliacao_textual' && $tipo !== 'quiz'): ?>
            <form method="post" action="/aluno/cursos/conteudo/item/concluir" class="conteudo-conclusao-form conteudo-item-concluir-form">
                <?php echo $csrfField; ?>
                <input type="hidden" name="item_id" value="<?php echo (int) $itemId; ?>">
                <input type="hidden" name="modulo_id" value="<?php echo (int) $moduloId; ?>">
                <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoId; ?>">
                <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
                <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? (int) $turmaId : ''; ?>">
                <input type="hidden" name="acao" value="<?php echo $itemConcluido ? 'desmarcar' : 'marcar'; ?>">

                <div class="conteudo-conclusao-panel <?php echo $itemConcluido ? 'conteudo-conclusao-panel--concluido' : 'conteudo-conclusao-panel--pendente'; ?>">
                    <div class="conteudo-conclusao-panel__estado" aria-live="polite">
                        <span class="conteudo-conclusao-icone" aria-hidden="true"><?php echo $itemConcluido ? '✓' : '○'; ?></span>
                        <strong><?php echo $itemConcluido ? 'Concluído' : 'Não concluído'; ?></strong>
                    </div>
                    <button type="submit" class="conteudo-conclusao-btn <?php echo $itemConcluido ? 'conteudo-conclusao-btn--concluido' : 'conteudo-conclusao-btn--pendente'; ?>" title="<?php echo $itemConcluido ? 'Reverter conclusão' : 'Concluir'; ?>" aria-label="<?php echo $itemConcluido ? 'Reverter conclusão' : 'Concluir'; ?>">
                        <span class="conteudo-conclusao-icone" aria-hidden="true"><?php echo $itemConcluido ? '↺' : '✓'; ?></span>
                        <span><?php echo $itemConcluido ? 'Reverter conclusão' : 'Concluir'; ?></span>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="study-shell study-shell--nav-bottom">
        <?php $renderNav($voltarModuloUrl, $anteriorUrl, $anteriorLabel, $proximoUrl, $proximoLabel); ?>
    </section>
</div>
