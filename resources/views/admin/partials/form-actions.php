<?php
$showSave = isset($show_save) ? (bool) $show_save : true;
$showSaveAndNew = isset($show_save_and_new) ? (bool) $show_save_and_new : true;
$showSaveAndExit = isset($show_save_and_exit) ? (bool) $show_save_and_exit : true;
$showSaveAsCopy = isset($show_save_as_copy) ? (bool) $show_save_as_copy : false;
$showCancel = isset($show_cancel) ? (bool) $show_cancel : true;
$cancelUrl = isset($cancel_url) ? (string) $cancel_url : '#';
$saveLabel = isset($save_label) ? (string) $save_label : 'Salvar';
$saveAndNewLabel = isset($save_and_new_label) ? (string) $save_and_new_label : 'Salvar e novo';
$saveAndExitLabel = isset($save_and_exit_label) ? (string) $save_and_exit_label : 'Salvar e sair';
$saveAsCopyLabel = isset($save_as_copy_label) ? (string) $save_as_copy_label : 'Salvar como cópia';
$cancelLabel = isset($cancel_label) ? (string) $cancel_label : 'Cancelar';
?>
<div class="cta-group full">
    <?php if ($showSave): ?>
        <button type="submit" name="submit_action" value="save_stay" class="button-link button-link--primary"><?php echo htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?></button>
    <?php endif; ?>
    <?php if ($showSaveAndNew): ?>
        <button type="submit" name="submit_action" value="save_new" class="button-link"><?php echo htmlspecialchars($saveAndNewLabel, ENT_QUOTES, 'UTF-8'); ?></button>
    <?php endif; ?>
    <?php if ($showSaveAndExit): ?>
        <button type="submit" name="submit_action" value="save_exit" class="button-link button-link--ghost"><?php echo htmlspecialchars($saveAndExitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
    <?php endif; ?>
    <?php if ($showSaveAsCopy): ?>
        <button type="submit" name="submit_action" value="save_copy" class="button-link"><?php echo htmlspecialchars($saveAsCopyLabel, ENT_QUOTES, 'UTF-8'); ?></button>
    <?php endif; ?>
    <?php if ($showCancel): ?>
        <a class="button-link button-link--ghost" href="<?php echo htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cancelLabel, ENT_QUOTES, 'UTF-8'); ?></a>
    <?php endif; ?>
</div>
