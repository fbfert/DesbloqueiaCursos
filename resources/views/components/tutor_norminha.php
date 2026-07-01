<?php
use App\Core\Helpers;

$tutorNorminha = isset($tutorNorminha) && is_array($tutorNorminha) ? $tutorNorminha : array();
$titulo = isset($tutorNorminha['titulo']) ? trim((string) $tutorNorminha['titulo']) : '';
$tituloPadrao = isset($tutorNorminha['titulo_padrao']) ? trim((string) $tutorNorminha['titulo_padrao']) : 'Norminha';
$texto = isset($tutorNorminha['texto']) ? (string) $tutorNorminha['texto'] : '';
$audioUrl = isset($tutorNorminha['audio_url']) ? trim((string) $tutorNorminha['audio_url']) : '';
$audioExiste = !empty($tutorNorminha['audio_existe']);
$estadoAvatar = isset($tutorNorminha['estado_avatar']) ? (string) $tutorNorminha['estado_avatar'] : 'speaking';
$textoBotao = isset($tutorNorminha['texto_botao']) ? trim((string) $tutorNorminha['texto_botao']) : 'Ouvir orientação';
$ttlHoras = isset($tutorNorminha['ttl_fechamento_horas']) ? (int) $tutorNorminha['ttl_fechamento_horas'] : 24;
$ttlHoras = $ttlHoras >= 1 && $ttlHoras <= 168 ? $ttlHoras : 24;
$mostrarAudio = $audioExiste && $audioUrl !== '' && strpos($audioUrl, '/uploads/tutor-norminha/audio/') === 0;
$mostrarAudio = $mostrarAudio && strpos($audioUrl, '..') === false && strpos($audioUrl, '?') === false && strpos($audioUrl, '#') === false;
$publicPathRoot = defined('PUBLIC_PATH') && is_string(PUBLIC_PATH) && trim(PUBLIC_PATH) !== ''
    ? rtrim((string) PUBLIC_PATH, "/\\")
    : (defined('BASE_PATH') && is_string(BASE_PATH) && trim(BASE_PATH) !== ''
        ? (preg_match('#(?:^|[\\\\/])public_html$#i', rtrim((string) BASE_PATH, "/\\")) ? rtrim((string) BASE_PATH, "/\\") : rtrim((string) BASE_PATH, "/\\") . DIRECTORY_SEPARATOR . 'public_html')
        : rtrim((string) getcwd(), "/\\") . DIRECTORY_SEPARATOR . 'public_html');
$publicPathFor = function ($publicUrl) use ($publicPathRoot) {
    $publicUrl = trim((string) $publicUrl);
    if ($publicUrl === '' || $publicUrl[0] !== '/') {
        return null;
    }

    $publicUrl = preg_split('/[?#]/', $publicUrl, 2)[0];
    if (!is_string($publicUrl) || $publicUrl === '' || strpos($publicUrl, '..') !== false) {
        return null;
    }

    return $publicPathRoot . $publicUrl;
};
$audioFisico = $publicPathFor($audioUrl);
$mostrarAudio = $mostrarAudio && $audioFisico !== null && is_file($audioFisico) && is_readable($audioFisico) && (int) @filesize($audioFisico) > 0;
$audioCacheBuster = '';
if ($mostrarAudio) {
    $mtime = @filemtime($audioFisico);
    if ($mtime) {
        $audioCacheBuster = '?v=' . $mtime;
    }
}
$tituloRender = $titulo !== '' ? $titulo : $tituloPadrao;

$validarAvatar = function ($valor) use ($publicPathFor) {
    $valor = trim((string) $valor);
    if ($valor === '') {
        return null;
    }

    if (preg_match('#^(https?:)?//#i', $valor) || stripos($valor, 'javascript:') === 0 || stripos($valor, 'data:') === 0) {
        return null;
    }

    $path = parse_url($valor, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return null;
    }

    if (strpos($path, '/assets/norminha/') !== 0 && strpos($path, '/uploads/tutor-norminha/avatar/') !== 0) {
        return null;
    }

    if (strpos($path, '..') !== false || strpos($path, 'public_html') !== false) {
        return null;
    }

    $query = parse_url($valor, PHP_URL_QUERY);
    if ($query !== null && $query !== '') {
        parse_str($query, $params);
        if (count($params) !== 1 || !isset($params['v']) || !ctype_digit((string) $params['v'])) {
            return null;
        }
    }

    $caminho = $publicPathFor($path);

    if (strpos($path, '/uploads/tutor-norminha/avatar/') === 0) {
        $caminho = $publicPathFor($path);
    }

    if ($caminho === null || !is_file($caminho) || !is_readable($caminho) || (int) @filesize($caminho) <= 0) {
        return null;
    }

    return $path . ($query !== null && $query !== '' ? '?' . $query : '');
};

