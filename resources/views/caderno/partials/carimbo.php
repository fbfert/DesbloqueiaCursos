<?php
/**
 * Carimbo circular do tema caderno (ornamento).
 *
 * Espera:
 *   $linhas  array com 3 strings (linha de cima, linha central em destaque, linha de baixo);
 *   $cor     'laranja' (padrão, usa --laranja-texto), 'verde' ou 'tinta'.
 *
 * O carimbo é decorativo (aria-hidden): quem inclui repete a informação em
 * texto normal por perto.
 */

use App\Core\Helpers;

$carimboLinhas = isset($linhas) && is_array($linhas) ? array_values($linhas) : array();
$carimboLinhas = array_pad(array_slice($carimboLinhas, 0, 3), 3, '');
$carimboCor = isset($cor) && in_array($cor, array('verde', 'tinta'), true) ? ' ' . $cor : '';
?>
<div class="carimbo<?= $carimboCor ?>" aria-hidden="true"><div><?= Helpers::e((string) $carimboLinhas[0]) ?><span><?= Helpers::e((string) $carimboLinhas[1]) ?></span><?= Helpers::e((string) $carimboLinhas[2]) ?></div></div>
