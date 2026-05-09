<?php
use App\Core\Helpers;
$permissao = $form_data['permissao'] ?? null;
$oldData = isset($old) && is_array($old) ? $old : array();
$value = function($key,$default='') use ($oldData,$permissao){ if(array_key_exists($key,$oldData)) return $oldData[$key]; if(is_array($permissao)&&array_key_exists($key,$permissao)) return $permissao[$key]; return $default; };
?>

<section class="admin-page">
    <header class="admin-page__header"><h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1></header>
    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <section class="status-card">
        <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
            <input type="hidden" name="id" value="<?php echo (int) $value('id', 0); ?>">
            <label>Módulo<input type="text" name="modulo" value="<?php echo Helpers::e($value('modulo')); ?>" required></label>
            <label>Ação<input type="text" name="acao" value="<?php echo Helpers::e($value('acao')); ?>" required></label>
            <label>Slug<input type="text" name="slug" value="<?php echo Helpers::e($value('slug')); ?>" required></label>
            <label>Nome<input type="text" name="nome" value="<?php echo Helpers::e($value('nome')); ?>" required></label>
            <label>Descrição<textarea name="descricao" rows="3"><?php echo Helpers::e($value('descricao')); ?></textarea></label>
            <div class="cta-group">
                <button type="submit">Salvar permissão</button>
                <a class="button-link button-link--ghost" href="/admin/permissoes">Voltar</a>
            </div>
        </form>
    </section>
</section>
