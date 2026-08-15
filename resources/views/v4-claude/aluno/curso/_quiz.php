<?php
/**
 * Bloco de quiz da área do aluno — template v4-claude.
 *
 * Mesma regra de negócio do template padrão (ver
 * resources/views/aluno/curso/_quiz.php): quiz simples ou simulado por banco
 * de questões, com cronômetro conferido no servidor, marcação para revisão e
 * questão discursiva. Aqui muda apenas a camada visual (classes dc-*).
 *
 * Variáveis esperadas: $itemId, $moduloId, $inscricaoId, $cursoId, $turmaId,
 * $csrfField, $statusTexto.
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

$formatarDuracao = function ($minutos) {
    $minutos = (int) $minutos;
    if ($minutos <= 0) {
        return '-';
    }
    $horas    = (int) floor($minutos / 60);
    $restante = $minutos % 60;
    if ($horas > 0) {
        return $restante > 0
            ? $horas . 'h' . str_pad((string) $restante, 2, '0', STR_PAD_LEFT)
            : $horas . 'h';
    }
    return $minutos . ' min';
};
?>

<div class="dc-study-card">
  <div class="dc-study-card__head">
    <strong>Quiz</strong>
    <span class="dc-badge dc-badge-novo"><?php echo Helpers::e($statusTexto); ?></span>
  </div>

  <?php if (!$quizParaAluno): ?>
    <p class="dc-study-note">Este quiz ainda não está disponível.</p>

  <?php elseif ($resultadoTentativa): ?>
    <?php
    $tent        = $resultadoTentativa['tentativa'];
    $qDados      = $resultadoTentativa['quiz'];
    $desempenho  = isset($resultadoTentativa['desempenho']) ? $resultadoTentativa['desempenho'] : null;
    $discursivas = isset($resultadoTentativa['discursivas']) ? $resultadoTentativa['discursivas'] : array();
    $totalObjetivas = (int) ($tent['total_objetivas'] ?? 0);
    if ($totalObjetivas <= 0) { $totalObjetivas = (int) $tent['total_perguntas']; }
    ?>

    <?php if (!empty($tent['encerrada_por_tempo'])): ?>
      <div class="dc-callout dc-callout-warning" role="status">
        <i class="ti ti-clock-exclamation"></i>
        <span>O tempo da prova terminou e as respostas salvas foram enviadas automaticamente.</span>
      </div>
    <?php endif; ?>

    <?php if (!empty($qDados['exibir_resultado_apos_envio'])): ?>
      <div class="dc-callout <?php echo !empty($tent['aprovado']) ? 'dc-callout-success' : 'dc-callout-warning'; ?>" style="flex-direction:column;align-items:stretch;">
        <strong>Você acertou <?php echo (int) $tent['total_acertos']; ?> de <?php echo $totalObjetivas; ?> questões objetivas (<?php echo number_format((float) $tent['percentual'], 1); ?>%)</strong>
        <?php if ($tent['aprovado'] !== null): ?>
          <span><?php echo !empty($tent['aprovado']) ? 'Aprovado!' : 'Não atingiu o percentual mínimo.'; ?></span>
        <?php endif; ?>
      </div>

      <?php if ($desempenho && !empty($desempenho['blocos'])): ?>
        <div style="margin:12px 0;">
          <strong style="display:block;margin-bottom:8px;">Desempenho por bloco</strong>
          <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($desempenho['blocos'] as $bloco): ?>
              <div class="dc-study-note" style="padding:8px 12px;border:1px solid rgba(75,0,142,.12);border-radius:10px;">
                <strong><?php echo Helpers::e((string) $bloco['titulo']); ?></strong><br>
                <?php echo (int) $bloco['acertos']; ?>/<?php echo (int) $bloco['total']; ?>
                (<?php echo number_format((float) $bloco['percentual'], 0); ?>%)
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($desempenho && !empty($desempenho['temas'])): ?>
        <details style="margin:12px 0;">
          <summary style="cursor:pointer;">Desempenho por tema</summary>
          <ul style="margin:8px 0 0;padding-left:18px;">
            <?php foreach ($desempenho['temas'] as $tema): ?>
              <li><?php echo Helpers::e((string) $tema['tema']); ?>:
                <?php echo (int) $tema['acertos']; ?>/<?php echo (int) $tema['total']; ?>
                (<?php echo number_format((float) $tema['percentual'], 0); ?>%)</li>
            <?php endforeach; ?>
          </ul>
        </details>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($discursivas)): ?>
      <div style="margin:12px 0;">
        <strong style="display:block;margin-bottom:8px;">Questão discursiva</strong>
        <?php foreach ($discursivas as $discursiva): ?>
          <div class="dc-quiz-pergunta">
            <div class="dc-quiz-enunciado"><?php echo nl2br(Helpers::e((string) $discursiva['enunciado'])); ?></div>
            <p class="dc-study-note" style="margin:4px 0 8px;">
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
              <span class="dc-badge dc-badge-novo">Aguardando correção</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($qDados['exibir_gabarito_apos_envio']) || !empty($qDados['exibir_comentarios_apos_envio'])): ?>
      <?php foreach ($resultadoTentativa['perguntas'] as $idxP => $pergunta): ?>
        <?php if ((string) ($pergunta['tipo'] ?? '') === 'discursiva') { continue; } ?>
        <div class="dc-quiz-pergunta">
          <div class="dc-quiz-enunciado"><?php echo $idxP + 1; ?>. <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?></div>
          <?php $resposta = $pergunta['resposta'] ?? null; ?>
          <?php foreach ($pergunta['alternativas'] as $alt): ?>
            <?php
            $isRespondida = $resposta && (int) ($resposta['alternativa_id'] ?? 0) === (int) $alt['id'];
            $isCorreta    = isset($alt['correta']) && !empty($alt['correta']);
            $cls = '';
            if ($isCorreta) { $cls = 'dc-quiz-res-alt--correta'; }
            elseif ($isRespondida && !$isCorreta) { $cls = 'dc-quiz-res-alt--errada'; }
            ?>
            <div class="dc-quiz-res-alt <?php echo $cls; ?>">
              <?php if ($isRespondida): ?><i class="ti ti-player-play-filled" style="font-size:12px;"></i><?php endif; ?>
              <?php if ($isCorreta): ?><i class="ti ti-circle-check"></i><?php endif; ?>
              <span><?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (!empty($qDados['exibir_comentarios_apos_envio']) && !empty($pergunta['explicacao'])): ?>
            <p class="dc-study-note" style="font-style:italic;margin-top:6px;"><?php echo nl2br(Helpers::e((string) $pergunta['explicacao'])); ?></p>
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
        <button type="submit" class="dc-btn dc-btn-outline dc-btn-block"><i class="ti ti-refresh"></i> Nova tentativa</button>
      </form>
    <?php endif; ?>

  <?php elseif ($tentativaAtiva): ?>
    <?php
    $respostasModel  = new \App\Models\ConteudoQuizResposta();
    $respostasSalvas = $respostasModel->listForTentativa((int) $tentativaAtiva['id']);
    $respostasMapa = array();
    $textosMapa    = array();
    $revisaoMapa   = array();
    foreach ($respostasSalvas as $r) {
        $pid = (int) $r['pergunta_id'];
        $respostasMapa[$pid] = (int) ($r['alternativa_id'] ?? 0);
        $textosMapa[$pid]    = (string) ($r['texto_resposta'] ?? '');
        $revisaoMapa[$pid]   = !empty($r['marcada_para_revisao']);
    }

    $perguntasQuiz = $quizParaAluno['perguntas'];
    $temTempo      = $tempo !== null;

    $grupos = array();
    foreach ($perguntasQuiz as $indice => $pergunta) {
        $codigo = (string) ($pergunta['bloco_codigo'] ?? '');
        $chave  = $codigo !== '' ? $codigo : '__geral__';
        if (!isset($grupos[$chave])) {
            $grupos[$chave] = array('titulo' => (string) ($pergunta['bloco_titulo'] ?? 'Questões'), 'perguntas' => array());
        }
        $grupos[$chave]['perguntas'][] = $indice;
    }
    $temBlocos = count($grupos) > 1 || (count($grupos) === 1 && !isset($grupos['__geral__']));
    ?>

    <div class="dc-study-note" style="margin-bottom:10px;">
      Em andamento — tentativa <?php echo (int) ($tentativaAtiva['numero_tentativa'] ?? 1); ?> · <?php echo count($perguntasQuiz); ?> questão(ões)
      <?php if (!empty($quizParaAluno['tentativas_maximas'])): ?> · usadas <?php echo (int) ($quizParaAluno['tentativas_usadas'] ?? 0); ?>/<?php echo (int) $quizParaAluno['tentativas_maximas']; ?><?php endif; ?>
    </div>

    <?php if ($temTempo): ?>
      <div id="quiz-cronometro" role="timer" aria-live="polite"
           data-tentativa="<?php echo (int) $tentativaAtiva['id']; ?>"
           data-restante="<?php echo (int) $tempo['segundos_restantes']; ?>"
           style="position:sticky;top:0;z-index:5;display:flex;gap:12px;align-items:center;justify-content:space-between;
                  padding:10px 14px;margin:0 0 12px;border-radius:12px;border:1px solid rgba(75,0,142,.12);background:#fff;">
        <span class="dc-study-note" style="margin:0;">Tempo restante</span>
        <strong id="quiz-cronometro-valor" style="font-size:1.2rem;font-variant-numeric:tabular-nums;">--:--:--</strong>
      </div>
    <?php endif; ?>

    <?php if (!empty($quizParaAluno['instrucoes'])): ?>
      <div class="dc-callout dc-callout-info"><i class="ti ti-info-circle"></i><span><?php echo nl2br(Helpers::e((string) $quizParaAluno['instrucoes'])); ?></span></div>
    <?php endif; ?>

    <form method="post" action="/aluno/cursos/quiz/enviar" id="quiz-form">
      <?php echo $csrfField; ?>
      <input type="hidden" name="tentativa_id" value="<?php echo (int) $tentativaAtiva['id']; ?>">

      <p class="dc-study-note" id="quiz-autosave" role="status" aria-live="polite" style="margin:0 0 10px;">
          Suas respostas são salvas automaticamente conforme você responde.
      </p>
      <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
      <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
      <input type="hidden" name="inscricao_id" value="<?php echo $inscricaoId; ?>">
      <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
      <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? $turmaId : ''; ?>">

      <?php if ($temBlocos): ?>
        <nav aria-label="Índice de questões" style="margin:0 0 14px;padding:12px;border:1px solid rgba(75,0,142,.12);border-radius:12px;">
          <strong style="display:block;margin-bottom:8px;">Índice da prova</strong>
          <?php foreach ($grupos as $grupo): ?>
            <div style="margin-bottom:10px;">
              <p class="dc-study-note" style="margin:0 0 4px;"><?php echo Helpers::e($grupo['titulo']); ?></p>
              <div style="display:flex;flex-wrap:wrap;gap:4px;">
                <?php foreach ($grupo['perguntas'] as $indice): ?>
                  <?php $pid = (int) $perguntasQuiz[$indice]['id']; ?>
                  <a href="#questao-<?php echo $pid; ?>" data-indice-pergunta="<?php echo $pid; ?>"
                     aria-label="Ir para a questão <?php echo $indice + 1; ?>"
                     style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;min-height:34px;
                            padding:2px 6px;border-radius:8px;border:1px solid rgba(75,0,142,.16);text-decoration:none;font-size:.85rem;">
                    <?php echo $indice + 1; ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <p class="dc-study-note" style="margin:0;"><span id="quiz-indice-resumo"></span></p>
        </nav>
      <?php endif; ?>

      <div id="quiz-progress" style="margin:10px 0 16px;" hidden>
        <div style="height:8px;border-radius:999px;background:rgba(75,0,142,.08);overflow:hidden;">
          <div id="quiz-progress-fill" style="height:100%;border-radius:999px;background:linear-gradient(135deg,var(--dc-laranja),var(--dc-coral));transition:width .25s ease;"></div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:10px;">
          <span id="quiz-progress-text" style="font-size:.85rem;font-weight:700;color:var(--dc-text-2);"></span>
          <div id="quiz-progress-dots" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
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
            <h3 style="margin:18px 0 8px;font-size:1rem;"><?php echo Helpers::e((string) ($pergunta['bloco_titulo'] ?? 'Questões')); ?></h3>
          <?php endif; ?>

          <div class="dc-quiz-pergunta" id="questao-<?php echo $pid; ?>"
               data-pergunta-id="<?php echo $pid; ?>"
               data-tipo="<?php echo $ehDiscursiva ? 'discursiva' : 'objetiva'; ?>">
            <div class="dc-quiz-enunciado" id="pergunta-<?php echo $pid; ?>">
              <?php echo $idxP + 1; ?>. <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?>
              <?php if (!empty($pergunta['obrigatoria'])): ?>
                <span style="color:var(--dc-coral,#e5484d);" title="Obrigatória" aria-label="Questão obrigatória">*</span>
              <?php endif; ?>
            </div>

            <?php if ($ehDiscursiva): ?>
              <label for="discursiva-<?php echo $pid; ?>" class="dc-study-note" style="display:block;margin-bottom:4px;">Sua resposta</label>
              <textarea id="discursiva-<?php echo $pid; ?>" name="discursivas[<?php echo $pid; ?>]" rows="12"
                        maxlength="<?php echo (int) ($quizParaAluno['limite_caracteres_discursiva'] ?? 50000); ?>"
                        style="width:100%;min-height:220px;padding:10px;border:1px solid rgba(75,0,142,.16);border-radius:10px;"
                        placeholder="Escreva sua resposta."><?php echo Helpers::e($textoSalvo); ?></textarea>
              <p class="dc-study-note" style="margin:4px 0 0;">
                A nota desta questão é informativa: não altera a aprovação nem o certificado.
              </p>
            <?php else: ?>
              <div role="radiogroup" aria-labelledby="pergunta-<?php echo $pid; ?>">
                <?php foreach ($alts as $alt): ?>
                  <label class="dc-quiz-alt">
                    <input type="radio" name="respostas[<?php echo $pid; ?>]" value="<?php echo (int) $alt['id']; ?>"
                           <?php echo $respostaSalva === (int) $alt['id'] ? 'checked' : ''; ?>>
                    <span><?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <label style="display:inline-flex;gap:6px;align-items:center;margin-top:10px;font-size:.85rem;cursor:pointer;">
              <input type="checkbox" class="quiz-revisao" data-pergunta="<?php echo $pid; ?>"
                     name="revisoes[<?php echo $pid; ?>]" value="1" <?php echo $marcada ? 'checked' : ''; ?>>
              Marcar para revisão
            </label>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;">
        <button type="button" class="dc-btn dc-btn-outline" id="quiz-prev-btn" hidden><i class="ti ti-arrow-left"></i> Anterior</button>
        <button type="button" class="dc-btn dc-btn-primary" id="quiz-next-btn" hidden>Próxima <i class="ti ti-arrow-right"></i></button>
        <button type="submit" class="dc-btn dc-btn-primary" id="quiz-submit-btn"><i class="ti ti-send"></i> Enviar respostas</button>
        <button type="button" class="dc-btn dc-btn-outline" id="quiz-save-btn"><i class="ti ti-device-floppy"></i> Salvar e continuar depois</button>
      </div>
      <p class="dc-study-note" style="margin-top:8px;">(*) Questões obrigatórias</p>
    </form>

    <?php
    // O comportamento (rascunho, revisão, cronômetro, envio) é idêntico ao do
    // template padrão; o script é compartilhado.
    require BASE_PATH . '/resources/views/aluno/curso/_quiz_script.php';
    ?>

  <?php else: ?>
    <?php $tentativas = $quizParaAluno['tentativas_usadas'] ?? 0; ?>
    <?php if (!empty($quizParaAluno['instrucoes'])): ?>
      <div class="dc-callout dc-callout-info"><i class="ti ti-info-circle"></i><span><?php echo nl2br(Helpers::e((string) $quizParaAluno['instrucoes'])); ?></span></div>
    <?php endif; ?>

    <div style="margin:12px 0;padding:14px;border:1px solid rgba(75,0,142,.12);border-radius:12px;">
      <strong style="display:block;margin-bottom:8px;">Antes de começar</strong>
      <ul style="margin:0;padding-left:18px;">
        <li>
          <strong><?php echo (int) ($estrutura['total_questoes'] ?? $quizParaAluno['total_perguntas']); ?></strong> questão(ões)
          <?php if (!empty($estrutura['total_discursivas'])): ?>
            — <?php echo (int) $estrutura['total_objetivas']; ?> objetivas e <?php echo (int) $estrutura['total_discursivas']; ?> discursiva(s)
          <?php endif; ?>
        </li>
        <?php if (!empty($estrutura['duracao_minutos'])): ?>
          <li>Duração: <strong><?php echo Helpers::e($formatarDuracao($estrutura['duracao_minutos'])); ?></strong>
            (<?php echo (int) $estrutura['duracao_minutos']; ?> minutos), contados a partir do início</li>
        <?php else: ?>
          <li>Sem limite de tempo</li>
        <?php endif; ?>
        <?php if (!empty($estrutura['exige_aprovacao'])): ?>
          <li>Aprovação com no mínimo <strong><?php echo number_format((float) $estrutura['percentual_minimo'], 0); ?>%</strong>
            <?php if (!empty($estrutura['total_discursivas'])): ?>
              nas questões objetivas. A nota da discursiva não altera a aprovação.
            <?php endif; ?>
          </li>
        <?php endif; ?>
        <?php if (($estrutura['tentativas_restantes'] ?? null) !== null): ?>
          <li>Tentativas restantes: <strong><?php echo (int) $estrutura['tentativas_restantes']; ?></strong>
            de <?php echo (int) $estrutura['tentativas_maximas']; ?></li>
        <?php elseif ($tentativas > 0): ?>
          <li><?php echo (int) $tentativas; ?> tentativa(s) realizada(s)</li>
        <?php endif; ?>
        <?php if (!empty($estrutura['duracao_minutos']) && (string) ($estrutura['acao_ao_expirar'] ?? '') === 'enviar_automatico'): ?>
          <li>Ao esgotar o tempo, as respostas salvas são enviadas automaticamente.</li>
        <?php endif; ?>
      </ul>

      <?php if (!empty($estrutura['blocos'])): ?>
        <strong style="display:block;margin:12px 0 4px;">Estrutura da prova</strong>
        <ul style="margin:0;padding-left:18px;">
          <?php foreach ($estrutura['blocos'] as $bloco): ?>
            <li>
              <?php echo Helpers::e((string) $bloco['titulo']); ?>:
              <strong><?php echo (int) $bloco['quantidade']; ?></strong>
              <?php echo (string) $bloco['tipo_questao'] === 'discursiva' ? 'questão discursiva' : 'questões objetivas'; ?>
              <?php if (empty($bloco['conta_para_percentual'])): ?>
                <span class="dc-study-note">(fora do percentual de aprovação)</span>
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
        <button type="submit" class="dc-btn dc-btn-primary dc-btn-block">
          <i class="ti ti-player-play"></i>
          <?php echo !empty($estrutura['duracao_minutos']) ? 'Iniciar prova' : 'Iniciar quiz'; ?>
        </button>
      </form>
      <?php if (!empty($estrutura['duracao_minutos'])): ?>
        <p class="dc-study-note" style="margin-top:6px;">
          O cronômetro começa assim que você iniciar e continua correndo mesmo se você sair da página.
        </p>
      <?php endif; ?>
    <?php else: ?>
      <p class="dc-study-note">Você atingiu o número máximo de tentativas para este quiz.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
