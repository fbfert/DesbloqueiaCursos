<?php use App\Core\Helpers; ?>

<?php
$modulo = isset($modulo) && is_array($modulo) ? $modulo : null;
$oldInput = isset($oldInput) && is_array($oldInput) ? $oldInput : array();

$value = function ($key, $default = '') use ($oldInput, $modulo) {
    if (array_key_exists($key, $oldInput)) {
        return $oldInput[$key];
    }
    if (is_array($modulo) && array_key_exists($key, $modulo)) {
        return $modulo[$key];
    }
    return $default;
};

$cursoId = isset($curso_id) ? (int) $curso_id : 0;
$turmaId = isset($turma_id) ? (int) $turma_id : 0;
$resolvedCancelUrl = isset($cancel_url) ? (string) $cancel_url : ('/admin/area-curso?curso_id=' . $cursoId . '&aba=conteudo' . ($turmaId > 0 ? '&turma_id=' . $turmaId : ''));

$editorValue = function ($value) {
    return Helpers::e(Helpers::decodeEditorHtml((string) $value));
};
?>

<div class="admin-page admin-area-curso">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e(isset($title) ? $title : 'Novo módulo'); ?></h1>
            <p class="admin-page__subtitle">Cadastre o módulo em uma tela própria e volte para a listagem ao salvar.</p>
        </div>
        <div class="cta-group">
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($resolvedCancelUrl); ?>">Voltar à aba Conteúdo</a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <form method="post" action="<?php echo Helpers::e(isset($action_url) ? $action_url : '/admin/area-curso/conteudo/modulo/salvar'); ?>" class="admin-form admin-area-curso__form">
            <?php echo $csrfField; ?>
            <input type="hidden" name="id" value="<?php echo !empty($modulo['id']) ? (int) $modulo['id'] : 0; ?>">
            <input type="hidden" name="curso_evento_id" value="<?php echo $cursoId; ?>">
            <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? $turmaId : ''; ?>">

            <label>
                Título
                <input type="text" name="titulo" value="<?php echo Helpers::e((string) $value('titulo', '')); ?>" required>
            </label>

            <label>
                Descrição
                <textarea name="descricao" class="js-conteudo-rich-editor" data-editor-mode="full" rows="8"><?php echo $editorValue($value('descricao', '')); ?></textarea>
            </label>

            <label>
                Status
                <?php $statusModulo = (string) $value('status', 'publicado'); ?>
                <select name="status">
                    <option value="rascunho" <?php echo $statusModulo === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                    <option value="publicado" <?php echo $statusModulo === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                    <option value="oculto" <?php echo $statusModulo === 'oculto' ? 'selected' : ''; ?>>Oculto</option>
                    <option value="arquivado" <?php echo $statusModulo === 'arquivado' ? 'selected' : ''; ?>>Arquivado</option>
                </select>
            </label>

            <?php
            $show_save_and_new = false;
            $show_save_and_exit = false;
            $show_save_as_copy = false;
            $cancel_url = $resolvedCancelUrl;
            $save_label = 'Salvar';
            $cancel_label = 'Cancelar';
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </form>
    </section>
</div>
