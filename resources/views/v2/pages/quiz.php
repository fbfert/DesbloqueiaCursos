<?php
use App\Core\Helpers;

$estado = isset($estado) && is_array($estado) ? $estado : null;
$cabecalho = isset($cabecalho) && is_array($cabecalho) ? $cabecalho : null;
$quiz = isset($quiz) && is_array($quiz) ? $quiz : null;
$formCtx = isset($formCtx) && is_array($formCtx) ? $formCtx : array();
$alunoHref = isset($alunoHref) ? (string) $alunoHref : '/v2/aluno/';
$voltarAulaUrl = isset($voltarAulaUrl) ? (string) $voltarAulaUrl : '/v2/aluno/';
$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;

$iniciarAction = isset($formCtx['iniciar_action']) ? (string) $formCtx['iniciar_action'] : '/v2/quiz/iniciar';
$enviarAction = isset($formCtx['enviar_action']) ? (string) $formCtx['enviar_action'] : '/v2/quiz/enviar';

// Campos ocultos comuns (localizadores; a autorização é refeita no servidor).
$hidden = ''
    . '<input type="hidden" name="inscricao_id" value="' . (int) ($formCtx['inscricao_id'] ?? 0) . '">'
    . '<input type="hidden" name="curso_id" value="' . (int) ($formCtx['curso_id'] ?? 0) . '">'
    . '<input type="hidden" name="turma_id" value="' . (int) ($formCtx['turma_id'] ?? 0) . '">'
    . '<input type="hidden" name="modulo_id" value="' . (int) ($formCtx['modulo_id'] ?? 0) . '">'
    . '<input type="hidden" name="item_id" value="' . (int) ($formCtx['item_id'] ?? 0) . '">';
?>
<script>window.V2_DISABLE_AUTORENDER_ALUNO = true;</script>

<?php if ($estado): ?>
  <div class="v2-container v2-lms-area">
    <div class="v2-empty">
      <i class="ti ti-help-circle"></i>
      <p><strong><?php echo Helpers::e((string) $estado['titulo']); ?></strong></p>
      <p><?php echo Helpers::e((string) $estado['mensagem']); ?></p>
      <a href="<?php echo Helpers::e($alunoHref); ?>" class="v2-btn v2-btn-primary"><i class="ti ti-arrow-left"></i> Voltar à minha área</a>
    </div>
  </div>
  <?php return; ?>
<?php endif; ?>

