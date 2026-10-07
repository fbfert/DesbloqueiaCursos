<?php
/**
 * Navegação do modo prova (mesmos ids da V2): bloco atual, posição, trilho de
 * respondidas, resumo e índice de questões por bloco (montado pelo módulo
 * `quiz` de caderno-aluno.js). Sem JS fica oculta e a prova continua rolável.
 */
?>
<div id="v2-quiz-prova-nav" class="prova-nav" hidden>
  <p class="prova-nav-topo"><strong id="v2-quiz-prova-bloco" class="prova-nav-bloco"></strong> <span id="v2-quiz-prova-pos"></span></p>
  <div class="trilho" aria-hidden="true"><div class="trilho-tinta" id="v2-quiz-prova-fill"></div></div>
  <div class="prova-nav-rodape">
    <span id="v2-quiz-prova-resumo"></span>
    <button type="button" class="link prova-indice-btn" id="v2-quiz-prova-indice-btn" aria-expanded="false" aria-controls="v2-quiz-prova-indice">Índice de questões</button>
  </div>
  <div id="v2-quiz-prova-indice" class="prova-indice" hidden></div>
</div>
