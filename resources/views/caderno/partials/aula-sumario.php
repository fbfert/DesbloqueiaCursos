<?php
/**
 * Sumário do caderno (árvore de módulos da aula). Mesmos dados de
 * v2/partials/lms-arvore.php: módulo com contagem de concluídos, itens com ✓
 * nos concluídos, item atual com aria-current="page" e etiquetas como
 * subtítulos sem link. Cada módulo é um <details> nativo (o acordeão funciona
 * sem JS); o módulo `aberto` do controller já vem aberto.
 *
 * Espera: $arvore (AulaController::montarArvore).
 */

use App\Core\Helpers;

$smArvore = isset($arvore) && is_array($arvore) ? $arvore : array();
// Mesma base do percentual do curso (ver AulaController: progresso_base).
$smObrig = isset($aulaBase) && $aulaBase === 'obrigatorios';
?>
<?php if (empty($smArvore)): ?>
<p class="sm-vazio">Nenhum módulo publicado ainda.</p>
<?php else: ?>
<ol class="sm-mods">
  <?php foreach ($smArvore as $smIdx => $smMod):
      $smItens = isset($smMod['itens']) && is_array($smMod['itens']) ? $smMod['itens'] : array();
      $smTotal = (int) ($smMod[$smObrig ? 'total_obrigatorios' : 'total_itens'] ?? 0);
      $smFeitos = (int) ($smMod[$smObrig ? 'concluidos_obrigatorios' : 'concluidos_itens'] ?? 0);
  ?>
  <li>
    <details class="sm-mod<?= $smTotal > 0 && $smFeitos >= $smTotal ? ' feito' : '' ?>"<?= !empty($smMod['aberto']) ? ' open' : '' ?>>
      <summary>
        <b class="sm-n" aria-hidden="true"><?= (int) $smIdx + 1 ?></b>
        <span class="sm-mod-tit"><?= Helpers::e((string) ($smMod['titulo'] ?? '')) ?>
          <small><?php if ($smTotal > 0): ?><?= $smFeitos ?>/<?= $smTotal ?> <?= $smObrig ? 'obrigatórios concluídos' : 'concluídos' ?><?php else: ?><?= $smObrig ? 'Sem itens obrigatórios' : 'Sem itens' ?><?php endif; ?></small></span>
      </summary>
      <?php if (!empty($smItens)): ?>
      <ul class="sm-itens">
        <?php foreach ($smItens as $smIt): ?>
        <?php if (!empty($smIt['etiqueta'])): ?>
        <li class="sm-etq"><?= Helpers::e((string) ($smIt['titulo'] ?? '')) ?></li>
        <?php else: ?>
        <li><a href="<?= Helpers::e((string) ($smIt['url'] ?? '#')) ?>"<?= !empty($smIt['atual']) ? ' aria-current="page"' : '' ?><?= !empty($smIt['concluido']) ? ' class="feito"' : '' ?>>
          <?php if (!empty($smIt['concluido'])): ?><svg class="sm-ok" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M3.5 10.8c1.4 1.2 2.6 2.6 3.8 4.1C9.6 10.6 12.6 6.8 16.8 3.6"/></svg><span class="vh">Concluído: </span><?php else: ?><span class="sm-bola" aria-hidden="true"></span><?php endif; ?>
          <span class="sm-it"><?= Helpers::e((string) ($smIt['titulo'] ?? '')) ?><small><?= Helpers::e((string) ($smIt['tipo_label'] ?? '')) ?><?= $smObrig && empty($smIt['obrigatorio']) ? ' · opcional' : '' ?></small></span>
        </a></li>
        <?php endif; ?>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </details>
  </li>
  <?php endforeach; ?>
</ol>
<?php endif; ?>
