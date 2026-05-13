<?php
use App\Core\Helpers;

$modelo = isset($form_data) && is_array($form_data) ? $form_data : array();
$old = isset($old) && is_array($old) ? $old : array();

$value = function ($field, $default = '') use ($old, $modelo) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }

    if (array_key_exists($field, $modelo)) {
        return $modelo[$field];
    }

    return $default;
};

$variaveisTexto = function () use ($old, $modelo) {
    if (array_key_exists('variaveis_disponiveis', $old)) {
        return (string) $old['variaveis_disponiveis'];
    }

    if (!empty($modelo['variaveis_disponiveis'])) {
        return (string) $modelo['variaveis_disponiveis'];
    }

    if (!empty($modelo['variaveis_json'])) {
        $decodificado = json_decode((string) $modelo['variaveis_json'], true);
        if (is_array($decodificado)) {
            return implode(PHP_EOL, $decodificado);
        }
    }

    return '';
};

$isEdit = !empty($modelo['id']);
$isDefaultEvent = !empty($modelo['is_default_event']);
$isSuperAdmin = !empty($is_superadmin);
$readOnlyDefault = $isDefaultEvent && !$isSuperAdmin;
$modelName = $isEdit ? 'modelo' : 'novo modelo';
?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
            <p class="admin-page__subtitle">Edite o assunto e o corpo HTML do e-mail transacional.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/emails/modelos">Voltar</a>
        </div>
    </header>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
            <?php echo $csrfField; ?>
            <input type="hidden" name="id" value="<?php echo (int) $value('id', 0); ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="evento" value="<?php echo Helpers::e($value('evento')); ?>">
                <input type="hidden" name="template" value="<?php echo Helpers::e($value('template')); ?>">
            <?php endif; ?>

            <label>
                Nome
                <input type="text" name="nome" value="<?php echo Helpers::e($value('nome')); ?>" required <?php echo $readOnlyDefault ? 'readonly' : ''; ?>>
            </label>

            <label>
                Evento
                <input type="text" name="evento" value="<?php echo Helpers::e($value('evento')); ?>" <?php echo ($isEdit || $readOnlyDefault) ? 'readonly' : 'required'; ?>>
            </label>

            <label>
                Template
                <input type="text" name="template" value="<?php echo Helpers::e($value('template')); ?>" <?php echo ($isEdit || $readOnlyDefault) ? 'readonly' : 'required'; ?>>
            </label>

            <label>
                Assunto
                <input type="text" name="assunto" value="<?php echo Helpers::e($value('assunto')); ?>" required <?php echo $readOnlyDefault ? 'readonly' : ''; ?>>
            </label>

            <label>
                Corpo HTML
                <textarea name="corpo_html" rows="18" style="font-family:Consolas, monospace;" <?php echo $readOnlyDefault ? 'readonly' : ''; ?>><?php echo Helpers::e($value('corpo_html')); ?></textarea>
            </label>

            <label>
                Gatilho
                <textarea name="gatilho_descricao" rows="4" <?php echo $readOnlyDefault ? 'readonly' : ''; ?>><?php echo Helpers::e($value('gatilho_descricao')); ?></textarea>
            </label>

            <label>
                Variáveis disponíveis
                <textarea name="variaveis_disponiveis" rows="4" placeholder="{usuario.nome}\n{usuario.email}\n{pedido.codigo}" <?php echo $readOnlyDefault ? 'readonly' : ''; ?>><?php echo Helpers::e($variaveisTexto()); ?></textarea>
                <small class="muted">Uma variável por linha. Use as chaves exatamente como aparecem no corpo e no assunto.</small>
            </label>

            <label class="checkbox">
                <input type="checkbox" name="ativo" value="1" <?php echo !empty($value('ativo', 1)) ? 'checked' : ''; ?> <?php echo $readOnlyDefault ? 'disabled' : ''; ?>>
                Envio automático ativo
            </label>

            <?php if ($isEdit && $isDefaultEvent): ?>
                <div class="status-card" style="margin-top:8px;">
                    <p class="muted" style="margin:0;">
                        Modelo padrão do sistema.
                        <?php if ($isSuperAdmin): ?>
                            Você pode alterar assunto, corpo, gatilho, variáveis e status.
                        <?php else: ?>
                            Apenas o superadministrador pode editar este modelo.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="status-card" style="margin-top:8px;">
                <p class="muted" style="margin:0;">Placeholders principais: {usuario.nome}, {usuario.email}, {pedido.codigo}, {pedido.total}, {reset_url}, {observacao}, {inscricao.participante_nome}, {inscricao.curso_nome}, {inscricao.turma_nome}, {sistema.nome}</p>
            </div>

            <?php if ($readOnlyDefault): ?>
                <div class="status-card" style="margin-top:8px;">
                    <p class="muted" style="margin:0;">Este modelo é padrão do sistema. Apenas o superadministrador pode editá-lo.</p>
                </div>
                <div class="admin-page__actions" style="margin-top:16px;">
                    <a class="button-link button-link--ghost" href="/admin/emails/modelos">Cancelar</a>
                </div>
            <?php else: ?>
                <?php
                $cancel_url = '/admin/emails/modelos';
                $show_save_as_copy = false;
                $save_label = $isEdit ? 'Salvar' : 'Salvar';
                $save_and_new_label = 'Salvar e novo';
                $save_and_exit_label = 'Salvar e sair';
                $cancel_label = 'Cancelar';
                require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                ?>
            <?php endif; ?>
        </form>
    </section>
</section>
