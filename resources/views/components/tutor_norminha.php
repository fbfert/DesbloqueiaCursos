<?php
use App\Core\Csrf;
use App\Core\Helpers;
use App\Core\Session;

/**
 * Norminha — casca visual do chat.
 *
 * O QUE FOI PRESERVADO da versão anterior, deliberadamente:
 *   - a raiz #norminha-tutor (a guarda de layout do smoke conta por ela);
 *   - o launcher, o minimizar e a chave localStorage norminha_tutor_minimized_v1;
 *   - avatar idle/speaking com fallback e a VALIDAÇÃO DE CAMINHO de avatar e
 *     áudio, que continua tão restrita quanto era: só /assets/norminha/ e
 *     /uploads/tutor-norminha/, sem '..', sem esquema, com o arquivo existindo
 *     em disco. Afrouxar isso transformaria uma configuração de admin em vetor
 *     de conteúdo arbitrário;
 *   - o áudio contextual opcional;
 *   - a fala do admin, que agora é a mensagem de abertura do chat.
 *
 * O QUE NÃO SE FAZ AQUI: nenhum texto vindo do servidor é renderizado como HTML.
 * A mensagem de abertura passa por Helpers::e() e nl2br; as mensagens do chat
 * são inseridas pelo JavaScript via textContent, nunca innerHTML.
 */

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
$contextoNorminha = isset($tutorNorminha['contexto']) ? (string) $tutorNorminha['contexto'] : 'area_aluno';

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

// Pistas de contexto, no contrato de App\Support\NorminhaHints.
//
// Preferência para o que o CONTROLLER resolveu; a query string é reserva, para
// rotas ainda não ligadas. Numa URL adulterada os dois divergem, e a Norminha
// precisa concordar com a página que o aluno está vendo.
//
// Isto é SUGESTÃO, não autorização: NorminhaContextService revalida cada id
// contra a sessão. Nada de nome, e-mail, CPF ou nota entra no HTML — e, em
// avaliação, nenhum dado da prova além do id do item.
$hints = isset($norminhaContexto) && is_array($norminhaContexto)
    ? $norminhaContexto
    : App\Support\NorminhaHints::daQuery($_GET);

$hintInscricao = isset($hints['inscricao_id']) ? (int) $hints['inscricao_id'] : 0;
$hintCurso = isset($hints['curso_id']) ? (int) $hints['curso_id'] : 0;
$hintTurma = isset($hints['turma_id']) ? (int) $hints['turma_id'] : 0;
$hintModulo = isset($hints['modulo_id']) ? (int) $hints['modulo_id'] : 0;
$hintItem = isset($hints['item_id']) ? (int) $hints['item_id'] : 0;
$rotaAtual = isset($hints['rota']) && $hints['rota'] !== null ? (string) $hints['rota'] : '/';
$contextoNorminha = isset($hints['contexto']) ? (string) $hints['contexto'] : $contextoNorminha;

// "Tirar dúvida desta aula" só existe quando existe aula. Na área geral do
// aluno o botão some, em vez de perguntar sobre coisa nenhuma.
$emAula = $hintItem > 0 && in_array($contextoNorminha, array('aula', 'avaliacao'), true);

// O chat existe para aluno logado. A identidade vem da MESMA fonte que a API
// usa (ApiAuthenticateMiddleware -> Session::get('usuario_id')), para que a
// tela e o servidor nunca discordem sobre quem esta falando.
//
// Para o visitante anonimo o componente volta a ser o que a versao anterior
// era: avatar + fala do admin. Sem campo de pergunta e sem acoes de aluno, que
// so responderiam 'nao_autenticado'. O JS ja e defensivo em todos esses pontos
// (if (form && input), if (quick), if (!listaMensagens) return), entao a
// ausencia dos blocos degrada limpo: launcher, minimizar e audio seguem vivos.
$alunoLogado = (int) Session::get('usuario_id') > 0;
?>

<!-- Norminha: chat acadêmico. Montado uma única vez, pelo layout. -->
<div
    id="norminha-tutor"
    class="norminha-tutor"
    data-estado-avatar="<?php echo Helpers::e($estadoAvatar); ?>"
    data-avatar-idle="<?php echo Helpers::e($avatarIdle !== null ? $avatarIdle : ''); ?>"
    data-avatar-speaking="<?php echo Helpers::e($avatarSpeaking !== null ? $avatarSpeaking : ''); ?>"
    data-texto-botao="<?php echo Helpers::e($textoBotao !== '' ? $textoBotao : 'Ouvir orientação'); ?>"
    data-ttl-hours="<?php echo (int) $ttlHoras; ?>"
    data-csrf="<?php echo Helpers::e(Csrf::token()); ?>"
    data-rota="<?php echo Helpers::e(substr($rotaAtual, 0, 255)); ?>"
    data-contexto="<?php echo Helpers::e($contextoNorminha); ?>"
    data-inscricao-id="<?php echo $hintInscricao ?: ''; ?>"
    data-curso-id="<?php echo $hintCurso ?: ''; ?>"
    data-turma-id="<?php echo $hintTurma ?: ''; ?>"
    data-modulo-id="<?php echo $hintModulo ?: ''; ?>"
    data-item-id="<?php echo $hintItem ?: ''; ?>"
