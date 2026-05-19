<?php
use App\Core\Helpers;

$template = isset($form_data) && is_array($form_data) ? $form_data : array();
$old = isset($old) && is_array($old) ? $old : array();

$value = function ($field, $default = '') use ($old, $template) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }
    if (array_key_exists($field, $template)) {
        return $template[$field];
    }
    return $default;
};

$isEdit = !empty($template['id']);
?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
            <p class="admin-page__subtitle">Use placeholders para montar o texto/HTML do certificado.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/certificados/templates">Voltar</a>
            <?php if ($isEdit): ?>
                <a class="button-link" href="/admin/certificados/templates/preview?template_id=<?php echo (int) $template['id']; ?>">Preview</a>
            <?php endif; ?>
        </div>
    </header>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
            <input type="hidden" name="id" value="<?php echo (int) $value('id', 0); ?>">

            <div class="form-grid">
                <label>
                    Nome
                    <input type="text" name="nome" value="<?php echo Helpers::e($value('nome')); ?>" required>
                </label>

                <label>
                    Slug / chave interna
                    <input type="text" name="slug" value="<?php echo Helpers::e($value('slug')); ?>" placeholder="padrao-certificado" required>
                    <small class="muted">Usado para referência interna. Aceita letras, números e hífen.</small>
                </label>

                <label>
                    Descrição
                    <input type="text" name="descricao" value="<?php echo Helpers::e($value('descricao')); ?>">
                </label>

                <label>
                    Status
                    <select name="status">
                        <?php $status = (string) $value('status', !empty($template['ativo']) ? 'ativo' : 'rascunho'); ?>
                        <option value="rascunho" <?php echo $status === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                        <option value="ativo" <?php echo $status === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inativo" <?php echo $status === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </label>

                <label>
                    Contexto
                    <?php $contexto = (string) $value('contexto', 'global'); ?>
                    <select name="contexto">
                        <option value="global" <?php echo $contexto === 'global' ? 'selected' : ''; ?>>Global</option>
                        <option value="curso" <?php echo $contexto === 'curso' ? 'selected' : ''; ?>>Curso</option>
                        <option value="turma" <?php echo $contexto === 'turma' ? 'selected' : ''; ?>>Turma</option>
                        <option value="evento" <?php echo $contexto === 'evento' ? 'selected' : ''; ?>>Evento</option>
                        <option value="outro" <?php echo $contexto === 'outro' ? 'selected' : ''; ?>>Livre/outro</option>
                    </select>
                    <small class="muted">Hoje o sistema prioriza turma → curso → global.</small>
                </label>

                <label>
                    Curso ID (opcional)
                    <input type="number" min="1" name="curso_id" value="<?php echo Helpers::e($value('curso_id')); ?>">
                </label>

                <label>
                    Turma ID (opcional)
                    <input type="number" min="1" name="turma_id" value="<?php echo Helpers::e($value('turma_id')); ?>">
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="padrao" value="1" <?php echo !empty($value('padrao', 0)) ? 'checked' : ''; ?>>
                    Definir como padrão global
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="ativo" value="1" <?php echo !empty($value('ativo', 1)) ? 'checked' : ''; ?>>
                    Template ativo (compatibilidade)
                </label>

                <hr style="grid-column:1/-1;border:none;border-top:1px solid rgba(0,0,0,.08);margin:4px 0;">

                <label>
                    Orientação
                    <?php $ori = (string) $value('orientacao', 'paisagem'); ?>
                    <select name="orientacao">
                        <option value="paisagem" <?php echo $ori === 'paisagem' ? 'selected' : ''; ?>>Paisagem</option>
                        <option value="retrato" <?php echo $ori === 'retrato' ? 'selected' : ''; ?>>Retrato</option>
                    </select>
                </label>

                <label>
                    Tamanho do papel
                    <?php $papel = (string) $value('tamanho_papel', 'A4'); ?>
                    <select name="tamanho_papel">
                        <option value="A4" <?php echo $papel === 'A4' ? 'selected' : ''; ?>>A4</option>
                        <option value="Carta" <?php echo $papel === 'Carta' ? 'selected' : ''; ?>>Carta</option>
                    </select>
                </label>

                <label>
                    Margem superior
                    <input type="number" step="0.01" min="0" name="margem_top" value="<?php echo Helpers::e($value('margem_top')); ?>">
                </label>

                <label>
                    Margem inferior
                    <input type="number" step="0.01" min="0" name="margem_bottom" value="<?php echo Helpers::e($value('margem_bottom')); ?>">
                </label>

                <label>
                    Margem esquerda
                    <input type="number" step="0.01" min="0" name="margem_left" value="<?php echo Helpers::e($value('margem_left')); ?>">
                </label>

                <label>
                    Margem direita
                    <input type="number" step="0.01" min="0" name="margem_right" value="<?php echo Helpers::e($value('margem_right')); ?>">
                </label>

                <label>
                    Cor de fundo
                    <input type="text" name="cor_fundo" value="<?php echo Helpers::e($value('cor_fundo', '#ffffff')); ?>" placeholder="#ffffff">
                </label>

                <label>
                    Cor do texto
                    <input type="text" name="cor_texto" value="<?php echo Helpers::e($value('cor_texto', '#111827')); ?>" placeholder="#111827">
                </label>

                <label>
                    Imagem de fundo (URL/caminho)
                    <input type="text" name="imagem_fundo" value="<?php echo Helpers::e($value('imagem_fundo')); ?>">
                </label>

                <label>
                    Logo (URL/caminho)
                    <input type="text" name="logo" value="<?php echo Helpers::e($value('logo')); ?>">
                </label>

                <label style="grid-column:1/-1;">
                    Conteúdo HTML do certificado
                    <textarea name="corpo_html" rows="16" style="font-family:Consolas, monospace;"><?php echo Helpers::e($value('corpo_html')); ?></textarea>
                    <small class="muted">Por enquanto o PDF do sistema segue o gerador atual; este conteúdo já fica pronto para evolução.</small>
                </label>

                <label style="grid-column:1/-1;">
                    CSS customizado (opcional)
                    <textarea name="css" rows="10" style="font-family:Consolas, monospace;"><?php echo Helpers::e($value('css')); ?></textarea>
                </label>

                <label style="grid-column:1/-1;">
                    Observações internas
                    <textarea name="observacoes" rows="4"><?php echo Helpers::e($value('observacoes')); ?></textarea>
                </label>
            </div>

            <div class="status-card" style="margin-top:12px;">
                <p class="muted" style="margin:0;">
                    Placeholders disponíveis (copie e cole no conteúdo):
                </p>
                <div class="table-wrap" style="margin-top:8px;">
                    <table class="admin-table">
                        <thead><tr><th>Grupo</th><th>Placeholder</th><th>Descrição</th></tr></thead>
                        <tbody>
                        <?php foreach (($placeholders ?? array()) as $grupo => $items): ?>
                            <?php foreach ($items as $ph => $desc): ?>
                                <tr>
                                    <td><?php echo Helpers::e($grupo); ?></td>
                                    <td style="font-family:Consolas, monospace;"><?php echo Helpers::e($ph); ?></td>
                                    <td><?php echo Helpers::e($desc); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php
            $cancel_url = '/admin/certificados/templates';
            $show_save_as_copy = $isEdit;
            $save_label = 'Salvar';
            $save_and_new_label = 'Salvar e novo';
            $save_and_exit_label = 'Salvar e sair';
            $cancel_label = 'Cancelar';
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </form>
    </section>
</section>

