<?php
/**
 * Revisão antes de enviar (modo prova; mesmos ids da V2) e o painel inline de
 * confirmação que substitui o confirm() da V2 quando há objetivas sem resposta
 * ("Enviar mesmo assim" envia; "Revisar" leva à primeira pendente). Sem JS os
 * dois ficam ocultos e o botão "Enviar respostas" do formulário segue valendo.
 */
?>
<section id="v2-quiz-prova-revisao" class="prova-revisao" tabindex="-1" aria-labelledby="quiz-revisao-tit" hidden>
  <h2 class="sec-tit" id="quiz-revisao-tit">Revisão antes de enviar</h2>
  <p class="prova-revisao-lead">Confira o que ficou pendente. Você ainda pode voltar e responder.</p>
  <div id="v2-quiz-prova-revisao-resumo"></div>
  <div id="v2-quiz-prova-revisao-listas"></div>
  <div class="form-acoes">
    <button type="submit" class="btn" id="v2-quiz-prova-enviar-btn" data-quiz-btn data-loading-label="Enviando…">Enviar prova <?= caderno_icone('seta-dir') ?></button>
    <button type="button" class="btn-sec" id="v2-quiz-prova-voltar-btn"><?= caderno_icone('seta-esq') ?> Voltar à prova<?= caderno_ck_contorno() ?></button>
  </div>
  <div id="quiz-confirmar" class="postit largo quiz-confirmar" role="group" aria-labelledby="quiz-confirmar-txt" tabindex="-1" hidden>
    <p id="quiz-confirmar-txt"></p>
    <div class="form-acoes">
      <button type="submit" class="btn" id="quiz-confirmar-enviar" data-loading-label="Enviando…">Enviar mesmo assim</button>
      <button type="button" class="link" id="quiz-confirmar-revisar">Revisar</button>
    </div>
  </div>
</section>
