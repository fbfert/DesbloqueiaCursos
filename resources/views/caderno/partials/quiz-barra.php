<?php
/**
 * Barra de estudo do quiz (fixa embaixo só abaixo de 900 px; o layout com
 * $cadernoEstudo omite a bnav geral).
 *   - fora do andamento: só "Voltar à aula";
 *   - quiz curto em andamento: Anterior · "Pergunta x de y" · Próxima;
 *   - prova em andamento: ‹ · Índice · Revisar · › ("Voltar à prova" na revisão).
 * Em andamento ela começa [hidden] e o módulo `quiz` a liga: seus botões só
 * repetem os da página. O envio nunca fica na barra (fica na folha, longe do
 * lugar onde se toca "Próxima"), para não ser acionado sem querer.
 *
 * Espera: $qEstado, $perguntas, $modoProva, $voltarAulaUrl.
 */

use App\Core\Helpers;

$qbNav = $qEstado === 'andamento' && count($perguntas) > 1;
?>
<?php if (!$qbNav): ?>
<nav class="barra-estudo aula-barra" aria-label="Navegação do quiz">
  <a class="be-lado" href="<?= Helpers::e($voltarAulaUrl) ?>"><?= caderno_icone('seta-esq') ?><span>Voltar à aula</span></a>
</nav>
<?php elseif (!$modoProva): ?>
<nav class="barra-estudo aula-barra quiz-barra" id="quiz-barra" aria-label="Navegação entre as perguntas" hidden>
  <button type="button" class="be-lado" id="quiz-be-ant"><?= caderno_icone('seta-esq') ?><span>Anterior</span></button>
  <span class="be-pos" id="quiz-be-pos" aria-hidden="true"></span>
  <button type="button" class="be-lado be-prox forte" id="quiz-be-prox"><span>Próxima</span><?= caderno_icone('seta-dir') ?></button>
</nav>
<?php else: ?>
<nav class="barra-estudo aula-barra quiz-barra" id="quiz-barra" aria-label="Navegação da prova" hidden>
  <button type="button" class="be-lado" id="quiz-be-ant" aria-label="Questão anterior"><?= caderno_icone('seta-esq') ?></button>
  <button type="button" class="be-lado be-txt" id="quiz-be-indice" aria-controls="v2-quiz-prova-indice">Índice</button>
  <button type="button" class="be-lado be-txt" id="quiz-be-revisar">Revisar</button>
  <button type="button" class="be-lado" id="quiz-be-prox" aria-label="Próxima questão"><?= caderno_icone('seta-dir') ?></button>
  <button type="button" class="be-lado" id="quiz-be-voltar" hidden><?= caderno_icone('seta-esq') ?><span>Voltar à prova</span></button>
</nav>
<?php endif; ?>
