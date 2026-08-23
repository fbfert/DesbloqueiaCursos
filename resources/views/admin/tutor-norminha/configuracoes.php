<?php
use App\Core\Helpers;

$configuracoes = isset($configuracoes) && is_array($configuracoes) ? $configuracoes : array();
$oldInput = isset($oldInput) && is_array($oldInput) ? $oldInput : array();

$value = function ($field, $default = '') use ($configuracoes, $oldInput) {
    if (array_key_exists($field, $oldInput)) {
        return $oldInput[$field];
    }

    if (array_key_exists($field, $configuracoes)) {
        return $configuracoes[$field];
    }

    return $default;
};

$checked = function ($field, $default = 0) use ($value) {
    return (int) $value($field, $default) === 1;
};

$previewTituloPadrao = trim((string) $value('tutor_titulo_padrao', 'Norminha')) !== '' ? trim((string) $value('tutor_titulo_padrao', 'Norminha')) : 'Norminha';
$previewTextoBotao = trim((string) $value('tutor_texto_botao', 'Ouvir orientação')) !== '' ? trim((string) $value('tutor_texto_botao', 'Ouvir orientação')) : 'Ouvir orientação';
$previewTtlHoras = (int) $value('tutor_ttl_fechamento_horas', 24);
$previewAvatarIdleDiag = (array) $value('avatar_parado_url_diagnostico', $value('tutor_avatar_idle_diagnostico', array()));
$previewAvatarSpeakingDiag = (array) $value('avatar_falando_url_diagnostico', $value('tutor_avatar_speaking_diagnostico', array()));
$previewAvatarIdle = isset($previewAvatarIdleDiag['url_publica']) && (string) $previewAvatarIdleDiag['url_publica'] !== ''
    ? (string) $previewAvatarIdleDiag['url_publica']
    : '';
$previewAvatarSpeaking = isset($previewAvatarSpeakingDiag['url_publica']) && (string) $previewAvatarSpeakingDiag['url_publica'] !== ''
    ? (string) $previewAvatarSpeakingDiag['url_publica']
    : '';
