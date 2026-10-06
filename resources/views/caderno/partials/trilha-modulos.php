<?php
/**
 * Conteúdo programático em módulos como trilha vertical à caneta.
 *
 * Títulos e tópicos de todos os módulos ficam sempre visíveis (sem sanfona).
 * Só a decoração anima: o traço de tinta entre um módulo e o seguinte e o
 * ✓ ao lado de cada número. Sem JS, em movimento reduzido ou no modo leve a
 * trilha já aparece desenhada; o módulo `curso` de caderno.js marca
 * .rolando e desenha conforme a rolagem (os estados "por desenhar" existem
 * só sob html.anima .rolando).
 *
 * Espera: $modulos (conteudo_programatico['modulos'] da V2).
 */

use App\Core\Helpers;

$tmModulos = array();
foreach ((isset($modulos) && is_array($modulos) ? $modulos : array()) as $tmModulo) {
    if (is_array($tmModulo)) {
        $tmModulos[] = $tmModulo;
    }
}
$tmTotal = count($tmModulos);
?>
<div class="trilha-mod" data-trilha-mod>
  <ol class="modulos">
    <?php foreach ($tmModulos as $tmIdx => $tmModulo): ?>
    <?php
    $tmNum = $tmIdx + 1;
    $tmTitulo = isset($tmModulo['titulo']) && trim((string) $tmModulo['titulo']) !== '' ? (string) $tmModulo['titulo'] : 'Módulo ' . $tmNum;
    $tmItens = array();
    if (!empty($tmModulo['itens']) && is_array($tmModulo['itens'])) {
        foreach ($tmModulo['itens'] as $tmItem) {
            $tmItem = trim((string) $tmItem);
            if ($tmItem !== '') {
                $tmItens[] = $tmItem;
            }
        }
    }
    $tmQt = !empty($tmModulo['itens']) && is_array($tmModulo['itens']) ? count($tmModulo['itens']) : 0;
    ?>
    <li class="mod">
      <span class="mod-no" aria-hidden="true">
        <svg viewBox="0 0 48 48" focusable="false"><path class="aro" d="M24 4.6c10.9-.3 19.6 8.6 19.2 19.5S34.3 43.7 23.6 43.4 4.4 34.6 4.8 23.8 13.6 4.9 24 4.6"/><path class="ok" d="M33 9.5l5.6 6.2L50 1"/></svg>
        <b><?= $tmNum ?></b>
      </span>
      <?php if ($tmNum < $tmTotal): ?><span class="mod-traco" aria-hidden="true"></span><?php endif; ?>
      <h3 class="mod-tit"><?= Helpers::e($tmTitulo) ?></h3>
      <p class="mod-qt"><?= $tmQt ?> <?= $tmQt === 1 ? 'tópico' : 'tópicos' ?></p>
      <?php if (!empty($tmItens)): ?>
      <ul class="mod-itens">
        <?php foreach ($tmItens as $tmItem): ?>
        <li><?= Helpers::e($tmItem) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ol>
</div>
