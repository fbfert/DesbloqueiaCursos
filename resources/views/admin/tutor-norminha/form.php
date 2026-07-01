<?php
use App\Core\Helpers;

$fala = isset($form_data['fala']) && is_array($form_data['fala']) ? $form_data['fala'] : array();
$contextos = isset($form_data['contextos']) && is_array($form_data['contextos']) ? $form_data['contextos'] : array('home', 'institucional', 'area_aluno', 'cursos', 'curso', 'aula', 'checkout');
$contextosLabels = array(
    'home' => 'Início',
    'institucional' => 'Institucional',
    'area_aluno' => 'Área do aluno',
    'cursos' => 'Cursos',
    'curso' => 'Curso',
    'aula' => 'Aula',
    'checkout' => 'Finalização',
);
$estadosAvatar = isset($form_data['estados_avatar']) && is_array($form_data['estados_avatar']) ? $form_data['estados_avatar'] : array('speaking', 'explaining', 'attention', 'celebrating', 'doubt');
$audioPublicUrl = isset($form_data['audio_public_url']) ? trim((string) $form_data['audio_public_url']) : '';
$audioUrlSalvo = isset($form_data['audio_url_salvo']) ? trim((string) $form_data['audio_url_salvo']) : '';
$audioExiste = !empty($form_data['audio_existe']);
$configuracoesTutor = isset($form_data['configuracoes']) && is_array($form_data['configuracoes']) ? $form_data['configuracoes'] : array();
$oldInput = isset($oldInput) && is_array($oldInput) ? $oldInput : array();

$value = function ($field, $default = '') use ($oldInput, $fala) {
    if (array_key_exists($field, $oldInput)) {
        return $oldInput[$field];
    }

    if (array_key_exists($field, $fala)) {
        return $fala[$field];
    }

    return $default;
};

$configValue = function ($field, $default = '') use ($configuracoesTutor) {
    if (array_key_exists($field, $configuracoesTutor)) {
        return $configuracoesTutor[$field];
    }

    return $default;
};
$nullableValue = function ($field) use ($value) {
    $raw = $value($field, '');
    if ($raw === null) {
        return '';
    }

    if (is_string($raw)) {
        $raw = trim($raw);
    }

    if ($raw === '' || $raw === '0' || $raw === 0) {
        return '';
    }

    return (string) $raw;
};

$id = (int) $value('id', 0);
$tituloAtual = (string) $value('titulo', '');
$textoAtual = (string) $value('texto', '');
$textoLength = function_exists('mb_strlen') ? mb_strlen($textoAtual, 'UTF-8') : strlen($textoAtual);
$ativoAtual = (int) $value('ativo', 1) === 1 ? 1 : 0;
$previewTituloPadrao = trim((string) $configValue('tutor_titulo_padrao', 'Norminha')) !== '' ? trim((string) $configValue('tutor_titulo_padrao', 'Norminha')) : 'Norminha';
$previewTextoBotao = trim((string) $configValue('tutor_texto_botao', 'Ouvir orientação')) !== '' ? trim((string) $configValue('tutor_texto_botao', 'Ouvir orientação')) : 'Ouvir orientação';
$previewTtlHoras = (int) $configValue('tutor_ttl_fechamento_horas', 24);
$previewAvatarIdle = (string) $configValue('tutor_avatar_idle', '/assets/norminha/norminha-idle.webp');
$previewAvatarSpeaking = (string) $configValue('tutor_avatar_speaking', '/assets/norminha/norminha-speaking.webp');
$estadosAvatarLabels = array(
    'speaking' => 'Falando',
    'explaining' => 'Explicando',
    'attention' => 'Atenção',
    'celebrating' => 'Celebrando',
    'doubt' => 'Em dúvida',
);
$audioMimeType = 'audio/mpeg';
if ($audioPublicUrl !== '') {
    $audioExt = strtolower(pathinfo(parse_url($audioPublicUrl, PHP_URL_PATH) ?: $audioPublicUrl, PATHINFO_EXTENSION));
    if ($audioExt === 'ogg') {
        $audioMimeType = 'audio/ogg';
    } elseif ($audioExt === 'wav') {
        $audioMimeType = 'audio/wav';
    }
}
$publicPathRoot = defined('PUBLIC_PATH') && is_string(PUBLIC_PATH) && trim(PUBLIC_PATH) !== ''
    ? rtrim((string) PUBLIC_PATH, "/\\")
    : (defined('BASE_PATH') && is_string(BASE_PATH) && trim(BASE_PATH) !== ''
        ? (preg_match('#(?:^|[\\\\/])public_html$#i', rtrim((string) BASE_PATH, "/\\")) ? rtrim((string) BASE_PATH, "/\\") : rtrim((string) BASE_PATH, "/\\") . DIRECTORY_SEPARATOR . 'public_html')
        : rtrim((string) getcwd(), "/\\") . DIRECTORY_SEPARATOR . 'public_html');
