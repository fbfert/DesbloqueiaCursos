<?php
/**
 * Trilha da abertura da home: caminho à caneta com três etapas marcadas
 * (Inscrição, Aulas, Simulado) e o carimbo "Certificado desbloqueado".
 *
 * Os traços têm o tremor de mão desenhado no próprio path (nada é gerado no
 * cliente). Os rótulos são HTML posicionados em % sobre a caixa 560×420, então
 * acompanham o SVG em qualquer largura. Sem JS a trilha aparece completa; a
 * cena (caderno.js, módulo home) só esconde e redesenha sob html.anima.
 */
?>
<div class="trilha" role="group" aria-label="Trilha de um curso">
  <svg viewBox="0 0 560 420" aria-hidden="true" focusable="false" fill="none" stroke="#1F3FA8" stroke-linecap="round" stroke-linejoin="round">
    <path class="cam" stroke-width="3" d="M40 370 C 72 377, 104 366, 136 345 C 160 329, 179 302, 210 292 C 243 281, 279 279, 302 250 C 318 230, 316 207, 332 192 C 356 170, 398 158, 428 128 C 449 107, 461 89, 486 78"/>
    <path class="aro" stroke-width="2.6" d="M41 351.5 c 10.5 -.8 19.4 7.6 19 18.4 s -9.6 19.2 -20.2 18.1 s -18.2 -9.9 -17.3 -19.6 s 9.9 -17 19.8 -16.6"/>
    <path class="ok" stroke-width="3.4" d="M31 370.5 l 7.2 7.4 l 13.6 -16.6"/>
    <path class="aro" stroke-width="2.6" d="M211 273.2 c 10.8 -.4 19.1 8.3 18.7 18.8 s -9.9 18.8 -20 18.3 s -18.3 -9.8 -17.4 -19.8 s 9.6 -16.8 19.9 -16.6"/>
    <path class="ok" stroke-width="3.4" d="M201 292.5 l 7.2 7.4 l 13.6 -16.6"/>
    <path class="aro" stroke-width="2.6" d="M333 173.4 c 10.4 -.6 19.3 8.1 18.8 18.6 s -9.8 19 -20.1 18.3 s -18.1 -10 -17.3 -19.7 s 9.8 -16.9 19.8 -16.6"/>
    <path class="ok" stroke-width="3.4" d="M323 192.5 l 7.2 7.4 l 13.6 -16.6"/>
  </svg>
  <ol class="trilha-etapas">
    <li class="rotulo" style="left:2%;top:94%"><b>Inscrição</b><small>turma escolhida</small></li>
    <li class="rotulo" style="left:33%;top:75%"><b>Aulas</b><small>no seu ritmo</small></li>
    <li class="rotulo" style="left:63%;top:44%"><b>Simulado</b><small>formato oficial</small></li>
    <li class="vh">Certificado desbloqueado, com validação pública</li>
  </ol>
  <span class="anot mao" style="left:36%;top:30%" aria-hidden="true">quase lá!</span>
  <?php
  $linhas = array('CERTIFICADO', 'DESBLOQUEADO', 'VALIDAÇÃO PÚBLICA');
  $cor = 'laranja';
  require __DIR__ . '/carimbo.php';
  ?>
</div>
