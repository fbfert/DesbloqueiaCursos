<?php
/**
 * Quiz no tema caderno ("Folha de prova"). Mesmos dados, ramos e formulários de
 * resources/views/v2/pages/quiz.php (QuizController V2):
 *   - estado() genérico (200/404) e quiz.estado indisponivel | antes | andamento | resultado;
 *   - iniciar: POST /v2/quiz/iniciar com os 5 campos ocultos de contexto;
 *   - responder: form #v2-quiz-answer-form (POST /v2/quiz/enviar) com os 5 campos,
 *     tentativa_id, respostas[<pid>] (radio) e discursivas[<pid>] (textarea);
 *     _token injetado pelo View::render (o JS lê dele para os endpoints JSON);
 *   - modo prova quando há discursiva ou bloco (navegação livre, índice, revisão);
 *     senão, quiz curto (uma pergunta por vez quando há mais de uma).
 * Em andamento o controller já não manda gabarito, rubrica nem explicação, e esta
 * view não imprime nenhum deles. Sem JS: todas as perguntas em sequência e o envio
 * nativo funciona (navegação, índice, revisão e barra começam com [hidden]).
 * O módulo `quiz` de caderno-aluno.js faz o salvamento automático, o cronômetro e
 * a navegação; os ids/names abaixo são contrato com ele e com os endpoints.
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';

$estado = isset($estado) && is_array($estado) ? $estado : null;
$cabecalho = isset($cabecalho) && is_array($cabecalho) ? $cabecalho : array();
$quiz = isset($quiz) && is_array($quiz) ? $quiz : null;
$formCtx = isset($formCtx) && is_array($formCtx) ? $formCtx : array();
$alunoHref = isset($alunoHref) ? (string) $alunoHref : '/v2/aluno/';
$voltarAulaUrl = isset($voltarAulaUrl) ? (string) $voltarAulaUrl : '/v2/aluno/';
$errors = isset($errors) && is_array($errors) ? array_values(array_filter($errors, 'is_scalar')) : array();
$success = isset($success) ? $success : null;
$successTexto = !empty($success) ? (is_array($success) ? (string) ($success['message'] ?? '') : (string) $success) : '';

if ($estado): ?>
<div class="quiz">
  <div class="vazio aula-estado">
    <h1 class="mao"><?= Helpers::e((string) $estado['titulo']) ?></h1>
    <p class="lead"><?= Helpers::e((string) $estado['mensagem']) ?></p>
    <a class="btn" href="<?= Helpers::e($alunoHref) ?>"><?= caderno_icone('seta-esq') ?> Voltar à minha área</a>
  </div>
</div>
<?php return; endif;

$iniciarAction = isset($formCtx['iniciar_action']) ? (string) $formCtx['iniciar_action'] : '/v2/quiz/iniciar';
$enviarAction = isset($formCtx['enviar_action']) ? (string) $formCtx['enviar_action'] : '/v2/quiz/enviar';
// Campos ocultos comuns (localizadores; a autorização é refeita no servidor).
$hidden = ''
    . '<input type="hidden" name="inscricao_id" value="' . (int) ($formCtx['inscricao_id'] ?? 0) . '">'
    . '<input type="hidden" name="curso_id" value="' . (int) ($formCtx['curso_id'] ?? 0) . '">'
    . '<input type="hidden" name="turma_id" value="' . (int) ($formCtx['turma_id'] ?? 0) . '">'
    . '<input type="hidden" name="modulo_id" value="' . (int) ($formCtx['modulo_id'] ?? 0) . '">'
    . '<input type="hidden" name="item_id" value="' . (int) ($formCtx['item_id'] ?? 0) . '">';

$qEstado = $quiz ? (string) ($quiz['estado'] ?? '') : 'indisponivel';
$perguntas = $quiz && isset($quiz['perguntas']) && is_array($quiz['perguntas']) ? $quiz['perguntas'] : array();
$estrutura = $quiz && isset($quiz['estrutura']) && is_array($quiz['estrutura']) ? $quiz['estrutura'] : array();
$duracao = isset($estrutura['duracao_minutos']) ? (int) $estrutura['duracao_minutos'] : 0;
$ehProva = $duracao > 0 || !empty($estrutura['por_blocos']);
// Modo prova (como a V2): alguma discursiva ou alguma pergunta em bloco.
$modoProva = false;
foreach ($perguntas as $qP) {
    if ((string) ($qP['tipo'] ?? '') === 'discursiva' || (string) ($qP['bloco_codigo'] ?? '') !== '') {
        $modoProva = true;
        break;
    }
}
$quizNome = (string) ($cabecalho['quiz_nome'] ?? '') !== '' ? (string) $cabecalho['quiz_nome'] : 'Quiz';
$cursoNome = (string) ($cabecalho['curso_nome'] ?? '');
$progresso = max(0, min(100, (int) ($cabecalho['progresso'] ?? 0)));
$instrucoes = $quiz ? trim((string) ($quiz['instrucoes'] ?? '')) : '';
?>
<div class="quiz">
  <header class="aula-cab">
    <nav class="migalha" aria-label="Caminho">
      <a href="<?= Helpers::e($alunoHref) ?>">Minha área</a><span aria-hidden="true">›</span>
      <a href="<?= Helpers::e($voltarAulaUrl) ?>"><?= Helpers::e($cursoNome !== '' ? $cursoNome : 'Voltar à aula') ?></a>
    </nav>
    <div class="al-prog aula-prog" style="--p:<?= $progresso ?>">
      <svg viewBox="0 0 200 14" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <path class="al-prog-base" pathLength="100" d="M2 8C40 5 70 10.5 102 7.5S162 5 198 8"/>
        <?php if ($progresso > 0): ?><path class="al-prog-tinta" pathLength="100" stroke-dasharray="<?= $progresso ?> 100" d="M2 8C40 5 70 10.5 102 7.5S162 5 198 8"/><?php endif; ?>
      </svg>
      <p><b><?= $progresso ?>%</b> <span>do curso</span></p>
    </div>
  </header>

  <article class="aula-pag quiz-folha" aria-labelledby="quiz-titulo">
    <p class="aula-kicker"><span class="fita"><?= $ehProva || $modoProva ? 'Prova' : 'Quiz' ?></span><?php if (!empty($cabecalho['modulo_nome'])): ?> <span><?= Helpers::e((string) $cabecalho['modulo_nome']) ?></span><?php endif; ?></p>
    <h1 class="aula-tit" id="quiz-titulo"><?= Helpers::e($quizNome) ?></h1>

    <div id="v2-quiz-feedback" class="aula-aviso" tabindex="-1" aria-live="assertive" data-aviso-foco>
      <?php if ($successTexto !== ''): ?>
      <p class="aula-ok-msg" role="status"><?= Helpers::e($successTexto) ?></p>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
      <div class="postit erro largo" role="alert"><?php foreach ($errors as $qI => $qErro): ?><?= $qI > 0 ? '<br>' : '' ?><?= Helpers::e((string) $qErro) ?><?php endforeach; ?></div>
      <?php endif; ?>
    </div>

    <?php if (!$quiz || $qEstado === 'indisponivel'): ?>
    <div class="vazio aula-estado">
      <p class="lead">Este quiz ainda não está disponível.</p>
      <a class="btn-sec" href="<?= Helpers::e($voltarAulaUrl) ?>"><?= caderno_icone('seta-esq') ?> Voltar à aula<?= caderno_ck_contorno() ?></a>
    </div>

    <?php elseif ($qEstado === 'antes'): ?>
    <?php if ($instrucoes !== ''): ?>
    <div class="quiz-instr"><p class="atv-rot">Instruções</p><p><?= nl2br(Helpers::e($instrucoes)) ?></p></div>
    <?php endif; ?>
    <?php
    // "5h30" para provas longas; "45 min" para as curtas (como a V2).
    $duracaoTexto = '';
    if ($duracao > 0) {
        $qHoras = (int) floor($duracao / 60);
        $qResto = $duracao % 60;
        $duracaoTexto = $qHoras > 0
            ? ($qResto > 0 ? $qHoras . 'h' . str_pad((string) $qResto, 2, '0', STR_PAD_LEFT) : $qHoras . 'h')
            : $duracao . ' min';
    }
    ?>
    <?php if ($ehProva): ?>
    <section class="prova-cab" aria-labelledby="prova-cab-tit">
      <h2 class="mao" id="prova-cab-tit">Antes de começar</h2>
      <ul class="prova-regras">
        <li><strong><?= (int) ($estrutura['total_questoes'] ?? $quiz['total_perguntas'] ?? 0) ?></strong> questões<?php if (!empty($estrutura['total_discursivas'])): ?> — <?= (int) ($estrutura['total_objetivas'] ?? 0) ?> objetivas e <?= (int) $estrutura['total_discursivas'] ?> discursiva(s)<?php endif; ?></li>
        <?php if ($duracao > 0): ?>
        <li>Duração: <strong><?= Helpers::e($duracaoTexto) ?></strong> (<?= $duracao ?> minutos), a partir do início</li>
        <?php else: ?>
        <li>Sem limite de tempo</li>
        <?php endif; ?>
        <?php if (!empty($estrutura['exige_aprovacao'])): ?>
        <li>Aprovação com no mínimo <strong><?= number_format((float) ($estrutura['percentual_minimo'] ?? 0), 0, ',', '.') ?>%</strong><?php if (!empty($estrutura['total_discursivas'])): ?> nas questões objetivas. A nota da discursiva não altera a aprovação.<?php endif; ?></li>
        <?php endif; ?>
        <?php if (($estrutura['tentativas_restantes'] ?? null) !== null): ?>
        <li>Tentativas restantes: <strong><?= (int) $estrutura['tentativas_restantes'] ?></strong> de <?= (int) ($estrutura['tentativas_maximas'] ?? 0) ?></li>
        <?php endif; ?>
        <?php if ($duracao > 0 && (string) ($estrutura['acao_ao_expirar'] ?? '') === 'enviar_automatico'): ?>
        <li>Ao esgotar o tempo, as respostas salvas são enviadas automaticamente.</li>
        <?php endif; ?>
      </ul>
      <?php if (!empty($estrutura['blocos']) && is_array($estrutura['blocos'])): ?>
      <h3 class="prova-sub">Estrutura da prova</h3>
      <ol class="prova-blocos">
        <?php foreach ($estrutura['blocos'] as $qBloco): ?>
        <li><span><?= Helpers::e((string) ($qBloco['titulo'] ?? '')) ?>:</span> <strong><?= (int) ($qBloco['quantidade'] ?? 0) ?></strong> <?= (string) ($qBloco['tipo_questao'] ?? '') === 'discursiva' ? 'questão discursiva' : 'questões objetivas' ?><?php if (empty($qBloco['conta_para_percentual'])): ?> <small>(fora do percentual de aprovação)</small><?php endif; ?></li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </section>
    <?php else: ?>
    <p class="quiz-meta">
      <?= (int) ($quiz['total_perguntas'] ?? 0) ?> pergunta(s)<?php if (($quiz['tentativas_maximas'] ?? null) !== null): ?> · Tentativas: <?= (int) ($quiz['tentativas_usadas'] ?? 0) ?>/<?= (int) $quiz['tentativas_maximas'] ?><?php elseif ((int) ($quiz['tentativas_usadas'] ?? 0) > 0): ?> · <?= (int) $quiz['tentativas_usadas'] ?> tentativa(s) realizada(s)<?php endif; ?>
    </p>
    <?php endif; ?>

    <?php if (!empty($quiz['pode_nova_tentativa'])): ?>
    <form method="post" action="<?= Helpers::e($iniciarAction) ?>" data-native-submit class="v2-quiz-form quiz-iniciar">
      <?= $hidden ?>
      <button type="submit" class="btn" data-quiz-btn data-loading-label="Iniciando…"><?= $ehProva ? 'Iniciar prova' : 'Iniciar quiz' ?> <?= caderno_icone('seta-dir') ?></button>
      <?php if ($duracao > 0): ?>
      <p class="quiz-nota-rel"><?= caderno_icone('relogio') ?> O cronômetro começa assim que você iniciar e continua correndo mesmo se você sair da página.</p>
      <?php endif; ?>
    </form>
    <?php else: ?>
    <p class="postit erro largo quiz-limite" role="status">Você atingiu o número máximo de tentativas para este quiz.</p>
    <?php endif; ?>

    <?php elseif ($qEstado === 'andamento'): ?>
    <?php $tempo = isset($quiz['tempo']) && is_array($quiz['tempo']) ? $quiz['tempo'] : null; ?>
    <p class="quiz-meta">
      <b class="selo tinta">Em andamento — tentativa <?= (int) ($quiz['numero_tentativa'] ?? 1) ?></b>
      <span><?= count($perguntas) ?> pergunta(s)</span>
      <?php if (($quiz['tentativas_maximas'] ?? null) !== null): ?><span>Tentativas usadas: <?= (int) ($quiz['tentativas_usadas'] ?? 0) ?>/<?= (int) $quiz['tentativas_maximas'] ?></span><?php endif; ?>
    </p>
    <?php if ($tempo !== null) { require BASE_PATH . '/resources/views/caderno/partials/quiz-relogio.php'; } ?>

    <?php if ($instrucoes !== ''): ?>
    <div class="quiz-instr"><p class="atv-rot">Instruções</p><p><?= nl2br(Helpers::e($instrucoes)) ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?= Helpers::e($enviarAction) ?>" data-native-submit class="v2-quiz-form quiz-form" id="v2-quiz-answer-form">
      <?= $hidden ?>
      <input type="hidden" name="tentativa_id" value="<?= (int) ($quiz['tentativa_ativa_id'] ?? 0) ?>">

      <p class="quiz-salvo" id="v2-quiz-autosave" role="status" aria-live="polite">Suas respostas são salvas automaticamente conforme você responde.</p>

      <?php if (count($perguntas) > 1 && !$modoProva): ?>
      <div id="v2-quiz-progress" class="quiz-passos" hidden>
        <p class="quiz-passos-txt" id="v2-quiz-progress-text"></p>
        <div class="trilho" aria-hidden="true"><div class="trilho-tinta" id="v2-quiz-progress-fill"></div></div>
        <div class="quiz-passos-n" id="v2-quiz-progress-dots"></div>
      </div>
      <?php endif; ?>
      <?php if ($modoProva) { require BASE_PATH . '/resources/views/caderno/partials/quiz-prova-nav.php'; } ?>

      <?php // Círculo à caneta da alternativa escolhida (desenhado só por CSS). ?>
      <svg class="vh" aria-hidden="true" focusable="false"><symbol id="q-circ" viewBox="0 0 48 48"><path pathLength="1" d="M31 5.5C19 2.5 5.5 10 5.8 24.5 6 36 15 43.5 25.5 42.6 36.7 41.8 43.2 32.5 42.3 21.8 41.4 11 32 4.8 20.5 6.8"/></symbol></svg>

      <div id="v2-quiz-perguntas" class="questoes"<?= $modoProva ? ' data-modo-prova="1"' : '' ?>>
        <?php $blocoAtual = null; ?>
        <?php foreach ($perguntas as $idxP => $pergunta):
            $codigoBloco = (string) ($pergunta['bloco_codigo'] ?? '');
            if ($codigoBloco !== '' && $codigoBloco !== $blocoAtual):
                $blocoAtual = $codigoBloco; ?>
        <h2 class="v2-quiz-bloco-titulo prova-bloco-tit"><?= Helpers::e((string) ($pergunta['bloco_titulo'] ?? 'Questões')) ?></h2>
        <?php endif; ?>
        <?php require BASE_PATH . '/resources/views/caderno/partials/quiz-questao.php'; ?>
        <?php endforeach; ?>
      </div>

      <?php if ($modoProva) { require BASE_PATH . '/resources/views/caderno/partials/quiz-revisao.php'; } ?>

      <p class="quiz-aviso" id="quiz-aviso-passo" role="alert" hidden></p>
      <div class="quiz-acoes">
        <div class="quiz-nav-pag">
          <?php if (!$modoProva): ?>
          <button type="button" class="btn-sec" id="v2-quiz-prev-btn" hidden><?= caderno_icone('seta-esq') ?> Anterior<?= caderno_ck_contorno() ?></button>
          <button type="button" class="btn" id="v2-quiz-next-btn" hidden>Próxima <?= caderno_icone('seta-dir') ?></button>
          <?php else: ?>
          <button type="button" class="btn-sec" id="v2-quiz-prova-prev-btn" hidden><?= caderno_icone('seta-esq') ?> Anterior<?= caderno_ck_contorno() ?></button>
          <button type="button" class="btn-sec" id="v2-quiz-prova-next-btn" hidden>Próxima <?= caderno_icone('seta-dir') ?><?= caderno_ck_contorno() ?></button>
          <?php endif; ?>
        </div>
        <?php if ($modoProva): ?>
        <button type="button" class="btn" id="v2-quiz-prova-revisar-btn" hidden><?= caderno_icone('check') ?> Revisar e enviar</button>
        <?php endif; ?>
        <button type="submit" class="btn" id="v2-quiz-submit-btn" data-quiz-btn data-loading-label="Enviando…">Enviar respostas <?= caderno_icone('seta-dir') ?></button>
        <a class="link quiz-voltar" href="<?= Helpers::e($voltarAulaUrl) ?>">Voltar à aula</a>
      </div>
      <p class="quiz-obrig-nota">(*) Perguntas obrigatórias</p>
    </form>

    <?php elseif ($qEstado === 'resultado'):
        $r = isset($quiz['resultado']) && is_array($quiz['resultado']) ? $quiz['resultado'] : array();
        $rAprovado = array_key_exists('aprovado', $r) ? $r['aprovado'] : null;
        $rPct = (float) ($r['percentual'] ?? 0);
        $rTipo = $ehProva || $modoProva ? 'PROVA' : 'QUIZ';
        if (empty($r['mostrar_resultado'])) {
            $rCarimbo = array('tinta', $rTipo, 'ENTREGUE', '');
        } elseif ($rAprovado === null) {
            $rCarimbo = array('tinta', $rTipo, 'CORRIGIDO', number_format($rPct, 0, ',', '.') . '%');
        } else {
            $rCarimbo = !empty($rAprovado)
                ? array('verde', $rTipo, 'APROVADO', number_format($rPct, 0, ',', '.') . '%')
                : array('vermelho', $rTipo, 'REFAZER', number_format($rPct, 0, ',', '.') . '%');
        }
    ?>
    <section class="quiz-res" role="status" data-carimbar>
      <div class="carimbo <?= $rCarimbo[0] ?>" aria-hidden="true"><div><?= $rCarimbo[1] ?><span><?= $rCarimbo[2] ?></span><?= $rCarimbo[3] ?></div></div>
      <?php if (!empty($r['mostrar_resultado'])): ?>
      <p class="quiz-placar-n" aria-hidden="true"><?= (int) ($r['total_acertos'] ?? 0) ?><small>/<?= (int) ($r['total_perguntas'] ?? 0) ?></small></p>
      <p class="quiz-placar">Você acertou <strong><?= (int) ($r['total_acertos'] ?? 0) ?> de <?= (int) ($r['total_perguntas'] ?? 0) ?></strong> questões (<?= number_format($rPct, 1, ',', '.') ?>%).</p>
      <?php if ($rAprovado !== null): ?>
      <?php if (!empty($rAprovado)): ?>
      <p class="quiz-status ok"><?= caderno_icone('check') ?> Aprovado</p>
      <?php else: ?>
      <p class="quiz-status nao"><?= caderno_icone('alerta') ?> Não atingiu o percentual mínimo</p>
      <?php endif; ?>
      <?php endif; ?>
      <?php else: ?>
      <p class="quiz-status ok"><?= caderno_icone('check') ?> Quiz enviado com sucesso.</p>
      <?php endif; ?>
    </section>

    <?php if (!empty($r['mostrar_gabarito']) || !empty($r['mostrar_comentarios'])): ?>
    <section class="gabarito" aria-labelledby="gabarito-tit">
      <h2 class="sec-tit" id="gabarito-tit"><?= !empty($r['mostrar_gabarito']) ? 'Correção' : 'Comentários' ?></h2>
      <ol class="gab-lista">
        <?php foreach ($perguntas as $idxP => $pergunta):
            $marcada = (int) ($pergunta['alternativa_id_respondida'] ?? 0); ?>
        <li class="questao questao-ro" data-pergunta-id="<?= (int) ($pergunta['id'] ?? 0) ?>">
          <p class="questao-enun"><span class="questao-n" aria-hidden="true"><?= (int) $idxP + 1 ?></span><span class="questao-txt"><span class="vh">Questão <?= (int) $idxP + 1 ?>: </span><?= nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))) ?></span></p>
          <?php if (!empty($pergunta['alternativas'])): ?>
          <ul class="alts">
            <?php foreach (array_values($pergunta['alternativas']) as $qK => $alt):
                $aid = (int) ($alt['id'] ?? 0);
                $temGabarito = !empty($r['mostrar_gabarito']) && array_key_exists('correta', $alt);
                $isCorreta = $temGabarito && !empty($alt['correta']);
                $isMarcada = $marcada === $aid;
                $cls = 'alt';
                if ($isCorreta) { $cls .= ' certa'; }
                if ($isMarcada && $temGabarito && !$isCorreta) { $cls .= ' errada'; }
                if ($isMarcada) { $cls .= ' escolhida'; }
            ?>
            <li class="<?= $cls ?>">
              <span class="alt-letra"><?php if ($isMarcada): ?><svg class="alt-circ" aria-hidden="true" focusable="false"><use href="#q-circ-r"/></svg><?php endif; ?><?= $qK < 26 ? chr(65 + $qK) : $qK + 1 ?></span>
              <span class="alt-txt"><?= Helpers::e((string) ($alt['texto'] ?? '')) ?>
                <?php if ($isMarcada): ?><em class="gab-tag">Sua resposta</em><?php endif; ?>
                <?php if ($isCorreta): ?><em class="gab-tag certa"><?= caderno_icone('check') ?> Correta</em><?php elseif ($isMarcada && $temGabarito): ?><em class="gab-tag errada">Incorreta</em><?php endif; ?>
              </span>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php if (!empty($r['mostrar_comentarios']) && trim((string) ($pergunta['explicacao'] ?? '')) !== ''): ?>
          <p class="gab-expl"><b>Comentário</b> <?= nl2br(Helpers::e((string) $pergunta['explicacao'])) ?></p>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ol>
      <svg class="vh" aria-hidden="true" focusable="false"><symbol id="q-circ-r" viewBox="0 0 48 48"><path d="M31 5.5C19 2.5 5.5 10 5.8 24.5 6 36 15 43.5 25.5 42.6 36.7 41.8 43.2 32.5 42.3 21.8 41.4 11 32 4.8 20.5 6.8"/></symbol></svg>
    </section>
    <?php endif; ?>

    <div class="quiz-acoes quiz-fim">
      <?php if (!empty($quiz['pode_nova_tentativa'])): ?>
      <form method="post" action="<?= Helpers::e($iniciarAction) ?>" data-native-submit class="v2-quiz-form">
        <?= $hidden ?>
        <button type="submit" class="btn" data-quiz-btn data-loading-label="Iniciando…">Nova tentativa</button>
      </form>
      <?php endif; ?>
      <a class="btn-sec" href="<?= Helpers::e($voltarAulaUrl) ?>"><?= caderno_icone('seta-esq') ?> Voltar à aula<?= caderno_ck_contorno() ?></a>
    </div>
    <?php endif; ?>
  </article>

  <?php require BASE_PATH . '/resources/views/caderno/partials/quiz-barra.php'; ?>
</div>