>
    <div class="norminha-tutor__panel" data-norminha-card role="dialog" aria-labelledby="norminha-tutor-titulo" aria-modal="false">

        <header class="norminha-tutor__header">
            <?php if ($avatarInicial !== null): ?>
                <img class="norminha-tutor__header-avatar" src="<?php echo Helpers::e($avatarInicial); ?>"
                     data-avatar-image alt="" aria-hidden="true" loading="lazy" decoding="async">
            <?php endif; ?>
            <div class="norminha-tutor__header-texto">
                <h2 class="norminha-tutor__title" id="norminha-tutor-titulo"><?php echo Helpers::e($tituloRender); ?></h2>
                <p class="norminha-tutor__eyebrow">Sua tutora no Desbloqueia</p>
            </div>
            <button type="button" class="norminha-tutor__close" data-norminha-close aria-label="Minimizar a Norminha">
                <span aria-hidden="true">&times;</span>
            </button>
        </header>

        <div class="norminha-tutor__mensagens" data-norminha-mensagens role="log" aria-live="polite" aria-relevant="additions" tabindex="0">
            <div class="norminha-tutor__msg norminha-tutor__msg--norminha">
                <?php if ($texto !== ''): ?>
                    <p class="norminha-tutor__text"><?php echo nl2br(Helpers::e($texto)); ?></p>
                <?php else: ?>
                    <p class="norminha-tutor__text">Olá! Posso estudar com você.</p>
                <?php endif; ?>

                <?php if ($mostrarAudio): ?>
                    <button type="button" class="norminha-tutor__audio-button" data-norminha-audio-button>
                        <?php echo Helpers::e($textoBotao !== '' ? $textoBotao : 'Ouvir orientação'); ?>
                    </button>
                    <audio class="norminha-tutor__audio" data-norminha-audio preload="metadata"
                           src="<?php echo Helpers::e($audioUrl . $audioCacheBuster); ?>"></audio>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($alunoLogado): ?>
        <div class="norminha-tutor__acoes-rapidas" data-norminha-quick>
            <button type="button" class="norminha-tutor__chip" data-norminha-acao="resume_course">Continuar de onde parei</button>
            <button type="button" class="norminha-tutor__chip" data-norminha-acao="show_progress">Ver meu progresso</button>
            <button type="button" class="norminha-tutor__chip" data-norminha-acao="certificate_status">Meu certificado</button>
            <?php if ($emAula): ?>
                <button type="button" class="norminha-tutor__chip" data-norminha-acao="explain_current_lesson">Tirar dúvida desta aula</button>
            <?php endif; ?>
        </div>

        <div class="norminha-tutor__pensando" data-norminha-pensando hidden aria-hidden="true">
            <span class="norminha-tutor__ponto"></span>
            <span class="norminha-tutor__ponto"></span>
            <span class="norminha-tutor__ponto"></span>
            <span class="norminha-tutor__pensando-texto">Norminha está pensando...</span>
        </div>

        <form class="norminha-tutor__form" data-norminha-form>
            <label class="norminha-tutor__label-oculto" for="norminha-tutor-input">Escreva sua dúvida</label>
            <textarea
                id="norminha-tutor-input"
                class="norminha-tutor__input"
                data-norminha-input
                rows="1"
                maxlength="2000"
                placeholder="Digite sua pergunta..."
                autocomplete="off"
            ></textarea>
            <button type="submit" class="norminha-tutor__enviar" data-norminha-enviar aria-label="Enviar pergunta">
                <span aria-hidden="true">&#10148;</span>
            </button>
        </form>
        <?php else: ?>
            <p class="norminha-tutor__convite">
                <a class="norminha-tutor__convite-link" href="/login">Entre na sua conta</a>
                para conversar com a Norminha sobre seus cursos.
            </p>
        <?php endif; ?>
    </div>

    <button type="button" class="norminha-tutor__launcher" data-norminha-launcher aria-label="Abrir a Norminha" aria-expanded="true">
        <?php if ($avatarLauncher !== null): ?>
            <img class="norminha-tutor__launcher-avatar" src="<?php echo Helpers::e($avatarLauncher); ?>"
                 alt="" aria-hidden="true" loading="lazy" decoding="async" data-norminha-launcher-avatar>
            <span class="norminha-tutor__launcher-fallback" data-norminha-launcher-fallback aria-hidden="true">N</span>
        <?php else: ?>
            <span class="norminha-tutor__launcher-fallback" data-norminha-launcher-fallback aria-hidden="true">N</span>
        <?php endif; ?>
    </button>
</div>