$previewAvatarIdleUsandoPadrao = !empty($previewAvatarIdleDiag['usando_padrao']);
$previewAvatarSpeakingUsandoPadrao = !empty($previewAvatarSpeakingDiag['usando_padrao']);
$debugAvatar = function (array $diag) {
    return array(
        'valor_banco' => isset($diag['valor_banco']) ? (string) $diag['valor_banco'] : '',
        'url_publica' => isset($diag['url_publica']) ? (string) $diag['url_publica'] : '',
        'caminho_fisico' => isset($diag['caminho_fisico']) ? (string) $diag['caminho_fisico'] : '',
        'file_exists' => !empty($diag['file_exists']) ? 'sim' : 'não',
    );
};
$debugAvatarIdle = $debugAvatar($previewAvatarIdleDiag);
$debugAvatarSpeaking = $debugAvatar($previewAvatarSpeakingDiag);
?>
<section class="admin-page tutor-norminha-admin">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
            <p class="admin-page__subtitle">Controle global das condições de exibição e dos textos padrão da Norminha no frontend público.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/tutor-norminha">Voltar para falas</a>
        </div>
    </header>

    <?php if (!empty($success)): ?>
        <section class="alert alert-success"><?php echo Helpers::e($success); ?></section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="alert alert-warning">
        As configurações controlam onde a Norminha aparece no frontend público. A geração automática de áudio ainda não está ativa nesta fase.
    </section>

    <section class="status-card tutor-norminha-admin__preview-panel">
        <div class="tutor-norminha-admin__section-head">
            <div>
                <strong>Preview das configurações</strong>
                <p class="muted">A área abaixo demonstra o visual básico com os valores atuais salvos ou editados no formulário.</p>
            </div>
        </div>

        <div class="tutor-norminha-admin__config-preview-shell" id="norminha-preview-configuracoes">
            <div class="tutor-norminha-admin__config-preview-avatars">
                <div class="tutor-norminha-admin__config-preview-avatar">
                    <span class="tutor-norminha-admin__preview-eyebrow">Avatar parado</span>
                    <?php if ($previewAvatarIdle !== ''): ?>
                        <img
                            src="<?php echo Helpers::e($previewAvatarIdle); ?>"
                            alt="Avatar parado configurado"
                            data-config-preview-idle
                        >
                        <span class="tutor-norminha-admin__preview-avatar-placeholder" hidden>Imagem indisponível</span>
                    <?php else: ?>
                        <span class="tutor-norminha-admin__preview-avatar-placeholder">Usando imagem padrão</span>
                    <?php endif; ?>
                </div>
                <div class="tutor-norminha-admin__config-preview-avatar">
                    <span class="tutor-norminha-admin__preview-eyebrow">Avatar falando</span>
                    <?php if ($previewAvatarSpeaking !== ''): ?>
                        <img
                            src="<?php echo Helpers::e($previewAvatarSpeaking); ?>"
                            alt="Avatar falando configurado"
                            data-config-preview-speaking
                        >
                        <span class="tutor-norminha-admin__preview-avatar-placeholder" hidden>Imagem indisponível</span>
                    <?php else: ?>
                        <span class="tutor-norminha-admin__preview-avatar-placeholder">Usando imagem padrão</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tutor-norminha-admin__config-preview-copy">
                <h2 class="tutor-norminha-admin__preview-title" data-config-preview-title><?php echo Helpers::e($previewTituloPadrao); ?></h2>
                <p class="tutor-norminha-admin__preview-text" data-config-preview-button><?php echo Helpers::e($previewTextoBotao); ?></p>
                <div class="tutor-norminha-admin__preview-meta">
                    <span class="tutor-norminha-admin__preview-chip">TTL: <strong data-config-preview-ttl><?php echo (int) $previewTtlHoras; ?>h</strong></span>
                    <span class="tutor-norminha-admin__preview-chip">Botão: <strong data-config-preview-button-label><?php echo Helpers::e($previewTextoBotao); ?></strong></span>
                </div>
                <div class="tutor-norminha-admin__config-preview-button-wrap">
                    <button type="button" class="button-link button-link--primary" data-config-preview-button-demo><?php echo Helpers::e($previewTextoBotao); ?></button>
                    <small class="muted">A prévia não executa ações no frontend público.</small>
                </div>
                <details class="tutor-norminha-admin__avatar-debug" open>
                    <summary>Diagnóstico temporário dos avatares</summary>
                    <div class="tutor-norminha-admin__avatar-debug-grid">
                        <div>
                            <strong>Avatar parado</strong>
                            <p><code>valor salvo no banco:</code> <?php echo Helpers::e($debugAvatarIdle['valor_banco']); ?></p>
                            <p><code>URL pública final:</code> <?php echo Helpers::e($debugAvatarIdle['url_publica']); ?></p>
                            <p><code>caminho físico:</code> <?php echo Helpers::e($debugAvatarIdle['caminho_fisico']); ?></p>
                            <p><code>file_exists:</code> <?php echo Helpers::e($debugAvatarIdle['file_exists']); ?></p>
                        </div>
                        <div>
                            <strong>Avatar falando</strong>
                            <p><code>valor salvo no banco:</code> <?php echo Helpers::e($debugAvatarSpeaking['valor_banco']); ?></p>
                            <p><code>URL pública final:</code> <?php echo Helpers::e($debugAvatarSpeaking['url_publica']); ?></p>
                            <p><code>caminho físico:</code> <?php echo Helpers::e($debugAvatarSpeaking['caminho_fisico']); ?></p>
                            <p><code>file_exists:</code> <?php echo Helpers::e($debugAvatarSpeaking['file_exists']); ?></p>
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </section>

    <section class="status-card">
        <form method="post" action="/admin/tutor-norminha/configuracoes/salvar" class="admin-form" enctype="multipart/form-data">
            <?php echo $csrfField; ?>
            <div class="admin-form-grid">
                <section class="status-card admin-form-grid__full">
                    <strong>Ativação</strong>
                    <div class="admin-form-grid" style="margin-top:12px;">
                        <label class="checkbox">
                            <input type="hidden" name="tutor_ativo" value="0">
                            <input type="checkbox" name="tutor_ativo" value="1" <?php echo $checked('tutor_ativo', 1) ? 'checked' : ''; ?>>
                            Tutor Virtual ativo
                        </label>
                        <label class="checkbox">
                            <input type="hidden" name="tutor_minimizado_padrao" value="0">
                            <input type="checkbox" name="tutor_minimizado_padrao" value="1" <?php echo $checked('tutor_minimizado_padrao', 0) ? 'checked' : ''; ?>>
                            Iniciar minimizado
                        </label>
                    </div>
                </section>

                <section class="status-card admin-form-grid__full">
                    <strong>Contextos de exibição</strong>
                    <div class="admin-form-grid" style="margin-top:12px;">
                        <label class="checkbox">
                            <input type="hidden" name="tutor_home" value="0">
                            <input type="checkbox" name="tutor_home" value="1" <?php echo $checked('tutor_home', 1) ? 'checked' : ''; ?>>
                            Exibir na home
                        </label>
                        <label class="checkbox">
                            <input type="hidden" name="tutor_area_aluno" value="0">
                            <input type="checkbox" name="tutor_area_aluno" value="1" <?php echo $checked('tutor_area_aluno', 1) ? 'checked' : ''; ?>>
                            Exibir na área do aluno
                        </label>
                        <label class="checkbox">
                            <input type="hidden" name="tutor_cursos" value="0">
                            <input type="checkbox" name="tutor_cursos" value="1" <?php echo $checked('tutor_cursos', 1) ? 'checked' : ''; ?>>
                            Exibir em cursos e aulas
                        </label>
                        <label class="checkbox">
                            <input type="hidden" name="tutor_checkout" value="0">
                            <input type="checkbox" name="tutor_checkout" value="1" <?php echo $checked('tutor_checkout', 0) ? 'checked' : ''; ?>>
                            Exibir no checkout
                        </label>
                    </div>
                </section>

                <section class="status-card admin-form-grid__full">
                    <strong>Comportamento</strong>
                    <div class="admin-form-grid" style="margin-top:12px;">
                        <label>
                            <span>TTL de fechamento em horas</span>
                            <input type="number" name="tutor_ttl_fechamento_horas" min="1" max="168" step="1" value="<?php echo Helpers::e((string) $value('tutor_ttl_fechamento_horas', 24)); ?>">
                        </label>
                        <label>
                            <span>Limite de mensagens por 5 minutos</span>
                            <input type="number" name="tutor_ia_limite_5min" min="1" max="500" step="1" value="<?php echo Helpers::e((string) $value('tutor_ia_limite_5min', 20)); ?>">
                            <small>Contra rajada. Padrão 20. Se muitos alunos estiverem sendo bloqueados no uso normal, aumente.</small>
                        </label>
                        <label>
                            <span>Limite de mensagens por dia</span>
                            <input type="number" name="tutor_ia_limite_diario" min="1" max="10000" step="1" value="<?php echo Helpers::e((string) $value('tutor_ia_limite_diario', 200)); ?>">
                            <small>Contra abuso sustentado. Padrão 200. Não pode ser menor que o limite de 5 minutos.</small>
                        </label>
                        <label>
                            <span>Texto padrão do botão</span>
                            <input type="text" name="tutor_texto_botao" maxlength="80" value="<?php echo Helpers::e((string) $value('tutor_texto_botao', 'Ouvir orientação')); ?>">
                        </label>
                        <label class="admin-form-grid__full">
                            <span>Título padrão do card</span>
                            <input type="text" name="tutor_titulo_padrao" maxlength="80" value="<?php echo Helpers::e((string) $value('tutor_titulo_padrao', 'Norminha')); ?>">
                        </label>
                    </div>
                </section>

                <section class="status-card admin-form-grid__full">
                    <strong>Imagens do avatar</strong>
                    <p class="muted">Envie imagens para substituir os avatares atuais. Se nenhum arquivo for enviado, a imagem salva permanece.</p>
                    <div class="admin-form-grid" style="margin-top:12px;">
                        <label class="admin-form-grid__full">
                            <span>Avatar parado</span>
                            <input type="file" name="tutor_avatar_idle_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                            <small class="muted">JPG, PNG, WEBP ou GIF até 5 MB.</small>
                            <div class="tutor-norminha-admin__config-preview-upload">
                                <strong>Imagem atual</strong>
                                <?php if (!empty($previewAvatarIdle)): ?>
                                    <img src="<?php echo Helpers::e($previewAvatarIdle); ?>" alt="Avatar parado atual" style="max-width:160px;display:block;margin-top:8px;border-radius:16px;">
                                    <small class="muted"><?php echo $previewAvatarIdleUsandoPadrao ? 'Usando a imagem padrão do sistema.' : 'Imagem personalizada salva no servidor.'; ?></small>
                                <?php else: ?>
                                    <small class="muted">Nenhuma imagem configurada ainda.</small>
                                <?php endif; ?>
                            </div>
                        </label>
                        <label class="admin-form-grid__full">
                            <span>Avatar falando</span>
                            <input type="file" name="tutor_avatar_speaking_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                            <small class="muted">JPG, PNG, WEBP ou GIF até 5 MB.</small>
                            <div class="tutor-norminha-admin__config-preview-upload">
                                <strong>Imagem atual</strong>
                                <?php if (!empty($previewAvatarSpeaking)): ?>
                                    <img src="<?php echo Helpers::e($previewAvatarSpeaking); ?>" alt="Avatar falando atual" style="max-width:160px;display:block;margin-top:8px;border-radius:16px;">
                                    <small class="muted"><?php echo $previewAvatarSpeakingUsandoPadrao ? 'Usando a imagem padrão do sistema.' : 'Imagem personalizada salva no servidor.'; ?></small>
                                <?php else: ?>
                                    <small class="muted">Nenhuma imagem configurada ainda.</small>
                                <?php endif; ?>
                            </div>
                        </label>
                    </div>
                </section>
            </div>

            <div class="cta-group full">
                <button type="submit" class="button-link button-link--primary">Salvar configurações</button>
                <a class="button-link button-link--ghost" href="/admin/tutor-norminha">Voltar para falas</a>
            </div>
        </form>
    </section>
