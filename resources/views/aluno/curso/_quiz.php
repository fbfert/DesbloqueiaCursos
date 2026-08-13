<?php
/**
 * Bloco de quiz da área do aluno.
 *
 * Atende tanto o quiz simples (todas as perguntas, sem tempo) quanto o
 * simulado por banco de questões (blocos sorteados, cronômetro no servidor,
 * marcação para revisão e questão discursiva).
 *
 * Variáveis esperadas do contexto: $itemId, $moduloId, $inscricaoId,
 * $cursoId, $turmaId, $csrfField, $statusClasse, $statusTexto.
 */

use App\Core\Helpers;
use App\Core\Session;

$quizService   = new \App\Services\ConteudoQuizService();
$quizParaAluno = $quizService->findQuizParaAluno($itemId, (int) Session::get('usuario_id'), $inscricaoId);

$tentativaIdResultado = Session::pullFlash('quiz_tentativa_id_resultado');
Session::pullFlash('quiz_tentativa_id');

$tentativaAtiva     = null;
$resultadoTentativa = null;
if ($quizParaAluno) {
    $tentativaAtiva = isset($quizParaAluno['tentativa_em_andamento']) ? $quizParaAluno['tentativa_em_andamento'] : null;
    if ($tentativaIdResultado) {
        $resultadoTentativa = $quizService->obterTentativaParaAluno((int) $tentativaIdResultado, (int) Session::get('usuario_id'));
    }
}

$estrutura = $quizParaAluno && !empty($quizParaAluno['estrutura']) ? $quizParaAluno['estrutura'] : array();
$tempo     = $quizParaAluno && !empty($quizParaAluno['tempo']) ? $quizParaAluno['tempo'] : null;

/** Formata minutos como "5h30" / "45 min". */
$formatarDuracao = function ($minutos) {
    $minutos = (int) $minutos;
    if ($minutos <= 0) {
        return '-';
    }
    $horas   = (int) floor($minutos / 60);
    $restante = $minutos % 60;
    if ($horas > 0) {
        return $restante > 0
            ? $horas . 'h' . str_pad((string) $restante, 2, '0', STR_PAD_LEFT)
            : $horas . 'h';
    }
    return $minutos . ' min';
};
?>

