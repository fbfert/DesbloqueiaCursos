<?php
use App\Core\Helpers;

$arvore = isset($arvore) && is_array($arvore) ? $arvore : array();
?>
<?php if (empty($arvore)): ?>
  <p class="v2-muted v2-sm" style="padding:12px;">Nenhum módulo publicado ainda.</p>
<?php else: ?>
  <?php foreach ($arvore as $idx => $modulo): ?>
    <div class="v2-mod<?php echo !empty($modulo['aberto']) ? ' is-open' : ''; ?>">
      <button type="button" class="v2-mod-head" aria-expanded="<?php echo !empty($modulo['aberto']) ? 'true' : 'false'; ?>">
        <span class="v2-mod-num"><?php echo (int) $idx + 1; ?></span>
        <span class="v2-mod-info">
          <b><?php echo Helpers::e((string) $modulo['titulo']); ?></b>
          <small><?php echo (int) $modulo['concluidos_itens']; ?>/<?php echo (int) $modulo['total_itens']; ?> concluídos</small>
        </span>
        <i class="ti ti-chevron-down v2-mod-chev" aria-hidden="true"></i>
      </button>
      <div class="v2-mod-items">
        <?php foreach ($modulo['itens'] as $it): ?>
          <?php if (!empty($it['etiqueta'])): ?>
            <div class="v2-mod-item" style="opacity:.7;font-weight:600;"><?php echo Helpers::e((string) $it['titulo']); ?></div>
          <?php else: ?>
            <a class="v2-mod-item<?php echo !empty($it['atual']) ? ' is-current' : ''; ?>"
               href="<?php echo Helpers::e((string) $it['url']); ?>"
               <?php echo !empty($it['atual']) ? 'aria-current="true"' : ''; ?>>
              <?php if (!empty($it['concluido'])): ?>
                <i class="ti ti-circle-check-filled" style="color:var(--v2-success);" aria-hidden="true"></i>
                <span class="v2-sr-only">Concluído: </span>
              <?php else: ?>
                <i class="ti ti-circle" aria-hidden="true"></i>
              <?php endif; ?>
              <span><?php echo Helpers::e((string) $it['titulo']); ?></span>
              <small class="v2-muted"><?php echo Helpers::e((string) $it['tipo_label']); ?></small>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