$audioFisico = $audioPublicUrl !== '' ? $publicPathRoot . $audioPublicUrl : '';
$audioFisicoValido = $audioFisico !== '' && is_file($audioFisico) && is_readable($audioFisico) && (int) @filesize($audioFisico) > 0;
?>
<section class="admin-page tutor-norminha-admin">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
            <p class="admin-page__subtitle">Cadastre o texto da fala e envie o áudio manualmente. A geração automática de áudio será implementada em uma fase futura.</p>
        </div>
        <div class="admin-page__actions">
            <?php if ($id > 0): ?>
                <form method="post" action="/admin/tutor-norminha/status" class="tutor-norminha-admin__status-form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                    <input type="hidden" name="ativo" value="<?php echo $ativoAtual ? 0 : 1; ?>">
                    <button type="submit" class="button-link button-link--ghost" onclick="return confirm('<?php echo $ativoAtual ? 'Desativar' : 'Ativar'; ?> esta fala?');"><?php echo $ativoAtual ? 'Desativar' : 'Ativar'; ?></button>
                </form>
            <?php endif; ?>
            <a class="button-link button-link--ghost" href="/admin/tutor-norminha">Voltar</a>
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

    <section class="status-card tutor-norminha-admin__notice">
        <strong>Regras do envio</strong>
        <p>Os arquivos devem ser enviados apenas para <code>/uploads/tutor-norminha/audio/</code>.</p>
        <p>Formatos permitidos: <code>.mp3</code>, <code>.ogg</code> e <code>.wav</code>. O sistema não gera áudio automaticamente nesta fase.</p>
    </section>

    <section class="status-card tutor-norminha-admin__preview-panel">
        <div class="tutor-norminha-admin__section-head">
            <div>
                <strong>Preview da Norminha</strong>
                <p class="muted">A prévia abaixo não salva alterações e serve apenas para testar a aparência da fala no admin.</p>
            </div>
        </div>

        <div class="tutor-norminha-admin__preview-shell" id="norminha-preview-fala" data-has-audio="<?php echo $audioPublicUrl !== '' ? '1' : '0'; ?>">
            <div class="tutor-norminha-admin__preview-avatar-box" data-preview-avatar-state="idle">
                <div class="tutor-norminha-admin__preview-avatar-frame is-idle" aria-live="polite">
                    <img
                        class="tutor-norminha-admin__preview-avatar-image tutor-norminha-admin__preview-avatar-image--idle"
                        src="<?php echo Helpers::e($previewAvatarIdle); ?>"
                        alt="Avatar parado da Norminha"
                        data-avatar-idle
                        onerror="this.style.display='none';var fallback=this.nextElementSibling; if (fallback) { fallback.hidden=false; }"
                    >
                    <img
                        class="tutor-norminha-admin__preview-avatar-image tutor-norminha-admin__preview-avatar-image--speaking"
                        src="<?php echo Helpers::e($previewAvatarSpeaking); ?>"
                        alt="Avatar falando da Norminha"
                        hidden
                        data-avatar-speaking
                        onerror="this.style.display='none';var fallback=this.nextElementSibling; if (fallback) { fallback.hidden=false; }"
                    >
                    <span class="tutor-norminha-admin__preview-avatar-placeholder" hidden>Imagem indisponível</span>
                </div>
            </div>

            <div class="tutor-norminha-admin__preview-copy">
                <span class="tutor-norminha-admin__preview-eyebrow">Card da fala</span>
                <h2 class="tutor-norminha-admin__preview-title" data-preview-title><?php echo Helpers::e(trim($tituloAtual) !== '' ? $tituloAtual : $previewTituloPadrao); ?></h2>
                <p class="tutor-norminha-admin__preview-text" data-preview-text><?php echo Helpers::e(trim($textoAtual) !== '' ? $textoAtual : 'O texto da fala ainda não foi preenchido.'); ?></p>

                <div class="tutor-norminha-admin__preview-meta">
                    <span class="tutor-norminha-admin__preview-chip">Estado: <strong data-preview-state><?php echo Helpers::e(isset($estadosAvatarLabels[(string) $value('estado_avatar', 'speaking')]) ? $estadosAvatarLabels[(string) $value('estado_avatar', 'speaking')] : 'Falando'); ?></strong></span>
                    <span class="tutor-norminha-admin__preview-chip">TTL: <strong data-preview-ttl><?php echo (int) $previewTtlHoras; ?>h</strong></span>
                </div>

                <div class="tutor-norminha-admin__preview-actions">
                    <?php if ($audioPublicUrl !== ''): ?>
                        <button type="button" class="button-link button-link--primary tutor-norminha-admin__preview-audio-button" data-preview-audio-button><?php echo Helpers::e($previewTextoBotao); ?></button>
                        <audio hidden preload="none" src="<?php echo Helpers::e($audioPublicUrl); ?>" data-preview-audio></audio>
                        <small class="muted">Áudio carregado apenas para teste. Nenhuma alteração é salva ao usar o preview.</small>
                    <?php else: ?>
                        <div class="tutor-norminha-admin__preview-warning">
                            Salve a fala com um áudio para testar a reprodução.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="status-card">
        <form method="post" action="<?php echo Helpers::e($action_url); ?>" enctype="multipart/form-data" class="admin-form">
            <?php echo $csrfField; ?>
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">

            <div class="admin-form-grid">
                <label class="admin-form-grid__full">
                    <span>Título</span>
                    <input type="text" name="titulo" maxlength="255" value="<?php echo Helpers::e((string) $value('titulo')); ?>" required>
                </label>

                <label>
                    <span>Contexto</span>
                    <select name="contexto" required>
                        <option value="">Selecione</option>
                        <?php foreach ($contextos as $contexto): ?>
                            <option value="<?php echo Helpers::e($contexto); ?>" <?php echo (string) $value('contexto') === (string) $contexto ? 'selected' : ''; ?>><?php echo Helpers::e(isset($contextosLabels[$contexto]) ? $contextosLabels[$contexto] : $contexto); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Rota</span>
                    <input type="text" name="rota" maxlength="255" value="<?php echo Helpers::e((string) $value('rota')); ?>" placeholder="/curso/*">
                    <small class="muted">Use rota absoluta e, se necessário, wildcard com <code>*</code>.</small>
                </label>

                <label>
                    <span>Curso ID</span>
                    <input type="number" name="curso_id" min="1" step="1" value="<?php echo Helpers::e($nullableValue('curso_id')); ?>" placeholder="Opcional">
                </label>

                <label>
                    <span>Módulo ID</span>
                    <input type="number" name="modulo_id" min="1" step="1" value="<?php echo Helpers::e($nullableValue('modulo_id')); ?>" placeholder="Opcional">
                </label>

                <label>
                    <span>Aula ID</span>
                    <input type="number" name="aula_id" min="1" step="1" value="<?php echo Helpers::e($nullableValue('aula_id')); ?>" placeholder="Opcional">
                </label>

                <label>
                        <span>Estado do avatar</span>
                        <select name="estado_avatar" required>
                            <?php foreach ($estadosAvatar as $estado): ?>
                            <option value="<?php echo Helpers::e($estado); ?>" <?php echo (string) $value('estado_avatar', 'speaking') === (string) $estado ? 'selected' : ''; ?>><?php echo Helpers::e(isset($estadosAvatarLabels[$estado]) ? $estadosAvatarLabels[$estado] : $estado); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                <label class="admin-form-grid__full">
                    <span>Texto</span>
                    <textarea id="norminha-texto" name="texto" rows="10" required><?php echo Helpers::e((string) $value('texto')); ?></textarea>
                    <small class="muted">Contagem aproximada: <span id="norminha-text-count"><?php echo (int) $textoLength; ?></span> caracteres.</small>
                </label>

                <label>
                    <span>Áudio</span>
                    <input type="file" name="audio_file" accept=".mp3,.ogg,.wav,audio/mpeg,audio/ogg,audio/wav">
                    <small class="muted">Envio manual. O arquivo será salvo em <code>/uploads/tutor-norminha/audio/</code>.</small>
                </label>

                <label>
                    <span>URL do áudio</span>
                    <input type="text" name="audio_url" value="<?php echo Helpers::e((string) $audioUrlSalvo); ?>" readonly>
                    <small class="muted">Preenchida automaticamente após o upload.</small>
                </label>

                <label class="admin-form-grid__full">
                    <span>Áudio atual</span>
                    <?php if ($audioExiste && $audioPublicUrl !== '' && $audioFisicoValido): ?>
                        <audio class="tutor-norminha-admin__audio-player" controls preload="metadata">
                            <source src="<?php echo Helpers::e($audioPublicUrl); ?>" type="<?php echo Helpers::e($audioMimeType); ?>">
                        </audio>
                        <small class="muted"><?php echo Helpers::e($audioPublicUrl); ?></small>
                    <?php elseif ($audioUrlSalvo !== ''): ?>
                        <p class="muted">Áudio cadastrado, mas o arquivo não foi encontrado ou está vazio no servidor.</p>
                    <?php else: ?>
                        <p class="muted">Nenhum áudio enviado.</p>
                    <?php endif; ?>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="ativo" value="1" <?php echo $ativoAtual ? 'checked' : ''; ?>>
                    Ativo
                </label>
            </div>

            <div class="cta-group full">
                <button type="submit" class="button-link button-link--primary">Salvar</button>
                <a class="button-link button-link--ghost" href="/admin/tutor-norminha">Voltar</a>
            </div>
        </form>
    </section>
