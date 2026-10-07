<?php
/**
 * Barra de estudo da aula (fixa embaixo só abaixo de 900 px; o layout com
 * $cadernoEstudo omite a bnav geral). Anterior, a ação do item no meio e
 * próxima. O botão "Concluir" pertence ao formulário #aula-concluir da página
 * pelo atributo form= (mesmo POST, mesmos campos; nada é duplicado).
 *
 * Espera: $item, $navegacao, $overviewUrl, $abConcluiu (✓ recém-traçado).
 */

use App\Core\Helpers;

$abAnt = !empty($navegacao['anterior']) ? $navegacao['anterior'] : null;
$abProx = !empty($navegacao['proximo']) ? $navegacao['proximo'] : null;
$abTipo = (string) $item['tipo'];
$abFeito = !empty($item['concluido']);
// A próxima vira o botão principal quando não há o que concluir aqui.
$abProxForte = $abFeito || empty($item['pode_concluir']);
?>
<nav class="barra-estudo aula-barra" aria-label="Navegação da aula">
  <?php if ($abAnt): ?>
  <a class="be-lado" href="<?= Helpers::e((string) $abAnt['url']) ?>"><?= caderno_icone('seta-esq') ?><span>Anterior</span></a>
  <?php else: ?>
  <span class="be-lado be-off" aria-hidden="true"><?= caderno_icone('seta-esq') ?><span>Anterior</span></span>
  <?php endif; ?>

  <?php if ($abFeito): ?>
  <span class="be-ok"><svg viewBox="0 0 28 24" aria-hidden="true" focusable="false"><path<?= $abConcluiu ? ' data-ok' : '' ?> d="M3 13.5c2.2 1.7 4.2 4 6 6.6C13 13 17.6 7 25 2.5"/></svg>Concluído</span>
  <?php elseif (!empty($item['pode_concluir'])): ?>
  <button class="btn be-meio" type="submit" form="aula-concluir" data-complete-btn data-loading-label="Concluindo…">Concluir</button>
  <?php elseif ($abTipo === 'quiz' && trim((string) ($item['quiz_url'] ?? '')) !== ''): ?>
  <a class="btn be-meio" href="<?= Helpers::e((string) $item['quiz_url']) ?>">Responder quiz</a>
  <?php elseif ($abTipo === 'avaliacao_textual' && trim((string) ($item['atividade_url'] ?? '')) !== ''): ?>
  <a class="btn be-meio" href="<?= Helpers::e((string) $item['atividade_url']) ?>">Responder</a>
  <?php elseif (!empty($item['eh_interativo'])): ?>
  <a class="btn be-meio" href="<?= Helpers::e((string) $item['oficial_url']) ?>">Abrir atividade</a>
  <?php else: ?>
  <a class="be-lado be-sumario" href="<?= Helpers::e($overviewUrl) ?>">Sumário</a>
  <?php endif; ?>

  <?php if ($abProx): ?>
  <a class="be-lado be-prox<?= $abProxForte ? ' forte' : '' ?>" href="<?= Helpers::e((string) $abProx['url']) ?>"><span>Próxima</span><?= caderno_icone('seta-dir') ?></a>
  <?php else: ?>
  <span class="be-lado be-off" aria-hidden="true"><span>Próxima</span><?= caderno_icone('seta-dir') ?></span>
  <?php endif; ?>
</nav>
