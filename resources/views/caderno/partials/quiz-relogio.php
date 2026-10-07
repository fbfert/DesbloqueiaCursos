<?php
/**
 * Relógio de prova (quiz em andamento com duração). O prazo é do servidor:
 * data-restante vem de quiz.tempo.segundos_restantes e o módulo `quiz` de
 * caderno-aluno.js só conta localmente, sincroniza com POST /aluno/cursos/quiz/tempo
 * e recarrega ao zerar. role="timer" (aria-live implícito off: nada é anunciado a
 * cada segundo); o aviso de "tempo acabando" sai uma vez em #quiz-tempo-aviso.
 * Sem JS fica o tempo restante no momento em que a página abriu.
 *
 * Espera: $quiz (andamento), $tempo (array com segundos_restantes).
 */

$qrRestante = max(0, (int) ($tempo['segundos_restantes'] ?? 0));
$qrTexto = sprintf('%02d:%02d:%02d', intdiv($qrRestante, 3600), intdiv($qrRestante % 3600, 60), $qrRestante % 60);
?>
<div class="relogio<?= $qrRestante <= 300 ? ' acabando' : '' ?>" id="v2-quiz-cronometro" role="timer" aria-labelledby="quiz-relogio-rot"
     data-tentativa="<?= (int) ($quiz['tentativa_ativa_id'] ?? 0) ?>" data-restante="<?= $qrRestante ?>">
  <?= caderno_icone('relogio') ?>
  <span class="relogio-rot" id="quiz-relogio-rot">Tempo restante</span>
  <strong class="relogio-valor" id="v2-quiz-cronometro-valor"><?= $qrTexto ?></strong>
</div>
<p class="relogio-aviso" id="quiz-tempo-aviso" role="status" aria-live="polite"></p>