<div class="v2-lms-area">
  <!-- Topbar do curso -->
  <div class="v2-lms-top">
    <div class="v2-container v2-lms-top-inner">
      <a href="<?php echo Helpers::e($voltarAulaUrl); ?>" class="v2-iconbtn" aria-label="Voltar à aula"><i class="ti ti-arrow-left"></i></a>
      <div class="v2-lms-top-body">
        <div class="v2-lms-curso"><?php echo Helpers::e((string) ($cabecalho['curso_nome'] ?? '')); ?></div>
        <?php if (!empty($cabecalho['modulo_nome'])): ?>
          <div class="v2-lms-aula"><?php echo Helpers::e((string) $cabecalho['modulo_nome']); ?></div>
        <?php endif; ?>
      </div>
      <div class="v2-lms-top-prog"><span class="v2-lms-top-pct"><?php echo (int) ($cabecalho['progresso'] ?? 0); ?>%</span></div>
    </div>
    <div class="v2-container">
      <div class="v2-progress-track v2-lms-topbar-track"><div class="v2-progress-fill" style="width:<?php echo (int) ($cabecalho['progresso'] ?? 0); ?>%"></div></div>
    </div>
  </div>

  <div class="v2-container">
    <div class="v2-quiz">
      <span class="v2-badge v2-badge-novo">Quiz</span>
      <h1 class="v2-h2" style="margin:8px 0;"><?php echo Helpers::e((string) ($cabecalho['quiz_nome'] ?? 'Quiz')); ?></h1>

      <!-- Mensagens reais (sucesso/erro) -->
      <div id="v2-quiz-feedback" tabindex="-1" aria-live="assertive">
        <?php if (!empty($success)): ?>
          <div class="v2-callout v2-callout-success" role="status"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e(is_array($success) && !empty($success['message']) ? (string) $success['message'] : (string) $success); ?></span></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
          <div class="v2-callout v2-callout-danger" role="alert"><i class="ti ti-alert-triangle"></i><span><?php foreach ($errors as $erro): ?><?php echo Helpers::e((string) $erro); ?><br><?php endforeach; ?></span></div>
        <?php endif; ?>
      </div>

      <?php if (!$quiz || ($quiz['estado'] ?? '') === 'indisponivel'): ?>
        <div class="v2-block">
          <p class="v2-muted">Este quiz ainda não está disponível.</p>
          <a class="v2-btn v2-btn-outline" href="<?php echo Helpers::e($voltarAulaUrl); ?>"><i class="ti ti-arrow-left"></i> Voltar à aula</a>
        </div>

      <?php elseif ($quiz['estado'] === 'antes'): ?>
        <div class="v2-block">
          <?php if (trim((string) ($quiz['instrucoes'] ?? '')) !== ''): ?>
            <div class="v2-quiz-instrucoes"><?php echo nl2br(Helpers::e((string) $quiz['instrucoes'])); ?></div>
          <?php endif; ?>
          <p class="v2-muted v2-sm">
            <?php echo (int) ($quiz['total_perguntas'] ?? 0); ?> pergunta(s)
            <?php if ($quiz['tentativas_maximas'] !== null): ?>
              · Tentativas: <?php echo (int) ($quiz['tentativas_usadas'] ?? 0); ?>/<?php echo (int) $quiz['tentativas_maximas']; ?>
            <?php elseif ((int) ($quiz['tentativas_usadas'] ?? 0) > 0): ?>
              · <?php echo (int) $quiz['tentativas_usadas']; ?> tentativa(s) realizada(s)
            <?php endif; ?>
          </p>
          <?php if (!empty($quiz['pode_nova_tentativa'])): ?>
            <form method="post" action="<?php echo Helpers::e($iniciarAction); ?>" data-native-submit class="v2-quiz-form">
              <?php echo $hidden; ?>
              <button type="submit" class="v2-btn v2-btn-primary" data-quiz-btn data-loading-label="Iniciando…"><i class="ti ti-player-play"></i> Iniciar quiz</button>
            </form>
          <?php else: ?>
            <p class="v2-callout v2-callout-danger" role="status"><i class="ti ti-alert-triangle"></i><span>Você atingiu o número máximo de tentativas para este quiz.</span></p>
          <?php endif; ?>
        </div>

      <?php elseif ($quiz['estado'] === 'andamento'): ?>
        <div class="v2-quiz-meta">
          <span class="v2-badge v2-badge-novo">Em andamento — tentativa <?php echo (int) ($quiz['numero_tentativa'] ?? 1); ?></span>
          <span class="v2-muted v2-sm"><?php echo count($quiz['perguntas'] ?? array()); ?> pergunta(s)</span>
          <?php if (($quiz['tentativas_maximas'] ?? null) !== null): ?>
            <span class="v2-muted v2-sm">Tentativas usadas: <?php echo (int) ($quiz['tentativas_usadas'] ?? 0); ?>/<?php echo (int) $quiz['tentativas_maximas']; ?></span>
          <?php endif; ?>
        </div>
        <?php if (trim((string) ($quiz['instrucoes'] ?? '')) !== ''): ?>
          <div class="v2-quiz-instrucoes"><?php echo nl2br(Helpers::e((string) $quiz['instrucoes'])); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo Helpers::e($enviarAction); ?>" data-native-submit class="v2-quiz-form" id="v2-quiz-answer-form">
          <?php echo $hidden; ?>
          <input type="hidden" name="tentativa_id" value="<?php echo (int) ($quiz['tentativa_ativa_id'] ?? 0); ?>">

          <?php foreach (($quiz['perguntas'] ?? array()) as $idxP => $pergunta): ?>
            <?php $pid = (int) $pergunta['id']; $marcada = (int) ($pergunta['alternativa_id_respondida'] ?? 0); ?>
            <fieldset class="v2-quiz-pergunta" data-pergunta-id="<?php echo $pid; ?>">
              <legend class="v2-quiz-enunciado">
                <span class="v2-quiz-num"><?php echo (int) $idxP + 1; ?></span>
                <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?>
                <?php if (!empty($pergunta['obrigatoria'])): ?><span class="v2-quiz-obrig" title="Pergunta obrigatória" aria-label="obrigatória">*</span><?php endif; ?>
              </legend>
              <div class="v2-quiz-alts">
                <?php foreach (($pergunta['alternativas'] ?? array()) as $alt): ?>
                  <?php $aid = (int) $alt['id']; ?>
                  <label class="v2-quiz-alt">
                    <input type="radio" name="respostas[<?php echo $pid; ?>]" value="<?php echo $aid; ?>"<?php echo $marcada === $aid ? ' checked' : ''; ?>>
                    <span class="v2-quiz-alt-text"><?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          <?php endforeach; ?>

          <div class="v2-quiz-actions">
            <button type="submit" class="v2-btn v2-btn-primary" data-quiz-btn data-loading-label="Enviando…"><i class="ti ti-send"></i> Enviar respostas</button>
            <a class="v2-btn v2-btn-ghost v2-btn-sm" href="<?php echo Helpers::e($voltarAulaUrl); ?>">Voltar à aula</a>
          </div>
          <p class="v2-muted v2-sm" style="margin-top:8px;">(*) Perguntas obrigatórias</p>
        </form>

      <?php elseif ($quiz['estado'] === 'resultado'): ?>
        <?php $r = isset($quiz['resultado']) && is_array($quiz['resultado']) ? $quiz['resultado'] : array(); ?>
        <div class="v2-block" role="status">
          <?php if (!empty($r['mostrar_resultado'])): ?>
            <p class="v2-quiz-score">Você acertou <strong><?php echo (int) ($r['total_acertos'] ?? 0); ?> de <?php echo (int) ($r['total_perguntas'] ?? 0); ?></strong> questões (<?php echo number_format((float) ($r['percentual'] ?? 0), 1); ?>%).</p>
            <?php if (($r['aprovado'] ?? null) !== null): ?>
              <?php if (!empty($r['aprovado'])): ?>
                <p class="v2-quiz-status v2-quiz-status--ok"><i class="ti ti-circle-check-filled"></i> Aprovado</p>
              <?php else: ?>
                <p class="v2-quiz-status v2-quiz-status--fail"><i class="ti ti-circle-x-filled"></i> Não atingiu o percentual mínimo</p>
              <?php endif; ?>
            <?php endif; ?>
          <?php else: ?>
            <p class="v2-quiz-status v2-quiz-status--ok"><i class="ti ti-circle-check-filled"></i> Quiz enviado com sucesso.</p>
          <?php endif; ?>
        </div>

        <?php if (!empty($r['mostrar_gabarito']) || !empty($r['mostrar_comentarios'])): ?>
          <?php foreach (($quiz['perguntas'] ?? array()) as $idxP => $pergunta): ?>
            <?php $marcada = (int) ($pergunta['alternativa_id_respondida'] ?? 0); ?>
            <fieldset class="v2-quiz-pergunta v2-quiz-pergunta--review" data-pergunta-id="<?php echo (int) $pergunta['id']; ?>">
              <legend class="v2-quiz-enunciado">
                <span class="v2-quiz-num"><?php echo (int) $idxP + 1; ?></span>
                <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?>
              </legend>
              <div class="v2-quiz-alts">
                <?php foreach (($pergunta['alternativas'] ?? array()) as $alt): ?>
                  <?php
                  $aid = (int) $alt['id'];
                  $temGabarito = !empty($r['mostrar_gabarito']) && array_key_exists('correta', $alt);
                  $isCorreta = $temGabarito && !empty($alt['correta']);
                  $isMarcada = $marcada === $aid;
                  $cls = 'v2-quiz-alt v2-quiz-alt--ro';
                  if ($isCorreta) { $cls .= ' v2-quiz-alt--correct'; }
                  if ($isMarcada && $temGabarito && !$isCorreta) { $cls .= ' v2-quiz-alt--wrong'; }
                  if ($isMarcada) { $cls .= ' v2-quiz-alt--chosen'; }
                  ?>
                  <div class="<?php echo $cls; ?>">
                    <span class="v2-quiz-alt-mark" aria-hidden="true">
                      <?php if ($isCorreta): ?><i class="ti ti-check"></i><?php elseif ($isMarcada && $temGabarito): ?><i class="ti ti-x"></i><?php else: ?><i class="ti ti-point"></i><?php endif; ?>
                    </span>
                    <span class="v2-quiz-alt-text"><?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?></span>
                    <?php if ($isMarcada): ?><span class="v2-quiz-tag">Sua resposta</span><?php endif; ?>
                    <?php if ($isCorreta): ?><span class="v2-quiz-tag v2-quiz-tag--ok">Correta</span><?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if (!empty($r['mostrar_comentarios']) && trim((string) ($pergunta['explicacao'] ?? '')) !== ''): ?>
                <p class="v2-quiz-explica"><i class="ti ti-info-circle"></i> <?php echo Helpers::e((string) $pergunta['explicacao']); ?></p>
              <?php endif; ?>
            </fieldset>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="v2-quiz-actions">
          <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e($voltarAulaUrl); ?>"><i class="ti ti-arrow-left"></i> Voltar à aula</a>
          <?php if (!empty($quiz['pode_nova_tentativa'])): ?>
            <form method="post" action="<?php echo Helpers::e($iniciarAction); ?>" data-native-submit class="v2-quiz-form">
              <?php echo $hidden; ?>
              <button type="submit" class="v2-btn v2-btn-outline" data-quiz-btn data-loading-label="Iniciando…"><i class="ti ti-refresh"></i> Nova tentativa</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
