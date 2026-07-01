<?php use App\Core\Helpers; ?>

<?php $perfil = isset($form_data['perfil']) && is_array($form_data['perfil']) ? $form_data['perfil'] : null; ?>
<?php $professores = isset($form_data['professores']) && is_array($form_data['professores']) ? $form_data['professores'] : array(); ?>
<?php $oldData = isset($oldInput) && is_array($oldInput) ? $oldInput : (isset($old) && is_array($old) ? $old : array()); ?>
<?php $value = function($key, $default = '') use ($oldData, $perfil) {
    if (array_key_exists($key, $oldData)) {
        return $oldData[$key];
    }
    if (is_array($perfil) && array_key_exists($key, $perfil)) {
        return $perfil[$key];
    }
    return $default;
}; ?>
<?php $show_save_as_copy = !empty((int) $value('id', 0)); ?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
        <p class="admin-page__subtitle">Cadastro e manutenção do perfil fiscal dos professores.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
        <?php echo \App\Core\Csrf::field(); ?>
        <input type="hidden" name="id" value="<?php echo (int) $value('id', 0); ?>">
        <label>
            Professor
            <select name="usuario_id" required>
                <option value="">Selecione</option>
                <?php foreach ($professores as $professor): ?>
                    <option value="<?php echo (int) $professor['id']; ?>" <?php echo (int) $value('usuario_id', 0) === (int) $professor['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($professor['nome'] . ' (' . $professor['email'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Tipo fiscal
            <select name="tipo_pessoa">
                <option value="pf" <?php echo ((string) $value('tipo_pessoa', 'pf') === 'pf') ? 'selected' : ''; ?>>PF</option>
                <option value="pj" <?php echo ((string) $value('tipo_pessoa', '') === 'pj') ? 'selected' : ''; ?>>PJ</option>
            </select>
        </label>
        <label>
            CPF
            <input type="text" name="cpf" value="<?php echo Helpers::e($value('cpf')); ?>">
        </label>
        <label>
            CNPJ
            <input type="text" name="cnpj" value="<?php echo Helpers::e($value('cnpj')); ?>">
        </label>
        <label>
            Razão social
            <input type="text" name="razao_social" value="<?php echo Helpers::e($value('razao_social')); ?>">
        </label>
        <label>
            Nome fantasia
            <input type="text" name="nome_fantasia" value="<?php echo Helpers::e($value('nome_fantasia')); ?>">
        </label>
        <label>
            Inscrição municipal
            <input type="text" name="inscricao_municipal" value="<?php echo Helpers::e($value('inscricao_municipal')); ?>">
        </label>
        <label>
            Alíquota de retenção (%)
            <input type="number" step="0.01" min="0" max="100" name="aliquota_retencao" value="<?php echo Helpers::e((string) $value('aliquota_retencao', '0.00')); ?>">
        </label>
        <label>
            E-mail financeiro
            <input type="email" name="email_financeiro" value="<?php echo Helpers::e($value('email_financeiro')); ?>">
        </label>
        <label>
            Status
            <select name="status">
                <option value="ativo" <?php echo ((string) $value('status', 'ativo') === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                <option value="inativo" <?php echo ((string) $value('status', '') === 'inativo') ? 'selected' : ''; ?>>Inativo</option>
            </select>
        </label>
        <label class="full">
            Observação
            <textarea name="observacao" rows="4"><?php echo Helpers::e($value('observacao')); ?></textarea>
        </label>
        <label class="checkbox">
            <input type="checkbox" name="exige_nota_fiscal" value="1" <?php echo !empty($value('exige_nota_fiscal', 0)) ? 'checked' : ''; ?>>
            Exigir nota fiscal
        </label>
        <?php $cancel_url = '/admin/professores-fiscais'; ?>
        <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
    </form>
</section>
</div>

