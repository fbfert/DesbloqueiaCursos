<?php
/**
 * Post-it do tema caderno (aviso colado na folha).
 *
 * Espera $postit = array(
 *   'titulo' => texto em negrito no topo (opcional),
 *   'texto'  => parágrafo simples (escapado aqui; opcional),
 *   'html'   => conteúdo extra já escapado por quem inclui (opcional),
 *   'classe' => classes extras: 'ok', 'erro', 'largo', ... (opcional),
 *   'role'   => 'status' | 'alert' (opcional),
 *   'id'     => id do elemento (opcional),
 * )
 */

use App\Core\Helpers;

$piDados = isset($postit) && is_array($postit) ? $postit : array();
$piClasse = trim('postit ' . (string) ($piDados['classe'] ?? ''));
$piRole = isset($piDados['role']) && in_array($piDados['role'], array('status', 'alert'), true) ? ' role="' . $piDados['role'] . '"' : '';
$piId = !empty($piDados['id']) ? ' id="' . Helpers::e((string) $piDados['id']) . '"' : '';
?>
<div class="<?= Helpers::e($piClasse) ?>"<?= $piId ?><?= $piRole ?>>
  <?php if (!empty($piDados['titulo'])): ?>
  <b><?= Helpers::e((string) $piDados['titulo']) ?></b>
  <?php endif; ?>
  <?php if (!empty($piDados['texto'])): ?>
  <p><?= Helpers::e((string) $piDados['texto']) ?></p>
  <?php endif; ?>
  <?php if (!empty($piDados['html'])): ?>
  <?= $piDados['html'] ?>
  <?php endif; ?>
</div>