</section>

<script>
(function () {
    var preview = document.getElementById('norminha-preview-configuracoes');
    if (!preview) {
        return;
    }

    var tituloInput = document.querySelector('input[name="tutor_titulo_padrao"]');
    var textoBotaoInput = document.querySelector('input[name="tutor_texto_botao"]');
    var ttlInput = document.querySelector('input[name="tutor_ttl_fechamento_horas"]');
    var avatarIdleInput = document.querySelector('input[name="tutor_avatar_idle_file"]');
    var avatarSpeakingInput = document.querySelector('input[name="tutor_avatar_speaking_file"]');
    var titleTarget = preview.querySelector('[data-config-preview-title]');
    var ttlTarget = preview.querySelector('[data-config-preview-ttl]');
    var buttonTarget = preview.querySelector('[data-config-preview-button-label]');
    var buttonDemo = preview.querySelector('[data-config-preview-button-demo]');
    var avatarIdle = preview.querySelector('[data-config-preview-idle]');
    var avatarSpeaking = preview.querySelector('[data-config-preview-speaking]');

    function safeText(value, fallback) {
        value = (value || '').toString().replace(/<[^>]*>/g, '').trim();
        return value !== '' ? value : fallback;
    }

    function syncPreview() {
        var titulo = safeText(tituloInput ? tituloInput.value : '', 'Norminha');
        var botao = safeText(textoBotaoInput ? textoBotaoInput.value : '', 'Ouvir orientação');
        var ttl = parseInt(ttlInput ? ttlInput.value : '24', 10);
        if (titleTarget) {
            titleTarget.textContent = titulo;
        }
        if (ttlTarget) {
            ttlTarget.textContent = (isNaN(ttl) || ttl < 1 ? 24 : ttl) + 'h';
        }
        if (buttonTarget) {
            buttonTarget.textContent = botao;
        }
        if (buttonDemo) {
            buttonDemo.textContent = botao;
        }
    }

    if (tituloInput) {
        tituloInput.addEventListener('input', syncPreview);
    }
    if (textoBotaoInput) {
        textoBotaoInput.addEventListener('input', syncPreview);
    }
    if (ttlInput) {
        ttlInput.addEventListener('input', syncPreview);
        ttlInput.addEventListener('change', syncPreview);
    }
    if (avatarIdleInput) {
        avatarIdleInput.addEventListener('input', syncPreview);
    }
    if (avatarSpeakingInput) {
        avatarSpeakingInput.addEventListener('input', syncPreview);
        avatarSpeakingInput.addEventListener('change', function () {
            var file = avatarSpeakingInput.files && avatarSpeakingInput.files[0];
            if (file && avatarSpeaking) {
                avatarSpeaking.src = URL.createObjectURL(file);
            }
        });
    }

    if (avatarIdleInput) {
        avatarIdleInput.addEventListener('change', function () {
            var file = avatarIdleInput.files && avatarIdleInput.files[0];
            if (file && avatarIdle) {
                avatarIdle.src = URL.createObjectURL(file);
            }
        });
    }

    syncPreview();
})();
</script>
