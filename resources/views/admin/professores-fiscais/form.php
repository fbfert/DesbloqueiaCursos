<?php use App\Core\Helpers; ?>

<?php $perfil = isset($form_data['perfil']) ? $form_data['perfil'] : null; ?>
<?php $professores = isset($form_data['professores']) ? $form_data['professores'] : array(); ?>

<section class="hero">
    <h1><?php echo Helpers::e($title); ?></h1>
    <p>Cadastro e manutencao do perfil fiscal dos professores.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
        <input type="hidden" name="id" value="<?php echo !empty($perfil['id']) ? (int) $perfil['id'] : 0; ?>">
        <label>
            Professor
            <select name="usuario_id" required>
                <option value="">Selecione</option>
                <?php foreach ($professores as $professor): ?>
                    <option value="<?php echo (int) $professor['id']; ?>" <?php echo !empty($perfil['usuario_id']) && (int) $perfil['usuario_id'] === (int) $professor['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($professor['nome'] . ' (' . $professor['email'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Tipo fiscal
            <select name="tipo_pessoa">
                <option value="pf" <?php echo (($perfil['tipo_pessoa'] ?? 'pf') === 'pf') ? 'selected' : ''; ?>>PF</option>
                <option value="pj" <?php echo (($perfil['tipo_pessoa'] ?? '') === 'pj') ? 'selected' : ''; ?>>PJ</option>
            </select>
        </label>
        <label>
            CPF
            <input type="text" name="cpf" value="<?php echo Helpers::e($perfil['cpf'] ?? ''); ?>">
        </label>
        <label>
            CNPJ
            <input type="text" name="cnpj" value="<?php echo Helpers::e($perfil['cnpj'] ?? ''); ?>">
        </label>
        <label>
            Razao social
            <input type="text" name="razao_social" value="<?php echo Helpers::e($perfil['razao_social'] ?? ''); ?>">
        </label>
        <label>
            Nome fantasia
            <input type="text" name="nome_fantasia" value="<?php echo Helpers::e($perfil['nome_fantasia'] ?? ''); ?>">
        </label>
        <label>
            Inscrição municipal
            <input type="text" name="inscricao_municipal" value="<?php echo Helpers::e($perfil['inscricao_municipal'] ?? ''); ?>">
        </label>
        <label>
            Alíquota de retencao (%)
            <input type="number" step="0.01" min="0" max="100" name="aliquota_retencao" value="<?php echo Helpers::e((string) ($perfil['aliquota_retencao'] ?? '0.00')); ?>">
        </label>
        <label>
            E-mail financeiro
            <input type="email" name="email_financeiro" value="<?php echo Helpers::e($perfil['email_financeiro'] ?? ''); ?>">
        </label>
        <label>
            Status
            <select name="status">
                <option value="ativo" <?php echo (($perfil['status'] ?? 'ativo') === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                <option value="inativo" <?php echo (($perfil['status'] ?? '') === 'inativo') ? 'selected' : ''; ?>>Inativo</option>
            </select>
        </label>
        <label class="full">
            Observação
            <textarea name="observacao" rows="4"><?php echo Helpers::e($perfil['observacao'] ?? ''); ?></textarea>
        </label>
        <label class="checkbox">
            <input type="checkbox" name="exige_nota_fiscal" value="1" <?php echo !empty($perfil['exige_nota_fiscal']) ? 'checked' : ''; ?>>
            Exigir nota fiscal
        </label>
        <button type="submit"><?php echo Helpers::e($submit_label); ?></button>
    </form>
</section>

