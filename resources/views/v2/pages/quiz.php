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
          <?php
          $estrutura = isset($quiz['estrutura']) && is_array($quiz['estrutura']) ? $quiz['estrutura'] : array();
          $duracao   = isset($estrutura['duracao_minutos']) ? (int) $estrutura['duracao_minutos'] : 0;
          $ehProva   = $duracao > 0 || !empty($estrutura['por_blocos']);

          // "5h30" para provas longas; "45 min" para as curtas.
          $duracaoTexto = '';
          if ($duracao > 0) {
              $horas = (int) floor($duracao / 60);
              $resto = $duracao % 60;
              $duracaoTexto = $horas > 0
                  ? ($resto > 0 ? $horas . 'h' . str_pad((string) $resto, 2, '0', STR_PAD_LEFT) : $horas . 'h')
                  : $duracao . ' min';
          }
          ?>

          <?php if ($ehProva): ?>
            <div class="v2-quiz-estrutura v2-block" style="border:1px solid rgba(0,0,0,.08);border-radius:12px;padding:14px;margin:12px 0;">
              <p style="font-weight:700;margin:0 0 8px;">Antes de começar</p>
              <ul style="margin:0;padding-left:18px;">
                <li>
                  <strong><?php echo (int) ($estrutura['total_questoes'] ?? $quiz['total_perguntas'] ?? 0); ?></strong> questões
                  <?php if (!empty($estrutura['total_discursivas'])): ?>
                    — <?php echo (int) $estrutura['total_objetivas']; ?> objetivas e
                    <?php echo (int) $estrutura['total_discursivas']; ?> discursiva(s)
                  <?php endif; ?>
                </li>
                <?php if ($duracao > 0): ?>
                  <li>Duração: <strong><?php echo Helpers::e($duracaoTexto); ?></strong> (<?php echo $duracao; ?> minutos), a partir do início</li>
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
                <?php endif; ?>
                <?php if ($duracao > 0 && (string) ($estrutura['acao_ao_expirar'] ?? '') === 'enviar_automatico'): ?>
                  <li>Ao esgotar o tempo, as respostas salvas são enviadas automaticamente.</li>
                <?php endif; ?>
              </ul>

              <?php if (!empty($estrutura['blocos'])): ?>
                <p style="font-weight:700;margin:12px 0 4px;">Estrutura da prova</p>
                <ul style="margin:0;padding-left:18px;">
                  <?php foreach ($estrutura['blocos'] as $bloco): ?>
                    <li>
                      <?php echo Helpers::e((string) $bloco['titulo']); ?>:
                      <strong><?php echo (int) $bloco['quantidade']; ?></strong>
                      <?php echo (string) $bloco['tipo_questao'] === 'discursiva' ? 'questão discursiva' : 'questões objetivas'; ?>
                      <?php if (empty($bloco['conta_para_percentual'])): ?>
                        <span class="v2-muted">(fora do percentual de aprovação)</span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <p class="v2-muted v2-sm">
              <?php echo (int) ($quiz['total_perguntas'] ?? 0); ?> pergunta(s)
              <?php if ($quiz['tentativas_maximas'] !== null): ?>
                · Tentativas: <?php echo (int) ($quiz['tentativas_usadas'] ?? 0); ?>/<?php echo (int) $quiz['tentativas_maximas']; ?>
              <?php elseif ((int) ($quiz['tentativas_usadas'] ?? 0) > 0): ?>
                · <?php echo (int) $quiz['tentativas_usadas']; ?> tentativa(s) realizada(s)
              <?php endif; ?>
            </p>
          <?php endif; ?>

          <?php if (!empty($quiz['pode_nova_tentativa'])): ?>
            <form method="post" action="<?php echo Helpers::e($iniciarAction); ?>" data-native-submit class="v2-quiz-form">
              <?php echo $hidden; ?>
              <button type="submit" class="v2-btn v2-btn-primary" data-quiz-btn data-loading-label="Iniciando…">
                <i class="ti ti-player-play"></i> <?php echo $ehProva ? 'Iniciar prova' : 'Iniciar quiz'; ?>
              </button>
            </form>
            <?php if ($duracao > 0): ?>
              <p class="v2-muted v2-sm" style="margin-top:6px;">
                O cronômetro começa assim que você iniciar e continua correndo mesmo se você sair da página.
              </p>
            <?php endif; ?>
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
        <?php $tempo = isset($quiz['tempo']) && is_array($quiz['tempo']) ? $quiz['tempo'] : null; ?>
        <?php if ($tempo !== null): ?>
          <div id="v2-quiz-cronometro" role="timer" aria-live="polite"
               data-tentativa="<?php echo (int) ($quiz['tentativa_ativa_id'] ?? 0); ?>"
               data-restante="<?php echo (int) $tempo['segundos_restantes']; ?>"
               style="position:sticky;top:0;z-index:5;display:flex;gap:12px;align-items:center;justify-content:space-between;
                      padding:10px 14px;margin:0 0 12px;border-radius:12px;border:1px solid rgba(0,0,0,.1);background:#fff;">
            <span class="v2-muted v2-sm" style="margin:0;">Tempo restante</span>
            <strong id="v2-quiz-cronometro-valor" style="font-size:1.15rem;font-variant-numeric:tabular-nums;">--:--:--</strong>
          </div>
        <?php endif; ?>

        <?php if (trim((string) ($quiz['instrucoes'] ?? '')) !== ''): ?>
          <div class="v2-quiz-instrucoes"><?php echo nl2br(Helpers::e((string) $quiz['instrucoes'])); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo Helpers::e($enviarAction); ?>" data-native-submit class="v2-quiz-form" id="v2-quiz-answer-form">
          <?php echo $hidden; ?>
          <input type="hidden" name="tentativa_id" value="<?php echo (int) ($quiz['tentativa_ativa_id'] ?? 0); ?>">

          <p class="v2-muted v2-sm" id="v2-quiz-autosave" role="status" aria-live="polite" style="margin:0 0 10px;">
            Suas respostas são salvas automaticamente conforme você responde.
          </p>

          <?php
          // Prova longa por blocos: navegação de uma questão por vez, com
          // índice, marcação para revisão e tela de conferência antes do envio.
          // Precisa ser calculado antes do primeiro uso, logo abaixo.
          $modoProva = false;
          foreach (($quiz['perguntas'] ?? array()) as $p) {
              if ((string) ($p['tipo'] ?? '') === 'discursiva' || (string) ($p['bloco_codigo'] ?? '') !== '') {
                  $modoProva = true;
                  break;
              }
          }
          ?>

          <?php if (count($quiz['perguntas'] ?? array()) > 1 && !$modoProva): ?>
            <div id="v2-quiz-progress" class="v2-quiz-progress" hidden>
              <div class="v2-quiz-progress__bar"><div class="v2-quiz-progress__fill" id="v2-quiz-progress-fill"></div></div>
              <div class="v2-quiz-progress__row">
                <span class="v2-quiz-progress__text" id="v2-quiz-progress-text"></span>
                <div class="v2-quiz-progress__dots" id="v2-quiz-progress-dots"></div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($modoProva): ?>
            <?php // Sem JS este bloco fica oculto e a prova continua rolável e enviável. ?>
            <div id="v2-quiz-prova-nav" class="v2-quiz-prova-nav" hidden>
              <div class="v2-quiz-prova-nav__topo">
                <strong id="v2-quiz-prova-bloco" class="v2-quiz-prova-nav__bloco"></strong>
                <span id="v2-quiz-prova-pos" class="v2-muted v2-sm"></span>
              </div>
              <div class="v2-quiz-progress__bar"><div class="v2-quiz-progress__fill" id="v2-quiz-prova-fill"></div></div>
              <div class="v2-quiz-prova-nav__rodape">
                <span id="v2-quiz-prova-resumo" class="v2-muted v2-sm" role="status" aria-live="polite"></span>
                <button type="button" class="v2-btn v2-btn-ghost v2-btn-sm" id="v2-quiz-prova-indice-btn"
                        aria-expanded="false" aria-controls="v2-quiz-prova-indice">
                  <i class="ti ti-layout-grid"></i> Índice de questões
                </button>
              </div>
              <div id="v2-quiz-prova-indice" class="v2-quiz-prova-indice" hidden></div>
            </div>
          <?php endif; ?>
          <div id="v2-quiz-perguntas"<?php echo $modoProva ? ' data-modo-prova="1"' : ''; ?>>
            <?php $blocoAtual = null; ?>
            <?php foreach (($quiz['perguntas'] ?? array()) as $idxP => $pergunta): ?>
              <?php
              $pid          = (int) $pergunta['id'];
              $marcada      = (int) ($pergunta['alternativa_id_respondida'] ?? 0);
              $ehDiscursiva = (string) ($pergunta['tipo'] ?? 'multipla_escolha') === 'discursiva';
              $codigoBloco  = (string) ($pergunta['bloco_codigo'] ?? '');
              ?>

              <?php if ($codigoBloco !== '' && $codigoBloco !== $blocoAtual): ?>
                <?php $blocoAtual = $codigoBloco; ?>
                <h3 class="v2-quiz-bloco-titulo" style="margin:20px 0 8px;font-size:1rem;">
                  <?php echo Helpers::e((string) ($pergunta['bloco_titulo'] ?? 'Questões')); ?>
                </h3>
              <?php endif; ?>

              <fieldset class="v2-quiz-pergunta" data-pergunta-id="<?php echo $pid; ?>"
                        data-tipo="<?php echo $ehDiscursiva ? 'discursiva' : 'objetiva'; ?>"
                        data-bloco="<?php echo Helpers::e($codigoBloco); ?>"
                        data-bloco-titulo="<?php echo Helpers::e((string) ($pergunta['bloco_titulo'] ?? '')); ?>"
                        data-revisao="<?php echo !empty($pergunta['marcada_para_revisao']) ? '1' : '0'; ?>">
                <legend class="v2-quiz-enunciado">
                  <span class="v2-quiz-num"><?php echo (int) $idxP + 1; ?></span>
                  <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?>
                  <?php if (!empty($pergunta['obrigatoria'])): ?><span class="v2-quiz-obrig" title="Questão obrigatória" aria-label="obrigatória">*</span><?php endif; ?>
                </legend>

                <?php if ($modoProva): ?>
                  <button type="button" class="v2-quiz-flag" data-flag-pergunta="<?php echo $pid; ?>"
                          aria-pressed="<?php echo !empty($pergunta['marcada_para_revisao']) ? 'true' : 'false'; ?>" hidden>
                    <i class="ti ti-flag"></i> <span class="v2-quiz-flag__texto">Marcar para revisão</span>
                  </button>
                <?php endif; ?>

                <?php if ($ehDiscursiva): ?>
                  <label class="v2-muted v2-sm" for="v2-discursiva-<?php echo $pid; ?>" style="display:block;margin-bottom:4px;">Sua resposta</label>
                  <textarea id="v2-discursiva-<?php echo $pid; ?>"
                            name="discursivas[<?php echo $pid; ?>]"
                            rows="12"
                            maxlength="<?php echo (int) ($quiz['limite_caracteres_discursiva'] ?? 50000); ?>"
                            style="width:100%;min-height:220px;padding:10px;border:1px solid rgba(0,0,0,.16);border-radius:10px;"
                            placeholder="Escreva sua resposta."><?php echo Helpers::e((string) ($pergunta['texto_resposta'] ?? '')); ?></textarea>
                  <p class="v2-muted v2-sm" style="margin:4px 0 0;">
                    A nota desta questão é informativa: não altera a aprovação nem o certificado.
                  </p>
                <?php else: ?>
                  <div class="v2-quiz-alts">
                    <?php foreach (($pergunta['alternativas'] ?? array()) as $alt): ?>
                      <?php $aid = (int) $alt['id']; ?>
                      <label class="v2-quiz-alt">
                        <input type="radio" name="respostas[<?php echo $pid; ?>]" value="<?php echo $aid; ?>"<?php echo $marcada === $aid ? ' checked' : ''; ?>>
                        <span class="v2-quiz-alt-text"><?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </fieldset>
            <?php endforeach; ?>
          </div>

          <?php if ($modoProva): ?>
            <?php // Conferência antes do envio. Sem JS continua oculta e o envio direto segue valendo. ?>
            <div id="v2-quiz-prova-revisao" class="v2-block" hidden
                 style="border:1px solid rgba(0,0,0,.1);border-radius:12px;padding:16px;margin-top:12px;">
              <h2 class="v2-h3" style="margin:0 0 4px;">Revisão antes de enviar</h2>
              <p class="v2-muted v2-sm" style="margin:0 0 12px;">
                Confira o que ficou pendente. Você ainda pode voltar e responder.
              </p>
              <div id="v2-quiz-prova-revisao-resumo"></div>
              <div id="v2-quiz-prova-revisao-listas"></div>
              <div class="v2-quiz-actions" style="margin-top:14px;">
                <button type="button" class="v2-btn v2-btn-ghost" id="v2-quiz-prova-voltar-btn">
                  <i class="ti ti-arrow-left"></i> Voltar à prova
                </button>
                <button type="submit" class="v2-btn v2-btn-primary" id="v2-quiz-prova-enviar-btn"
                        data-quiz-btn data-loading-label="Enviando…">
                  <i class="ti ti-send"></i> Enviar prova
                </button>
              </div>
            </div>
          <?php endif; ?>

          <div class="v2-quiz-actions">
            <?php if (!$modoProva): ?>
              <?php // Assistente dos quizzes curtos. Em modo prova estes botões
                    // não existem: `hidden` não os esconderia, porque .v2-btn
                    // define display:inline-flex e vence o atributo. ?>
              <button type="button" class="v2-btn v2-btn-ghost" id="v2-quiz-prev-btn" hidden><i class="ti ti-arrow-left"></i> Anterior</button>
              <button type="button" class="v2-btn v2-btn-primary" id="v2-quiz-next-btn" hidden>Próxima <i class="ti ti-arrow-right"></i></button>
            <?php endif; ?>
            <?php if ($modoProva): ?>
              <button type="button" class="v2-btn v2-btn-ghost" id="v2-quiz-prova-prev-btn" hidden><i class="ti ti-arrow-left"></i> Anterior</button>
              <button type="button" class="v2-btn v2-btn-primary" id="v2-quiz-prova-next-btn" hidden>Próxima <i class="ti ti-arrow-right"></i></button>
              <button type="button" class="v2-btn v2-btn-primary" id="v2-quiz-prova-revisar-btn" hidden><i class="ti ti-list-check"></i> Revisar e enviar</button>
            <?php endif; ?>
            <button type="submit" class="v2-btn v2-btn-primary" id="v2-quiz-submit-btn" data-quiz-btn data-loading-label="Enviando…"><i class="ti ti-send"></i> Enviar respostas</button>
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
                <p class="v2-quiz-explica"><i class="ti ti-info-circle"></i> <?php echo nl2br(Helpers::e((string) $pergunta['explicacao'])); ?></p>
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
