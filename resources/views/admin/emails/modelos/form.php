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

            <?php
            $corpoHtmlValue = (string) $value('corpo_html');
            $docParts = \App\Support\EmailHtmlDocument::split($corpoHtmlValue);
            $temShellDocumento = !empty($docParts['is_full']);
            $editavel = !$readOnlyDefault;
            // Fragmento editável -> CKEditor 5. Documento HTML completo editável ->
            // editor visual do corpo com preservação do shell (contenteditable).
            $modoFragmentoVisual = $editavel && !$temShellDocumento;
            $modoDocumentoVisual = $editavel && $temShellDocumento;
            // Conteúdo interno do <body> apenas para injeção segura no admin.
            $innerBodySeguro = \App\Support\EmailHtmlDocument::sanitize((string) $docParts['inner']);
            ?>
            <div class="email-editor__head">
                <span class="email-editor__label">Corpo do e-mail</span>
            </div>
            <small class="muted" style="display:block;margin:4px 0 6px;">
                O conteúdo é salvo em HTML.
                <?php if ($modoDocumentoVisual): ?>
                    Edite o corpo pelos botões de formatação, sem ver as tags. O documento HTML completo (doctype, head, body, estilos) é preservado; use “Editar código HTML completo” apenas para ajustes estruturais avançados.
                <?php elseif ($modoFragmentoVisual): ?>
                    Utilize os botões de formatação para editar o conteúdo.
                <?php else: ?>
                    Este modelo é somente leitura para o seu perfil.
                <?php endif; ?>
                Os placeholders entre chaves (ex.: {usuario.nome}) devem ser preservados.
            </small>

            <?php if ($modoDocumentoVisual): ?>
                <div class="email-editor email-doc-editor" data-email-editor data-email-doc-editor data-mode="visual"
                     data-prefix="<?php echo Helpers::e((string) $docParts['prefix']); ?>"
                     data-suffix="<?php echo Helpers::e((string) $docParts['suffix']); ?>">
                    <div class="email-doc-editor__toolbar" role="toolbar" aria-label="Ferramentas de formatação">
                        <button type="button" class="email-doc-editor__btn" data-cmd="bold" title="Negrito"><strong>N</strong></button>
                        <button type="button" class="email-doc-editor__btn" data-cmd="italic" title="Itálico"><em>I</em></button>
                        <button type="button" class="email-doc-editor__btn" data-cmd="underline" title="Sublinhado"><span style="text-decoration:underline;">S</span></button>
                        <span class="email-doc-editor__sep"></span>
                        <button type="button" class="email-doc-editor__btn" data-cmd="formatBlock" data-value="p" title="Parágrafo">Parágrafo</button>
                        <button type="button" class="email-doc-editor__btn" data-cmd="formatBlock" data-value="h2" title="Título 2">T2</button>
                        <button type="button" class="email-doc-editor__btn" data-cmd="formatBlock" data-value="h3" title="Título 3">T3</button>
                        <span class="email-doc-editor__sep"></span>
                        <button type="button" class="email-doc-editor__btn" data-cmd="insertUnorderedList" title="Lista com marcadores">• Lista</button>
                        <button type="button" class="email-doc-editor__btn" data-cmd="insertOrderedList" title="Lista numerada">1. Lista</button>
                        <span class="email-doc-editor__sep"></span>
                        <button type="button" class="email-doc-editor__btn" data-cmd="justifyLeft" title="Alinhar à esquerda">⯇</button>
                        <button type="button" class="email-doc-editor__btn" data-cmd="justifyCenter" title="Centralizar">≡</button>
                        <button type="button" class="email-doc-editor__btn" data-cmd="justifyRight" title="Alinhar à direita">⯈</button>
                        <span class="email-doc-editor__sep"></span>
                        <button type="button" class="email-doc-editor__btn" data-cmd="createLink" title="Inserir/editar link">Link</button>
                        <button type="button" class="email-doc-editor__btn" data-cmd="unlink" title="Remover link">Remover link</button>
                        <span class="email-doc-editor__sep"></span>
                        <label class="email-doc-editor__color" title="Cor do texto">Cor <input type="color" data-role="forecolor" value="#1f2937"></label>
                        <button type="button" class="email-doc-editor__btn" data-cmd="insertHorizontalRule" title="Linha horizontal">Linha</button>
                        <button type="button" class="email-doc-editor__btn" data-cmd="removeFormat" title="Limpar formatação">Limpar</button>
                        <span class="email-doc-editor__sep"></span>
                        <button type="button" class="email-doc-editor__btn" data-cmd="undo" title="Desfazer">Desfazer</button>
                        <button type="button" class="email-doc-editor__btn" data-cmd="redo" title="Refazer">Refazer</button>
                        <span class="email-doc-editor__spacer"></span>
                        <button type="button" class="email-doc-editor__btn" data-role="toggle-source">Editar código HTML completo</button>
                        <button type="button" class="email-doc-editor__btn" data-email-editor-fullscreen>Tela cheia</button>
                    </div>
                    <div class="email-doc-editor__visual" data-role="visual" contenteditable="true"><?php echo $innerBodySeguro; ?></div>
                    <div class="email-doc-editor__source" data-role="source" hidden>
                        <p class="muted" style="margin:0 0 6px;">Modo avançado: edite o documento HTML inteiro (doctype, head, body). Use apenas para alterações estruturais.</p>
                        <textarea data-role="source-textarea" spellcheck="false"><?php echo Helpers::e($corpoHtmlValue); ?></textarea>
                    </div>
                    <textarea name="corpo_html" data-role="output" hidden><?php echo Helpers::e($corpoHtmlValue); ?></textarea>
                </div>
            <?php elseif ($modoFragmentoVisual): ?>
                <div class="email-editor" data-email-editor>
                    <div class="email-editor__head" style="justify-content:flex-end;">
                        <button type="button" class="button-link button-link--ghost" data-email-editor-fullscreen>Tela cheia</button>
                    </div>
                    <textarea name="corpo_html" rows="18" class="js-email-html-editor"><?php echo Helpers::e($corpoHtmlValue); ?></textarea>
                </div>
            <?php else: ?>
                <textarea name="corpo_html" rows="18" style="font-family:Consolas, monospace;" readonly><?php echo Helpers::e($corpoHtmlValue); ?></textarea>
            <?php endif; ?>

            <label>
                Gatilho
                <textarea name="gatilho_descricao" rows="4" <?php echo $readOnlyDefault ? 'readonly' : ''; ?>><?php echo Helpers::e($value('gatilho_descricao')); ?></textarea>
            </label>

            <label>
                Variáveis disponíveis
                <textarea name="variaveis_disponiveis" rows="4" placeholder="{usuario.nome}\n{usuario.email}\n{pedido.codigo}\n{certificado_url_download}" <?php echo $readOnlyDefault ? 'readonly' : ''; ?>><?php echo Helpers::e($variaveisTexto()); ?></textarea>
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

            <?php
            $placeholderInventario = isset($placeholder_inventario) && is_array($placeholder_inventario) ? $placeholder_inventario : array();
            $placeholderAvisos = isset($placeholder_avisos) && is_array($placeholder_avisos) ? $placeholder_avisos : array('desconhecidos' => array(), 'incompativeis' => array());
            $avisosDesconhecidos = isset($placeholderAvisos['desconhecidos']) ? $placeholderAvisos['desconhecidos'] : array();
            $avisosIncompativeis = isset($placeholderAvisos['incompativeis']) ? $placeholderAvisos['incompativeis'] : array();
            $eventoAtual = (string) $value('evento');
            ?>

            <?php if (!empty($avisosDesconhecidos)): ?>
                <div class="status-card" style="margin-top:8px;border-left:4px solid #b45309;background:#fff7ed;">
                    <p style="margin:0;"><strong>Aviso:</strong> o conteúdo usa placeholder(s) não reconhecido(s) pelo sistema:
                        <?php echo Helpers::e(implode(', ', $avisosDesconhecidos)); ?>.
                        Eles serão enviados vazios. Revise a grafia ou remova-os.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($avisosIncompativeis)): ?>
                <div class="status-card" style="margin-top:8px;border-left:4px solid #b45309;background:#fff7ed;">
                    <p style="margin:0;"><strong>Aviso:</strong> placeholder(s) reconhecido(s), mas normalmente sem valor neste tipo de e-mail<?php echo $eventoAtual !== '' ? ' (' . Helpers::e($eventoAtual) . ')' : ''; ?>:
                        <?php echo Helpers::e(implode(', ', $avisosIncompativeis)); ?>.
                        Confirme se há contexto para eles neste evento.</p>
                </div>
            <?php endif; ?>

            <div class="status-card" style="margin-top:8px;">
                <details<?php echo (!empty($avisosDesconhecidos) || !empty($avisosIncompativeis)) ? ' open' : ''; ?>>
                    <summary style="cursor:pointer;font-weight:600;">Placeholders disponíveis<?php echo $eventoAtual !== '' ? ' para este e-mail' : ''; ?></summary>
                    <?php if (empty($placeholderInventario)): ?>
                        <p class="muted" style="margin:8px 0 0;">Nenhum placeholder específico mapeado para este evento.</p>
                    <?php else: ?>
                        <div class="table-wrapper" style="margin-top:8px;">
                            <table class="admin-table">
                                <thead>
                                    <tr><th>Placeholder</th><th>Significado</th><th>Formato</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($placeholderInventario as $ph): ?>
                                        <tr>
                                            <td><code><?php echo Helpers::e($ph['token']); ?></code>
                                                <?php if (!empty($ph['aliases'])): ?>
                                                    <br><small class="muted">equivale a <?php echo Helpers::e(implode(', ', $ph['aliases'])); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo Helpers::e($ph['nome']); ?><br><small class="muted"><?php echo Helpers::e($ph['origem']); ?></small></td>
                                            <td><?php echo Helpers::e($ph['formato']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="muted" style="margin:8px 0 0;">As chaves simples ({exemplo}) e duplas ({{exemplo}}) equivalentes apontam para o mesmo dado. Placeholders sem valor no envio são substituídos por conteúdo seguro (nunca aparecem em formato bruto ao destinatário).</p>
                    <?php endif; ?>
                </details>
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
                $show_save_as_copy = $isEdit;
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