<article class="conteudo-item-quiz">
    <header class="conteudo-item-quiz__header">
        <strong>Quiz</strong>
        <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
    </header>

    <?php if (!$quizParaAluno): ?>
        <p class="conteudo-item-note">Este quiz ainda não está disponível.</p>

    <?php elseif ($resultadoTentativa): ?>
        <?php
        $tent       = $resultadoTentativa['tentativa'];
        $qDados     = $resultadoTentativa['quiz'];
        $desempenho = isset($resultadoTentativa['desempenho']) ? $resultadoTentativa['desempenho'] : null;
        $discursivas = isset($resultadoTentativa['discursivas']) ? $resultadoTentativa['discursivas'] : array();
        ?>
        <div class="conteudo-quiz-resultado">
            <?php if (!empty($tent['encerrada_por_tempo'])): ?>
                <p class="conteudo-item-note" role="status">
                    O tempo da prova terminou e as respostas salvas foram enviadas automaticamente.
                </p>
            <?php endif; ?>

            <?php if (!empty($qDados['exibir_resultado_apos_envio'])): ?>
                <div class="conteudo-quiz-resultado__summary">
                    <p>
                        <strong>Você acertou <?php echo (int) $tent['total_acertos']; ?>
                        de <?php echo (int) ($tent['total_objetivas'] > 0 ? $tent['total_objetivas'] : $tent['total_perguntas']); ?> questões objetivas</strong>
                        (<?php echo number_format((float) $tent['percentual'], 1); ?>%)
                    </p>
                    <?php if ($tent['aprovado'] !== null): ?>
                        <?php if (!empty($tent['aprovado'])): ?>
                            <p class="conteudo-quiz-resultado__aprovado">Aprovado!</p>
                        <?php else: ?>
                            <p class="conteudo-quiz-resultado__reprovado">Não atingiu o percentual mínimo.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($desempenho && !empty($desempenho['blocos'])): ?>
                    <div class="conteudo-quiz-desempenho" style="margin:12px 0;">
                        <h4 style="margin:0 0 6px; font-size:0.95em;">Desempenho por bloco</h4>
                        <ul style="list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:8px;">
                            <?php foreach ($desempenho['blocos'] as $bloco): ?>
                                <li style="padding:6px 10px; border:1px solid var(--color-border, #e5e7eb); border-radius:6px;">
                                    <strong><?php echo Helpers::e((string) $bloco['titulo']); ?></strong><br>
                                    <?php echo (int) $bloco['acertos']; ?>/<?php echo (int) $bloco['total']; ?>
                                    (<?php echo number_format((float) $bloco['percentual'], 0); ?>%)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($desempenho && !empty($desempenho['temas'])): ?>
                    <details style="margin:12px 0;">
                        <summary style="cursor:pointer;">Desempenho por tema</summary>
                        <ul style="margin:8px 0 0; padding-left:18px;">
                            <?php foreach ($desempenho['temas'] as $tema): ?>
                                <li>
                                    <?php echo Helpers::e((string) $tema['tema']); ?>:
                                    <?php echo (int) $tema['acertos']; ?>/<?php echo (int) $tema['total']; ?>
                                    (<?php echo number_format((float) $tema['percentual'], 0); ?>%)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($discursivas)): ?>
                <div class="conteudo-quiz-discursiva-resultado" style="margin:12px 0;">
                    <h4 style="margin:0 0 6px; font-size:0.95em;">Questão discursiva</h4>
                    <?php foreach ($discursivas as $discursiva): ?>
                        <div style="padding:12px; border:1px solid var(--color-border, #e5e7eb); border-radius:6px; margin-bottom:8px;">
                            <p style="margin:0 0 6px; font-weight:600;"><?php echo nl2br(Helpers::e((string) $discursiva['enunciado'])); ?></p>
                            <p class="muted" style="font-size:0.85em; margin:0 0 8px;">
                                A nota da discursiva é informativa e não altera a aprovação nem o certificado.
                            </p>

                            <?php if ((string) $discursiva['status'] === 'corrigida'): ?>
                                <p style="margin:0 0 6px;">
                                    <strong>Nota:</strong>
                                    <?php echo Helpers::e(number_format((float) $discursiva['nota'], 2, ',', '.')); ?>
                                    de <?php echo Helpers::e(number_format((float) $discursiva['nota_maxima'], 2, ',', '.')); ?>
                                </p>
                                <?php if (!empty($discursiva['feedback'])): ?>
                                    <p style="margin:0;"><strong>Comentário do corretor:</strong><br>
                                        <?php echo nl2br(Helpers::e((string) $discursiva['feedback'])); ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="pill pill--info" style="display:inline-block; margin:0;">Aguardando correção</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($qDados['exibir_gabarito_apos_envio']) || !empty($qDados['exibir_comentarios_apos_envio'])): ?>
                <?php foreach ($resultadoTentativa['perguntas'] as $idxP => $pergunta): ?>
                    <?php if ((string) ($pergunta['tipo'] ?? '') === 'discursiva') { continue; } ?>
                    <div class="conteudo-quiz-pergunta-resultado" style="margin:12px 0; padding:10px; border-radius:4px; border:1px solid var(--color-border, #e5e7eb);">
                        <p style="font-weight:600; margin:0 0 6px;"><?php echo $idxP + 1; ?>. <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?></p>
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
                                <?php if ($isRespondida): ?><span aria-hidden="true">▶</span><span class="sr-only">Sua resposta:</span><?php endif; ?>
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
        $respostasModel  = new \App\Models\ConteudoQuizResposta();
        $respostasSalvas = $respostasModel->listForTentativa((int) $tentativaAtiva['id']);
        $respostasMapa   = array();
        $textosMapa      = array();
        $revisaoMapa     = array();
        foreach ($respostasSalvas as $r) {
            $pid = (int) $r['pergunta_id'];
            $respostasMapa[$pid] = (int) ($r['alternativa_id'] ?? 0);
            $textosMapa[$pid]    = (string) ($r['texto_resposta'] ?? '');
            $revisaoMapa[$pid]   = !empty($r['marcada_para_revisao']);
        }

        $perguntasQuiz = $quizParaAluno['perguntas'];
        $temTempo      = $tempo !== null;

        // Agrupa por bloco para a navegação da prova longa.
        $grupos = array();
        foreach ($perguntasQuiz as $indice => $pergunta) {
            $codigo = (string) ($pergunta['bloco_codigo'] ?? '');
            $chave  = $codigo !== '' ? $codigo : '__geral__';
            if (!isset($grupos[$chave])) {
                $grupos[$chave] = array(
                    'titulo'    => (string) ($pergunta['bloco_titulo'] ?? 'Questões'),
                    'perguntas' => array(),
                );
            }
            $grupos[$chave]['perguntas'][] = $indice;
        }
        $temBlocos = count($grupos) > 1 || (count($grupos) === 1 && !isset($grupos['__geral__']));
        ?>

        <div class="conteudo-item-quiz__meta">
            <span class="pill pill--info">Em andamento — tentativa <?php echo (int) ($tentativaAtiva['numero_tentativa'] ?? 1); ?></span>
            <span class="muted"><?php echo count($perguntasQuiz); ?> questão(ões)</span>
            <?php if (!empty($quizParaAluno['tentativas_maximas'])): ?>
                <span class="muted">Tentativas usadas: <?php echo (int) ($quizParaAluno['tentativas_usadas'] ?? 0); ?> / <?php echo (int) $quizParaAluno['tentativas_maximas']; ?></span>
            <?php endif; ?>
        </div>

        <?php if ($temTempo): ?>
            <div id="quiz-cronometro" class="conteudo-quiz-cronometro" role="timer" aria-live="polite"
                 data-tentativa="<?php echo (int) $tentativaAtiva['id']; ?>"
                 data-restante="<?php echo (int) $tempo['segundos_restantes']; ?>"
                 style="position:sticky; top:0; z-index:5; display:flex; gap:12px; align-items:center; justify-content:space-between;
                        padding:10px 12px; margin:12px 0; border-radius:8px;
                        border:1px solid var(--color-border, #e5e7eb); background:var(--color-surface, #fff);">
                <span>Tempo restante</span>
                <strong id="quiz-cronometro-valor" style="font-size:1.2em; font-variant-numeric:tabular-nums;">--:--:--</strong>
            </div>
        <?php endif; ?>

        <?php if (!empty($quizParaAluno['instrucoes'])): ?>
            <div class="conteudo-item-quiz__instrucoes"><?php echo nl2br(Helpers::e((string) $quizParaAluno['instrucoes'])); ?></div>
        <?php endif; ?>

        <form method="post" action="/aluno/cursos/quiz/enviar" id="quiz-form" class="conteudo-item-quiz__form">
            <?php echo $csrfField; ?>
            <input type="hidden" name="tentativa_id" value="<?php echo (int) $tentativaAtiva['id']; ?>">

            <p class="muted" id="quiz-autosave" role="status" aria-live="polite" style="font-size:0.85em; margin:0 0 10px;">
                Suas respostas são salvas automaticamente conforme você responde.
            </p>
            <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
            <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
            <input type="hidden" name="inscricao_id" value="<?php echo $inscricaoId; ?>">
            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? $turmaId : ''; ?>">

            <?php if ($temBlocos): ?>
                <nav class="conteudo-quiz-indice" aria-label="Índice de questões"
                     style="margin:12px 0; padding:12px; border:1px solid var(--color-border, #e5e7eb); border-radius:8px;">
                    <p style="margin:0 0 8px; font-weight:600;">Índice da prova</p>
                    <?php foreach ($grupos as $chave => $grupo): ?>
                        <div style="margin-bottom:10px;">
                            <p style="margin:0 0 4px; font-size:0.9em;"><?php echo Helpers::e($grupo['titulo']); ?></p>
                            <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                <?php foreach ($grupo['perguntas'] as $indice): ?>
                                    <?php $pid = (int) $perguntasQuiz[$indice]['id']; ?>
                                    <a class="conteudo-quiz-indice__item" href="#questao-<?php echo $pid; ?>"
                                       data-indice-pergunta="<?php echo $pid; ?>"
                                       aria-label="Ir para a questão <?php echo $indice + 1; ?>"
                                       style="display:inline-flex; align-items:center; justify-content:center;
                                              min-width:34px; min-height:34px; padding:2px 6px; border-radius:6px;
                                              border:1px solid var(--color-border, #e5e7eb); text-decoration:none;">
                                        <?php echo $indice + 1; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <p class="muted" style="margin:0; font-size:0.85em;">
                        <span id="quiz-indice-resumo"></span>
                    </p>
                </nav>
            <?php endif; ?>

            <div id="quiz-progress" class="conteudo-quiz-progress" hidden>
                <div class="conteudo-quiz-progress__bar"><div class="conteudo-quiz-progress__fill" id="quiz-progress-fill"></div></div>
                <div class="conteudo-quiz-progress__row">
                    <span class="conteudo-quiz-progress__text" id="quiz-progress-text"></span>
                    <div class="conteudo-quiz-progress__dots" id="quiz-progress-dots"></div>
                </div>
            </div>

            <div id="quiz-perguntas">
                <?php $blocoAtual = null; ?>
                <?php foreach ($perguntasQuiz as $idxP => $pergunta): ?>
                    <?php
                    $pid           = (int) $pergunta['id'];
                    $ehDiscursiva  = (string) ($pergunta['tipo'] ?? 'multipla_escolha') === 'discursiva';
                    $respostaSalva = isset($respostasMapa[$pid]) ? $respostasMapa[$pid] : 0;
                    $textoSalvo    = isset($textosMapa[$pid]) ? $textosMapa[$pid] : '';
                    $marcada       = !empty($revisaoMapa[$pid]);
                    $codigoBloco   = (string) ($pergunta['bloco_codigo'] ?? '');
                    $alts          = $pergunta['alternativas'] ?? array();
                    ?>

                    <?php if ($temBlocos && $codigoBloco !== $blocoAtual): ?>
                        <?php $blocoAtual = $codigoBloco; ?>
                        <h3 class="conteudo-quiz-bloco-titulo" style="margin:20px 0 8px; font-size:1.05em;">
                            <?php echo Helpers::e((string) ($pergunta['bloco_titulo'] ?? 'Questões')); ?>
                        </h3>
                    <?php endif; ?>

                    <div class="conteudo-quiz-pergunta" id="questao-<?php echo $pid; ?>"
                         style="margin:16px 0; padding:12px; border:1px solid var(--color-border, #e5e7eb); border-radius:6px;"
                         data-pergunta-id="<?php echo $pid; ?>"
                         data-tipo="<?php echo $ehDiscursiva ? 'discursiva' : 'objetiva'; ?>">

                        <p style="font-weight:600; margin:0 0 8px;" id="pergunta-<?php echo $pid; ?>">
                            <?php echo $idxP + 1; ?>. <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?>
                            <?php if (!empty($pergunta['obrigatoria'])): ?>
                                <span style="color:red;" title="Obrigatória" aria-label="Questão obrigatória">*</span>
                            <?php endif; ?>
                        </p>

                        <?php if ($ehDiscursiva): ?>
                            <label for="discursiva-<?php echo $pid; ?>" class="sr-only">Resposta da questão discursiva</label>
                            <textarea id="discursiva-<?php echo $pid; ?>"
                                      name="discursivas[<?php echo $pid; ?>]"
                                      rows="12"
                                      maxlength="<?php echo (int) ($quizParaAluno['limite_caracteres_discursiva'] ?? 50000); ?>"
                                      style="width:100%; min-height:220px;"
                                      placeholder="Escreva sua resposta."><?php echo Helpers::e($textoSalvo); ?></textarea>
                            <p class="muted" style="font-size:0.85em; margin:4px 0 0;">
                                A nota desta questão é informativa: não altera a aprovação nem o certificado.
                            </p>
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column; gap:8px;" role="radiogroup" aria-labelledby="pergunta-<?php echo $pid; ?>">
                                <?php foreach ($alts as $alt): ?>
                                    <label style="display:flex; gap:8px; align-items:flex-start; cursor:pointer; padding:4px;">
                                        <input type="radio"
                                               name="respostas[<?php echo $pid; ?>]"
                                               value="<?php echo (int) $alt['id']; ?>"
                                               <?php echo $respostaSalva === (int) $alt['id'] ? 'checked' : ''; ?>
                                               style="margin-top:4px;">
                                        <span><?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <label style="display:inline-flex; gap:6px; align-items:center; margin-top:10px; font-size:0.9em; cursor:pointer;">
                            <input type="checkbox" class="quiz-revisao" data-pergunta="<?php echo $pid; ?>"
                                   name="revisoes[<?php echo $pid; ?>]" value="1" <?php echo $marcada ? 'checked' : ''; ?>>
                            Marcar para revisão
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="conteudo-item-actions" style="display:flex; gap:8px; flex-wrap:wrap; margin-top:16px;">
                <button type="button" class="button-link button-link--ghost" id="quiz-prev-btn" hidden>← Anterior</button>
                <button type="button" class="button-link button-link--primary" id="quiz-next-btn" hidden>Próxima →</button>
                <button type="submit" class="button-link button-link--primary" id="quiz-submit-btn">Enviar respostas</button>
                <button type="button" class="button-link button-link--ghost" id="quiz-save-btn">Salvar e continuar depois</button>
            </div>
            <p class="muted" style="font-size:0.85em; margin-top:8px;">(*) Questões obrigatórias</p>
        </form>

        <?php require BASE_PATH . '/resources/views/aluno/curso/_quiz_script.php'; ?>

    <?php else: ?>
        <?php $tentativas = $quizParaAluno['tentativas_usadas'] ?? 0; ?>
        <div class="conteudo-item-quiz__start">
            <?php if (!empty($quizParaAluno['instrucoes'])): ?>
                <div class="conteudo-item-quiz__instrucoes"><?php echo nl2br(Helpers::e((string) $quizParaAluno['instrucoes'])); ?></div>
            <?php endif; ?>

            <div class="conteudo-quiz-estrutura" style="margin:12px 0; padding:12px; border:1px solid var(--color-border, #e5e7eb); border-radius:8px;">
                <h4 style="margin:0 0 8px;">Antes de começar</h4>
                <ul style="margin:0; padding-left:18px;">
                    <li>
                        <strong><?php echo (int) ($estrutura['total_questoes'] ?? $quizParaAluno['total_perguntas']); ?></strong> questão(ões)
                        <?php if (!empty($estrutura['total_discursivas'])): ?>
                            — <?php echo (int) $estrutura['total_objetivas']; ?> objetivas
                            e <?php echo (int) $estrutura['total_discursivas']; ?> discursiva(s)
                        <?php endif; ?>
                    </li>
                    <?php if (!empty($estrutura['duracao_minutos'])): ?>
                        <li>Duração: <strong><?php echo Helpers::e($formatarDuracao($estrutura['duracao_minutos'])); ?></strong>
                            (<?php echo (int) $estrutura['duracao_minutos']; ?> minutos), contados a partir do início</li>
                    <?php else: ?>
                        <li>Sem limite de tempo</li>
                    <?php endif; ?>
                    <?php if (!empty($estrutura['exige_aprovacao'])): ?>
                        <li>Aprovação com no mínimo
                            <strong><?php echo number_format((float) $estrutura['percentual_minimo'], 0); ?>%</strong>
                            <?php if (!empty($estrutura['total_discursivas'])): ?>
                                nas questões objetivas. A nota da discursiva não altera a aprovação.
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                    <?php if (($estrutura['tentativas_restantes'] ?? null) !== null): ?>
                        <li>Tentativas restantes:
                            <strong><?php echo (int) $estrutura['tentativas_restantes']; ?></strong>
                            de <?php echo (int) $estrutura['tentativas_maximas']; ?></li>
                    <?php elseif ($tentativas > 0): ?>
                        <li><?php echo (int) $tentativas; ?> tentativa(s) realizada(s)</li>
                    <?php endif; ?>
                    <?php if (!empty($estrutura['duracao_minutos']) && (string) ($estrutura['acao_ao_expirar'] ?? '') === 'enviar_automatico'): ?>
                        <li>Ao esgotar o tempo, as respostas salvas são enviadas automaticamente.</li>
                    <?php endif; ?>
                </ul>

                <?php if (!empty($estrutura['blocos'])): ?>
                    <p style="margin:12px 0 4px; font-weight:600;">Estrutura da prova</p>
                    <ul style="margin:0; padding-left:18px;">
                        <?php foreach ($estrutura['blocos'] as $bloco): ?>
                            <li>
                                <?php echo Helpers::e((string) $bloco['titulo']); ?>:
                                <strong><?php echo (int) $bloco['quantidade']; ?></strong>
                                <?php echo (string) $bloco['tipo_questao'] === 'discursiva' ? 'questão discursiva' : 'questões objetivas'; ?>
                                <?php if (empty($bloco['conta_para_percentual'])): ?>
                                    <span class="muted">(fora do percentual de aprovação)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($quizParaAluno['pode_nova_tentativa']): ?>
                <form method="post" action="/aluno/cursos/quiz/iniciar" style="margin-top:12px;">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                    <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                    <input type="hidden" name="inscricao_id" value="<?php echo $inscricaoId; ?>">
                    <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? $turmaId : ''; ?>">
                    <button type="submit" class="button-link button-link--primary">
                        <?php echo !empty($estrutura['duracao_minutos']) ? 'Iniciar prova' : 'Iniciar quiz'; ?>
                    </button>
                </form>
                <?php if (!empty($estrutura['duracao_minutos'])): ?>
                    <p class="muted" style="font-size:0.85em; margin-top:6px;">
                        O cronômetro começa assim que você iniciar e continua correndo mesmo se você sair da página.
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p class="conteudo-item-note">Você atingiu o número máximo de tentativas para este quiz.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</article>