</section>

<script>
(function () {
    var textarea = document.getElementById('norminha-texto');
    var counter = document.getElementById('norminha-text-count');
    if (!textarea || !counter) {
        return;
    }

    var atualizar = function () {
        counter.textContent = String(textarea.value.length);
    };

    textarea.addEventListener('input', atualizar);
    atualizar();
})();

(function () {
    var preview = document.getElementById('norminha-preview-fala');
    if (!preview) {
        return;
    }

    var tituloInput = document.querySelector('input[name="titulo"]');
    var textoInput = document.getElementById('norminha-texto');
    var estadoInput = document.querySelector('select[name="estado_avatar"]');
    var audioButton = preview.querySelector('[data-preview-audio-button]');
    var audio = preview.querySelector('[data-preview-audio]');
    var titleTarget = preview.querySelector('[data-preview-title]');
    var textTarget = preview.querySelector('[data-preview-text]');
    var stateTarget = preview.querySelector('[data-preview-state]');
    var avatarFrame = preview.querySelector('.tutor-norminha-admin__preview-avatar-frame');
    var avatarIdle = preview.querySelector('[data-avatar-idle]');
    var avatarSpeaking = preview.querySelector('[data-avatar-speaking]');
    var hasAudio = preview.getAttribute('data-has-audio') === '1' && !!audio;
    var speaking = false;
    var playedOnce = false;
    var defaultTitle = <?php echo json_encode($previewTituloPadrao); ?>;
    var defaultButtonText = <?php echo json_encode($previewTextoBotao); ?>;
    var estadoLabels = <?php echo json_encode($estadosAvatarLabels, JSON_UNESCAPED_UNICODE); ?>;

    function setAvatarState(isSpeaking) {
        speaking = !!isSpeaking;
        if (avatarFrame) {
            avatarFrame.classList.toggle('is-speaking', speaking);
            avatarFrame.classList.toggle('is-idle', !speaking);
        }
        if (avatarIdle) {
            avatarIdle.hidden = speaking;
        }
        if (avatarSpeaking) {
            avatarSpeaking.hidden = !speaking;
        }
        if (stateTarget && estadoInput) {
            stateTarget.textContent = estadoLabels[estadoInput.value] || estadoInput.value;
        }
        if (audioButton) {
            if (!hasAudio) {
                audioButton.textContent = defaultButtonText;
            } else if (!audio.paused) {
                audioButton.textContent = 'Pausar';
            } else if (audio.ended) {
                audioButton.textContent = 'Ouvir novamente';
            } else if (playedOnce && audio.currentTime > 0) {
                audioButton.textContent = 'Continuar';
            } else {
                audioButton.textContent = defaultButtonText;
            }
        }
    }

    function syncText() {
        if (titleTarget) {
            var titleValue = tituloInput && tituloInput.value.trim() !== '' ? tituloInput.value.trim() : defaultTitle;
            titleTarget.textContent = titleValue;
        }
        if (textTarget) {
            textTarget.textContent = textoInput && textoInput.value.trim() !== '' ? textoInput.value.trim() : 'O texto da fala ainda não foi preenchido.';
        }
        if (stateTarget && estadoInput) {
            var option = estadoInput.options[estadoInput.selectedIndex];
            stateTarget.textContent = option ? option.textContent : estadoInput.value;
        }
    }

    if (tituloInput) {
        tituloInput.addEventListener('input', syncText);
    }

    if (textoInput) {
        textoInput.addEventListener('input', syncText);
    }

    if (estadoInput) {
        estadoInput.addEventListener('change', function () {
            syncText();
        });
    }

    if (audioButton && hasAudio && audio) {
        audioButton.addEventListener('click', function () {
            if (audio.ended || (audio.duration && audio.currentTime >= audio.duration)) {
                try {
                    audio.currentTime = 0;
                } catch (e) {}
            }
            if (audio.paused) {
                var playPromise = audio.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function () {});
                }
            } else {
                audio.pause();
            }
        });
    }

    if (audio) {
        audio.addEventListener('play', function () {
            playedOnce = true;
            setAvatarState(true);
        });

        audio.addEventListener('playing', function () {
            playedOnce = true;
            setAvatarState(true);
        });

        audio.addEventListener('pause', function () {
            setAvatarState(false);
        });

        audio.addEventListener('ended', function () {
            setAvatarState(false);
            if (audioButton) {
                audioButton.textContent = 'Ouvir novamente';
            }
        });
    }

    syncText();
    setAvatarState(false);
})();
</script>