$avatarIdle = $validarAvatar(isset($tutorNorminha['avatar_idle']) ? $tutorNorminha['avatar_idle'] : null);
$avatarSpeaking = $validarAvatar(isset($tutorNorminha['avatar_speaking']) ? $tutorNorminha['avatar_speaking'] : null);
$avatarInicial = $estadoAvatar === 'idle' ? $avatarIdle : $avatarSpeaking;
if ($avatarInicial === null) {
    $avatarInicial = $avatarIdle !== null ? $avatarIdle : $avatarSpeaking;
}
$avatarLauncher = $avatarIdle !== null ? $avatarIdle : $avatarSpeaking;
?>

<!-- Norminha renderizada: ativa, fala encontrada, áudio opcional -->
<div
    id="norminha-tutor"
    class="norminha-tutor"
    data-estado-avatar="<?php echo Helpers::e($estadoAvatar); ?>"
    data-avatar-idle="<?php echo Helpers::e($avatarIdle !== null ? $avatarIdle : ''); ?>"
    data-avatar-speaking="<?php echo Helpers::e($avatarSpeaking !== null ? $avatarSpeaking : ''); ?>"
    data-texto-botao="<?php echo Helpers::e($textoBotao !== '' ? $textoBotao : 'Ouvir orientação'); ?>"
    data-ttl-hours="<?php echo (int) $ttlHoras; ?>"
>
    <div class="norminha-tutor__panel" data-norminha-card>
        <button type="button" class="norminha-tutor__close" data-norminha-close aria-label="Minimizar tutor virtual">
            <span aria-hidden="true">&times;</span>
        </button>

        <div class="norminha-tutor__shell">
            <?php if ($avatarInicial !== null): ?>
                <figure class="norminha-tutor__avatar-wrap" aria-hidden="true">
                    <img
                        class="norminha-tutor__avatar"
                        src="<?php echo Helpers::e($avatarInicial); ?>"
                        data-avatar-image
                        alt="Norminha"
                        loading="lazy"
                        decoding="async"
                    >
                </figure>
            <?php endif; ?>

            <section class="norminha-tutor__card" aria-label="Orientação da Norminha">
                <p class="norminha-tutor__eyebrow">Tutor Virtual Norminha</p>

                <?php if ($tituloRender !== ''): ?>
                    <h2 class="norminha-tutor__title"><?php echo Helpers::e($tituloRender); ?></h2>
                <?php endif; ?>

                <?php if ($texto !== ''): ?>
                    <p class="norminha-tutor__text"><?php echo nl2br(Helpers::e($texto)); ?></p>
                <?php endif; ?>

                <?php if ($mostrarAudio): ?>
                    <button type="button" class="norminha-tutor__audio-button" data-norminha-audio-button>
                        <?php echo Helpers::e($textoBotao !== '' ? $textoBotao : 'Ouvir orientação'); ?>
                    </button>
                <?php endif; ?>
                <?php if ($mostrarAudio): ?>
                    <audio
                        class="norminha-tutor__audio"
                        data-norminha-audio
                        preload="metadata"
                        src="<?php echo Helpers::e($audioUrl . $audioCacheBuster); ?>"
                    ></audio>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <button type="button" class="norminha-tutor__launcher" data-norminha-launcher aria-label="Reabrir a Norminha">
        <?php if ($avatarLauncher !== null): ?>
            <img
                class="norminha-tutor__launcher-avatar"
                src="<?php echo Helpers::e($avatarLauncher); ?>"
                alt=""
                aria-hidden="true"
                loading="lazy"
                decoding="async"
                data-norminha-launcher-avatar
            >
            <span class="norminha-tutor__launcher-fallback" data-norminha-launcher-fallback aria-hidden="true">N</span>
        <?php else: ?>
            <span class="norminha-tutor__launcher-fallback" data-norminha-launcher-fallback aria-hidden="true">N</span>
        <?php endif; ?>
    </button>
</div>
